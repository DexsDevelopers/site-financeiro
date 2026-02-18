<?php
// /buscar_dias_uso.php
header('Content-Type: application/json');
require_once 'includes/db_connect.php';

$response = ['success' => false];
$usuario = $_GET['usuario'] ?? '';

if (empty($usuario)) {
    echo json_encode($response);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT data_criacao FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $data_criacao_str = $stmt->fetchColumn();

    if ($data_criacao_str) {
        $data_criacao = new DateTime($data_criacao_str);
        $hoje = new DateTime();
        $dias_de_uso = $data_criacao->diff($hoje)->days + 1;
        
        $response['success'] = true;
        $response['dias_uso'] = $dias_de_uso;
    }
} catch (PDOException $e) {
    // Silenciosamente falha, não expõe erros de banco
}

echo json_encode($response);
?>