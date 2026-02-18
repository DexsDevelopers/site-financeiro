<?php
// aplicar_config_layout.php

session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

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

// Salvar na sessão
$_SESSION['layout_config'] = $config;

// Aplicar classes CSS
$body_classes = [];
$body_classes[] = 'layout-' . $config['tipo_layout'];
$body_classes[] = 'densidade-' . $config['densidade'];

if ($config['tema_escuro']) {
    $body_classes[] = 'theme-dark';
}

if ($config['animacoes']) {
    $body_classes[] = 'animations-enabled';
} else {
    $body_classes[] = 'animations-disabled';
}

$_SESSION['body_classes'] = implode(' ', $body_classes);

echo json_encode(['success' => true, 'message' => 'Configurações aplicadas!', 'config' => $config]);
?>
