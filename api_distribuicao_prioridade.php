<?php
// api_distribuicao_prioridade.php - Versão alternativa da API de distribuição por prioridade

// Limpar qualquer output anterior
ob_clean();

session_start();

// Headers corretos para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

$response = ['success' => false, 'message' => 'Erro inesperado.'];

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        echo '{"success":false,"message":"Erro ao gerar JSON"}';
    } else {
        echo $json;
    }
    exit();
}

try {
    require_once 'includes/db_connect.php';
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Erro de conexão: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if (!$stmt_check->fetch()) {
        $response['message'] = 'Tabela tarefas não encontrada.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Buscar distribuição por prioridade
    $stmt = $pdo->prepare("
        SELECT 
            prioridade,
            COUNT(*) as total
        FROM tarefas 
        WHERE id_usuario = ? 
        AND status = 'pendente'
        GROUP BY prioridade
    ");
    $stmt->execute([$userId]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar dados
    $distribuicao = [
        'alta' => 0,
        'media' => 0,
        'baixa' => 0
    ];
    
    foreach ($resultados as $resultado) {
        $prioridade = strtolower(trim($resultado['prioridade']));
        $total = (int)$resultado['total'];
        
        if ($prioridade === 'alta') {
            $distribuicao['alta'] = $total;
        } elseif ($prioridade === 'média' || $prioridade === 'media') {
            $distribuicao['media'] = $total;
        } elseif ($prioridade === 'baixa') {
            $distribuicao['baixa'] = $total;
        }
    }
    
    $response['success'] = true;
    $response['alta'] = $distribuicao['alta'];
    $response['media'] = $distribuicao['media'];
    $response['baixa'] = $distribuicao['baixa'];
    $response['total'] = array_sum($distribuicao);
    
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        $response['success'] = false;
        $response['message'] = 'Erro ao gerar JSON: ' . json_last_error_msg();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        echo $json;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Erro geral: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>
