# WhatsApp Bot - Site Financeiro

Bot WhatsApp específico para o projeto Site Financeiro, com configurações e autenticação separadas.

## 🚀 Instalação

```bash
npm install
```

## ⚙️ Configuração

1. Copie o arquivo `.env.example` para `.env`:
```bash
cp .env.example .env
```

2. Ajuste as configurações no arquivo `.env`:
- `API_PORT`: Porta da API (padrão: 3001)
- `API_TOKEN`: Token de autenticação
- `AUTO_REPLY`: Ativar auto-resposta (true/false)
- `AUTO_REPLY_WINDOW_MS`: Janela de tempo para auto-resposta

## 🎯 Uso

### Iniciar o bot:
```bash
npm run dev
```

Este comando automaticamente:
- Mata processos na porta 3001
- Inicia o bot

### Apenas matar processos na porta:
```bash
npm run kill-port
```

### Iniciar sem matar processos:
```bash
npm start
```

## 📡 Endpoints

Todos os endpoints requerem o header `x-api-token` com o valor configurado em `API_TOKEN`.

### GET /status
Verifica o status do bot.

**Resposta:**
```json
{
  "ok": true,
  "ready": true,
  "port": 3001,
  "project": "site-financeiro"
}
```

### GET /qr
Exibe o QR Code para escanear com o WhatsApp.

### POST /send
Envia uma mensagem.

**Body:**
```json
{
  "to": "5511999999999",
  "text": "Mensagem de teste"
}
```

### POST /check
Verifica se um número está no WhatsApp.

**Body:**
```json
{
  "to": "5511999999999"
}
```

**Resposta:**
```json
{
  "ok": true,
  "exists": true,
  "to": "5511999999999",
  "jid": "5511999999999@s.whatsapp.net"
}
```

## 🔐 Autenticação

A pasta `auth-site-financeiro` contém as credenciais de autenticação do WhatsApp. Esta pasta é específica para este projeto e não interfere com outros bots.

**⚠️ Importante:** Não compartilhe ou faça commit desta pasta!

## 🔄 Diferenças do Bot Original

- **Porta padrão:** 3001 (outro projeto usa 3000)
- **Pasta de auth:** `auth-site-financeiro` (separada)
- **Token padrão:** `site-financeiro-token-2024`
- **Identificação:** Respostas incluem `project: 'site-financeiro'`

## 🐛 Troubleshooting

### Erro: "address already in use"
Execute `npm run kill-port` para matar processos na porta 3001.

### Sessão expirada
Delete a pasta `auth-site-financeiro` e escaneie o QR Code novamente.

### Bot não conecta
Verifique se:
- A porta 3001 está livre
- O arquivo `.env` está configurado corretamente
- Não há outro bot rodando na mesma porta

