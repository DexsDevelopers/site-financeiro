/* WhatsApp Bot local - Baileys + Express
 * - Exibe QR no console para logar
 * - Endpoints:
 *   GET  /status
 *   POST /send  { to: "55DDDNUMERO", text: "mensagem" }  Header: x-api-token
 */
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason } = require('@whiskeysockets/baileys');
const qrcode = require('qrcode-terminal');
const express = require('express');
const cors = require('cors');
const pino = require('pino');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

const PORT = Number(process.env.API_PORT || 3000);
const API_TOKEN = process.env.API_TOKEN || 'troque-este-token';

let sock;
let isReady = false;

async function start() {
  const { state, saveCreds } = await useMultiFileAuthState('./auth');
  sock = makeWASocket({
    auth: state,
    // printQRInTerminal removido (deprecated). O QR é tratado no evento connection.update
    logger: pino({ level: 'silent' })
  });

  sock.ev.on('creds.update', saveCreds);
  sock.ev.on('connection.update', (update) => {
    const { connection, lastDisconnect, qr } = update;
    if (qr) qrcode.generate(qr, { small: true });
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
        case 401:
          hint = 'Sessão expirada/deslogada. Apague a pasta ./auth e escaneie o QR novamente.';
          break;
        case 405:
          hint = 'Sessão inválida (405). Apague ./auth e refaça o login.';
          break;
        case DisconnectReason.connectionReplaced:
        case 409:
          hint = 'Conexão substituída por outro login do mesmo número.';
          break;
        case DisconnectReason.restartRequired:
        case 410:
          hint = 'Reinício requerido. Tentando reconectar...';
          break;
        default:
          hint = 'Tentando reconectar...';
      }

      if (code !== DisconnectReason.loggedOut && code !== 401 && code !== 405) {
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
      const text =
        msg.message.conversation ||
        msg.message.extendedTextMessage?.text ||
        '';
      // Auto-reply simples (ajuste como quiser)
      if (text?.toLowerCase().includes('oi')) {
        await sock.sendMessage(remoteJid, { text: 'Olá! Como posso ajudar?' });
      }
    } catch (e) { console.error(e); }
  });
}

// Middleware simples de autenticação por header
function auth(req, res, next) {
  const token = req.headers['x-api-token'];
  if (!API_TOKEN || token !== API_TOKEN) {
    return res.status(401).json({ ok: false, error: 'unauthorized' });
  }
  next();
}

app.get('/status', (req, res) => res.json({ ok: true, ready: isReady }));
app.post('/send', auth, async (req, res) => {
  try {
    if (!isReady) return res.status(503).json({ ok: false, error: 'not_ready' });
    const { to, text } = req.body || {};
    if (!to || !text) return res.status(400).json({ ok: false, error: 'missing_params' });
    const jid = to.includes('@s.whatsapp.net') ? to : `${to}@s.whatsapp.net`;
    await sock.sendMessage(jid, { text });
    res.json({ ok: true });
  } catch (e) {
    console.error(e);
    res.status(500).json({ ok: false, error: e.message });
  }
});

app.listen(PORT, () => console.log(`API WhatsApp rodando em http://localhost:${PORT}`));
start().catch(console.error);


