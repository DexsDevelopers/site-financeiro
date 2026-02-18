<?php
// resetar_preferencias_avancadas.php

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

// Preferências padrão
$preferencias_padrao = [
    'notificacoes' => [
        'email' => true,
        'push' => true,
        'sms' => false,
        'som' => true,
        'vibrar' => false
    ],
    'interface' => [
        'idioma' => 'pt-BR',
        'fuso_horario' => 'America/Sao_Paulo',
        'formato_data' => 'dd/mm/yyyy',
        'formato_moeda' => 'BRL',
        'tamanho_fonte' => 'normal',
        'alto_contraste' => false,
        'reduzir_animacoes' => false
    ],
    'privacidade' => [
        'dados_analytics' => true,
        'cookies' => true,
        'localizacao' => false,
        'compartilhar_dados' => false,
        'backup_automatico' => true
    ],
    'performance' => [
        'cache_agressivo' => false,
        'lazy_loading' => true,
        'compressao_imagens' => true,
        'preload_recursos' => true,
        'otimizacao_mobile' => true
    ],
    'acessibilidade' => [
        'leitor_tela' => false,
        'navegacao_teclado' => true,
        'alto_contraste' => false,
        'fonte_grande' => false,
        'reduzir_movimento' => false
    ],
    'backup' => [
        'frequencia' => 'diario',
        'manter_historico' => 30,
        'comprimir_backup' => true,
        'notificar_backup' => true
    ]
];

try {
    // Resetar no banco
    $stmt = $pdo->prepare("DELETE FROM preferencias_usuarios WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    
    // Resetar no cache
    $cache->invalidateUserCache($userId, 'preferencias_avancadas');
    
    // Resetar na sessão
    $_SESSION['preferencias_avancadas'] = $preferencias_padrao;
    
    echo json_encode(['success' => true, 'message' => 'Preferências resetadas!', 'preferencias' => $preferencias_padrao]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao resetar preferências: ' . $e->getMessage()]);
}
?>
