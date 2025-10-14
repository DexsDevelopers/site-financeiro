<?php
// teste_logout.php - Teste do sistema de logout

session_start();

echo "<h2>🧪 Teste do Sistema de Logout</h2>";

// Verificar se está logado
if (isset($_SESSION['user_id'])) {
    echo "<p>✅ Usuário logado: ID " . $_SESSION['user_id'] . "</p>";
    echo "<p>Nome: " . ($_SESSION['user_name'] ?? 'N/A') . "</p>";
    echo "<p>Email: " . ($_SESSION['user_email'] ?? 'N/A') . "</p>";
    
    echo "<h3>🔍 Verificando Cookies:</h3>";
    if (isset($_COOKIE['remember_token'])) {
        echo "<p>✅ Cookie remember_token encontrado: " . substr($_COOKIE['remember_token'], 0, 20) . "...</p>";
    } else {
        echo "<p>❌ Cookie remember_token NÃO encontrado</p>";
    }
    
    echo "<h3>🧪 Testando Logout:</h3>";
    echo "<p><a href='logout.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Fazer Logout</a></p>";
    
} else {
    echo "<p>❌ Usuário NÃO está logado</p>";
    echo "<p><a href='index.php'>← Voltar para Login</a></p>";
}

echo "<hr>";
echo "<h3>📊 Informações da Sessão:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>🍪 Cookies:</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";
?>
