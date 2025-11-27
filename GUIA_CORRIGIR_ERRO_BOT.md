# 🔧 Guia: Corrigir Erro de Comando no Bot WhatsApp

## ❌ Erro Atual
```
[COMMAND] 553791101425: !menu
[COMMAND] Erro: Error
```

## 🔍 Diagnóstico

O erro ocorre quando o bot tenta se comunicar com a API PHP. Possíveis causas:

1. **URL da API incorreta** - O bot não consegue acessar `admin_bot_api.php`
2. **Token de autenticação incorreto** - Token não corresponde
3. **API não acessível** - Servidor PHP não está rodando ou caminho errado

## ✅ Solução Passo a Passo

### 1. Verificar URL da API

Edite o arquivo `.env` na pasta `whatsapp-bot-site-financeiro/`:

```env
# Para desenvolvimento local (XAMPP/WAMP)
ADMIN_API_URL=http://localhost/seu_projeto

# Para produção (Hostinger)
ADMIN_API_URL=https://gold-quail-250128.hostingersite.com/seu_projeto
```

### 2. Verificar Token

Certifique-se que o token no `.env` do bot corresponde ao `config.json`:

**`.env` do bot:**
```env
API_TOKEN=site-financeiro-token-2024
```

**`config.json`:**
```json
{
  "WHATSAPP_API_TOKEN": "site-financeiro-token-2024"
}
```

### 3. Testar API Manualmente

Acesse no navegador ou use curl:

```bash
# Teste local
curl -X POST http://localhost/seu_projeto/admin_bot_api.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer site-financeiro-token-2024" \
  -d '{"phone":"553791101425","command":"!menu","args":[],"message":"!menu"}'
```

Ou acesse: `http://localhost/seu_projeto/test_api_bot.php`

### 4. Reiniciar o Bot

Após fazer as alterações:

1. Pare o bot (Ctrl+C no terminal)
2. Edite o `.env` com a URL correta
3. Inicie novamente: `npm run dev`

### 5. Verificar Logs

O bot agora mostra logs detalhados:
- `[COMMAND] Enviando para: [URL]` - URL que está tentando acessar
- `[COMMAND] Resposta da API:` - Resposta recebida
- `[COMMAND] Erro completo:` - Detalhes do erro

## 🎯 URLs Comuns

### Desenvolvimento Local:
- XAMPP: `http://localhost/seu_projeto`
- WAMP: `http://localhost/seu_projeto`
- Laragon: `http://localhost/seu_projeto`

### Produção:
- Hostinger: `https://gold-quail-250128.hostingersite.com/seu_projeto`
- Seu domínio: `https://seudominio.com/seu_projeto`

## 📝 Checklist

- [ ] URL da API está correta no `.env`
- [ ] Token no `.env` corresponde ao `config.json`
- [ ] API PHP está acessível (teste manual)
- [ ] Bot foi reiniciado após alterações
- [ ] Logs mostram a URL correta sendo chamada

## 🆘 Se Ainda Não Funcionar

1. Verifique os logs do bot no console
2. Teste a API manualmente com curl/Postman
3. Verifique se o arquivo `admin_bot_api.php` existe e está acessível
4. Verifique permissões do arquivo `config.json`

