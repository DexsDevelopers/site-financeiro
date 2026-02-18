<?php
// logout.php - Sistema de Logout Seguro
// Painel Financeiro Helmer - Encerra sessão e registra log

// Configurações de segurança
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sessão se não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se há usuário logado para registrar o logout
$usuarioLogado = false;
$userId = null;
$userEmail = null;
$userNome = null;

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $usuarioLogado = true;
    $userId = $_SESSION['user_id'];
    $userEmail = $_SESSION['user_email'] ?? 'N/A';
    $userNome = $_SESSION['user_name'] ?? 'N/A';
    
    // Log de logout
    error_log("LOGOUT: Usuário deslogado - ID: $userId, Nome: $userNome, Email: $userEmail");
    
    // Revogar tokens de "Lembre-se de mim" se existirem
    try {
        require_once 'includes/db_connect.php';
        require_once 'includes/remember_me_manager.php';
        
        $rememberManager = new RememberMeManager($pdo);
        
        // Revogar todos os tokens do usuário
        $rememberManager->revokeAllUserTokens($userId);
        
        // Log de revogação de tokens
        error_log("LOGOUT: Tokens de 'Lembre-se de mim' revogados para usuário ID: $userId");
        
    } catch (Exception $e) {
        // Log do erro, mas não interromper o logout
        error_log("LOGOUT: Erro ao revogar tokens - " . $e->getMessage());
    }
}

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Redirecionar para login com mensagem
$mensagem = $usuarioLogado ? 'Logout realizado com sucesso!' : 'Sessão encerrada.';
$parametros = http_build_query(['mensagem' => $mensagem]);

header("Location: index.php?$parametros");
exit();
?>