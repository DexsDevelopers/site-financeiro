/* WhatsApp Bot local - Baileys + Express
 * - Exibe QR no console para logar
 * - Endpoints:
 *   GET  /status
 *   GET  /qr
 *   POST /send  { to: "55DDDNUMERO", text: "mensagem" }  Header: x-api-token
 *   POST /check { to: "55DDDNUMERO" } Header: x-api-token
 */
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } = require('@whiskeysockets/baileys');
const qrcode = require('qrcode-terminal');
const QRCodeImg = require('qrcode');
const express = require('express');
const cors = require('cors');
const pino = require('pino');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

const PORT = Number(process.env.API_PORT || 3000);
const API_TOKEN = process.env.API_TOKEN || 'troque-este-token';
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

  const { state, saveCreds } = await useMultiFileAuthState('./auth');
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
      console.log('✅ Conectado ao WhatsApp');
    }

    if (connection === 'close') {
      isReady = false;
      const code = lastDisconnect?.error?.output?.statusCode;
      let hint = '';
      switch (code) {
        case DisconnectReason.loggedOut:
        case 401: hint = 'Sessão expirada/deslogada. Apague ./auth e escaneie QR novamente.'; break;
        case 405: hint = 'Sessão inválida (405). Apague ./auth e refaça o login.'; break;
        case DisconnectReason.connectionReplaced:
        case 409: hint = 'Conexão substituída por outro login do mesmo número.'; break;
        case DisconnectReason.restartRequired:
        case 410: hint = 'Reinício requerido. Tentando reconectar...'; break;
        default: hint = 'Tentando reconectar...';
      }

      if (![DisconnectReason.loggedOut, 401, 405].includes(code)) {
        console.log(`♻️ Reconectando... ${code || ''} ${hint}`);
        start().catch(console.error);
      } else {
        console.log(`🔒 Desconectado: ${code || ''}. ${hint}`);
      }
    }
  });

  sock.ev.on('messages.upsert', async (m) => {
    try {
      const msg = m.messages?.[0];
      if (!msg?.message || msg.key.fromMe) return;
      const remoteJid = msg.key.remoteJid;
      const text = msg.message.conversation || msg.message.extendedTextMessage?.text || '';

      // Auto-resposta opcional (desativada por padrão)
      if (AUTO_REPLY) {
        const now = Date.now();
        const last = lastReplyAt.get(remoteJid) || 0;
        if (now - last > AUTO_REPLY_WINDOW_MS) {
          const lower = (text || '').toLowerCase();
          if (lower.includes('oi') || lower.includes('olá') || lower.includes('ola')) {
            await sock.sendMessage(remoteJid, { text: 'Olá! Como posso ajudar?' });
            lastReplyAt.set(remoteJid, now);
          }
        }
      }
    } catch (e) { console.error(e); }
  });
}

// Middleware de autenticação
function auth(req, res, next) {
  const token = req.headers['x-api-token'];
  if (!API_TOKEN || token !== API_TOKEN) return res.status(401).json({ ok: false, error: 'unauthorized' });
  next();
}

// Status
app.get('/status', (req, res) => res.json({ ok: true, ready: isReady }));

// QR Code
app.get('/qr', async (req, res) => {
  if (!lastQR) return res.status(404).send('<html><body style="background:#111;color:#eee;font-family:sans-serif"><h3>Nenhum QR disponível</h3></body></html>');
  try {
    const dataUrl = await QRCodeImg.toDataURL(lastQR, { scale: 8, margin: 1 });
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end(`<html><body style="background:#0f0f10;color:#eee;font-family:system-ui;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh">
      <div style="text-align:center">
        <h3>Escaneie o QR Code</h3>
        <img src="${dataUrl}" style="image-rendering: pixelated; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.5)" />
      </div></body></html>`);
  } catch (e) {
    res.status(500).send('Falha ao gerar QR');
  }
});

// Resolve JID preferindo LID mapeado pelo onWhatsApp (Baileys v7+)
async function resolveJidFromPhone(digits) {
  // Tentativa inicial usando PN JID
  const pnJid = `${digits}@s.whatsapp.net`;
  try {
    const res = await sock.onWhatsApp(pnJid);
    if (Array.isArray(res) && res.length > 0) {
      const item = res[0];
      const mapped = item?.jid || pnJid; // pode vir ...@lid
      const exists = !!item?.exists || !!item?.isBusiness || !!item?.isEnterprise;
      return { exists, pnJid, mappedJid: mapped };
    }
    // Alguns ambientes retornam objeto único
    const exists = !!res?.exists;
    const mapped = res?.jid || pnJid;
    return { exists, pnJid, mappedJid: mapped };
  } catch (e) {
    return { exists: false, pnJid, mappedJid: pnJid, error: e?.message || String(e) };
  }
}

// Envio de mensagens robusto (com suporte a LID)
app.post('/send', auth, async (req, res) => {
  try {
    if (!isReady) return res.status(503).json({ ok: false, error: 'not_ready' });

    let { to, text } = req.body || {};
    if (!to || !text) return res.status(400).json({ ok: false, error: 'missing_params' });

    const digits = formatBrazilNumber(to);
    const { exists, pnJid, mappedJid, error } = await resolveJidFromPhone(digits);
    if (!exists) {
      return res.status(400).json({ ok: false, error: 'number_not_registered', to: digits, pnJid, mappedJid, detail: error });
    }
    console.log(`[SEND] Preparando para enviar mensagem`, { digits, pnJid, mappedJid });

    try {
      await sock.sendMessage(mappedJid, { text });
      console.log(`[SEND] ✅ Mensagem enviada`, { digits, mappedJid });
      return res.json({ ok: true, to: digits, jid: mappedJid });
    } catch (err) {
      console.error(`[SEND] ❌ Falha ao enviar`, digits, mappedJid, err?.message || err);

      // Detecta erro de número inexistente
      if (err?.output?.statusCode === 400 || err?.message?.includes('not a WhatsApp user')) {
        return res.status(400).json({ ok: false, error: 'number_not_registered', to: digits, jid: mappedJid });
      }

      return res.status(500).json({ ok: false, error: err.message || 'unknown_error' });
    }
  } catch (e) {
    console.error('[SEND] Erro geral:', e);
    res.status(500).json({ ok: false, error: e.message });
  }
});

// Check prático de número (retorna JID mapeado/LID quando existir)
app.post('/check', auth, async (req, res) => {
  try {
    if (!isReady) return res.status(503).json({ ok: false, error: 'not_ready' });

    const { to } = req.body || {};
    if (!to) return res.status(400).json({ ok: false, error: 'missing_params' });

    const digits = formatBrazilNumber(to);
    const { exists, pnJid, mappedJid, error } = await resolveJidFromPhone(digits);
    if (!exists) {
      return res.status(400).json({ ok: false, error: 'number_not_registered', to: digits, pnJid, mappedJid, detail: error });
    }
    return res.json({ ok: true, to: digits, jid: mappedJid });
  } catch (e) {
    console.error('[CHECK] Erro geral:', e);
    res.status(500).json({ ok: false, error: e.message });
  }
});

app.listen(PORT, () => console.log(`API WhatsApp rodando em http://localhost:${PORT}`));
start().catch(console.error);
