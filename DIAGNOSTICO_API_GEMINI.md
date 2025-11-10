# 🔍 Diagnóstico da API Gemini

## ⚠️ Problema: Erro 429 mesmo com API não no limite

Se você está recebendo erro 429 mas a API não está no limite, pode ser:

### 1. **Rate Limiting Interno Bloqueando**

O sistema tem um rate limiting interno que pode estar bloqueando antes mesmo de chegar na API.

**Solução:**
1. Acesse `processar_ia.php`
2. Encontre a linha: `$enableInternalRateLimit = true;`
3. Altere para: `$enableInternalRateLimit = false;`
4. Isso desabilita o rate limiting interno temporariamente

### 2. **Rate Limit Temporário da API**

A API pode ter um rate limit por minuto/hora que você atingiu temporariamente, mesmo não tendo excedido a cota total.

**Solução:**
- Aguarde alguns segundos/minutos e tente novamente
- O sistema tenta automaticamente após alguns segundos

### 3. **Múltiplas Requisições Simultâneas**

Se você está fazendo muitas requisições muito rápido, a API pode bloquear temporariamente.

**Solução:**
- Aguarde entre requisições
- Não faça muitas requisições ao mesmo tempo

## 🧪 Teste da API

Use o script de teste para verificar o status da API:

```
https://seu-site.com/testar_api_gemini.php
```

Este script vai:
- ✅ Testar a conexão com a API
- ✅ Verificar se a chave está válida
- ✅ Mostrar o tipo de erro (se houver)
- ✅ Mostrar informações de debug

## 🔧 Configurações Atuais

### Rate Limiting Interno:
- **10 requisições por minuto** (aumentado de 5)
- **60 requisições por hora** (aumentado de 30)
- **10 segundos** entre requisições (reduzido de 60)

### Para Desabilitar Rate Limiting Interno:

1. Abra `processar_ia.php`
2. Encontre:
```php
$enableInternalRateLimit = true;
```
3. Altere para:
```php
$enableInternalRateLimit = false;
```

## 📊 Tipos de Erro 429

O sistema agora distingue entre:

1. **Rate Limit Interno** - Bloqueio do próprio sistema
   - Mensagem: "Limite interno do sistema"
   - Solução: Desabilitar rate limiting interno temporariamente

2. **Rate Limit Temporário da API** - Bloqueio temporário da Google
   - Mensagem: "Limite de requisições temporário"
   - Solução: Aguardar alguns segundos

3. **Cota Excedida** - Limite do plano gratuito atingido
   - Mensagem: "Cota da API excedida"
   - Solução: Aguardar reset da cota ou atualizar plano

## 🚀 Próximos Passos

1. **Execute o teste:**
   ```
   https://seu-site.com/testar_api_gemini.php
   ```

2. **Verifique o resultado:**
   - Se a API está funcionando: O problema pode ser o rate limiting interno
   - Se a API retorna erro: Veja o tipo de erro no resultado

3. **Ajuste conforme necessário:**
   - Se for rate limiting interno: Desabilite temporariamente
   - Se for rate limit da API: Aguarde e tente novamente
   - Se for cota excedida: Aguarde reset ou atualize plano

## 📝 Logs e Debug

O sistema agora inclui informações de debug nas respostas de erro:
- Tipo de erro
- Código HTTP
- Mensagem da API
- Preview da resposta
- Informações de rate limiting

Verifique o console do navegador (F12) para ver os detalhes completos.

---

**Última Atualização:** Janeiro 2025

