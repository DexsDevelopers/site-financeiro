<?php
// resetar_config_menu.php

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

// Configuração padrão
$config_padrao = [
    'secoes_visiveis' => [
        'academy' => true,
        'financeiro' => true,
        'produtividade' => true,
        'personalizacao' => true,
        'sistema' => true
    ],
    'paginas_visiveis' => [
        'academy' => ['cursos.php', 'treinos.php', 'rotina_academia.php', 'alimentacao.php', 'notas_cursos.php'],
        'financeiro' => ['compras_futuras.php', 'relatorios.php', 'extrato_completo.php', 'recorrentes.php', 'orcamento.php', 'categorias.php', 'regras_categorizacao.php', 'alertas_inteligentes.php'],
        'produtividade' => ['tarefas.php', 'calendario.php', 'temporizador.php'],
        'personalizacao' => ['temas_customizaveis.php', 'layouts_flexiveis.php', 'preferencias_avancadas.php', 'personalizar_menu.php'],
        'sistema' => ['perfil.php']
    ],
    'ordem_secoes' => ['academy', 'financeiro', 'produtividade', 'personalizacao', 'sistema'],
    'ordem_paginas' => [
        'academy' => ['cursos.php', 'treinos.php', 'rotina_academia.php', 'alimentacao.php', 'notas_cursos.php'],
        'financeiro' => ['compras_futuras.php', 'relatorios.php', 'extrato_completo.php', 'recorrentes.php', 'orcamento.php', 'categorias.php', 'regras_categorizacao.php', 'alertas_inteligentes.php'],
        'produtividade' => ['tarefas.php', 'calendario.php', 'temporizador.php'],
        'personalizacao' => ['temas_customizaveis.php', 'layouts_flexiveis.php', 'preferencias_avancadas.php', 'personalizar_menu.php'],
        'sistema' => ['perfil.php']
    ]
];

try {
    // Resetar no banco
    $stmt = $pdo->prepare("DELETE FROM config_menu_personalizado WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    
    // Resetar no cache
    $cache->invalidateUserCache($userId, 'menu_personalizado');
    
    // Resetar na sessão
    $_SESSION['menu_personalizado'] = $config_padrao;
    
    echo json_encode(['success' => true, 'message' => 'Menu resetado para o padrão!', 'config' => $config_padrao]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao resetar menu: ' . $e->getMessage()]);
}
?>
