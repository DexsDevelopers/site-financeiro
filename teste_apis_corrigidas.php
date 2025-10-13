<?php
// teste_apis_corrigidas.php - Teste das APIs após correções

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🧪 TESTE DAS APIs CORRIGIDAS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Função para testar API com melhor tratamento de erros
function testarAPICorrigida($nome, $url) {
    echo "<h3>🔍 Teste: $nome</h3>";
    echo "URL: $url<br>";
    
    try {
        // Configurar contexto HTTP
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: Mozilla/5.0 (compatible; API-Test)'
                ],
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            echo "❌ Erro ao acessar a API<br>";
            return false;
        }
        
        echo "✅ Resposta recebida (tamanho: " . strlen($response) . " bytes)<br>";
        
        // Verificar se a resposta não está vazia
        if (empty(trim($response))) {
            echo "❌ Resposta vazia<br>";
            return false;
        }
        
        // Mostrar primeiros 200 caracteres da resposta
        echo "Primeiros 200 caracteres: " . htmlspecialchars(substr($response, 0, 200)) . "...<br>";
        
        // Tentar decodificar JSON
        $data = json_decode($response, true);
        
        if ($data === null) {
            echo "❌ Resposta não é JSON válido<br>";
            echo "Erro JSON: " . json_last_error_msg() . "<br>";
            echo "Resposta completa: " . htmlspecialchars($response) . "<br>";
            return false;
        }
        
        echo "✅ JSON válido<br>";
        echo "Dados decodificados:<br>";
        echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        if (isset($data['success']) && $data['success'] === true) {
            echo "✅ API funcionando corretamente<br>";
            return true;
        } else {
            echo "❌ API retornou erro: " . ($data['message'] ?? 'Erro desconhecido') . "<br>";
            return false;
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao testar API: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Testar cada API
$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
$resultados = [];

echo "<h2>📊 RESULTADOS DOS TESTES</h2>";

$resultados['tarefas_hoje'] = testarAPICorrigida("Tarefas de Hoje", $baseUrl . "/buscar_tarefas_hoje.php");
echo "<hr>";

$resultados['distribuicao'] = testarAPICorrigida("Distribuição por Prioridade", $baseUrl . "/buscar_distribuicao_prioridade.php");
echo "<hr>";

$resultados['produtividade'] = testarAPICorrigida("Produtividade 7 Dias", $baseUrl . "/buscar_produtividade_7_dias.php");
echo "<hr>";

// Resumo dos resultados
echo "<h2>📋 RESUMO DOS RESULTADOS</h2>";
$sucessos = 0;
$total = count($resultados);

foreach ($resultados as $nome => $sucesso) {
    if ($sucesso) {
        echo "✅ $nome: FUNCIONANDO<br>";
        $sucessos++;
    } else {
        echo "❌ $nome: COM PROBLEMAS<br>";
    }
}

echo "<br><strong>Total: $sucessos/$total APIs funcionando</strong><br>";

if ($sucessos === $total) {
    echo "<br><h3>🎉 PARABÉNS! Todas as APIs estão funcionando!</h3>";
    echo "O sistema de estatísticas está funcionando corretamente.<br>";
    echo "<a href='tarefas.php' class='btn btn-success'>Voltar para Tarefas</a>";
} else {
    echo "<br><h3>⚠️ Ainda há problemas</h3>";
    echo "Algumas APIs ainda não estão funcionando. Verifique:<br>";
    echo "1. Se há tarefas no banco de dados<br>";
    echo "2. Se o usuário está logado corretamente<br>";
    echo "3. Se há erros nos logs do servidor<br>";
    echo "<br><a href='corrigir_estatisticas.php' class='btn btn-warning'>Executar Correção Automática</a>";
}

// Teste adicional: verificar se as tabelas existem
echo "<h3>🔍 VERIFICAÇÃO ADICIONAL DO BANCO</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if ($stmt->fetch()) {
        echo "✅ Tabela 'tarefas' existe<br>";
        
        // Verificar se há dados
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $total = $stmt->fetch()['total'];
        echo "📊 Total de tarefas do usuário: $total<br>";
        
        if ($total == 0) {
            echo "⚠️ Nenhuma tarefa encontrada. Isso pode causar problemas nas APIs.<br>";
        }
    } else {
        echo "❌ Tabela 'tarefas' não existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao verificar banco: " . $e->getMessage() . "<br>";
}
?>
