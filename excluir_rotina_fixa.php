<?php
// excluir_rotina_fixa.php - Excluir rotina fixa

session_start();
require_once 'includes/db_connect.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

$userId = $_SESSION['user_id'];

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da rotina fixa não fornecido']);
    exit();
}

$id = (int)$input['id'];

try {
    // Verificar se a rotina fixa pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Rotina fixa não encontrada']);
        exit();
    }
    
    // Excluir registros de controle diário primeiro (devido à foreign key)
    $stmt = $pdo->prepare("DELETE FROM rotina_controle_diario WHERE id_rotina_fixa = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    // Excluir a rotina fixa
    $stmt = $pdo->prepare("DELETE FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Rotina fixa excluída com sucesso!'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao excluir rotina fixa: ' . $e->getMessage()
    ]);
}
?>
