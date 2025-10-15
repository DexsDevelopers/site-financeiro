<?php
// Teste simples de deploy
echo "✅ Deploy funcionando!<br>";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "<br>";
echo "Servidor: " . $_SERVER['SERVER_NAME'] . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Teste de sessão
session_start();
if (isset($_SESSION['user_id'])) {
    echo "Usuário logado: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "Usuário não logado<br>";
}

// Teste de banco de dados
try {
    require_once 'includes/db_connect.php';
    echo "✅ Conexão com banco: OK<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch();
    echo "Total de usuários: " . $result['total'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Erro de banco: " . $e->getMessage() . "<br>";
}

echo "<br><strong>Deploy testado com sucesso!</strong>";
?>
