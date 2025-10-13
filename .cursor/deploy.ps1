# 🚀 Script de deploy automático para Hostinger via GitHub
# Autor: Lucas + Cursor IA

$BRANCH = "main"
$REPO = "https://github.com/DexsDevelopers/site-financeiro.git"

Write-Host "🔄 Iniciando deploy..." -ForegroundColor Cyan

git add .
git commit -m "Atualização automática do Cursor 🚀"
git push origin $BRANCH

Write-Host "✅ Código enviado para o GitHub ($REPO)" -ForegroundColor Green
Write-Host "⏳ Aguardando Hostinger atualizar via webhook..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

Write-Host "🚀 Deploy Hostinger em andamento. Verifique o painel se quiser ver o log!" -ForegroundColor Magenta

# 🔔 Notificação visual (requer Windows 10 ou 11)
Add-Type -AssemblyName System.Windows.Forms
[System.Windows.Forms.MessageBox]::Show("Deploy enviado com sucesso para o GitHub e Hostinger!", "Deploy Concluído 🚀", 'OK', 'Information')
