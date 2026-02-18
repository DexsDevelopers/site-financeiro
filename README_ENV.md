# 🔐 Configuração de Variáveis de Ambiente

## Arquivo .env

O sistema agora utiliza um arquivo `.env` para armazenar chaves de API e configurações sensíveis.

### Como configurar na Hostinger:

1. **Acesse o File Manager** no painel da Hostinger
2. **Navegue até a raiz do projeto** (pasta `public_html/seu_projeto/`)
3. **Crie um arquivo chamado `.env`** (com o ponto no início)
4. **Adicione o seguinte conteúdo:**

```env
# Gemini API Key (OBRIGATÓRIO para funcionalidade de IA)
# Obtenha sua chave em: https://aistudio.google.com/apikey
GEMINI_API_KEY=sua_chave_aqui

# OneSignal (opcional, se usar notificações push)
ONESIGNAL_APP_ID=8b948d38-c99d-402b-a456-e99e66fcc60f
ONESIGNAL_REST_API_KEY=os_v2_app_roki2ogjtvacxjcw5gpgn7ggb6mdk2tfshne5g4h2i6iyji25kg3h7mljd6u7rl2kw23egygxcbkcxdvfjehi7u5x5df4e2z7zefrhi
```

### ⚠️ IMPORTANTE:

- **NUNCA** compartilhe o arquivo `.env` publicamente
- **NUNCA** faça commit do arquivo `.env` no Git
- O arquivo `.env` já está no `.gitignore` para proteção
- Substitua `sua_chave_aqui` pela sua chave real do Gemini

### Obter nova chave do Gemini:

1. Acesse: https://aistudio.google.com/apikey
2. Faça login com sua conta Google
3. Clique em "Create API Key" ou "Get API Key"
4. Copie a chave gerada
5. Cole no arquivo `.env` na linha `GEMINI_API_KEY=`

### Verificar se está funcionando:

Após criar o arquivo `.env` com a chave, acesse:
- `https://seu-dominio.com/seu_projeto/debug_ia_whatsapp.php`

Se a chave estiver configurada corretamente, aparecerá:
```
✅ API Key configurada
```


