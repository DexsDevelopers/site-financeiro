<?php
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;

try {
    // Verificar se a rotina pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Rotina não encontrada');
    }
    
    // Excluir controles diários primeiro
    $stmt = $pdo->prepare("DELETE FROM rotina_controle_diario WHERE id_rotina_fixa = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    // Excluir rotina fixa
    $stmt = $pdo->prepare("DELETE FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Rotina fixa excluída com sucesso!']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>