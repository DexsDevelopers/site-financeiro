<?php
// excluir_mapa_mental.php - Excluir mapa mental
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$mapa_id = $input['id'] ?? 0;

if (empty($mapa_id)) {
    echo json_encode(['success' => false, 'message' => 'ID do mapa não fornecido']);
    exit;
}

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'mapas_mentais'");
    if ($stmt_check->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Tabela de mapas mentais não existe']);
        exit;
    }
    
    // Verificar se o mapa pertence ao usuário
    $stmt_check = $pdo->prepare("SELECT id FROM mapas_mentais WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$mapa_id, $userId]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Mapa mental não encontrado']);
        exit;
    }
    
    // Excluir mapa
    $stmt = $pdo->prepare("DELETE FROM mapas_mentais WHERE id = ? AND id_usuario = ?");
    $result = $stmt->execute([$mapa_id, $userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Mapa mental excluído com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir mapa mental']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>

