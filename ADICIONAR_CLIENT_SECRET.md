# 🔐 Como Adicionar o Client Secret do Google OAuth

## ⚠️ IMPORTANTE: Segurança

**NUNCA compartilhe sua chave secreta publicamente!** Ela deve ser mantida em segredo.

## 📝 Passo a Passo

### 1. Obter a Chave Secreta Completa

1. No Google Cloud Console, você vê apenas: `****ALCg`
2. Clique no ícone de **olho** 👁️ ou **copiar** 📋 ao lado da chave secreta
3. **Copie a chave secreta completa** (ela será algo como: `GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx`)

### 2. Adicionar no Arquivo

1. Abra o arquivo: `includes/db_connect.php`
2. Encontre a linha comentada:
   ```php
   // define('GOOGLE_CLIENT_SECRET', 'SEU_CLIENT_SECRET_AQUI');
   ```
3. **Descomente e adicione sua chave secreta:**
   ```php
   define('GOOGLE_CLIENT_SECRET', 'GOCSPX-sua-chave-secreta-aqui');
   ```
4. Salve o arquivo

### 3. Verificar Configuração

Após adicionar, a integração estará pronta. Você pode testar:

1. Acesse: `integracoes_google.php` no painel
2. O aviso sobre Client Secret não configurado deve desaparecer
3. Clique em **Conectar Google** para testar

## 🔒 Boas Práticas

- ✅ Mantenha o arquivo `db_connect.php` fora do controle de versão (se possível)
- ✅ Use variáveis de ambiente em produção
- ✅ Nunca commite chaves secretas no Git
- ✅ Revogue e recrie chaves se expostas acidentalmente

## 📋 Exemplo Completo

```php
// Google OAuth (Integrações Google)
define('GOOGLE_CLIENT_ID', '945016861625-47dgg8sgrqgqpt99ct7e46l0o52vn2up.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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qui');
```



