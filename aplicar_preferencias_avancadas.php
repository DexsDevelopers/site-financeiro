<?php
// aplicar_preferencias_avancadas.php

session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Processar dados do formulário
$preferencias = [
    'notificacoes' => [
        'email' => isset($_POST['notificacoes[email]']) && $_POST['notificacoes[email]'] === '1',
        'push' => isset($_POST['notificacoes[push]']) && $_POST['notificacoes[push]'] === '1',
        'sms' => isset($_POST['notificacoes[sms]']) && $_POST['notificacoes[sms]'] === '1',
        'som' => isset($_POST['notificacoes[som]']) && $_POST['notificacoes[som]'] === '1',
        'vibrar' => isset($_POST['notificacoes[vibrar]']) && $_POST['notificacoes[vibrar]'] === '1'
    ],
    'interface' => [
        'idioma' => $_POST['interface[idioma]'] ?? 'pt-BR',
        'fuso_horario' => $_POST['interface[fuso_horario]'] ?? 'America/Sao_Paulo',
        'formato_data' => $_POST['interface[formato_data]'] ?? 'dd/mm/yyyy',
        'formato_moeda' => $_POST['interface[formato_moeda]'] ?? 'BRL',
        'tamanho_fonte' => $_POST['interface[tamanho_fonte]'] ?? 'normal',
        'alto_contraste' => isset($_POST['interface[alto_contraste]']) && $_POST['interface[alto_contraste]'] === '1',
        'reduzir_animacoes' => isset($_POST['interface[reduzir_animacoes]']) && $_POST['interface[reduzir_animacoes]'] === '1'
    ],
    'privacidade' => [
        'dados_analytics' => isset($_POST['privacidade[dados_analytics]']) && $_POST['privacidade[dados_analytics]'] === '1',
        'cookies' => isset($_POST['privacidade[cookies]']) && $_POST['privacidade[cookies]'] === '1',
        'localizacao' => isset($_POST['privacidade[localizacao]']) && $_POST['privacidade[localizacao]'] === '1',
        'compartilhar_dados' => isset($_POST['privacidade[compartilhar_dados]']) && $_POST['privacidade[compartilhar_dados]'] === '1',
        'backup_automatico' => isset($_POST['privacidade[backup_automatico]']) && $_POST['privacidade[backup_automatico]'] === '1'
    ],
    'performance' => [
        'cache_agressivo' => isset($_POST['performance[cache_agressivo]']) && $_POST['performance[cache_agressivo]'] === '1',
        'lazy_loading' => isset($_POST['performance[lazy_loading]']) && $_POST['performance[lazy_loading]'] === '1',
        'compressao_imagens' => isset($_POST['performance[compressao_imagens]']) && $_POST['performance[compressao_imagens]'] === '1',
        'preload_recursos' => isset($_POST['performance[preload_recursos]']) && $_POST['performance[preload_recursos]'] === '1',
        'otimizacao_mobile' => isset($_POST['performance[otimizacao_mobile]']) && $_POST['performance[otimizacao_mobile]'] === '1'
    ],
    'acessibilidade' => [
        'leitor_tela' => isset($_POST['acessibilidade[leitor_tela]']) && $_POST['acessibilidade[leitor_tela]'] === '1',
        'navegacao_teclado' => isset($_POST['acessibilidade[navegacao_teclado]']) && $_POST['acessibilidade[navegacao_teclado]'] === '1',
        'alto_contraste' => isset($_POST['acessibilidade[alto_contraste]']) && $_POST['acessibilidade[alto_contraste]'] === '1',
        'fonte_grande' => isset($_POST['acessibilidade[fonte_grande]']) && $_POST['acessibilidade[fonte_grande]'] === '1',
        'reduzir_movimento' => isset($_POST['acessibilidade[reduzir_movimento]']) && $_POST['acessibilidade[reduzir_movimento]'] === '1'
    ],
    'backup' => [
        'frequencia' => $_POST['backup[frequencia]'] ?? 'diario',
        'manter_historico' => (int) ($_POST['backup[manter_historico]'] ?? 30),
        'comprimir_backup' => isset($_POST['backup[comprimir_backup]']) && $_POST['backup[comprimir_backup]'] === '1',
        'notificar_backup' => isset($_POST['backup[notificar_backup]']) && $_POST['backup[notificar_backup]'] === '1'
    ]
];

// Salvar na sessão
$_SESSION['preferencias_avancadas'] = $preferencias;

// Aplicar configurações de interface
if (isset($preferencias['interface'])) {
    $interface = $preferencias['interface'];
    
    // Alto contraste
    if (isset($interface['alto_contraste']) && $interface['alto_contraste']) {
        $_SESSION['body_classes'] = ($_SESSION['body_classes'] ?? '') . ' high-contrast';
    }
    
    // Tamanho da fonte
    if (isset($interface['tamanho_fonte'])) {
        $_SESSION['body_classes'] = ($_SESSION['body_classes'] ?? '') . ' font-' . $interface['tamanho_fonte'];
    }
    
    // Reduzir animações
    if (isset($interface['reduzir_animacoes']) && $interface['reduzir_animacoes']) {
        $_SESSION['body_classes'] = ($_SESSION['body_classes'] ?? '') . ' reduce-motion';
    }
}

echo json_encode(['success' => true, 'message' => 'Preferências aplicadas!']);
?>
