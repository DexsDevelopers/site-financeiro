<?php
// cancelar_sessao_pomodoro.php - Cancelar sessão de pomodoro

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
    $stmt = $pdo->prepare("SELECT id FROM pomodoro_sessions WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$sessionId, $userId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        $response['message'] = 'Sessão não encontrada.';
        echo json_encode($response);
        exit();
    }
    
    // Cancelar sessão
    $stmt = $pdo->prepare("UPDATE pomodoro_sessions SET status = 'cancelado' WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$sessionId, $userId]);
    
    $response['success'] = true;
    $response['message'] = 'Sessão cancelada com sucesso.';
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>
