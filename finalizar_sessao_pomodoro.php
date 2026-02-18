<?php
// finalizar_sessao_pomodoro.php - Finalizar sessão de pomodoro

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro inesperado.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($sessionId)) {
    http_response_code(400);
    $response['message'] = 'ID da sessão é obrigatório.';
    echo json_encode($response);
    exit();
}

try {
    // Verificar se a sessão pertence ao usuário
    $stmt = $pdo->prepare("SELECT * FROM pomodoro_sessions WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$sessionId, $userId]);
    $sessao = $stmt->fetch();
    
    if (!$sessao) {
        http_response_code(404);
        $response['message'] = 'Sessão não encontrada.';
        echo json_encode($response);
        exit();
    }
    
    // Calcular duração real
    $inicio = new DateTime($sessao['inicio']);
    $fim = new DateTime();
    $duracaoReal = $fim->diff($inicio)->i; // minutos
    
    // Finalizar sessão
    $stmt = $pdo->prepare("
        UPDATE pomodoro_sessions 
        SET fim = NOW(), status = 'concluido', duracao_minutos = ? 
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$duracaoReal, $sessionId, $userId]);
    
    // Atualizar tempo gasto na tarefa (se associada)
    if ($sessao['id_tarefa']) {
        $stmt = $pdo->prepare("
            UPDATE tarefas 
            SET tempo_gasto = tempo_gasto + ? 
            WHERE id = ? AND id_usuario = ?
        ");
        $stmt->execute([$duracaoReal, $sessao['id_tarefa'], $userId]);
    }
    
    $response['success'] = true;
    $response['message'] = 'Sessão finalizada com sucesso.';
    $response['duracao_real'] = $duracaoReal;
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>
