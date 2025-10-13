<?php
// diagnostico_json_apis.php - Diagnóstico específico de problemas JSON nas APIs

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔍 DIAGNÓSTICO ESPECÍFICO DE PROBLEMAS JSON</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Função para testar JSON específico
function testarJSON($nome, $url) {
    echo "<h3>🧪 Teste JSON: $nome</h3>";
    
    // Capturar output
    ob_start();
    
    try {
        // Fazer requisição
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Content-Type: application/json',
                'timeout' => 10
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        $output = ob_get_clean();
        
        echo "URL: $url<br>";
        echo "Status HTTP: " . (isset($http_response_header) ? $http_response_header[0] : 'Desconhecido') . "<br>";
        
        if ($response === false) {
            echo "❌ Erro ao acessar URL<br>";
            return false;
        }
        
        echo "✅ Resposta recebida<br>";
        echo "Tamanho da resposta: " . strlen($response) . " bytes<br>";
        
        // Verificar se há output antes do JSON
        if (!empty($output)) {
            echo "⚠️ Output antes do JSON: " . htmlspecialchars($output) . "<br>";
        }
        
        // Verificar se a resposta começa com {
        if (substr(trim($response), 0, 1) !== '{') {
            echo "❌ Resposta não começa com JSON válido<br>";
            echo "Primeiros 100 caracteres: " . htmlspecialchars(substr($response, 0, 100)) . "<br>";
            return false;
        }
        
        // Tentar decodificar JSON
        $data = json_decode($response, true);
        
        if ($data === null) {
            echo "❌ JSON inválido<br>";
            echo "Erro JSON: " . json_last_error_msg() . "<br>";
            echo "Código do erro: " . json_last_error() . "<br>";
            echo "Resposta completa: " . htmlspecialchars($response) . "<br>";
            return false;
        }
        
        echo "✅ JSON válido<br>";
        echo "Estrutura do JSON:<br>";
        echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        return true;
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ Exceção: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Testar cada API
$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

echo "<h2>📊 TESTE DAS APIs</h2>";

$apis = [
    'buscar_tarefas_hoje.php' => 'Tarefas de Hoje',
    'buscar_distribuicao_prioridade.php' => 'Distribuição por Prioridade',
    'buscar_produtividade_7_dias.php' => 'Produtividade 7 Dias'
];

$resultados = [];

foreach ($apis as $arquivo => $nome) {
    $url = $baseUrl . "/" . $arquivo;
    $resultados[$arquivo] = testarJSON($nome, $url);
    echo "<hr>";
}

// Resumo
echo "<h2>📋 RESUMO DOS RESULTADOS</h2>";
$sucessos = 0;
$total = count($resultados);

foreach ($resultados as $arquivo => $sucesso) {
    if ($sucesso) {
        echo "✅ $arquivo: FUNCIONANDO<br>";
        $sucessos++;
    } else {
        echo "❌ $arquivo: COM PROBLEMAS<br>";
    }
}

echo "<br><strong>Total: $sucessos/$total APIs funcionando</strong><br>";

// Verificações adicionais
echo "<h3>🔍 VERIFICAÇÕES ADICIONAIS</h3>";

// Verificar se as tabelas existem
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if ($stmt->fetch()) {
        echo "✅ Tabela 'tarefas' existe<br>";
    } else {
        echo "❌ Tabela 'tarefas' não existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao verificar tabela: " . $e->getMessage() . "<br>";
}

// Verificar se há dados
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetch()['total'];
    echo "📊 Total de tarefas: $total<br>";
} catch (Exception $e) {
    echo "❌ Erro ao contar tarefas: " . $e->getMessage() . "<br>";
}

// Verificar configuração PHP
echo "<h3>⚙️ CONFIGURAÇÃO PHP</h3>";
echo "Versão PHP: " . phpversion() . "<br>";
echo "JSON support: " . (function_exists('json_encode') ? 'Sim' : 'Não') . "<br>";
echo "PDO support: " . (class_exists('PDO') ? 'Sim' : 'Não') . "<br>";
echo "Session support: " . (function_exists('session_start') ? 'Sim' : 'Não') . "<br>";

if ($sucessos === $total) {
    echo "<br><h3>🎉 TODAS AS APIs ESTÃO FUNCIONANDO!</h3>";
    echo "<a href='tarefas.php' class='btn btn-success'>Voltar para Tarefas</a>";
} else {
    echo "<br><h3>⚠️ AINDA HÁ PROBLEMAS</h3>";
    echo "Execute as correções necessárias e teste novamente.<br>";
}
?>
