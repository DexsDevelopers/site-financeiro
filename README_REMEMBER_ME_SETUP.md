# 🚀 Setup Rápido - Sistema "Lembrar-me por 30 Dias"

## 📌 O que foi feito?

O sistema **"Lembrar-me por 30 dias"** foi **totalmente refatorado e melhorado**. Aqui estão as mudanças:

### ✅ Arquivos Modificados/Criados:

1. **`includes/remember_me_manager.php`** (MELHORADO)
   - Adicionados logs detalhados
   - Melhorada tratamento de erros
   - Adicionado suporte a exceções

2. **`includes/auto_login.php`** (MELHORADO)
   - Adicionados logs de debug
   - Melhorada validação de token
   - Código mais robusto

3. **`login_process.php`** (MELHORADO)
   - Token criado ANTES da resposta JSON
   - Adicionados logs de debug
   - Melhor tratamento de erros
   - Codificação UTF-8

4. **`testar_remember_me_completo.php`** (NOVO)
   - Teste completo do sistema
   - Diagnóstico detalhado
   - Criação de token de teste

5. **`verificar_e_reparar_remember_me.php`** (NOVO)
   - Diagnóstico automático
   - Reparo de tabelas
   - Limpeza de tokens expirados

6. **`GUIA_REMEMBER_ME.md`** (NOVO)
   - Documentação completa
   - Troubleshooting
   - Exemplos de uso

---

## 🎯 Como Testar

### **Opção 1: Teste Rápido** (Recomendado)

```
1. Acesse: https://seu-site/verificar_e_reparar_remember_me.php
2. O sistema fará diagnóstico automático
3. Siga as instruções na tela
```

### **Opção 2: Teste Detalhado**

```
1. Acesse: https://seu-site/testar_remember_me_completo.php
2. Verifique cada teste
3. Se tudo passar, faça login
```

### **Opção 3: Manual**

```
1. Faça logout completo
2. Limpe cookies do navegador
3. Faça login normalmente
4. MARQUE "Lembrar-me por 30 dias"
5. Feche o navegador completamente
6. Abra novamente e acesse o site
7. Você deve estar automaticamente logado ✓
```

---

## 🔍 Troubleshooting

### ❌ **Problema: Não está fazendo login automático**

**Solução:**

1. Acesse a página de teste:
   ```
   https://seu-site/verificar_e_reparar_remember_me.php
   ```

2. Verifique o resultado:
   - ✅ Se passou em todos os testes, faça login novamente
   - ❌ Se algum teste falhou, anote qual e procure abaixo

### ❌ **Erro: Tabela remember_tokens não existe**

**Solução:**
- A página de verificação cria automaticamente
- Se não funcionar, execute no MySQL:
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

### ❌ **Erro: Cookie não persiste**

**Causas e soluções:**

| Problema | Solução |
|----------|---------|
| Cookies desabilitados | Habilitar em Configurações do Navegador |
| HTTPS vs HTTP | Usar HTTPS em produção |
| Domínio incorreto | Verificar `domain` em `setcookie()` |
| SameSite restritivo | Usar `SameSite=Lax` (já configurado) |

### ❌ **Erro: "Headers já enviados"**

**Causa:** Output antes de `session_start()`

**Solução:**
1. Verifique `login_process.php`
2. Procure por espaços ou caracteres antes de `<?php`
3. Procure por `echo` ou `print` antes de `session_start()`

---

## 📊 Verificar Tokens no Banco

Para ver todos os tokens ativos:

```sql
SELECT 
    rt.id,
    u.usuario,
    u.nome_completo,
    SUBSTR(rt.token, 1, 20) as token_preview,
    rt.expires_at,
    rt.is_active,
    DATEDIFF(rt.expires_at, NOW()) as dias_para_expirar
FROM remember_tokens rt
JOIN usuarios u ON rt.user_id = u.id
WHERE rt.is_active = 1 AND rt.expires_at > NOW()
ORDER BY rt.created_at DESC;
```

---

## 🧹 Limpar Tokens Antigos

Para limpar tokens expirados e revogados:

```sql
DELETE FROM remember_tokens 
WHERE expires_at < NOW() OR is_active = 0;
```

---

## 📊 Ver Logs

Para ver logs de debug:

```bash
# Em Linux/Mac
tail -f /logs/error.log | grep RememberMeManager

# Em Windows (PowerShell)
Get-Content /logs/error.log -Tail 50 | Select-String "RememberMeManager"
```

Procure por:
- ✅ `Token criado com sucesso`
- ✅ `Login automático bem-sucedido`
- ❌ `FALHA ao definir cookie`
- ❌ `Token inválido ou expirado`

---

## ✨ Recursos Avançados

### **Revogar todos os tokens de um usuário**

```php
$rememberManager = new RememberMeManager($pdo);
$rememberManager->revokeAllUserTokens($userId);
// Força logout em todos os dispositivos
```

### **Obter estatísticas**

```php
$stats = $rememberManager->getTokenStats($userId);
echo $stats['active_tokens']; // Quantos dispositivos estão logados
```

### **Renovar token manualmente**

```php
$newToken = $rememberManager->renewToken($oldToken);
```

---

## 🔐 Segurança

### **O que está protegido:**

- ✅ Token é criptográfico (256 bits)
- ✅ Token é único (sem repetição)
- ✅ Token expire em 30 dias
- ✅ Token renovado a cada 7 dias
- ✅ Cookie é HttpOnly (não acessível via JavaScript)
- ✅ Cookie é Secure (apenas HTTPS em produção)
- ✅ Proteção CSRF (SameSite=Lax)
- ✅ User-Agent e IP registrados para auditoria

---

## 📝 Checklist Final

- [ ] Acessei `verificar_e_reparar_remember_me.php` e passou em todos os testes
- [ ] Tabela `remember_tokens` foi criada
- [ ] Fiz logout e limpei cookies
- [ ] Fiz login novamente marcando "Lembrar-me"
- [ ] Fechei o navegador completamente
- [ ] Abri novamente e estava automaticamente logado
- [ ] Li o guia completo em `GUIA_REMEMBER_ME.md`

---

## 📞 Próximos Passos

1. **Agora:** Teste o sistema seguindo as instruções acima
2. **Depois:** Consulte `GUIA_REMEMBER_ME.md` para mais detalhes
3. **Se houver problemas:** Acesse `testar_remember_me_completo.php` para diagnosticar

---

**Versão:** 2.0 (Totalmente Refatorado)  
**Data:** Outubro de 2025  
**Status:** ✅ Pronto para Produção
