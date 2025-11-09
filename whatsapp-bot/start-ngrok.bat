@echo off
setlocal

:: Caminhos — edite conforme seu ambiente
set PROJECT_PATH=C:\Users\Johan 7K\Documents\GitHub\site-financeiro
set HTACCESS_PATH=%PROJECT_PATH%\public_html\seu_projeto\.htaccess

echo Iniciando ngrok...
start "" ngrok http 3000 > nul

echo Aguardando ngrok iniciar...
timeout /t 5 > nul

:: Captura o link público gerado pelo ngrok
for /f "tokens=2 delims=: " %%A in ('curl -s http://127.0.0.1:4040/api/tunnels ^| findstr "public_url"') do (
    set "NGROK_URL=%%A"
)
set NGROK_URL=%NGROK_URL:"=%

echo Novo link do ngrok: %NGROK_URL%

:: Atualiza o arquivo .htaccess com o novo link
powershell -Command "(Get-Content '%HTACCESS_PATH%') -replace 'SetEnv WHATSAPP_API_URL .*', 'SetEnv WHATSAPP_API_URL %NGROK_URL%' | Set-Content '%HTACCESS_PATH%'"

echo .htaccess atualizado com sucesso!
pause
