@echo off
title Deploy Hostinger - Helmer
color 0a
echo ==============================================
echo  🚀 Iniciando Deploy Automático - Cursor -> Hostinger
echo ==============================================
echo.

:: Caminho do projeto
cd "C:\Users\Johan 7K\Documents\GitHub\site-financeiro"

:: Executa o script PowerShell sem bloqueio de política
powershell -ExecutionPolicy Bypass -File ".\.cursor\deploy.ps1"

echo.
echo ==============================================
echo ✅ Deploy finalizado! Verifique o log ou o popup.
echo ==============================================
pause
