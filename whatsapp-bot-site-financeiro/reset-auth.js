// reset-auth.js - Limpa a autenticação corrompida do WhatsApp
const fs = require('fs');
const path = require('path');

const authDir = path.join(__dirname, 'auth-site-financeiro');

console.log('🔧 Limpando autenticação do WhatsApp...');

if (fs.existsSync(authDir)) {
  try {
    // Lista todos os arquivos na pasta de auth
    const files = fs.readdirSync(authDir);
    
    files.forEach(file => {
      const filePath = path.join(authDir, file);
      try {
        fs.unlinkSync(filePath);
        console.log(`✅ Removido: ${file}`);
      } catch (err) {
        console.error(`❌ Erro ao remover ${file}:`, err.message);
      }
    });
    
    console.log('✅ Autenticação limpa com sucesso!');
    console.log('📱 Reinicie o bot e escaneie o QR Code novamente.');
  } catch (err) {
    console.error('❌ Erro ao limpar autenticação:', err.message);
  }
} else {
  console.log('ℹ️ Pasta de autenticação não existe.');
}

