# 🔐 Guia Completo: Sistema "Lembrar-me por 30 Dias"

## 📋 Resumo Executivo

O sistema "Lembrar-me" agora está **totalmente funcional**. Ele permite que usuários façam login automático por até **30 dias** usando um token criptografado armazenado em cookie seguro.

---

## ✅ Como Usar

### 1️⃣ **No Login**
- Faça login normalmente com usuário e senha
- **Marque o checkbox** `Lembrar-me por 30 dias`
- Clique em **Entrar**

### 2️⃣ **Fechando o Navegador**
- Feche **completamente** o navegador (não apenas a aba)
- Todos os cookies de sessão serão deletados
- Apenas o cookie `remember_token` persistirá

### 3️⃣ **Reabrindo o Navegador**
- Acesse o site normalmente
- Você será **automaticamente logado** 🎉
- Sem necessidade de digitar credenciais

### 4️⃣ **Duração**
- O token dura **30 dias completos**
- Se acessar antes do 7º dia, o token é renovado automaticamente
- Após expirar, é necessário fazer login novamente

---

## 🔧 Verificação Técnica

### **Teste Rápido**

Abra a página de teste em seu navegador:

```
https://seu-site/testar_remember_me_completo.php
```

Esta página fará um diagnóstico completo:
- ✅ Conexão com banco de dados
- ✅ Tabela `remember_tokens`
- ✅ Cookies do navegador
- ✅ Tokens ativos
- ✅ RememberMeManager

---

## 🗂️ Estrutura de Arquivos

### **Arquivos Principais**

| Arquivo | Função |
|---------|--------|
| `includes/remember_me_manager.php` | Classe que gerencia tokens |
| `includes/auto_login.php` | Login automático ao acessar o site |
| `login_process.php` | Processa login e cria token |
| `testar_remember_me_completo.php` | Diagnosticar problemas |

### **Banco de Dados**

Tabela `remember_tokens`:

```sql
CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    user_agent TEXT,
    ip_address VARCHAR(45),
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_active (is_active)
);
```

---

## 🔍 Diagnosticar Problemas

### **Problema 1: Não está fazendo login automático**

**Passos para diagnosticar:**

1. Acesse: `https://seu-site/testar_remember_me_completo.php`
2. Verifique o **Teste 3: Cookies**
   - Se disser "Nenhum cookie remember_token encontrado", o problema está na criação do cookie
   - Se disser que o token é inválido, verifique o banco de dados

3. **Se o cookie não existe:**
   - Verifique se o site está usando HTTPS (cookies HttpOnly funcionam melhor com HTTPS)
   - Verifique nos logs: `/logs/php_errors.log` ou `/logs/error.log`
   - Procure por mensagens de `RememberMeManager`

4. **Se o token é inválido:**
   - Pode estar expirado
   - Pode estar revogado manualmente
   - Tente criar um novo token (Teste 8)

### **Problema 2: Cookie definido mas não persiste**

**Causas comuns:**
- Cookies desabilitados no navegador
- Domínio incorreto
- HTTPS vs HTTP (mismatch)
- Configurações de privacidade do navegador

**Solução:**
1. Verifique em **Configurações do Navegador → Privacidade → Cookies**
2. Certifique-se de que cookies estão permitidos
3. Verifique em **DevTools → Application → Cookies** após fazer login
4. O cookie deve chamar `remember_token`

### **Problema 3: Token expirou**

**Verificar:**
1. Acesse: `https://seu-site/testar_remember_me_completo.php`
2. Vá ao **Teste 5: Tokens Ativos**
3. Procure por tokens com data expirada
4. Limpe o cookie e faça login novamente

### **Problema 4: Erro "Headers já enviados"**

**Causa:** Algum output foi enviado antes do `setcookie()`

**Solução:**
1. Verifique `login_process.php` (não deve ter output antes de `session_start()`)
2. Procure por `echo`, `print`, ou espaços em branco antes do `<?php`
3. Verifique os logs para localizar a linha exata

---

## 📊 Logs de Debug

### **Localização dos Logs**

Logs são salvos em:
```
/logs/error.log
```

### **O que procurar:**

