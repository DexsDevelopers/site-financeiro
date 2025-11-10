# 📚 Entendendo Como o Git Funciona

## Como o `git push` Funciona

### ❌ O que o Git NÃO faz:
- ❌ Não reenvia TODOS os arquivos a cada push
- ❌ Não transfere o projeto inteiro novamente
- ❌ Não é ineficiente

### ✅ O que o Git FAZ:
- ✅ Envia apenas os **novos commits** (mudanças)
- ✅ Cada commit contém apenas as **diferenças** (diffs)
- ✅ Apenas arquivos **modificados, novos ou removidos**
- ✅ Usa **compressão** para reduzir o tamanho
- ✅ É **muito eficiente** mesmo em projetos grandes

## Exemplo Prático

### Situação:
- Projeto tem **1000 arquivos**
- Você modifica **1 arquivo** (admin/index.php)
- Faz commit e push

### O que é enviado:
- ✅ Apenas as **diferenças** do admin/index.php
- ✅ Informações do commit (hash, mensagem, autor)
- ❌ Os outros **999 arquivos** NÃO são enviados

### Tamanho do push:
- Projeto completo: ~50 MB
- Push com 1 arquivo modificado: ~5 KB
- **99.99% mais eficiente!**

## Como Funciona Internamente

### 1. Commit Local:
```
git add admin/index.php
git commit -m "feat: adicionar filtros"
```
- Cria um **snapshot** das mudanças
- Gera um **hash único** (ex: e3de58a)
- Armazena apenas as **diferenças**

### 2. Push para GitHub:
```
git push origin main
```
- Envia apenas o **novo commit** (hash e diffs)
- GitHub recebe e **aplica as mudanças**
- Outros arquivos permanecem **inalterados**

### 3. Pull na Hostinger:
```
git pull origin main
```
- Hostinger **baixa apenas** o novo commit
- **Aplica as mudanças** nos arquivos locais
- Sincroniza com o GitHub

## Por Que a Hostinger Não Atualiza?

### Problema:
1. ✅ Você faz `git push` → GitHub recebe as mudanças
2. ❌ Hostinger **não faz** `git pull` → Não busca as mudanças
3. ❌ Arquivos na Hostinger **permanecem desatualizados**

### Solução:
- **Manual**: Fazer `git pull` na Hostinger
- **Automático**: Configurar webhook ou cron job
- **Integrado**: Usar funcionalidade Git da Hostinger

## Verificação

### Ver o que será enviado:
```bash
git status              # Ver arquivos modificados
git diff --stat         # Ver estatísticas das mudanças
git log --oneline -5    # Ver últimos commits
```

### Ver o que foi enviado:
```bash
git log --oneline --stat -5    # Ver commits e arquivos modificados
git diff --stat HEAD~5 HEAD    # Comparar últimos 5 commits
```

## Resumo

| Ação | O que acontece |
|------|----------------|
| `git add` | Adiciona arquivos ao **staging area** |
| `git commit` | Cria **snapshot** das mudanças (local) |
| `git push` | Envia **apenas os novos commits** para o remoto |
| `git pull` | Baixa **apenas os novos commits** do remoto |

## Conclusão

- ✅ `git push` é **muito eficiente**
- ✅ Envia apenas **mudanças**
- ✅ Não reenvia arquivos **inalterados**
- ✅ O problema na Hostinger é **falta de sincronização**
- ✅ Solução: Fazer `git pull` ou configurar **auto-deploy**

