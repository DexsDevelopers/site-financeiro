<?php
header('Content-Type: application/json');
require_once 'includes/db_connect.php';

session_start();

// Pegar o ID do usuário da sessão (seja qual for o padrão do projeto)
$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Inscrever dispositivo
    $endpoint = $input['endpoint'] ?? '';
    $p256dh = $input['keys']['p256dh'] ?? '';
    $auth = $input['keys']['auth'] ?? '';

    if (!$endpoint || !$p256dh || !$auth) {
        echo json_encode(['success' => false, 'message' => 'Dados de inscrição incompletos']);
        exit;
    }

    try {
        // Usar REPLACE INTO ou INSERT ON DUPLICATE KEY UPDATE para evitar duplicatas
        $stmt = $pdo->prepare("REPLACE INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $endpoint, $p256dh, $auth]);
        
        echo json_encode(['success' => true, 'message' => 'Inscrição salva com sucesso']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }

} elseif ($method === 'DELETE') {
    // Desinscrever dispositivo
    $endpoint = $input['endpoint'] ?? '';

    if (!$endpoint) {
        echo json_encode(['success' => false, 'message' => 'Endpoint não fornecido']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?");
        $stmt->execute([$endpoint, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Inscrição removida com sucesso']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao remover inscrição']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não suportado']);
}