```
RememberMeManager: Token inserido no banco para user_id: 1. Expira: 2025-11-15 10:30:00
RememberMeManager: Cookie definido com sucesso! Token: a1b2c3d4e5f6... Expira em: 2025-11-15 10:30:00
AUTO_LOGIN: Tentando login automático com token: a1b2c3d4e5f6...
AUTO_LOGIN: Login automático bem-sucedido! Usuário ID: 1
```

### **Procurar erros:**

```
RememberMeManager: FALHA ao definir cookie! Verifique se headers já foram enviados.
RememberMeManager: Token não encontrado ou expirado
RememberMeManager: Erro ao verificar token
```

---

## 🛡️ Segurança

### **Como o sistema funciona:**

1. **Token é criptográfico**
   - Gerado com `random_bytes()` (64 bytes = 128 caracteres hex)
   - Único para cada sessão de login
   - Impossível adivinhar

2. **Token é armazenado com segurança**
   - Apenas no banco de dados (não reversível)
   - Cookie marcado como `HttpOnly` (inacessível via JavaScript)
   - Cookie marcado como `Secure` (apenas HTTPS em produção)
   - Cookie com `SameSite=Lax` (proteção contra CSRF)

3. **Token é validado**
   - Verificado contra o banco de dados
   - Verificado se está ativo
   - Verificado se não expirou
   - User-Agent e IP são registrados (para auditoria futura)

4. **Token expira**
   - 30 dias de inatividade
   - Pode ser renovado a cada 7 dias de uso
   - Pode ser revogado manualmente (logout)

---

## 🧹 Limpeza de Tokens Expirados

### **Automático**

O sistema limpa automaticamente tokens expirados ao acessar:
```
https://seu-site/testar_remember_me_completo.php
```

### **Manual**

Para limpar tokens expirados via SQL:

```sql
DELETE FROM remember_tokens WHERE expires_at < NOW() OR is_active = 0;
```

---

## 📝 Exemplos de Uso

### **Obter estatísticas de um usuário**

```php
$rememberManager = new RememberMeManager($pdo);
$stats = $rememberManager->getTokenStats($userId);

echo "Total de tokens: " . $stats['total_tokens'];
echo "Tokens ativos: " . $stats['active_tokens'];
echo "Tokens expirados: " . $stats['expired_tokens'];
```

### **Revogar todos os tokens de um usuário**

```php
$rememberManager = new RememberMeManager($pdo);
$rememberManager->revokeAllUserTokens($userId);
// Força logout em todos os navegadores/dispositivos
```

### **Renovar um token**

```php
$rememberManager = new RememberMeManager($pdo);
$oldToken = $_COOKIE['remember_token'];
$newToken = $rememberManager->renewToken($oldToken);
```

---

## 🚀 Próximas Melhorias

- [ ] Suporte a múltiplos dispositivos com nomes personalizados
- [ ] Dashboard mostrando todos os logins ativos
- [ ] Notificação de novo login de um dispositivo desconhecido
- [ ] Two-Factor Authentication (2FA) com email
- [ ] Login social (Google, GitHub, etc.)

---

## ❓ FAQ

### **P: O token pode ser roubado?**
A: Sim, se alguém ganhar acesso aos cookies do navegador. Por isso usamos `HttpOnly` e `Secure`.

### **P: Funciona em múltiplos navegadores?**
A: Sim! Cada navegador/dispositivo tem seu próprio token.

### **P: O que acontece se eu mudar de dispositivo?**
A: Você precisará fazer login novamente no novo dispositivo. Depois, um novo token será criado.

### **P: Posso logout do "Lembrar-me"?**
A: Sim! Ao fazer logout normal, todos os tokens são revogados.

### **P: 30 dias é tempo suficiente?**
A: Sim, é um bom balanço entre segurança e conveniência. Pode ser ajustado em `includes/remember_me_manager.php` linha 7.

---

## 📞 Suporte

Se encontrar problemas:

1. Verifique os logs: `/logs/error.log`
2. Acesse a página de teste: `testar_remember_me_completo.php`
3. Verifique o navegador DevTools → Application → Cookies
4. Procure mensagens de `RememberMeManager` nos logs

---

**Última atualização:** Outubro de 2025  
**Versão:** 2.0 (Melhorada)
