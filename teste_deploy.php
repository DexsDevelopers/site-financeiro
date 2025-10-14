<?php
// Arquivo de teste para verificar se o deploy está funcionando
echo "<h1>🚀 TESTE DE DEPLOY</h1>";
echo "<p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>Status:</strong> ✅ Arquivo acessível no servidor!</p>";
echo "<p><strong>Deploy funcionando:</strong> SIM</p>";

// Teste de conexão com banco
try {
    require_once 'includes/db_connect.php';
    echo "<p><strong>Conexão BD:</strong> ✅ Funcionando</p>";
    
    // Teste simples de query
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch();
    echo "<p><strong>Total usuários:</strong> " . $result['total'] . "</p>";
    
} catch (Exception $e) {
    echo "<p><strong>Conexão BD:</strong> ❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><em>Se você está vendo esta mensagem, o deploy está funcionando!</em></p>";
?>
