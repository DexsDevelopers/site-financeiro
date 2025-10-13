<?php
// teste_automatizacao.php - Versão de teste da página de automatização

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "Erro: Usuário não está logado. <a href='index.php'>Fazer login</a>";
    exit();
}

echo "<h1>Teste da Página de Automação por Horário</h1>";
echo "<p>Usuário logado: " . ($_SESSION['user_name'] ?? 'N/A') . "</p>";
echo "<p>ID do usuário: " . ($_SESSION['user_id'] ?? 'N/A') . "</p>";

// Testar conexão com banco
try {
    require_once 'includes/db_connect.php';
    echo "<p>✅ Conexão com banco de dados: OK</p>";
    
    // Testar consulta simples
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    echo "<p>✅ Consulta ao banco: OK - Total de tarefas: " . $result['total'] . "</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão com banco: " . $e->getMessage() . "</p>";
}

echo "<p><a href='automatizacao_horario.php'>Ir para página original</a></p>";
echo "<p><a href='dashboard.php'>Voltar ao Dashboard</a></p>";
?>
