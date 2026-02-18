<?php
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$rotinaId = $_GET['id'] ?? null;

if (!$rotinaId) {
    echo json_encode(['success' => false, 'message' => 'ID da rotina não fornecido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, horario_sugerido, descricao 
        FROM rotinas_fixas 
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$rotinaId, $userId]);
    $rotina = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rotina) {
        echo json_encode(['success' => false, 'message' => 'Rotina não encontrada']);
        exit;
    }
    
    echo json_encode(['success' => true, 'rotina' => $rotina]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>
