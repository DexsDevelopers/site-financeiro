<?php
// Configurações do banco de dados
$db_host = 'localhost';
$db_user = 'u853242961_only';
$db_pass = 'Lucastav8012@';
$db_name = 'u853242961_only';

// Conexão com o banco de dados
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verifica a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>