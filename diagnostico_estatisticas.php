<?php
// diagnostico_estatisticas.php - Diagnóstico das APIs de estatísticas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔍 DIAGNÓSTICO DAS APIs DE ESTATÍSTICAS</h2>";

// 1. Verificar se o usuário está logado
echo "<h3>1. Verificação de Sessão</h3>";
if (isset($_SESSION['user_id'])) {
    echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ Usuário não está logado<br>";
    echo "Solução: Faça login primeiro<br><br>";
}

// 2. Verificar conexão com banco
echo "<h3>2. Verificação de Conexão com Banco</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Conexão com banco funcionando<br>";
} catch (Exception $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "<br>";
}

// 3. Verificar estrutura da tabela tarefas
echo "<h3>3. Verificação da Tabela 'tarefas'</h3>";
try {
    $stmt = $pdo->query("DESCRIBE tarefas");
    $colunas = $stmt->fetchAll();
    echo "✅ Tabela 'tarefas' existe<br>";
    echo "Colunas encontradas:<br>";
    foreach ($colunas as $coluna) {
        echo "- " . $coluna['Field'] . " (" . $coluna['Type'] . ")<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao verificar tabela: " . $e->getMessage() . "<br>";
}

// 4. Verificar dados na tabela
echo "<h3>4. Verificação de Dados</h3>";
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $total = $stmt->fetch()['total'];
        echo "✅ Total de tarefas do usuário: " . $total . "<br>";
        
        if ($total == 0) {
            echo "⚠️ Nenhuma tarefa encontrada. Isso pode causar problemas nas APIs.<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao contar tarefas: " . $e->getMessage() . "<br>";
    }
}

// 5. Testar cada API individualmente
echo "<h3>5. Teste das APIs</h3>";

// Teste 1: buscar_tarefas_hoje.php
echo "<h4>Teste: buscar_tarefas_hoje.php</h4>";
try {
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/buscar_tarefas_hoje.php";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        echo "✅ API funcionando<br>";
        echo "Resposta: " . json_encode($data, JSON_PRETTY_PRINT) . "<br>";
    } else {
        echo "❌ API com erro: " . $response . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao testar API: " . $e->getMessage() . "<br>";
}

// Teste 2: buscar_distribuicao_prioridade.php
echo "<h4>Teste: buscar_distribuicao_prioridade.php</h4>";
try {
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/buscar_distribuicao_prioridade.php";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        echo "✅ API funcionando<br>";
        echo "Resposta: " . json_encode($data, JSON_PRETTY_PRINT) . "<br>";
    } else {
        echo "❌ API com erro: " . $response . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao testar API: " . $e->getMessage() . "<br>";
}

// Teste 3: buscar_produtividade_7_dias.php
echo "<h4>Teste: buscar_produtividade_7_dias.php</h4>";
try {
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/buscar_produtividade_7_dias.php";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        echo "✅ API funcionando<br>";
        echo "Resposta: " . json_encode($data, JSON_PRETTY_PRINT) . "<br>";
    } else {
        echo "❌ API com erro: " . $response . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao testar API: " . $e->getMessage() . "<br>";
}

echo "<br><h3>🎯 PRÓXIMOS PASSOS</h3>";
echo "1. Se o usuário não estiver logado, faça login primeiro<br>";
echo "2. Se não houver tarefas, crie algumas tarefas de teste<br>";
echo "3. Se as APIs ainda não funcionarem, verifique os logs de erro do servidor<br>";
?>