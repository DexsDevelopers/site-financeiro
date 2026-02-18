<?php
// buscar_nota.php - Buscar nota por ID para edição
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$nota_id = $_GET['id'] ?? 0;

if (empty($nota_id)) {
    echo json_encode(['success' => false, 'message' => 'ID da nota não fornecido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT n.*, c.nome_curso FROM notas_cursos n 
                          LEFT JOIN cursos c ON n.id_curso = c.id 
                          WHERE n.id = ? AND n.id_usuario = ?");
    $stmt->execute([$nota_id, $userId]);
    $nota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($nota) {
        echo json_encode(['success' => true, 'nota' => $nota]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nota não encontrada']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>

