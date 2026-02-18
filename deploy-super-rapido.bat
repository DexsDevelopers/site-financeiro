@echo off
title Deploy Super Rápido - Helmer
color 0a
echo ==============================================
echo  🚀 Deploy Super Rápido - Apenas Commit + Push
echo ==============================================
echo.

:: Caminho do projeto
cd "C:\Users\Johan 7K\Documents\GitHub\site-financeiro"

echo 📝 Fazendo commit das mudanças...
git add .
git commit -m "Deploy super rápido - %date% %time%"

echo 🚀 Enviando para GitHub...
git push origin main

echo.
echo ✅ Deploy super rápido concluído!
echo 💡 O site será atualizado automaticamente pela Hostinger
echo 🔗 Verifique: https://gold-quail-250128.hostingersite.com/seu_projeto/
echo.

pause
