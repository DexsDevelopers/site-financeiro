// test_api_connection.js - Testar conexão com API PHP
const axios = require('axios');
require('dotenv').config();

const ADMIN_API_URL = process.env.ADMIN_API_URL || 'http://localhost/seu_projeto';
const API_TOKEN = process.env.API_TOKEN || 'site-financeiro-token-2024';

const testUrls = [
    'http://localhost/seu_projeto/admin_bot_api.php',
    'http://localhost:80/seu_projeto/admin_bot_api.php',
    'http://localhost:8080/seu_projeto/admin_bot_api.php',
    'http://127.0.0.1/seu_projeto/admin_bot_api.php',
    ADMIN_API_URL + '/admin_bot_api.php'
];

console.log('🔍 Testando conexão com API PHP...\n');
console.log(`Token: ${API_TOKEN.substring(0, 10)}...\n`);

async function testUrl(url) {
    try {
        console.log(`Testando: ${url}`);
        const response = await axios.post(url, {
            phone: '553791101425',
            command: '!menu',
            args: [],
            message: '!menu'
        }, {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${API_TOKEN}`
            },
            timeout: 5000
        });
        
        console.log(`✅ SUCESSO! Status: ${response.status}`);
        console.log(`Resposta:`, response.data);
        return true;
    } catch (error) {
        if (error.code === 'ECONNREFUSED') {
            console.log(`❌ ERRO: Não foi possível conectar (servidor não está rodando)`);
        } else if (error.response) {
            console.log(`⚠️  Resposta recebida: Status ${error.response.status}`);
            console.log(`Dados:`, error.response.data);
        } else {
            console.log(`❌ ERRO: ${error.message}`);
        }
        return false;
    }
}

async function runTests() {
    for (const url of testUrls) {
        const success = await testUrl(url);
        if (success) {
            console.log(`\n✅ URL FUNCIONANDO: ${url}`);
            console.log(`\n📝 Configure no .env:`);
            console.log(`ADMIN_API_URL=${url.replace('/admin_bot_api.php', '')}`);
            break;
        }
        console.log('');
    }
}

runTests();



