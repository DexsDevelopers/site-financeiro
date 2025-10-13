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
    $userEmail = $_SESSION['email'] ?? 'N/A';
    $userNome = $_SESSION['nome'] ?? 'N/A';
    
    // Log de logout
    error_log("LOGOUT: Usuário deslogado - ID: $userId, Nome: $userNome, Email: $userEmail");
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