/* WhatsApp Bot - Site Financeiro (Baileys + Express)
 * - Exibe QR no console para logar
 * - Endpoints:
 *   GET  /status
 *   GET  /qr
 *   POST /send  { to: "55DDDNUMERO", text: "mensagem" }  Header: x-api-token
 *   POST /check { to: "55DDDNUMERO" } Header: x-api-token
 */
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers, proto, downloadMediaMessage } = require('@whiskeysockets/baileys');
const qrcode = require('qrcode-terminal');
const QRCodeImg = require('qrcode');
const express = require('express');
const cors = require('cors');
const pino = require('pino');
const axios = require('axios');
const FormData = require('form-data');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

// Configurações específicas para este projeto
const PORT = Number(process.env.API_PORT || 3001); // Porta diferente do outro projeto
const API_TOKEN = process.env.API_TOKEN || 'site-financeiro-token-2024';
const AUTO_REPLY = String(process.env.AUTO_REPLY || 'false').toLowerCase() === 'true';
const AUTO_REPLY_WINDOW_MS = Number(process.env.AUTO_REPLY_WINDOW_MS || 3600000); // 1h
// URL da API PHP - ajuste conforme seu ambiente
// Para desenvolvimento local: http://localhost/seu_projeto
// Para produção: https://seu-dominio.com
const ADMIN_API_URL = process.env.ADMIN_API_URL || 'http://localhost/seu_projeto';
const ADMIN_NUMBERS = (process.env.ADMIN_NUMBERS || '').split(',').map(n => n.trim()).filter(Boolean);

// Estado para aguardar fotos de comprovantes
const waitingForPhoto = new Map(); // key: jid, value: { transactionId, timestamp }

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

// Função helper para criar e enviar poll (enquete)
async function sendPoll(sock, jid, question, options) {
  try {
    if (!options || options.length < 2 || options.length > 12) {
      throw new Error('Poll deve ter entre 2 e 12 opções');
    }

    // Criar mensagem de poll usando proto
    const pollMessage = {
      pollCreationMessage: {
        name: question,
        options: options.map((opt) => ({
          optionName: String(opt)
        })),
        selectableOptionsCount: 1 // Permite apenas uma escolha
      }
    };

    // Enviar poll
    const sent = await sock.sendMessage(jid, pollMessage);
    console.log(`[POLL] Enquete enviada: "${question}" com ${options.length} opções`);
    return { success: true, messageId: sent.key.id };
  } catch (error) {
    console.error('[POLL] Erro ao enviar enquete:', error);
    throw error;
  }
}

