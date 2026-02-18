<?php
// excluir_compra_futura.php - Excluir compra futura
require_once 'templates/header.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verificar se a compra pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM compras_futuras WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Compra não encontrada']);
        exit;
    }
    
    // Excluir a compra
    $stmt = $pdo->prepare("DELETE FROM compras_futuras WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Compra excluída com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir compra']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao excluir compra futura: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
