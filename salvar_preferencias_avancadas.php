<?php
// salvar_preferencias_avancadas.php

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

// Criar tabela se não existir
try {
    $sql_create_table = "CREATE TABLE IF NOT EXISTS preferencias_usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL UNIQUE,
        preferencias JSON NOT NULL,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_create_table);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar tabela: ' . $e->getMessage()]);
    exit;
}

// Salvar preferências
try {
    $stmt = $pdo->prepare("INSERT INTO preferencias_usuarios (id_usuario, preferencias) 
                          VALUES (?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          preferencias = VALUES(preferencias)");
    
    $stmt->execute([$userId, json_encode($preferencias)]);
    
    // Salvar no cache
    $cache->setUserCache($userId, 'preferencias_avancadas', $preferencias, 3600);
    
    // Aplicar configurações de performance
    if ($preferencias['performance']['cache_agressivo']) {
        $cache->default_ttl = 7200; // 2 horas
    } else {
        $cache->default_ttl = 3600; // 1 hora
    }
    
    echo json_encode(['success' => true, 'message' => 'Preferências salvas com sucesso!']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar preferências: ' . $e->getMessage()]);
}
?>
