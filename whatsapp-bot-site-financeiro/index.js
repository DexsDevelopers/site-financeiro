/* WhatsApp Bot - Site Financeiro (Baileys + Express)
 * - Exibe QR no console para logar
 * - Endpoints:
 *   GET  /status
 *   GET  /qr
 *   POST /send  { to: "55DDDNUMERO", text: "mensagem" }  Header: x-api-token
 *   POST /check { to: "55DDDNUMERO" } Header: x-api-token
 */
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers, proto } = require('@whiskeysockets/baileys');
const qrcode = require('qrcode-terminal');
const QRCodeImg = require('qrcode');
const express = require('express');
const cors = require('cors');
const pino = require('pino');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

// Configurações específicas para este projeto
const PORT = Number(process.env.API_PORT || 3001); // Porta diferente do outro projeto
const API_TOKEN = process.env.API_TOKEN || 'site-financeiro-token-2024';
const AUTO_REPLY = String(process.env.AUTO_REPLY || 'false').toLowerCase() === 'true';
const AUTO_REPLY_WINDOW_MS = Number(process.env.AUTO_REPLY_WINDOW_MS || 3600000); // 1h

let sock;
let isReady = false;
let lastQR = null;
// Controle simples para evitar auto-resposta repetida
const lastReplyAt = new Map(); // key: jid, value: timestamp

// Formata número brasileiro para WhatsApp
function formatBrazilNumber(raw) {
  let digits = String(raw).replace(/\D+/g, '');
  if (digits.startsWith('0')) digits = digits.slice(1);
  if (!digits.startsWith('55')) digits = '55' + digits;
  return digits;
}

async function start() {
  const { version, isLatest } = await fetchLatestBaileysVersion();
  console.log(`WhatsApp Web version: ${version?.join('.')} (latest=${isLatest})`);

  // Usa pasta de auth específica para este projeto
  const { state, saveCreds } = await useMultiFileAuthState('./auth-site-financeiro');
  sock = makeWASocket({
    auth: state,
    logger: pino({ level: 'silent' }),
    version,
    browser: Browsers.appropriate('Desktop')
  });

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      lastQR = qr;
      qrcode.generate(qr, { small: true });
      console.log('Abra http://localhost:' + PORT + '/qr para escanear o QR em alta qualidade.');
    }

    if (connection === 'open') {
      isReady = true;
      console.log('✅ Conectado ao WhatsApp (Site Financeiro)');
    }

    if (connection === 'close') {
      isReady = false;
      const code = lastDisconnect?.error?.output?.statusCode;
      let hint = '';
      switch (code) {
        case DisconnectReason.loggedOut:
        case 401: hint = 'Sessão expirada/deslogada. Apague ./auth-site-financeiro e escaneie QR novamente.'; break;
        case 405: hint = 'Sessão inválida (405). Apague ./auth-site-financeiro e refaça o login.'; break;
        case DisconnectReason.connectionReplaced:
        case 409: hint = 'Conexão substituída por outro login do mesmo número.'; break;
        case DisconnectReason.restartRequired:
        case 410: hint = 'Reinício requerido. Tentando reconectar...'; break;
        case DisconnectReason.timedOut:
        case 408: hint = 'Timeout. Tentando reconectar...'; break;
        default: hint = `Código: ${code}`;
      }
      console.log('❌ Desconectado:', hint);
      if (code !== DisconnectReason.connectionClosed) {
        setTimeout(start, 3000);
      }
    }
  });

  sock.ev.on('messages.upsert', async (m) => {
    if (!AUTO_REPLY || !isReady) return;
    const msg = m.messages[0];
    if (!msg || msg.key.fromMe || !msg.message) return;

    const jid = msg.key.remoteJid;
    if (!jid || jid.includes('@g.us')) return; // Ignora grupos

    const now = Date.now();
    const lastReply = lastReplyAt.get(jid) || 0;
    if (now - lastReply < AUTO_REPLY_WINDOW_MS) return;

    lastReplyAt.set(jid, now);

    try {
      await sock.sendMessage(jid, { text: 'Olá! Como posso ajudar?' });
      console.log(`[AUTO-REPLY] Enviado para ${jid}`);
    } catch (e) {
      console.error('[AUTO-REPLY] Erro:', e);
    }
  });
}

function auth(req, res, next) {
  const token = req.headers['x-api-token'];
  if (!API_TOKEN || token !== API_TOKEN) return res.status(401).json({ ok: false, error: 'unauthorized' });
  next();
}

app.get('/status', (req, res) => {
  res.json({ ok: true, ready: isReady, port: PORT, project: 'site-financeiro' });
});

app.get('/qr', async (req, res) => {
  if (!lastQR) return res.status(404).json({ ok: false, error: 'QR não disponível' });
  try {
    const qrImg = await QRCodeImg.toDataURL(lastQR);
    res.send(`<html><body style="margin:0;padding:20px;text-align:center;background:#1a1a1a;color:#fff;"><h2>QR Code WhatsApp - Site Financeiro</h2><img src="${qrImg}" style="max-width:400px;border:2px solid #25D366;border-radius:10px;"/><p>Escaneie com seu WhatsApp</p></body></html>`);
  } catch (e) {
    res.status(500).json({ ok: false, error: e.message });
  }
});

// Resolve JID preferindo LID mapeado pelo onWhatsApp (Baileys v7+)
async function resolveJID(digits) {
  const normalized = formatBrazilNumber(digits);
  const phoneJID = `${normalized}@s.whatsapp.net`;
  
  try {
    const [result] = await sock.onWhatsApp(phoneJID);
    if (result && result.exists) {
      return result.jid; // Retorna o JID correto (pode ser LID ou Phone JID)
    }
  } catch (e) {
    console.error('[CHECK] Erro onWhatsApp:', e);
  }
  
  return phoneJID; // Fallback
}

app.post('/send', auth, async (req, res) => {
  if (!isReady) return res.status(503).json({ ok: false, error: 'Bot não está pronto' });
  const { to, text } = req.body;
  if (!to || !text) return res.status(400).json({ ok: false, error: 'to e text são obrigatórios' });

  try {
    const jid = await resolveJID(to);
    await sock.sendMessage(jid, { text });
    return res.json({ ok: true, to: digits, jid });
  } catch (e) {
    console.error('[SEND] Erro:', e);
    return res.status(500).json({ ok: false, error: e.message });
  }
});

app.post('/check', auth, async (req, res) => {
  if (!isReady) return res.status(503).json({ ok: false, error: 'Bot não está pronto' });
  const { to } = req.body;
  if (!to) return res.status(400).json({ ok: false, error: 'to é obrigatório' });

  try {
    const digits = formatBrazilNumber(to);
    const phoneJID = `${digits}@s.whatsapp.net`;
    const [result] = await sock.onWhatsApp(phoneJID);
    
    if (!result || !result.exists) {
      return res.json({ ok: false, exists: false, to: digits });
    }

    const mappedJid = result.jid;
    return res.json({ ok: true, to: digits, jid: mappedJid });
  } catch (e) {
    console.error('[CHECK] Erro geral:', e);
    res.status(500).json({ ok: false, error: e.message });
  }
});

app.listen(PORT, () => console.log(`API WhatsApp (Site Financeiro) rodando em http://localhost:${PORT}`));
start().catch(console.error);

