<?php
require_once 'includes/db_connect.php';
session_start();

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

echo "<h1>🔍 DIAGNÓSTICO - ROTINAS CRIADAS</h1>";
echo "<hr>";

if (!$userId) {
    echo "<p style='color: red;'>Não autenticado</p>";
    exit;
}

try {
    echo "<h2>Últimas 10 Rotinas Criadas pelo Usuário:</h2>";
    $stmt = $pdo->prepare("
        SELECT id, nome, horario_sugerido, descricao, ativo, data_criacao
        FROM rotinas_fixas
        WHERE id_usuario = ?
        ORDER BY id DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $rotinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rotinas)) {
        echo "<p>Nenhuma rotina encontrada</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Horário</th><th>Descrição</th><th>Ativo</th><th>Data Criação</th></tr>";
        
        foreach ($rotinas as $r) {
            echo "<tr>";
            echo "<td>" . $r['id'] . "</td>";
            echo "<td>" . htmlspecialchars($r['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($r['horario_sugerido'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars(substr($r['descricao'], 0, 50)) . "</td>";
            echo "<td>" . ($r['ativo'] ? 'Sim' : 'Não') . "</td>";
            echo "<td>" . $r['data_criacao'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>Dados Raw (JSON):</h2>";
    echo "<pre>";
    foreach ($rotinas as $r) {
        echo "ID: " . $r['id'] . " | Nome: '" . $r['nome'] . "' | Horário: '" . $r['horario_sugerido'] . "' | Descrição: '" . $r['descricao'] . "'\n";
    }
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>
