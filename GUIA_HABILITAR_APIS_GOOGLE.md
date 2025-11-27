# 🔧 Guia: Habilitar APIs do Google

## ⚠️ Erro Encontrado

Você está recebendo o erro:
```
Google Tasks API has not been used in project 945016861625 before or it is disabled.
```

Isso significa que as APIs do Google precisam ser habilitadas no Google Cloud Console.

## 📋 APIs Necessárias

Para que a integração Google funcione completamente, você precisa habilitar as seguintes APIs:

1. ✅ **Google Tasks API** - Para sincronizar tarefas
2. ✅ **Google Calendar API** - Para sincronizar eventos
3. ✅ **Google Drive API** - Para armazenar arquivos
4. ✅ **Gmail API** - Para enviar emails
5. ✅ **Google Sheets API** - Para exportar dados

## 🚀 Passo a Passo

### 1. Acesse o Google Cloud Console

1. Acesse: https://console.cloud.google.com/
2. Faça login com a conta Google que criou o projeto OAuth
3. Selecione o projeto: **945016861625**

### 2. Habilite as APIs

#### **Google Tasks API**
1. Acesse: https://console.developers.google.com/apis/api/tasks.googleapis.com/overview?project=945016861625
2. Clique em **"HABILITAR"** ou **"ENABLE"**
3. Aguarde alguns minutos para a propagação

#### **Google Calendar API**
1. Acesse: https://console.developers.google.com/apis/api/calendar-json.googleapis.com/overview?project=945016861625
2. Clique em **"HABILITAR"** ou **"ENABLE"**

#### **Google Drive API**
1. Acesse: https://console.developers.google.com/apis/api/drive.googleapis.com/overview?project=945016861625
2. Clique em **"HABILITAR"** ou **"ENABLE"**

#### **Gmail API**
1. Acesse: https://console.developers.google.com/apis/api/gmail.googleapis.com/overview?project=945016861625
2. Clique em **"HABILITAR"** ou **"ENABLE"**

#### **Google Sheets API**
1. Acesse: https://console.developers.google.com/apis/api/sheets.googleapis.com/overview?project=945016861625
2. Clique em **"HABILITAR"** ou **"ENABLE"**

### 3. Método Alternativo (Mais Rápido)

1. Acesse: https://console.cloud.google.com/apis/library?project=945016861625
2. Use a barra de pesquisa para encontrar cada API:
   - Digite "Google Tasks API" → Clique → Habilite
   - Digite "Google Calendar API" → Clique → Habilite
   - Digite "Google Drive API" → Clique → Habilite
   - Digite "Gmail API" → Clique → Habilite
   - Digite "Google Sheets API" → Clique → Habilite

### 4. Verificar APIs Habilitadas

1. Acesse: https://console.cloud.google.com/apis/dashboard?project=945016861625
2. Você verá todas as APIs habilitadas listadas

## ⏱️ Tempo de Propagação

Após habilitar uma API, pode levar **5-10 minutos** para que as mudanças sejam propagadas. Se ainda receber erro, aguarde alguns minutos e tente novamente.

## ✅ Verificação

Após habilitar as APIs, teste novamente:

1. Acesse `integracoes_google.php`
2. Conecte sua conta Google
3. Tente sincronizar tarefas ou calendário

## 🔗 Links Rápidos

- **Google Cloud Console**: https://console.cloud.google.com/
- **Biblioteca de APIs**: https://console.cloud.google.com/apis/library?project=945016861625
- **Dashboard de APIs**: https://console.cloud.google.com/apis/dashboard?project=945016861625

## 📝 Notas Importantes

- ⚠️ Certifique-se de estar logado com a conta correta do Google
- ⚠️ Verifique se o projeto correto está selecionado (945016861625)
- ⚠️ Algumas APIs podem exigir verificação adicional (como Gmail API)
- ⚠️ APIs gratuitas têm limites de uso (quota)

## 🆘 Problemas Comuns

### "API não encontrada"
- Verifique se o projeto está correto
- Certifique-se de estar logado com a conta que criou o OAuth

### "Permissão negada"
- Aguarde alguns minutos após habilitar
- Verifique se a API foi realmente habilitada no dashboard

### "Quota excedida"
- Algumas APIs têm limites diários
- Verifique a quota em: https://console.cloud.google.com/apis/api/tasks.googleapis.com/quotas?project=945016861625



