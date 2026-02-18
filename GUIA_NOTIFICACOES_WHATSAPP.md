# 📱 Guia de Notificações Automáticas WhatsApp

## 🎯 Funcionalidades

O sistema envia notificações automáticas via WhatsApp sobre:

1. **Resumo Diário** - Tarefas do dia, gastos e estatísticas
2. **Alertas de Gastos Altos** - Quando você registra uma despesa acima de R$ 500,00
3. **Tarefas Urgentes** - Tarefas com alta prioridade ou próximas do vencimento

---

## 📋 Configuração

### 1. Notificações Diárias

As notificações diárias são enviadas automaticamente todas as manhãs (recomendado: 08:00).

**Configurar Cron Job (Linux/Hostinger):**

```bash
# Editar crontab
crontab -e

# Adicionar linha (executa todo dia às 08:00)
0 8 * * * /usr/bin/php /caminho/para/seu_projeto/enviar_notificacoes_diarias.php >> /caminho/para/logs/notificacoes.log 2>&1
```

**Exemplo para Hostinger:**
```bash
0 8 * * * /usr/bin/php /home/u853242961/domains/gold-quail-250128.hostingersite.com/public_html/seu_projeto/enviar_notificacoes_diarias.php
```

**Configurar no Windows (Task Scheduler):**

1. Abra o "Agendador de Tarefas"
2. Crie uma nova tarefa
3. Configure para executar diariamente às 08:00
4. Ação: Iniciar programa
5. Programa: `php.exe`
6. Argumentos: `C:\caminho\para\enviar_notificacoes_diarias.php`

---

### 2. Alertas de Gastos Altos

Os alertas são enviados automaticamente quando você registra uma despesa acima de R$ 500,00.

**Configurar limite:**

Edite `enviar_alertas_gastos.php` e altere:
```php
$LIMITE_GASTO_ALTO = 500.00; // Ajuste conforme necessário
```

**Execução automática:**

Os alertas são enviados automaticamente quando você usa o comando `!despesa` no WhatsApp.

---

## 📊 Conteúdo das Notificações

### Notificação Diária

A notificação diária inclui:

1. **Tarefas de Hoje**
   - Lista de tarefas pendentes para o dia
   - Prioridade e data limite
   - Limite de 5 tarefas (mostra total se houver mais)

2. **Tarefas Urgentes**
   - Tarefas com alta prioridade
   - Tarefas com data limite nos próximos 7 dias
   - Status de urgência

3. **Resumo Financeiro do Mês**
   - Total de receitas
   - Total de despesas
   - Saldo atual
   - Alerta se gastou mais de 80% da receita

4. **Top 3 Maiores Gastos do Mês**
   - Lista dos 3 maiores gastos
   - Valor e descrição
   - Categoria (se houver)

5. **Pendências**
   - Total de cobranças pendentes
   - Valor total pendente

---

### Alerta de Gasto Alto

Quando você registra uma despesa acima de R$ 500,00:

- Valor da despesa
- Descrição
- Categoria
- Data e hora
- Percentual da receita do mês (se > 10%)

---

## 🔧 Solução de Problemas

### Notificações não estão sendo enviadas

1. **Verificar se o WhatsApp está conectado:**
   ```bash
   # Verificar status do bot
   curl http://localhost:3001/status
   ```

2. **Verificar se o usuário está logado:**
   - O usuário precisa ter feito login via `!login`
   - A sessão precisa estar ativa (última atividade nos últimos 7 dias)

3. **Verificar logs:**
   ```bash
   # Ver logs do PHP
   tail -f /var/log/php_errors.log
   
   # Ver logs do bot
   # Console do Node.js onde o bot está rodando
   ```

4. **Testar manualmente:**
   ```bash
   php enviar_notificacoes_diarias.php
   ```

### Erro de sessão do WhatsApp

Se você receber erros como "MessageCounterError":

1. **Parar o bot:**
   ```bash
   # No terminal onde o bot está rodando, pressione Ctrl+C
   ```

2. **Limpar autenticação:**
   ```bash
   cd whatsapp-bot-site-financeiro
   node reset-auth.js
   ```

3. **Reiniciar o bot:**
   ```bash
   npm run dev
   ```

4. **Escanear QR Code novamente:**
   - Acesse: `http://localhost:3001/qr`
   - Escaneie com seu WhatsApp

---

## 📝 Personalização

### Alterar horário das notificações

Edite o cron job para alterar o horário:
```bash
# Exemplo: 07:00
0 7 * * * /usr/bin/php /caminho/enviar_notificacoes_diarias.php

# Exemplo: 09:30
30 9 * * * /usr/bin/php /caminho/enviar_notificacoes_diarias.php
```

### Alterar limite de gasto alto

Edite `enviar_alertas_gastos.php`:
```php
$LIMITE_GASTO_ALTO = 1000.00; // R$ 1.000,00
```

### Desabilitar notificações

Para desabilitar temporariamente, comente a linha no cron job:
```bash
# 0 8 * * * /usr/bin/php /caminho/enviar_notificacoes_diarias.php
```

---

## 🔐 Segurança

- As notificações são enviadas apenas para usuários com sessão ativa
- Apenas usuários logados recebem notificações
- Os dados são filtrados por usuário (cada um vê apenas seus dados)

---

## 📞 Suporte

Para mais informações:
- Verifique os logs do sistema
- Teste os scripts manualmente
- Verifique se o bot WhatsApp está rodando
- Verifique se o cron job está configurado corretamente



