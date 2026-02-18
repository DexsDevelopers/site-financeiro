# 🔄 Configuração do Cron Job - Reset Automático de Rotinas

## 📌 O que é?

O arquivo `reset_rotinas_meia_noite.php` é um script que:
- ✅ Reseta automaticamente o status de todas as rotinas fixas para "pendente"
- ✅ Cria novos registros de controle diário à meia-noite
- ✅ Garante que suas rotinas estejam "zeradas" todos os dias
- ✅ Funciona para todos os usuários do sistema

---

## 🔧 Como Configurar (Hostinger)

### **Passo 1: Acessar o Painel de Controle**
1. Acesse `https://seu-dominio.hostinger.com.br/admin`
2. Faça login com sua conta Hostinger
3. Procure por **"Cron Jobs"** no menu lateral

### **Passo 2: Adicionar Novo Cron Job**
1. Clique em **"Novo Cron Job"**
2. Preencha com os seguintes dados:

```
Tempo de Execução:    00:00 (meia-noite)
Frequência:           Diário
Comando:              php /home/seu_usuario/seu_dominio/public_html/reset_rotinas_meia_noite.php
Email de Resultado:   seu_email@email.com (opcional)
```

### **Passo 3: Salvar**
- Clique em **"Criar Cron Job"**
- Você verá o cron listado como ativo ✅

---

## 🖥️ Como Configurar (Via SSH Terminal)

Se você tem acesso SSH ao servidor:

```bash
# 1. Acessar o crontab
crontab -e

# 2. Adicionar esta linha no final do arquivo:
0 0 * * * /usr/bin/php /home/seu_usuario/seu_dominio/public_html/reset_rotinas_meia_noite.php >> /home/seu_usuario/seu_dominio/public_html/logs/cron.log 2>&1

# 3. Salvar (Ctrl+X, depois Y, depois Enter)
```

### **Explicação do Comando Cron:**
```
0 0 * * * /usr/bin/php /caminho/para/script.php
│ │ │ │ │
│ │ │ │ └─── Dia da semana (0=domingo, 6=sábado, * = todos)
│ │ │ └───── Mês (* = todos)
│ │ └─────── Dia do mês (* = todos)
│ └───────── Hora (0 = meia-noite)
└─────────── Minuto (0 = minuto 0)
```

---

## ✅ Como Verificar se Está Funcionando

### **1. Verificar Log de Execução**
```bash
# Ver últimas linhas do log
tail -f /home/seu_usuario/seu_dominio/logs/reset_rotinas_YYYY-MM.log
```

### **2. Verificar no Banco de Dados**
```sql
-- Ver registros criados hoje
SELECT * FROM rotina_controle_diario 
WHERE data_execucao = CURDATE();

-- Ver últimas execuções
SELECT * FROM rotina_controle_diario 
ORDER BY data_execucao DESC 
LIMIT 10;
```

### **3. Testar Manualmente**
Execute o script direto via navegador ou terminal:
```bash
php /caminho/para/reset_rotinas_meia_noite.php
```

---

## 📊 O que Acontece Quando o Cron Executa?

1. **Para cada usuário do sistema:**
   - Busca todas as rotinas fixas ativas
   - Verifica se já existem controles para hoje
   - Se não existirem, cria novos controles com `status = 'pendente'`

2. **Resultado esperado:**
   ```
   [2025-10-17 00:00:00] Iniciando reset automático das rotinas fixas...
   Encontrados 5 usuários para processar...
   Usuário 1: Criados 8 controles para hoje
   Usuário 2: Criados 5 controles para hoje
   Usuário 3: Controles já existem para hoje
   [2025-10-17 00:00:01] Reset automático concluído!
   Usuários processados: 5/5
   ```

---

## 🐛 Troubleshooting

### **Problema: Cron não está executando**

✅ **Solução 1:** Verificar se o path do PHP está correto
```bash
# Encontrar o caminho correto do PHP
which php
# Resultado esperado: /usr/bin/php ou /usr/local/bin/php
```

✅ **Solução 2:** Adicionar redirect de erro para debug
```
0 0 * * * /usr/bin/php /seu/script.php >> /seu/logs/cron.log 2>&1
```

✅ **Solução 3:** Verificar permissões do arquivo
```bash
chmod 755 /seu/caminho/reset_rotinas_meia_noite.php
chmod 755 /seu/caminho/logs/
```

### **Problema: Arquivo de log não é criado**

✅ **Solução:** Criar manualmente a pasta de logs
```bash
mkdir -p /seu/caminho/logs
chmod 777 /seu/caminho/logs
```

### **Problema: Comando não reconhecido**

✅ **Solução:** Usar caminho absoluto do PHP
```bash
# Descobrir o caminho
which php
# Usar esse caminho no cron
0 0 * * * /usr/local/bin/php /seu/script.php
```

---

## 📝 Exemplo Completo (Hostinger)

**URL do Script:**
```
https://seu-dominio.com.br/reset_rotinas_meia_noite.php
```

**Comando do Cron:**
```
0 0 * * * /usr/bin/php /home/u123456789/seu_dominio.com.br/public_html/reset_rotinas_meia_noite.php
```

**Log gerado em:**
```
/home/u123456789/seu_dominio.com.br/public_html/logs/reset_rotinas_2025-10.log
```

---

## 🔗 Relacionados

- `reset_rotinas_meia_noite.php` - Script de reset
- `tarefas.php` - Página de gerenciamento de rotinas
- `INTEGRACAO_TAREFAS_COMPLETA.md` - Documentação completa

---

**Última Atualização:** 17 de Outubro de 2025
**Status:** ✅ Pronto para Produção
