<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

require_once 'includes/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$nome = trim($data['nome'] ?? '');
$cor = trim($data['cor'] ?? '#6bcf7f');

if (!$nome) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
    exit;
}

try {
    // Criar tabela se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tarefas_categorias (
            id INT PRIMARY KEY AUTO_INCREMENT,
            id_usuario INT NOT NULL,
            nome VARCHAR(50),
            cor VARCHAR(7),
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
        )
    ");
    
    $stmt = $pdo->prepare("
        INSERT INTO tarefas_categorias (id_usuario, nome, cor) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $nome, $cor]);
    
    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
