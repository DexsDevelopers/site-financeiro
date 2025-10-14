<?php
// teste_logout_simples.php - Teste simples de logout

echo "<h2>🧪 Teste Simples de Logout</h2>";

// Verificar se está logado
if (isset($_SESSION['user_id'])) {
    echo "<p>✅ Usuário logado: ID " . $_SESSION['user_id'] . "</p>";
    echo "<p><strong>Testando logout...</strong></p>";
    
    // Simular logout
    session_destroy();
    echo "<p>✅ Sessão destruída</p>";
    
    // Verificar se ainda está logado
    if (!isset($_SESSION['user_id'])) {
        echo "<p>✅ Logout bem-sucedido!</p>";
    } else {
        echo "<p>❌ Logout falhou - ainda logado</p>";
    }
    
} else {
    echo "<p>❌ Usuário NÃO está logado</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Voltar para Login</a></p>";
echo "<p><a href='dashboard.php'>← Ir para Dashboard</a></p>";
?>
