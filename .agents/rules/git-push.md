---
trigger: always_on
---

Regra global de versionamento:

Sempre que arquivos forem criados, modificados ou deletados no projeto, faça automaticamente:

git add .
git commit -m "auto update"
git push

Antes de executar, verifique mudanças com:
git status --porcelain

Se não houver alterações, não faça commit.

Após o push, mostre no terminal:
"Changes pushed to repository successfully."

Se ocorrer erro no push, tente novamente.