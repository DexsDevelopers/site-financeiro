<?php
session_start();
require_once '../includes/db_connect.php';

// Verificar se é um admin (só admin real pode usar isso)
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
$is_already_impersonating = isset($_SESSION['admin_auth']);

if (!$is_admin && !$is_already_impersonating) {
    die("Acesso negado.");
}

$target_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($target_id <= 0) {
    die("ID de usuário inválido.");
}

// Buscar o usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$target_id]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    die("Usuário não encontrado.");
}

// Se não estava impersonando, salva os dados do admin
if (!$is_already_impersonating) {
    $_SESSION['admin_auth'] = [
        'user_id' => $_SESSION['user_id'],
        'user_name' => $_SESSION['user_name'],
        'user_email' => $_SESSION['user_email'] ?? '',
        'user_type' => $_SESSION['user_type'],
        'user' => $_SESSION['user']
    ];
}

// Loga como o usuário selecionado
$_SESSION['user_id'] = $targetUser['id'];
$_SESSION['user_name'] = $targetUser['nome_completo'];
$_SESSION['user_email'] = $targetUser['email'];
$_SESSION['user_type'] = $targetUser['tipo'];
$_SESSION['user'] = [
    'id' => $targetUser['id'],
    'nome' => $targetUser['nome_completo'],
    'email' => $targetUser['email'],
    'tipo' => $targetUser['tipo']
];

// Redireciona para o dashboard
header("Location: ../dashboard.php");
exit();
