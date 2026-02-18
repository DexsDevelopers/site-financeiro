<?php
require_once 'db.php';

// Exemplo simples para verificar se a conexão está funcionando
echo json_encode([
    'success' => true,
    'message' => 'Conexão com o banco de dados estabelecida com sucesso!',
    'database' => DB_NAME
]);
?>
