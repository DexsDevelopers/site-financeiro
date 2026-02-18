@echo off
title Deploy Rápido - Helmer
color 0a
echo ==============================================
echo  🚀 Deploy Rápido - Otimizado
echo ==============================================
echo.

:: Caminho do projeto
cd "C:\Users\Johan 7K\Documents\GitHub\site-financeiro"

:: Executa o script PowerShell otimizado
powershell -ExecutionPolicy Bypass -File ".\.cursor\deploy-otimizado.ps1"

echo.
echo ==============================================
echo ✅ Deploy finalizado! (Versão Otimizada)
echo ==============================================
pause
