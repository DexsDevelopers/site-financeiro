<?php
// test_api_bot.php - Teste rápido da API do bot
header('Content-Type: application/json; charset=utf-8');

require_once 'includes/db_connect.php';
require_once 'includes/finance_helper.php';

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

// Simular requisição do bot
$testData = [
    'phone' => '553791101425',
    'command' => '!menu',
    'args' => [],
    'message' => '!menu'
];

// Validar token
$headers = getallheaders();
$token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

if ($token !== $config['WHATSAPP_API_TOKEN']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token inválido. Token recebido: ' . substr($token, 0, 10) . '...']);
    exit;
}

// Processar comando
$phoneNumber = $testData['phone'];
$command = $testData['command'];
$args = $testData['args'];

switch ($command) {
    case '!menu':
    case '!help':
        $response = [
            'success' => true,
            'message' => "📋 *MENU DE COMANDOS*\n\n" .
                       "*FINANCEIRO*\n" .
                       "💰 !receita VALOR DESCRIÇÃO [CLIENTE]\n" .
                       "💸 !despesa VALOR DESCRIÇÃO [CATEGORIA]\n" .
                       "💵 !saldo [MÊS]\n" .
                       "📊 !extrato [INÍCIO] [FIM]\n" .
                       "🗑️ !deletar ID\n\n" .
                       "*CLIENTES*\n" .
                       "👤 !cliente NOME TELEFONE [EMAIL]\n" .
                       "📋 !clientes\n" .
                       "ℹ️ !clienteinfo ID\n" .
                       "⚠️ !pendencias [ID]\n\n" .
                       "*COMPROVANTES*\n" .
                       "📸 !comprovante ID\n" .
                       "👁️ !vercomprovante ID\n\n" .
                       "*RELATÓRIOS*\n" .
                       "📈 !relatorio [MÊS]\n" .
                       "📊 !dashboard\n" .
                       "🏆 !topo [LIMITE]\n\n" .
                       "*COBRANÇAS*\n" .
                       "💳 !cobrar CLIENTE_ID VALOR VENCIMENTO DESCRIÇÃO\n" .
                       "🔔 !lembrar COBRANCA_ID\n" .
                       "📨 !notificar CLIENTE_ID MENSAGEM\n" .
                       "✅ !pagar COBRANCA_ID\n\n" .
                       "💡 Digite !ajuda COMANDO para detalhes"
        ];
        break;
    default:
        $response = ['success' => false, 'message' => 'Comando não reconhecido'];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

