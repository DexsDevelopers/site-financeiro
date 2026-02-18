<?php
// API para obter estatísticas de tarefas em tempo real
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];

try {
    // Tarefas de hoje
    $stmt_hoje = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas FROM tarefas WHERE id_usuario = ? AND DATE(data_criacao) = CURDATE()");
    $stmt_hoje->execute([$userId]);
    $hoje = $stmt_hoje->fetch();
    
    // Tarefas da semana
    $stmt_semana = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas FROM tarefas WHERE id_usuario = ? AND YEARWEEK(data_criacao, 1) = YEARWEEK(CURDATE(), 1)");
    $stmt_semana->execute([$userId]);
    $semana = $stmt_semana->fetch();
    
    // Tarefas pendentes
    $stmt_pendentes = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
    $stmt_pendentes->execute([$userId]);
    $pendentes = $stmt_pendentes->fetchColumn();
    
    // Tempo pendente
    $stmt_tempo = $pdo->prepare("SELECT SUM(tempo_estimado) as estimado FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
    $stmt_tempo->execute([$userId]);
    $tempo_pendente = $stmt_tempo->fetchColumn() ?? 0;
    
    $response['success'] = true;
    $response['hoje'] = [
        'total' => (int)($hoje['total'] ?? 0),
        'concluidas' => (int)($hoje['concluidas'] ?? 0)
    ];
    $response['semana'] = [
        'total' => (int)($semana['total'] ?? 0),
        'concluidas' => (int)($semana['concluidas'] ?? 0)
    ];
    $response['pendentes'] = (int)($pendentes ?? 0);
    $response['tempo_pendente'] = (int)($tempo_pendente ?? 0);
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>
