# 🔧 Solução Rápida: Deploy Manual na Hostinger

## Problema
Os arquivos estão no GitHub, mas não aparecem na Hostinger.

## Solução Imediata: Deploy Manual

### Método 1: Via File Manager da Hostinger (Mais Rápido)

1. **Acesse o hPanel da Hostinger**
2. **Vá em File Manager**
3. **Navegue até**: `public_html/seu_projeto/`
4. **Baixe os arquivos novos do GitHub:**
   - Acesse: https://github.com/DexsDevelopers/site-financeiro
   - Baixe o arquivo `admin/criar_tabela_atividade.php`
   - Faça upload no File Manager em `public_html/seu_projeto/admin/`

### Método 2: Via Git na Hostinger (Se disponível)

1. **Acesse o hPanel > Git**
2. **Verifique se há um repositório conectado**
3. **Se não houver, conecte:**
   - URL: `https://github.com/DexsDevelopers/site-financeiro.git`
   - Branch: `main`
   - Diretório: `public_html/seu_projeto`

4. **Faça o Pull manual:**
   - Clique em "Pull" ou "Update"
   - Ou execute via SSH (se tiver acesso)

### Método 3: Via SSH (Se tiver acesso)

```bash
# Conecte via SSH
ssh u853242961@gold-quail-250128.hostingersite.com

# Navegue até o diretório
cd public_html/seu_projeto

# Faça pull
git pull origin main
```

### Método 4: Usar o Webhook (Após configurar)

1. **Primeiro, faça upload do arquivo `deploy_webhook.php` manualmente**
2. **Configure o webhook no GitHub:**
   - Settings > Webhooks > Add webhook
   - URL: `https://gold-quail-250128.hostingersite.com/seu_projeto/deploy_webhook.php?key=SUA_CHAVE`
   - Events: Just the push event

3. **Teste fazendo um push**

## Arquivos que Precisam ser Enviados AGORA

### 1. `admin/criar_tabela_atividade.php`
   - **Localização no GitHub**: `admin/criar_tabela_atividade.php`
   - **Localização na Hostinger**: `public_html/seu_projeto/admin/criar_tabela_atividade.php`

### 2. Verificar se `admin/index.php` está atualizado
   - Deve ter o botão "Criar Tabela de Atividade"

## Verificação Rápida

1. **Acesse**: `https://gold-quail-250128.hostingersite.com/seu_projeto/admin/index.php`
2. **Verifique se aparece o botão amarelo "Criar Tabela de Atividade"**
3. **Se não aparecer, o arquivo não foi atualizado**

## Solução Definitiva: Configurar Auto-Deploy

### Opção A: Usar o Git Integrado da Hostinger

1. **Acesse hPanel > Git**
2. **Conecte o repositório** (se ainda não estiver conectado)
3. **Ative "Auto-Deploy"** (se disponível)
4. **Ou configure para fazer pull automaticamente**

### Opção B: Configurar Webhook (Recomendado)

1. **Faça upload do `deploy_webhook.php`**
2. **Configure no GitHub:**
   - Webhook URL: `https://gold-quail-250128.hostingersite.com/seu_projeto/deploy_webhook.php?key=CHAVE`
3. **Teste fazendo um push**

### Opção C: Cron Job (Fallback)

1. **Acesse hPanel > Cron Jobs**
2. **Crie um cron job:**
   ```
   */30 * * * * cd /home/u853242961/domains/gold-quail-250128.hostingersite.com/public_html/seu_projeto && git pull origin main
   ```
3. **Ou use o script PHP:**
   ```
   */30 * * * * php /home/u853242961/domains/gold-quail-250128.hostingersite.com/public_html/seu_projeto/deploy_webhook.php?key=CHAVE
   ```

## Próximos Passos

1. **Faça o deploy manual AGORA** (Método 1 ou 2)
2. **Configure o auto-deploy** (Opção A, B ou C)
3. **Teste fazendo um novo push**

## Contato com Suporte Hostinger

Se nada funcionar, contate o suporte da Hostinger e pergunte:
- "Como configurar deploy automático via Git?"
- "O servidor tem Git instalado?"
- "Como configurar webhook para deploy automático?"

