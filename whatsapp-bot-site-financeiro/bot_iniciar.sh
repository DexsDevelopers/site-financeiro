cd "$(dirname "$0")"
echo "Iniciando o bot de WhatsApp (Financeiro)..."
# O bot do financeiro já usa a porta 3001 definida no .env
pm2 start index.js --name bot-financeiro --node-args="--max-old-space-size=4096 --expose-gc"
pm2 save


echo "Bot Financeiro iniciado em segundo plano."
read -p "Pressione Enter para ver o status..."
pm2 status
