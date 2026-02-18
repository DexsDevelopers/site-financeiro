<?php
// aplicar_tema_cores.php

session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$cores = $input['cores'] ?? [];

if (empty($cores)) {
    echo json_encode(['success' => false, 'message' => 'Cores não fornecidas']);
    exit;
}

// Salvar cores na sessão
$_SESSION['cores_tema'] = $cores;

echo json_encode(['success' => true, 'message' => 'Cores aplicadas!']);
?>
