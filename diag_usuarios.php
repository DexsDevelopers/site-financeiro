<?php
require_once 'includes/db_connect.php';
try {
    $stmt = $pdo->query("DESCRIBE usuarios");
    $desc = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($desc, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
