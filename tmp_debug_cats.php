<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("SELECT id, nome, id_usuario, tipo FROM categorias");
$cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cats, JSON_PRETTY_PRINT);
