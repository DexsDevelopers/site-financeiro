<?php
// salvar_tema_personalizado.php

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$nome_tema = trim($_POST['nome_tema'] ?? '');
$base_tema = $_POST['base_tema'] ?? 'padrao';
$cores_json = $_POST['cores'] ?? '{}';

// Validações
if (empty($nome_tema)) {
    echo json_encode(['success' => false, 'message' => 'Nome do tema é obrigatório']);
    exit;
}

$cores = json_decode($cores_json, true);
if (!$cores) {
    echo json_encode(['success' => false, 'message' => 'Cores inválidas']);
    exit;
}

// Criar tabela se não existir
try {
    $sql_create_table = "CREATE TABLE IF NOT EXISTS temas_personalizados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        base_tema VARCHAR(50) NOT NULL,
        cores JSON NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (id_usuario),
        UNIQUE KEY unique_user_theme (id_usuario, nome)
    )";
    $pdo->exec($sql_create_table);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar tabela: ' . $e->getMessage()]);
    exit;
}

// Verificar se já existe tema com o mesmo nome
try {
    $stmt_check = $pdo->prepare("SELECT id FROM temas_personalizados WHERE id_usuario = ? AND nome = ?");
    $stmt_check->execute([$userId, $nome_tema]);
    if ($stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe um tema com este nome']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar tema existente']);
    exit;
}

// Salvar tema
try {
    $stmt = $pdo->prepare("INSERT INTO temas_personalizados (id_usuario, nome, base_tema, cores) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $nome_tema, $base_tema, json_encode($cores)]);
    
    echo json_encode(['success' => true, 'message' => 'Tema personalizado salvo com sucesso!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar tema: ' . $e->getMessage()]);
}
?>
