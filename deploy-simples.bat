@echo off
title Deploy Simples - Helmer
color 0a
echo ==============================================
echo  🚀 Deploy Simples e Rápido
echo ==============================================
echo.

:: Caminho do projeto
cd "C:\Users\Johan 7K\Documents\GitHub\site-financeiro"

:: Executa o script PowerShell simples
powershell -ExecutionPolicy Bypass -File ".\.cursor\deploy-simples.ps1"

echo.
echo ==============================================
echo ✅ Deploy finalizado! (Versão Simples)
echo ==============================================
pause
