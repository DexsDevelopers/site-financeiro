<?php
// includes/auto_login.php - Sistema de login automático

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'remember_me_manager.php';

// Verificar se já está logado
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    return; // Já está logado, não precisa verificar token
}

// Verificar se há token de lembrança
$rememberManager = new RememberMeManager($pdo);
$token = $rememberManager->getTokenFromCookie();

if ($token) {
    // Tentar fazer login automático
    if ($rememberManager->autoLogin($token)) {
        // Login automático bem-sucedido
        // Redirecionar para a página atual ou dashboard
        $currentPage = basename($_SERVER['PHP_SELF']);
        
        // Se estiver na página de login, redirecionar para dashboard
        if ($currentPage === 'index.php' || $currentPage === 'login.php') {
            header("Location: dashboard.php");
            exit();
        }
        
        // Se estiver em outra página, recarregar para aplicar a sessão
        if (!in_array($currentPage, ['index.php', 'login.php', 'registrar.php'])) {
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }
    } else {
        // Token inválido ou expirado, limpar cookie
        $rememberManager->revokeToken($token);
    }
}
?>
