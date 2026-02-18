<?php
// salvar_config_layout.php

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

// Configurações de layout
$config = [
    'tipo_layout' => $input['tipo_layout'] ?? 'padrao',
    'sidebar_posicao' => $input['sidebar_posicao'] ?? 'esquerda',
    'sidebar_tamanho' => $input['sidebar_tamanho'] ?? 'normal',
    'header_fixo' => isset($input['header_fixo']) ? (bool)$input['header_fixo'] : true,
    'footer_fixo' => isset($input['footer_fixo']) ? (bool)$input['footer_fixo'] : false,
    'densidade' => $input['densidade'] ?? 'normal',
    'animacoes' => isset($input['animacoes']) ? (bool)$input['animacoes'] : true,
    'tema_escuro' => isset($input['tema_escuro']) ? (bool)$input['tema_escuro'] : true
];

// Criar tabela se não existir
try {
    $sql_create_table = "CREATE TABLE IF NOT EXISTS config_layouts (
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
    $stmt = $pdo->prepare("INSERT INTO config_layouts (id_usuario, configuracao) 
                          VALUES (?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          configuracao = VALUES(configuracao)");
    
    $stmt->execute([$userId, json_encode($config)]);
    
    // Salvar no cache
    $cache->setUserCache($userId, 'layout_config', $config, 3600);
    
    // Salvar na sessão para aplicação imediata
    $_SESSION['layout_config'] = $config;
    
    echo json_encode(['success' => true, 'message' => 'Configurações de layout salvas!', 'config' => $config]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar configurações: ' . $e->getMessage()]);
}
?>
