#!/bin/bash
echo "Parando o bot do Financeiro..."
pm2 stop bot-financeiro
pm2 delete bot-financeiro
pm2 save
echo "Bot Financeiro parado e removido."
