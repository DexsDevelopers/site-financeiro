<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Recebe os dados JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Verifica se a ação é salvar a inscrição
if (isset($data['action']) && $data['action'] === 'subscribe') {
    $subscription = $data['subscription'];

    if (!isset($subscription['endpoint'])) {
        echo json_encode(['success' => false, 'message' => 'Endpoint inválido.']);
        exit;
    }

    $endpoint = $subscription['endpoint'];
    $p256dh = $subscription['keys']['p256dh'] ?? '';
    $auth = $subscription['keys']['auth'] ?? '';

    try {
        // Verifica se a inscrição já existe
        $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
        
        if ($stmt->rowCount() > 0) {
            // Atualiza caso pertença a outro usuário (ex: login diferente no mesmo navegador) ou chaves mudaram
            $stmt = $pdo->prepare("UPDATE push_subscriptions SET user_id = ?, p256dh = ?, auth = ?, created_at = NOW() WHERE endpoint = ?");
            $stmt->execute([$user_id, $p256dh, $auth, $endpoint]);
        } else {
            // Insere nova inscrição
            $stmt = $pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $endpoint, $p256dh, $auth]);
        }

        echo json_encode(['success' => true, 'message' => 'Inscrição salva com sucesso.']);
    } catch (PDOException $e) {
        error_log("Erro ao salvar push subscription: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro no servidor ao salvar a inscrição.']);
    }
} 
// Ação para remover inscrição
elseif (isset($data['action']) && $data['action'] === 'unsubscribe') {
    $endpoint = $data['endpoint'];

    if (!$endpoint) {
        echo json_encode(['success' => false, 'message' => 'Endpoint inválido.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?");
        $stmt->execute([$endpoint, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Inscrição removida com sucesso.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no servidor ao remover a inscrição.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
}
