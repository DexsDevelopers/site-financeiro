<?php
// test_admin_bot_api.php - Teste do admin_bot_api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE ADMIN_BOT_API.PHP ===\n\n";

// Teste 1: Verificar se arquivos existem
echo "1. Verificando arquivos...\n";
$files = [
    'includes/db_connect.php',
    'includes/finance_helper.php',
    'includes/tasks_helper.php',
    'includes/command_helper.php',
    'config.json'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file existe\n";
    } else {
        echo "   ❌ $file NÃO existe\n";
    }
}

// Teste 2: Verificar se funções estão disponíveis
echo "\n2. Verificando funções...\n";
try {
    require_once 'includes/db_connect.php';
    echo "   ✅ db_connect.php carregado\n";
} catch (Exception $e) {
    echo "   ❌ Erro ao carregar db_connect.php: " . $e->getMessage() . "\n";
}

try {
    require_once 'includes/finance_helper.php';
    echo "   ✅ finance_helper.php carregado\n";
} catch (Exception $e) {
    echo "   ❌ Erro ao carregar finance_helper.php: " . $e->getMessage() . "\n";
}

try {
    require_once 'includes/tasks_helper.php';
    echo "   ✅ tasks_helper.php carregado\n";
} catch (Exception $e) {
    echo "   ❌ Erro ao carregar tasks_helper.php: " . $e->getMessage() . "\n";
}

try {
    require_once 'includes/command_helper.php';
    echo "   ✅ command_helper.php carregado\n";
    
    // Verificar se funções existem
    if (function_exists('parseMoney')) {
        echo "   ✅ parseMoney() existe\n";
    } else {
        echo "   ❌ parseMoney() NÃO existe\n";
    }
    
    if (function_exists('formatHelpMessage')) {
        echo "   ✅ formatHelpMessage() existe\n";
    } else {
        echo "   ❌ formatHelpMessage() NÃO existe\n";
    }
    
    if (function_exists('suggestCommand')) {
        echo "   ✅ suggestCommand() existe\n";
    } else {
        echo "   ❌ suggestCommand() NÃO existe\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro ao carregar command_helper.php: " . $e->getMessage() . "\n";
}

// Teste 3: Verificar config.json
echo "\n3. Verificando config.json...\n";
try {
    $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    if ($config) {
        echo "   ✅ config.json carregado\n";
        echo "   WHATSAPP_API_TOKEN: " . (isset($config['WHATSAPP_API_TOKEN']) ? 'Definido' : 'Não definido') . "\n";
    } else {
        echo "   ❌ config.json não pôde ser decodificado\n";
    }
} catch (Exception $e) {
    echo "   ❌ Erro ao carregar config.json: " . $e->getMessage() . "\n";
}

// Teste 4: Simular requisição
echo "\n4. Simulando requisição...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
file_put_contents('php://input', json_encode([
    'phone' => '553791101425',
    'command' => '!menu',
    'args' => [],
    'message' => '!menu'
]));

echo "   ✅ Dados simulados\n";
echo "\n=== FIM DO TESTE ===\n";
echo "\nSe todos os testes passaram, o problema pode estar em:\n";
echo "- Erro de sintaxe PHP\n";
echo "- Erro de banco de dados\n";
echo "- Erro em alguma função específica\n";
echo "\nVerifique os logs de erro do PHP no servidor.\n";



