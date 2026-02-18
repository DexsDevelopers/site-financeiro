@echo off
title Deploy Instantâneo - Helmer
color 0a
echo ==============================================
echo  ⚡ Deploy Instantâneo - Ultra Rápido
echo ==============================================
echo.

:: Caminho do projeto
cd "C:\Users\Johan 7K\Documents\GitHub\site-financeiro"

:: Executa o script PowerShell instantâneo
powershell -ExecutionPolicy Bypass -File ".\.cursor\deploy-instantaneo.ps1"

echo.
echo ==============================================
echo ✅ Deploy instantâneo finalizado!
echo ==============================================
pause
