<?php
// salvar_mapa_mental.php - Salvar mapa mental
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$titulo = trim($input['titulo'] ?? '');
$dados_mapa = $input['dados'] ?? '{}'; // JSON string com nodes e edges

if (empty($titulo)) {
    echo json_encode(['success' => false, 'message' => 'Título é obrigatório']);
    exit;
}

try {
    // Verificar se a tabela mapas_mentais existe, se não, criar
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'mapas_mentais'");
    if ($stmt_check->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS mapas_mentais (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                titulo VARCHAR(255) NOT NULL,
                dados TEXT NOT NULL,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
                INDEX idx_usuario (id_usuario)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    // Inserir novo mapa mental
    $sql = "INSERT INTO mapas_mentais (id_usuario, titulo, dados) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$userId, $titulo, $dados_mapa]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Mapa mental salvo com sucesso!', 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar mapa mental']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>

