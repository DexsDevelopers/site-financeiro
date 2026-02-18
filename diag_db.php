<?php
require_once 'includes/db_connect.php';
header('Content-Type: text/plain');

$tables = ['ge_empresas', 'ge_financeiro', 'ge_ideias', 'ge_conteudo', 'ge_tarefas', 'ge_redes_sociais'];

foreach ($tables as $table) {
    echo "Checking table: $table\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll();
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
    } catch (Exception $e) {
        echo "  - ERROR: " . $e->getMessage() . "\n";
    }
}
