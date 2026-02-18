<?php
// excluir_nota_curso.php - Excluir anotação
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$notaId = (int)($input['id'] ?? 0);

if ($notaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da anotação inválido']);
    exit;
}

try {
    // Verificar se a nota pertence ao usuário
    $stmt_check = $pdo->prepare("SELECT id FROM notas_cursos WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$notaId, $userId]);
    
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Anotação não encontrada']);
        exit;
    }
    
    // Excluir a nota
    $stmt_delete = $pdo->prepare("DELETE FROM notas_cursos WHERE id = ? AND id_usuario = ?");
    $result = $stmt_delete->execute([$notaId, $userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Anotação excluída com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir anotação']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>