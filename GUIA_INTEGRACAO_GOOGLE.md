# 🔗 Guia de Integração com Google APIs

## 📋 Pré-requisitos

### 1. Criar Projeto no Google Cloud Console

1. Acesse: https://console.cloud.google.com/
2. Crie um novo projeto ou selecione um existente
3. Ative as APIs necessárias:
   - Google Calendar API
   - Google Drive API
   - Google Tasks API
   - Gmail API
   - Google Sheets API

### 2. Configurar OAuth 2.0

1. Vá em **APIs & Services** > **Credentials**
2. Clique em **Create Credentials** > **OAuth client ID**
3. Escolha **Web application**
4. Configure:
   - **Name**: Painel Financeiro
   - **Authorized JavaScript origins**: 
     - `https://gold-quail-250128.hostingersite.com`
     - `http://localhost` (para desenvolvimento)
   - **Authorized redirect URIs**:
     - `https://gold-quail-250128.hostingersite.com/seu_projeto/google_oauth_callback.php`
     - `http://localhost/seu_projeto/google_oauth_callback.php` (para desenvolvimento)

### 3. Configurar Variáveis de Ambiente

Adicione ao arquivo `.env`:

```env
# Google OAuth
GOOGLE_CLIENT_ID=seu_client_id_aqui
GOOGLE_CLIENT_SECRET=seu_client_secret_aqui
GOOGLE_REDIRECT_URI=https://gold-quail-250128.hostingersite.com/seu_projeto/google_oauth_callback.php
```

## 🚀 Funcionalidades Implementadas

### ✅ Google Calendar
- Sincronização de eventos/tarefas
- Criação automática de eventos
- Atualização de eventos existentes

### ✅ Google Drive
- Upload de arquivos
- Download de arquivos
- Gerenciamento de documentos

### ✅ Google Tasks
- Sincronização bidirecional de tarefas
- Criação de listas personalizadas

### ✅ Gmail
- Envio de emails
- Notificações por email

### ✅ Google Sheets
- Exportação de dados
- Criação de planilhas

## 📝 Como Usar

1. Acesse a página **Integrações Google** no menu
2. Clique em **Conectar Google**
3. Autorize o acesso aos serviços necessários
4. Ative os serviços que deseja usar
5. Use os botões de sincronização para sincronizar dados

## 🔒 Segurança

- Tokens são armazenados de forma segura no banco
- Refresh tokens são usados para renovar acesso automaticamente
- Usuário pode desconectar a qualquer momento
- Cada usuário só acessa seus próprios dados

## 🛠️ Troubleshooting

### Erro: "Client ID não configurado"
- Verifique se as variáveis de ambiente estão configuradas
- Certifique-se de que o arquivo `.env` existe e está sendo carregado

### Erro: "Redirect URI mismatch"
- Verifique se o URI no Google Cloud Console corresponde ao do código
- Certifique-se de usar HTTPS em produção

### Token expirado
- O sistema renova tokens automaticamente
- Se persistir, desconecte e reconecte a conta

## 📚 Documentação das APIs

- [Google Calendar API](https://developers.google.com/calendar/api)
- [Google Drive API](https://developers.google.com/drive/api)
- [Google Tasks API](https://developers.google.com/tasks/api)
- [Gmail API](https://developers.google.com/gmail/api)
- [Google Sheets API](https://developers.google.com/sheets/api)

