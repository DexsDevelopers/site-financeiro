<?php
// criar_sessao_pomodoro.php - Criar nova sessão de pomodoro

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
$tarefaId = $input['id_tarefa'] ?? null;
$duracao = $input['duracao_minutos'] ?? 25;
$userId = $_SESSION['user_id'];

// Verificar se há sessão ativa
$stmt = $pdo->prepare("SELECT id FROM pomodoro_sessions WHERE id_usuario = ? AND status = 'ativo'");
$stmt->execute([$userId]);
if ($stmt->fetch()) {
    http_response_code(409);
    $response['message'] = 'Já existe uma sessão ativa.';
    echo json_encode($response);
    exit();
}

// Verificar se a tarefa pertence ao usuário (se fornecida)
if ($tarefaId) {
    $stmt = $pdo->prepare("SELECT id FROM tarefas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$tarefaId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        $response['message'] = 'Tarefa não encontrada.';
        echo json_encode($response);
        exit();
    }
}

try {
    // Criar nova sessão
    $stmt = $pdo->prepare("
        INSERT INTO pomodoro_sessions (id_usuario, id_tarefa, duracao_minutos, inicio, status) 
        VALUES (?, ?, ?, NOW(), 'ativo')
    ");
    $stmt->execute([$userId, $tarefaId, $duracao]);
    
    $sessionId = $pdo->lastInsertId();
    
    $response['success'] = true;
    $response['message'] = 'Sessão iniciada com sucesso.';
    $response['session_id'] = $sessionId;
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>
