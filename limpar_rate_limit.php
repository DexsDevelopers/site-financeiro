<?php
require_once 'includes/db_connect.php';

$userId = 87; // Seu ID conforme logs anteriores
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

try {
    // Limpar registros de rate limit para o usuário
    $stmt = $pdo->prepare("DELETE FROM rate_limit_ia WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    
    echo "✅ Rate limit limpo com sucesso para o usuário ID $userId.<br>";
    echo "Registros deletados: " . $stmt->rowCount() . "<br>";
    
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'rate_limit_ia'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela 'rate_limit_ia' existe.<br>";
    } else {
        echo "❌ Tabela 'rate_limit_ia' NÃO existe.<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>

