<?php
// editar_rotina_diaria.php - Editar hábito da rotina diária

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

if (!$input || !isset($input['id']) || !isset($input['nome']) || !isset($input['horario'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

$id = (int)$input['id'];
$nome = trim($input['nome']);
$horario = trim($input['horario']);

// Validar dados
if (empty($nome)) {
    echo json_encode(['success' => false, 'message' => 'Nome do hábito é obrigatório']);
    exit();
}

if (!empty($horario) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $horario)) {
    echo json_encode(['success' => false, 'message' => 'Formato de horário inválido (use HH:MM)']);
    exit();
}

try {
    // Verificar se o hábito pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM rotina_diaria WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Hábito não encontrado']);
        exit();
    }
    
    // Atualizar o hábito
    $stmt = $pdo->prepare("
        UPDATE rotina_diaria 
        SET nome = ?, horario = ? 
        WHERE id = ? AND id_usuario = ?
    ");
    
    $stmt->execute([$nome, $horario ?: null, $id, $userId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Hábito atualizado com sucesso!'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao atualizar hábito: ' . $e->getMessage()
    ]);
}
?>
