<?php
// resetar_config_layout.php

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

if (!isset($input['reset']) || !$input['reset']) {
    echo json_encode(['success' => false, 'message' => 'Parâmetro inválido']);
    exit;
}

// Configurações padrão
$config_padrao = [
    'tipo_layout' => 'padrao',
    'sidebar_posicao' => 'esquerda',
    'sidebar_tamanho' => 'normal',
    'header_fixo' => true,
    'footer_fixo' => false,
    'densidade' => 'normal',
    'animacoes' => true,
    'tema_escuro' => true
];

try {
    // Resetar no banco
    $stmt = $pdo->prepare("DELETE FROM config_layouts WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    
    // Resetar no cache
    $cache->invalidateUserCache($userId, 'layout_config');
    
    // Resetar na sessão
    $_SESSION['layout_config'] = $config_padrao;
    
    echo json_encode(['success' => true, 'message' => 'Configurações resetadas!', 'config' => $config_padrao]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao resetar configurações: ' . $e->getMessage()]);
}
?>
