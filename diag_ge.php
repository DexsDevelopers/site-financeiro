<?php
require_once 'includes/db_connect.php';
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM ge_empresas");
    $count = $stmt->fetchColumn();
    
    $stmt2 = $pdo->query("SELECT id_usuario, COUNT(*) as qty FROM ge_empresas GROUP BY id_usuario");
    $users = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    $res = [
        'total_empresas' => $count,
        'user_stats' => $users,
        'db_name' => $dbname
    ];
    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
