<?php
session_start();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

require_once 'includes/db_connect.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $status = trim($input['status'] ?? '');

    if (!$id || !$status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID ou status inválido']);
        exit;
    }

    // Validar status
    if (!in_array($status, ['pendente', 'concluida'])) {
        $status = 'pendente';
    }

    $stmt = $pdo->prepare("UPDATE subtarefas SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Subtarefa atualizada']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Subtarefa não encontrada']);
    }
} catch (PDOException $e) {
    error_log('Erro em atualizar_subtarefa_status.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar']);
}
?>
