<?php
// buscar_tarefas_hoje.php - Buscar tarefas de hoje para o modal de estatísticas

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
$dataHoje = date('Y-m-d');

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if (!$stmt_check->fetch()) {
        $response['message'] = 'Tabela tarefas não encontrada.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Buscar tarefas de hoje (pendentes e concluídas)
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
    $stmt->execute([$userId, $dataHoje, $dataHoje]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sanitizar dados para evitar problemas de encoding
    foreach ($tarefas as &$tarefa) {
        $tarefa['descricao'] = mb_convert_encoding($tarefa['descricao'], 'UTF-8', 'UTF-8');
        $tarefa['prioridade'] = mb_convert_encoding($tarefa['prioridade'], 'UTF-8', 'UTF-8');
        $tarefa['status'] = mb_convert_encoding($tarefa['status'], 'UTF-8', 'UTF-8');
    }
    
    $response['success'] = true;
    $response['tarefas'] = $tarefas;
    $response['total'] = count($tarefas);
    
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
