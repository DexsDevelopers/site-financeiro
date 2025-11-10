# 🚀 Guia de Deploy Automático na Hostinger

## Problema
Os arquivos estão sendo enviados para o GitHub, mas a Hostinger não está atualizando automaticamente.

## Soluções

### Opção 1: Webhook do GitHub (Recomendado)

1. **Acesse o painel da Hostinger (hPanel)**
   - Entre em "Git" ou "Deploy"
   - Verifique se o repositório está conectado

2. **Configure o Webhook no GitHub:**
   - Acesse: https://github.com/DexsDevelopers/site-financeiro/settings/hooks
   - Clique em "Add webhook"
   - **Payload URL**: `https://gold-quail-250128.hostingersite.com/seu_projeto/deploy_webhook.php`
   - **Content type**: `application/json`
   - **Secret**: (deixe vazio ou crie uma chave secreta)
   - **Events**: Selecione "Just the push event"
   - Clique em "Add webhook"

3. **Configure a chave secreta no `deploy_webhook.php`:**
   ```php
   $SECRET_KEY = 'sua-chave-secreta-forte-aqui';
   ```

4. **Teste o webhook:**
   - Faça um push para o GitHub
   - Verifique os logs em `logs/deploy_webhook.log`

### Opção 2: Deploy Manual via Script PHP

1. **Acesse o script diretamente:**
   ```
   https://gold-quail-250128.hostingersite.com/seu_projeto/deploy_webhook.php?key=SUA_CHAVE_SECRETA
   ```

2. **Ou crie um botão no admin para executar:**
   - Adicione um link no painel admin
   - Execute o script periodicamente

### Opção 3: Cron Job na Hostinger

1. **Acesse o hPanel > Cron Jobs**
2. **Crie um novo cron job:**
   - **Comando**: `php /home/u853242961/domains/gold-quail-250128.hostingersite.com/public_html/seu_projeto/deploy_webhook.php?key=SUA_CHAVE_SECRETA`
   - **Frequência**: A cada 5 minutos (`*/5 * * * *`)
   - Ou a cada hora (`0 * * * *`)

### Opção 4: Configurar Git na Hostinger (SSH)

1. **Acesse o hPanel > SSH Access**
2. **Conecte via SSH:**
   ```bash
   ssh u853242961@gold-quail-250128.hostingersite.com
   ```

3. **Navegue até o diretório:**
   ```bash
   cd public_html/seu_projeto
   ```

4. **Configure o Git:**
   ```bash
   git remote -v
   git pull origin main
   ```

5. **Crie um script de deploy:**
   ```bash
   #!/bin/bash
   cd /home/u853242961/domains/gold-quail-250128.hostingersite.com/public_html/seu_projeto
   git pull origin main
   ```

### Opção 5: Usar a Funcionalidade Git da Hostinger

1. **Acesse o hPanel > Git**
2. **Conecte o repositório:**
   - URL: `https://github.com/DexsDevelopers/site-financeiro.git`
   - Branch: `main`
   - Diretório: `public_html/seu_projeto`

3. **Configure o Auto-Deploy:**
   - Ative o "Auto-Deploy" se disponível
   - Ou configure um webhook manual

## Verificação

1. **Verifique se o arquivo foi atualizado:**
   - Acesse: `https://gold-quail-250128.hostingersite.com/seu_projeto/admin/criar_tabela_atividade.php`
   - Se aparecer, o deploy funcionou

2. **Verifique os logs:**
   - Acesse: `logs/deploy_webhook.log`
   - Veja se há erros

## Troubleshooting

### Erro: "Git not available"
- A Hostinger pode não ter Git instalado
- Contate o suporte da Hostinger para instalar Git

### Erro: "Permission denied"
- Verifique as permissões dos arquivos
- Execute: `chmod 755 deploy_webhook.php`

### Erro: "Repository not found"
- Verifique se o repositório está conectado
- Verifique as credenciais do Git

### Deploy não funciona
- Verifique se o webhook está configurado corretamente
- Verifique os logs do GitHub (Settings > Webhooks > Recent Deliveries)
- Teste manualmente acessando o script

## Recomendação Final

**Use a Opção 1 (Webhook)** combinada com a **Opção 3 (Cron Job)** como fallback:
- Webhook para deploy instantâneo
- Cron job para garantir que sempre esteja atualizado (a cada hora)

## Segurança

⚠️ **IMPORTANTE**: 
- Nunca exponha a chave secreta no código
- Use variáveis de ambiente ou arquivo `.env`
- Limite o acesso ao script `deploy_webhook.php`

