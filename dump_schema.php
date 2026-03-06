<?php
require 'includes/db_connect.php';
$stmt = $pdo->query('EXPLAIN tarefas');
file_put_contents('tarefas_schema.txt', print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true));
echo "Schema dumped!\n";
?>
