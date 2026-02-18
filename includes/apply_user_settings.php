<?php
// includes/apply_user_settings.php - Aplicar configurações do usuário

require_once 'includes/cache_manager.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    return;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

// Aplicar configurações de layout
$layout_config = $cache->getUserCache($userId, 'layout_config');
if (!$layout_config) {
    // Buscar do banco se não estiver no cache
    try {
        $stmt = $pdo->prepare("SELECT configuracao FROM config_layouts WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $layout_config = json_decode($result['configuracao'], true);
            $cache->setUserCache($userId, 'layout_config', $layout_config, 3600);
        }
    } catch (PDOException $e) {
        $layout_config = null;
    }
}

if ($layout_config) {
    // Aplicar configurações de layout
    $_SESSION['layout_config'] = $layout_config;
    
    // Aplicar classes CSS baseadas na configuração
    $body_classes = [];
    
    // Tipo de layout
    if (isset($layout_config['tipo_layout'])) {
        $body_classes[] = 'layout-' . $layout_config['tipo_layout'];
    }
    
    // Densidade
    if (isset($layout_config['densidade'])) {
        $body_classes[] = 'densidade-' . $layout_config['densidade'];
    }
    
    // Tema escuro
    if (isset($layout_config['tema_escuro']) && $layout_config['tema_escuro']) {
        $body_classes[] = 'theme-dark';
    }
    
    // Animações
    if (isset($layout_config['animacoes']) && $layout_config['animacoes']) {
        $body_classes[] = 'animations-enabled';
    } else {
        $body_classes[] = 'animations-disabled';
    }
    
    // Salvar classes para aplicação no body
    $_SESSION['body_classes'] = implode(' ', $body_classes);
}

// Aplicar preferências avançadas
$preferencias = $cache->getUserCache($userId, 'preferencias_avancadas');
if (!$preferencias) {
    // Buscar do banco se não estiver no cache
    try {
        $stmt = $pdo->prepare("SELECT preferencias FROM preferencias_usuarios WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $preferencias = json_decode($result['preferencias'], true);
            $cache->setUserCache($userId, 'preferencias_avancadas', $preferencias, 3600);
        }
    } catch (PDOException $e) {
        $preferencias = null;
    }
}

if ($preferencias) {
    // Aplicar preferências de interface
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
    
    // Aplicar preferências de performance
    if (isset($preferencias['performance'])) {
        $performance = $preferencias['performance'];
        
        // Cache agressivo
        if (isset($performance['cache_agressivo']) && $performance['cache_agressivo']) {
            $cache->default_ttl = 7200; // 2 horas
        }
    }
}

// Aplicar tema personalizado
$tema_ativo = $_SESSION['tema_ativo'] ?? 'padrao';
if (strpos($tema_ativo, 'personalizado_') === 0) {
    $tema_id = str_replace('personalizado_', '', $tema_ativo);
    
    try {
        $stmt = $pdo->prepare("SELECT cores FROM temas_personalizados WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$tema_id, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $cores = json_decode($result['cores'], true);
            $_SESSION['cores_tema'] = $cores;
        }
    } catch (PDOException $e) {
        // Erro ao buscar tema
    }
}
?>
