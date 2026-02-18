<?php
// includes/auto_login.php - Sistema de login automático melhorado

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__FILE__) . '/remember_me_manager.php';

// Verificar se já está logado
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    return; // Já está logado, não precisa verificar token
}

// Criar instância do gerenciador
$rememberManager = new RememberMeManager($pdo);

// Obter token do cookie
$token = $rememberManager->getTokenFromCookie();

if (!empty($token)) {
    // Tentar fazer login automático
    error_log("AUTO_LOGIN: Tentando login automático com token: " . substr($token, 0, 20) . "...");
    
    if ($rememberManager->autoLogin($token)) {
        // Login automático bem-sucedido
        error_log("AUTO_LOGIN: Login automático bem-sucedido! Usuário ID: " . $_SESSION['user_id']);
        
        // Redirecionar para a página apropriada
        $currentPage = basename($_SERVER['PHP_SELF']);
        
        // Se estiver em páginas públicas de login, redirecionar para dashboard
        if (in_array($currentPage, ['index.php', 'login.php'])) {
            header("Location: dashboard.php", true, 302);
            exit();
        }
        
        // Se estiver em página protegida, recarregar para aplicar a sessão
        if (!in_array($currentPage, ['index.php', 'login.php', 'registrar.php', 'login_process.php'])) {
            // Silenciosamente aplicar a sessão sem redirecionar
            // Isso permite que o header.php e outros scripts vejam o usuário logado
        }
    } else {
        // Token inválido ou expirado
        error_log("AUTO_LOGIN: Token inválido ou expirado. Revogando...");
        $rememberManager->revokeToken($token);
    }
} else {
    error_log("AUTO_LOGIN: Nenhum token de lembrança encontrado no cookie");
}
?>
