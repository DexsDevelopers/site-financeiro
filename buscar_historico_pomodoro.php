<?php
// buscar_historico_pomodoro.php - Buscar histórico de sessões de pomodoro

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

$userId = $_SESSION['user_id'];
$limite = $_GET['limite'] ?? 10;

try {
    $stmt = $pdo->prepare("
        SELECT 
            ps.*,
            t.descricao as tarefa_descricao,
            DATE_FORMAT(ps.inicio, '%d/%m/%Y %H:%i') as data_formatada
        FROM pomodoro_sessions ps
        LEFT JOIN tarefas t ON ps.id_tarefa = t.id
        WHERE ps.id_usuario = ? AND ps.status = 'concluido'
        ORDER BY ps.inicio DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $limite]);
    $historico = $stmt->fetchAll();
    
    $response['success'] = true;
    $response['history'] = $historico;
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>
