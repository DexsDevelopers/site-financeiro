<?php
// salvar_config_menu.php

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/cache_manager.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Validar estrutura dos dados
$required_keys = ['secoes_visiveis', 'paginas_visiveis', 'ordem_secoes', 'ordem_paginas'];
foreach ($required_keys as $key) {
    if (!isset($input[$key])) {
        echo json_encode(['success' => false, 'message' => "Campo obrigatório ausente: {$key}"]);
        exit;
    }
}

// Criar tabela se não existir
try {
    $sql_create_table = "CREATE TABLE IF NOT EXISTS config_menu_personalizado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL UNIQUE,
        configuracao JSON NOT NULL,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_create_table);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar tabela: ' . $e->getMessage()]);
    exit;
}

// Salvar configuração
try {
    $stmt = $pdo->prepare("INSERT INTO config_menu_personalizado (id_usuario, configuracao) 
                          VALUES (?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          configuracao = VALUES(configuracao)");
    
    $stmt->execute([$userId, json_encode($input)]);
    
    // Salvar no cache
    $cache->setUserCache($userId, 'menu_personalizado', $input, 3600);
    
    // Salvar na sessão para aplicação imediata
    $_SESSION['menu_personalizado'] = $input;
    
    echo json_encode(['success' => true, 'message' => 'Configurações do menu salvas com sucesso!']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar configurações: ' . $e->getMessage()]);
}
?>
