<?php
// api_tarefas_hoje_limpa.php - API limpa para tarefas de hoje

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_clean();
}

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
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
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

    // Buscar tarefas de hoje
    $stmt = $pdo->prepare("
        SELECT 
            id,
            descricao,
            prioridade,
            status,
            data_limite,
            tempo_estimado,
            data_criacao,
            data_conclusao
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
    
    // Sanitizar dados
    foreach ($tarefas as &$tarefa) {
        $tarefa['descricao'] = mb_convert_encoding($tarefa['descricao'], 'UTF-8', 'UTF-8');
        $tarefa['prioridade'] = mb_convert_encoding($tarefa['prioridade'], 'UTF-8', 'UTF-8');
        $tarefa['status'] = mb_convert_encoding($tarefa['status'], 'UTF-8', 'UTF-8');
    }
    
    $response['success'] = true;
    $response['tarefas'] = $tarefas;
    $response['total'] = count($tarefas);
    $response['message'] = 'Tarefas de hoje carregadas com sucesso';
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
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
