<?php
// excluir_rotina_diaria.php - Excluir hábito da rotina diária

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
    echo json_encode(['success' => false, 'message' => 'ID do hábito não fornecido']);
    exit();
}

$id = (int)$input['id'];

try {
    // Verificar se o hábito pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM rotina_diaria WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Hábito não encontrado']);
        exit();
    }
    
    // Excluir o hábito
    $stmt = $pdo->prepare("DELETE FROM rotina_diaria WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Hábito excluído com sucesso!'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao excluir hábito: ' . $e->getMessage()
    ]);
}
?>
