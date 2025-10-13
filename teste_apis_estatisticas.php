<?php
// teste_apis_estatisticas.php - Teste específico das APIs de estatísticas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🧪 TESTE ESPECÍFICO DAS APIs DE ESTATÍSTICAS</h2>";

// Simular uma sessão de usuário para teste
if (!isset($_SESSION['user_id'])) {
    // Para teste, vamos usar um ID fixo
    $_SESSION['user_id'] = 1;
    echo "⚠️ Usando ID de usuário fixo para teste: 1<br><br>";
}

echo "✅ Usuário ID: " . $_SESSION['user_id'] . "<br>";

// Teste direto das consultas SQL
echo "<h3>1. Teste Direto das Consultas SQL</h3>";

// Teste 1: Consulta de tarefas de hoje
echo "<h4>Consulta: Tarefas de Hoje</h4>";
try {
    $dataHoje = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            id,
            descricao,
            prioridade,
            status,
            data_limite,
            tempo_estimado,
            tempo_gasto,
            data_criacao
        FROM tarefas 
        WHERE id_usuario = ? 
        AND (
            DATE(data_limite) = ? 
            OR DATE(data_criacao) = ?
        )
        ORDER BY 
            CASE prioridade 
                WHEN 'Alta' THEN 1 
                WHEN 'Média' THEN 2 
                WHEN 'Baixa' THEN 3 
            END,
            data_criacao DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $dataHoje, $dataHoje]);
    $tarefas = $stmt->fetchAll();
    
    echo "✅ Consulta executada com sucesso<br>";
    echo "Tarefas encontradas: " . count($tarefas) . "<br>";
    
    if (count($tarefas) > 0) {
        echo "Primeira tarefa: " . $tarefas[0]['descricao'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro na consulta: " . $e->getMessage() . "<br>";
}

// Teste 2: Consulta de distribuição por prioridade
echo "<h4>Consulta: Distribuição por Prioridade</h4>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            prioridade,
            COUNT(*) as total
        FROM tarefas 
        WHERE id_usuario = ? 
        AND status = 'pendente'
        GROUP BY prioridade
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $resultados = $stmt->fetchAll();
    
    echo "✅ Consulta executada com sucesso<br>";
    echo "Resultados: " . json_encode($resultados) . "<br>";
} catch (Exception $e) {
    echo "❌ Erro na consulta: " . $e->getMessage() . "<br>";
}

// Teste 3: Consulta de produtividade dos últimos 7 dias
echo "<h4>Consulta: Produtividade dos Últimos 7 Dias</h4>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(data_conclusao) as data,
            COUNT(*) as total_concluidas
        FROM tarefas 
        WHERE id_usuario = ? 
        AND status = 'concluida'
        AND data_conclusao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(data_conclusao)
        ORDER BY data ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $resultados = $stmt->fetchAll();
    
    echo "✅ Consulta executada com sucesso<br>";
    echo "Resultados: " . json_encode($resultados) . "<br>";
} catch (Exception $e) {
    echo "❌ Erro na consulta: " . $e->getMessage() . "<br>";
}

// Teste das APIs via HTTP
echo "<h3>2. Teste das APIs via HTTP</h3>";

// Função para testar API
function testarAPI($nome, $url) {
    echo "<h4>Teste: $nome</h4>";
    try {
        $response = file_get_contents($url);
        echo "Resposta bruta: " . htmlspecialchars($response) . "<br>";
        
        $data = json_decode($response, true);
        if ($data === null) {
            echo "❌ Resposta não é JSON válido<br>";
            echo "Erro JSON: " . json_last_error_msg() . "<br>";
        } else {
            echo "✅ JSON válido<br>";
            echo "Dados: " . json_encode($data, JSON_PRETTY_PRINT) . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao acessar API: " . $e->getMessage() . "<br>";
    }
    echo "<br>";
}

// Testar cada API
$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
testarAPI("buscar_tarefas_hoje.php", $baseUrl . "/buscar_tarefas_hoje.php");
testarAPI("buscar_distribuicao_prioridade.php", $baseUrl . "/buscar_distribuicao_prioridade.php");
testarAPI("buscar_produtividade_7_dias.php", $baseUrl . "/buscar_produtividade_7_dias.php");

echo "<h3>🎯 CONCLUSÃO</h3>";
echo "Se as consultas SQL funcionam mas as APIs não, o problema pode ser:<br>";
echo "1. Erro de sintaxe PHP nas APIs<br>";
echo "2. Problema de encoding/headers<br>";
echo "3. Erro de sessão nas APIs<br>";
echo "4. Problema de caminho dos arquivos<br>";
?>