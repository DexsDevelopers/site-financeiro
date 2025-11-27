# 📱 Sistema de Comandos WhatsApp - Painel Financeiro

## 🚀 Instalação Rápida

1. **Criar tabelas no banco:**
   ```
   Acesse: setup_finance_tables.php
   ```

2. **Configurar bot:**
   ```bash
   cd whatsapp-bot-site-financeiro
   npm install
   cp .env.example .env
   # Edite o .env com suas configurações
   ```

3. **Iniciar bot:**
   ```bash
   npm run dev
   ```

4. **Escanear QR Code:**
   - Acesse: `http://localhost:3001/qr`
   - Ou veja no console do bot

## 📋 Comandos Disponíveis

### 💰 Financeiro

#### `!receita VALOR DESCRIÇÃO [CLIENTE]`
Registra uma receita no sistema.

**Exemplos:**
```
!receita 1500 Consultoria João Silva
!receita 2500 Desenvolvimento de site
!receita 500 Venda de produto
```

#### `!despesa VALOR DESCRIÇÃO [CATEGORIA]`
Registra uma despesa no sistema.

**Exemplos:**
```
!despesa 300 Aluguel Escritório
!despesa 150 Material de escritório
!despesa 500 Marketing Digital
```

#### `!saldo [MÊS] [ANO]`
Consulta o saldo do mês atual ou de um mês específico.

**Exemplos:**
```
!saldo
!saldo 11
!saldo 11 2025
```

#### `!extrato [DATA_INICIO] [DATA_FIM]`
Visualiza extrato de transações em um período.

**Exemplos:**
```
!extrato
!extrato 2025-11-01
!extrato 2025-11-01 2025-11-30
```

#### `!deletar ID`
Remove uma transação do sistema.

**Exemplo:**
```
!deletar 1234
```

### 👥 Clientes

#### `!cliente NOME TELEFONE [EMAIL]`
Cadastra um novo cliente.

**Exemplos:**
```
!cliente João Silva 5511999999999 joao@email.com
!cliente Maria Santos 5511888888888
```

#### `!clientes`
Lista todos os clientes cadastrados.

#### `!clienteinfo ID`
Exibe informações detalhadas de um cliente.

**Exemplo:**
```
!clienteinfo 5
```

#### `!pendencias [CLIENTE_ID]`
Lista pagamentos pendentes (de todos ou de um cliente específico).

**Exemplos:**
```
!pendencias
!pendencias 5
```

### 📸 Comprovantes

#### `!comprovante TRANSACAO_ID`
Inicia o processo de anexar comprovante. Após enviar este comando, envie a foto do comprovante.

**Exemplo:**
```
!comprovante 1234
[Envie a foto do comprovante]
```

#### `!vercomprovante TRANSACAO_ID`
Gera link para visualizar comprovante anexado.

**Exemplo:**
```
!vercomprovante 1234
```

### 📊 Relatórios

#### `!relatorio [MÊS] [ANO]`
Gera relatório completo do mês.

**Exemplos:**
```
!relatorio
!relatorio 11
!relatorio 11 2025
```

#### `!dashboard`
Exibe resumo geral do sistema (receitas, despesas, saldo, pendências).

#### `!topo [LIMITE]`
Lista top clientes e categorias.

**Exemplos:**
```
!topo
!topo 10
```

### 💳 Cobranças

#### `!cobrar CLIENTE_ID VALOR VENCIMENTO DESCRIÇÃO`
Cria uma nova cobrança para um cliente.

**Exemplo:**
```
!cobrar 5 2500 30/12/2025 Desenvolvimento de site
```

#### `!lembrar COBRANCA_ID`
Envia lembrete de pagamento para o cliente.

**Exemplo:**
```
!lembrar 890
```

#### `!notificar CLIENTE_ID MENSAGEM`
Envia mensagem personalizada para um cliente.

**Exemplo:**
```
!notificar 5 Olá! Seu pagamento está pendente.
```

#### `!pagar COBRANCA_ID`
Marca uma cobrança como paga.

**Exemplo:**
```
!pagar 890
```

### 📱 Comandos Públicos (Clientes)

#### `!minhasdividas`
Cliente consulta suas pendências de pagamento.

#### `!meusaldo`
Cliente consulta seu saldo/histórico.

#### `!pagarvia PIX|BOLETO`
Cliente solicita dados para pagamento.

**Exemplos:**
```
!pagarvia PIX
!pagarvia BOLETO
```

### 🛠️ Sistema

#### `!menu` ou `!help`
Lista todos os comandos disponíveis.

#### `!ajuda COMANDO`
Exibe ajuda detalhada de um comando específico.

**Exemplo:**
```
!ajuda receita
```

## ⚙️ Configuração

### Arquivo `config.json`

```json
{
  "WHATSAPP_API_URL": "http://localhost:3001",
  "WHATSAPP_API_TOKEN": "seu-token-seguro",
  "ADMIN_WHATSAPP_NUMBERS": [
    "5551996148568",
    "551996148568"
  ],
  "LIMITE_UPLOAD_MB": 10,
  "COMPROVANTES_DIR": "uploads/comprovantes/"
}
```

### Arquivo `.env` do Bot

```env
API_PORT=3001
API_TOKEN=seu-token-seguro
ADMIN_API_URL=https://seu-dominio.com
ADMIN_NUMBERS=5551996148568,551996148568
AUTO_REPLY=true
AUTO_REPLY_MESSAGE=Olá! Sou o assistente financeiro. Digite !menu para ver os comandos.
```

## 🔒 Segurança

- **Token de Autenticação:** Todas as requisições requerem token Bearer válido
- **Permissões:** Apenas números configurados em `ADMIN_WHATSAPP_NUMBERS` podem usar comandos admin
- **Upload de Arquivos:** Validação de tipo MIME e tamanho máximo
- **Logs:** Todas as ações são registradas no banco de dados

## 📝 Exemplos de Uso

### Fluxo Completo: Registrar Receita com Comprovante

```
1. Admin: !receita 1500 Consultoria João Silva
   Bot: ✅ Receita registrada! ID #1234

2. Admin: !comprovante 1234
   Bot: 📸 Envie o comprovante agora

3. Admin: [Envia foto]
   Bot: ✅ Comprovante anexado ao ID #1234
```

### Consultar Saldo e Relatório

```
1. Admin: !saldo
   Bot: 💰 SALDO - NOVEMBRO/2025
        📈 Receitas: R$ 25.000,00
        📉 Despesas: R$ 12.500,00
        💵 Saldo: R$ 12.500,00

2. Admin: !relatorio
   Bot: 📊 RELATÓRIO - NOVEMBRO/2025
        [Relatório completo com top clientes e categorias]
```

## 🐛 Troubleshooting

### Bot não responde comandos
- Verifique se o bot está rodando: `npm run dev`
- Verifique se está conectado ao WhatsApp
- Verifique logs no console

### Erro ao enviar comando
- Verifique se o token está correto em `config.json` e `.env`
- Verifique se a URL da API está acessível
- Verifique logs em `whatsapp_bot_logs` no banco

### Erro ao anexar comprovante
- Verifique se o arquivo é JPEG, PNG ou PDF
- Verifique se o tamanho é menor que 10MB
- Verifique permissões do diretório `uploads/comprovantes/`

## 📞 Suporte

Para mais informações, consulte:
- `debug_finance_whatsapp.php` - Página de diagnóstico
- Logs no banco de dados: tabela `whatsapp_bot_logs`
- Console do bot Node.js

