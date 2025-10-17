<?php
require_once 'includes/db_connect.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $controleId = (int)($_POST['controle_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'pendente');
    
    if (!$controleId) {
        throw new Exception('ID de controle inválido');
    }
    
    // Validar status
    if (!in_array($status, ['pendente', 'concluido', 'concluida'])) {
        throw new Exception('Status inválido');
    }
    
    // Converter para padrão esperado
    if ($status === 'concluida') {
        $status = 'concluido';
    }
    
    // Atualizar status da rotina no controle diário
    $stmt = $pdo->prepare("
        UPDATE rotina_controle_diario 
        SET status = ? 
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$status, $controleId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => $status === 'concluido' ? 'Rotina marcada como concluída!' : 'Rotina marcada como pendente!'
        ]);
    } else {
        throw new Exception('Rotina não encontrada');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
