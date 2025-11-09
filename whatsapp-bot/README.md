# WhatsApp Bot Local (Baileys + Express)

API local para enviar mensagens de WhatsApp usando o seu número (não oficial).

## Requisitos
- Node.js 18+ (Windows)
- Seu PC ligado e com internet

## Instalação
```bash
cd whatsapp-bot
npm install
```

Crie um arquivo `.env` (opcional) na pasta `whatsapp-bot` com:
```
API_PORT=3000
API_TOKEN=troque-este-token
```

## Executar
```bash
npm run dev
```
Escaneie o QR code no console (WhatsApp > Aparelhos Conectados).

## Endpoints
- GET `/status` → `{ ok: true, ready: boolean }`
- POST `/send` (header `x-api-token`) body:
```json
{ "to": "55DDDNUMERO", "text": "mensagem" }
```

## Manter online
- Use `pm2` ou `nssm` (serviço) se quiser iniciar com o Windows.

## Expor para a internet (Hostinger → seu painel)
- ngrok: `ngrok http 3000` (pegue a URL e use no PHP)
- Cloudflared: `cloudflared tunnel --url http://localhost:3000`