async function start() {
  const { version, isLatest } = await fetchLatestBaileysVersion();
  console.log(`WhatsApp Web version: ${version?.join('.')} (latest=${isLatest})`);

  // Usa pasta de auth específica para este projeto
  const { state, saveCreds } = await useMultiFileAuthState('./auth-site-financeiro');
  // Logger customizado para reduzir verbosidade
  // Filtrar erros de descriptografia que são normais
  const baseLogger = pino({ level: 'error' });
  
  // Criar logger wrapper que filtra mensagens indesejadas
  const logger = {
    trace: () => {},
    debug: () => {},
    info: () => {},
    warn: (obj, msg) => {
      // Filtrar mensagens de descriptografia
      if (msg && (msg.includes('failed to decrypt') || msg.includes('decrypt'))) return;
      if (obj && (obj.msg === 'failed to decrypt message' || obj.message?.includes('decrypt'))) return;
      baseLogger.warn(obj, msg);
    },
    error: (obj, msg) => {
      // Filtrar mensagens de descriptografia
      if (msg && (msg.includes('failed to decrypt') || msg.includes('decrypt'))) return;
      if (obj && (obj.msg === 'failed to decrypt message' || obj.message?.includes('decrypt'))) return;
      // Filtrar também por chave do objeto
      if (obj && obj.key && obj.msg === 'failed to decrypt message') return;
      baseLogger.error(obj, msg);
    },
    fatal: (obj, msg) => {
      // Filtrar mensagens de descriptografia
      if (msg && (msg.includes('failed to decrypt') || msg.includes('decrypt'))) return;
      if (obj && (obj.msg === 'failed to decrypt message' || obj.message?.includes('decrypt'))) return;
      baseLogger.fatal(obj, msg);
    },
    child: () => logger,
    level: 'error'
  };
  
  sock = makeWASocket({
    auth: state,
    logger: logger,
    version,
    browser: Browsers.appropriate('Desktop'),
    printQRInTerminal: false, // QR será exibido via /qr endpoint
    markOnlineOnConnect: true
  });

  sock.ev.on('creds.update', saveCreds);

  // Tratamento de atualizações de polls (quando usuário vota)
  sock.ev.on('messages.update', async (updates) => {
    if (!isReady) return;
    
    for (const update of updates) {
      try {
        // Verificar se é uma atualização de poll
        if (update.update?.pollUpdate) {
          const pollUpdate = update.update.pollUpdate;
          const pollMessage = pollUpdate.pollCreationMessageKey;
          
          if (pollMessage && pollMessage.id) {
            const jid = pollMessage.remoteJid || update.key?.remoteJid;
            if (!jid || jid.includes('@g.us')) continue; // Ignorar grupos
            
            const phoneNumber = jid.split('@')[0];
            
            // Obter informações da poll
            const pollVote = pollUpdate.vote;
            if (pollVote && pollVote.selectedOptions) {
              const selectedOptionIndex = pollVote.selectedOptions[0];
              
              console.log(`[POLL] Usuário ${phoneNumber} votou na opção ${selectedOptionIndex}`);
              
              // Mapear opções para comandos
              const optionCommands = {
                0: '!saldo',
                1: '!receita',
                2: '!despesa',
                3: '!tarefas',
                4: '!menu'
              };
              
              const command = optionCommands[selectedOptionIndex];
              if (command) {
                // Processar comando automaticamente
                try {
                  const apiUrl = `${ADMIN_API_URL}/admin_bot_api.php`;
                  const apiResponse = await axios.post(apiUrl, {
                    phone: phoneNumber,
                    command: command,
                    args: [],
                    message: command,
                    source: 'poll' // Indicar que veio de uma poll
                  }, {
                    headers: {
                      'Content-Type': 'application/json',
                      'Authorization': `Bearer ${API_TOKEN}`
                    },
                    timeout: 30000
                  });
                  
                  if (apiResponse.data && apiResponse.data.message) {
                    await sock.sendMessage(jid, { text: apiResponse.data.message });
                    console.log(`[POLL] Comando ${command} executado via poll`);
                  }
                } catch (apiError) {
                  console.error('[POLL] Erro ao processar comando da poll:', apiError);
                  await sock.sendMessage(jid, { 
                    text: `❌ Erro ao processar sua escolha. Digite ${command} manualmente.` 
                  });
                }
              }
            }
          }
        }
      } catch (error) {
        console.error('[POLL] Erro ao processar atualização de poll:', error);
      }
    }
  });

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
      console.log(`📱 Bot pronto para receber comandos. API: ${ADMIN_API_URL}`);
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

  // Tratamento de erros de descriptografia
  sock.ev.on('messages.upsert', async (m) => {
    // Ignorar eventos vazios
    if (!m || !m.messages || m.messages.length === 0) {
      return;
    }
    
    if (!isReady) {
      return;
    }
    
    try {
      // Processar todas as mensagens do evento
      for (const msg of m.messages) {
        // Ignorar mensagens próprias
        if (msg.key?.fromMe) {
          continue;
        }
        
        // Ignorar grupos (por enquanto)
        const jid = msg.key.remoteJid;
        if (!jid || jid.includes('@g.us') || jid.includes('@broadcast') || jid.includes('status@')) {
          continue;
        }
        
        // Ignorar mensagens sem conteúdo
        if (!msg.message) {
          continue;
        }
        
        // Ignorar mensagens com erro de descriptografia
        if (msg.messageStubType === 1 || msg.messageStubType === 2) {
          continue;
        }
        
        console.log('[MESSAGE] Nova mensagem recebida de:', jid);

        // Extrair número do JID
        const phoneNumber = jid.split('@')[0];
        
        // Verificar se está aguardando foto
        const waiting = waitingForPhoto.get(jid);
        if (waiting && (Date.now() - waiting.timestamp) < 300000) { // 5 minutos
          if (msg.message.imageMessage || msg.message.documentMessage) {
            try {
              // Processar upload de foto
              const media = msg.message.imageMessage || msg.message.documentMessage;
              const stream = await downloadMediaMessage(msg, 'buffer', {}, { logger: pino({ level: 'silent' }) });
              const chunks = [];
              for await (const chunk of stream) {
                chunks.push(chunk);
              }
              const buffer = Buffer.concat(chunks);
              
              // Enviar para API de upload
              const formData = new FormData();
              formData.append('photo', buffer, {
                filename: `comprovante_${waiting.transactionId}_${Date.now()}.jpg`,
                contentType: 'image/jpeg'
              });
              formData.append('transaction_id', waiting.transactionId);
              formData.append('phone', phoneNumber);
              
              const uploadResponse = await axios.post(`${ADMIN_API_URL}/admin_bot_photo.php`, formData, {
                headers: {
                  ...formData.getHeaders(),
                  'Authorization': `Bearer ${API_TOKEN}`
                }
              });
              
              if (uploadResponse.data.success) {
                await sock.sendMessage(jid, { text: `✅ Comprovante anexado ao ID #${waiting.transactionId}` });
              } else {
                await sock.sendMessage(jid, { text: `❌ Erro ao anexar comprovante: ${uploadResponse.data.error || 'Erro desconhecido'}` });
              }
              
              waitingForPhoto.delete(jid);
            } catch (e) {
              console.error('[PHOTO-UPLOAD] Erro:', e);
              await sock.sendMessage(jid, { text: '❌ Erro ao processar foto. Tente novamente.' });
              waitingForPhoto.delete(jid);
            }
            continue; // Próxima mensagem
          }
        }
        
        // Processar apenas comandos que começam com !
        const text = msg.message.conversation || msg.message.extendedTextMessage?.text || '';
        
        if (!text) {
          console.log('[MESSAGE] Mensagem sem texto, ignorando');
          continue; // Próxima mensagem
        }
        
        console.log(`[MESSAGE] Texto recebido: "${text}" de ${phoneNumber}`);
        
        if (text.startsWith('!')) {
          try {
            const parts = text.trim().split(/\s+/);
            const command = parts[0].toLowerCase();
            const args = parts.slice(1);
            
            console.log(`[COMMAND] ${phoneNumber}: ${command} ${args.join(' ')}`);
            
            // Se for comando !menu, enviar poll interativa
            if (command === '!menu') {
              try {
                const pollQuestion = '👋 Olá! Como posso ajudar você hoje?';
                const pollOptions = [
                  '📊 Ver saldo',
                  '💰 Registrar receita',
                  '💸 Registrar despesa',
                  '📋 Ver tarefas',
                  '❓ Ver menu completo'
                ];
                
                await sendPoll(sock, jid, pollQuestion, pollOptions);
                console.log(`[COMMAND] Poll enviada para ${jid} via !menu`);
                continue; // Não enviar para API, já processamos aqui
              } catch (pollError) {
                console.error('[COMMAND] Erro ao enviar poll, enviando menu texto:', pollError);
                // Fallback: enviar para API normalmente
              }
            }
            
            // Se for comando !comprovante, aguardar foto
            if (command === '!comprovante' && args.length > 0) {
              const transactionId = args[0];
              waitingForPhoto.set(jid, {
                transactionId,
                timestamp: Date.now()
              });
              await sock.sendMessage(jid, { text: '📸 Envie o comprovante agora (foto ou documento)' });
              continue; // Próxima mensagem
            }
            
            // Enviar comando para API PHP
            const apiUrl = `${ADMIN_API_URL}/admin_bot_api.php`;
            console.log(`[COMMAND] Enviando para: ${apiUrl}`);
            
            const apiResponse = await axios.post(apiUrl, {
              phone: phoneNumber,
              command: command,
              args: args,
              message: text
            }, {
              headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${API_TOKEN}`
              },
              timeout: 30000
            });
            
            console.log(`[COMMAND] Resposta da API:`, apiResponse.status, JSON.stringify(apiResponse.data));
            
            if (apiResponse.data && apiResponse.data.message) {
              console.log(`[COMMAND] Enviando resposta para ${jid}:`, apiResponse.data.message.substring(0, 50) + '...');
              await sock.sendMessage(jid, { text: apiResponse.data.message });
              console.log(`[COMMAND] Resposta enviada com sucesso`);
            } else {
              console.error(`[COMMAND] Resposta inválida da API:`, apiResponse.data);
              await sock.sendMessage(jid, { text: '❌ Erro ao processar comando. Resposta inválida da API.' });
            }
          } catch (e) {
            console.error('[COMMAND] Erro completo:', e);
            console.error('[COMMAND] Erro message:', e.message);
            console.error('[COMMAND] Erro code:', e.code);
            console.error('[COMMAND] Erro response:', e.response?.data);
            console.error('[COMMAND] Erro status:', e.response?.status);
            console.error('[COMMAND] Stack:', e.stack);
            
            let errorMsg = '❌ Erro ao processar comando.';
            if (e.code === 'ECONNREFUSED') {
              errorMsg = '❌ Não foi possível conectar à API. Verifique se o servidor está rodando.';
            } else if (e.response?.status === 401) {
              errorMsg = '❌ Token de autenticação inválido.';
            } else if (e.response?.status === 500) {
              errorMsg = '❌ Erro no servidor (500). Verifique os logs do servidor PHP.';
            } else if (e.response?.data?.error) {
              errorMsg = `❌ ${e.response.data.error}`;
            } else if (e.message) {
              errorMsg = `❌ Erro: ${e.message}`;
            }
            
            try {
              await sock.sendMessage(jid, { text: errorMsg });
              console.log(`[COMMAND] Mensagem de erro enviada para ${jid}`);
            } catch (sendError) {
              console.error('[COMMAND] Erro ao enviar mensagem de erro:', sendError);
            }
          }
        } else {
          // Auto-reply padrão (se habilitado) - apenas para mensagens que não são comandos
          if (AUTO_REPLY) {
            const now = Date.now();
            const lastReply = lastReplyAt.get(jid) || 0;
            if (now - lastReply < AUTO_REPLY_WINDOW_MS) {
              continue; // Próxima mensagem
            }

            lastReplyAt.set(jid, now);

            try {
              await sock.sendMessage(jid, { text: 'Olá! Sou o assistente financeiro. Digite !menu para ver os comandos disponíveis.' });
              console.log(`[AUTO-REPLY] Enviado para ${jid}`);
            } catch (e) {
              console.error('[AUTO-REPLY] Erro:', e);
            }
          }
        }
      } // Fim do loop for
    } catch (err) {
      // Ignorar erros de descriptografia silenciosamente
      if (err.message && (
        err.message.includes('MessageCounterError') ||
        err.message.includes('Failed to decrypt') ||
        err.message.includes('Session error')
      )) {
        // Sessão corrompida - será tratada no próximo restart
        // Não logar esses erros para evitar spam
        return;
      }
      console.error('[MESSAGE] Erro ao processar mensagem:', err.message);
      console.error('[MESSAGE] Stack:', err.stack);
    }
  });
  
  // Tratamento de erros de conexão
  sock.ev.on('connection.update', (update) => {
    if (update.error) {
      console.error('[CONNECTION] Erro de conexão:', update.error);
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

// Endpoint para enviar poll (enquete)
app.post('/send-poll', auth, async (req, res) => {
  if (!isReady) return res.status(503).json({ ok: false, error: 'Bot não está pronto' });
  const { to, question, options } = req.body;
  
  if (!to || !question || !options || !Array.isArray(options)) {
    return res.status(400).json({ 
      ok: false, 
      error: 'to, question e options (array) são obrigatórios. Options deve ter entre 2 e 12 itens.' 
    });
  }
  
  if (options.length < 2 || options.length > 12) {
    return res.status(400).json({ 
      ok: false, 
      error: 'Poll deve ter entre 2 e 12 opções' 
    });
  }

  try {
    const jid = await resolveJID(to);
    const result = await sendPoll(sock, jid, question, options);
    return res.json({ ok: true, ...result, to: to, jid });
  } catch (e) {
    console.error('[SEND-POLL] Erro:', e);
    return res.status(500).json({ ok: false, error: e.message });
  }
});

app.listen(PORT, () => console.log(`API WhatsApp (Site Financeiro) rodando em http://localhost:${PORT}`));
start().catch(console.error);

