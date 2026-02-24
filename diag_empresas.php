<?php
// diag_empresas.php
require_once 'includes/db_connect.php';

try {
    $stmt = $pdo->query("SELECT e.id, e.nome, e.id_usuario, u.nome_completo as dono, e.id_pai 
                         FROM ge_empresas e 
                         LEFT JOIN usuarios u ON e.id_usuario = u.id");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Empresas cadastradas:</h3>";
    echo "<table border='1' cellspacing='0' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nome</th><th>ID Usuário (Dono)</th><th>Nome Dono</th><th>ID Pai</th></tr>";
    foreach ($empresas as $e) {
        echo "<tr>";
        echo "<td>{$e['id']}</td>";
        echo "<td>{$e['nome']}</td>";
        echo "<td>{$e['id_usuario']}</td>";
        echo "<td>{$e['dono']}</td>";
        echo "<td>{$e['id_pai']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
