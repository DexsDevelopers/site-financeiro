# 🔐 Configuração do Google OAuth

## ✅ Credenciais Configuradas

**Client ID:** `945016861625-47dgg8sgrqgqpt99ct7e46l0o52vn2up.apps.googleusercontent.com`

## ⚠️ Ação Necessária: Obter Client Secret

1. Acesse: https://console.cloud.google.com/
2. Vá em **APIs & Services** > **Credentials**
3. Encontre o OAuth 2.0 Client ID: `945016861625-47dgg8sgrqgqpt99ct7e46l0o52vn2up`
4. Clique no ícone de **olho** ou **editar** para ver o **Client Secret**
5. Copie o Client Secret

## 📝 Adicionar Client Secret

Adicione o Client Secret no arquivo `includes/db_connect.php`:

```php
define('GOOGLE_CLIENT_SECRET', 'SEU_CLIENT_SECRET_AQUI');
```

## 🔗 Configurar Redirect URI no Google Cloud Console

1. No Google Cloud Console, edite o OAuth Client ID
2. Em **Authorized redirect URIs**, adicione:
   ```
   https://gold-quail-250128.hostingersite.com/seu_projeto/google_oauth_callback.php
   ```
3. Em **Authorized JavaScript origins**, adicione:
   ```
   https://gold-quail-250128.hostingersite.com
   ```

## ✅ Verificar APIs Ativadas

Certifique-se de que as seguintes APIs estão ativadas no Google Cloud Console:

- ✅ Google Calendar API
- ✅ Google Drive API
- ✅ Google Tasks API
- ✅ Gmail API
- ✅ Google Sheets API

## 🚀 Testar Integração

1. Acesse: `integracoes_google.php` no painel
2. Clique em **Conectar Google**
3. Autorize o acesso
4. Verifique se a conexão foi bem-sucedida

