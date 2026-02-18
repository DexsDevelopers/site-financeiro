<?php
// /registrar_process.php

require_once 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: registrar.php");
    exit();
}

$nome_completo = trim($_POST['nome_completo'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$senha = $_POST['senha'] ?? '';
$confirmar_senha = $_POST['confirmar_senha'] ?? '';

// --- Validações ---
if (empty($nome_completo) || empty($usuario) || empty($senha)) {
    // Redireciona com erro se algum campo estiver vazio
    header("Location: registrar.php?error=empty");
    exit();
}

if (strlen($senha) < 6) {
    // Redireciona com erro se a senha for muito curta
    header("Location: registrar.php?error=password_short");
    exit();
}

if ($senha !== $confirmar_senha) {
    // Redireciona com erro se as senhas não baterem
    header("Location: registrar.php?error=password_mismatch");
    exit();
}

try {
    // Verifica se o nome de usuário já existe
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $stmt_check->execute([$usuario]);
    if ($stmt_check->fetch()) {
        // Redireciona com erro se o usuário já existir
        header("Location: registrar.php?error=user_exists");
        exit();
    }

    // Se passou por todas as validações, cria o usuário
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt_insert = $pdo->prepare("INSERT INTO usuarios (nome_completo, usuario, senha_hash, tipo) VALUES (?, ?, ?, 'usuario')");
    $stmt_insert->execute([$nome_completo, $usuario, $senha_hash]);
    
    // Redireciona para o login com uma mensagem de sucesso
    header("Location: index.php?success=registered");
    exit();

} catch (PDOException $e) {
    // Em caso de erro de banco, redireciona com um erro genérico
    header("Location: registrar.php?error=dberror");
    exit();
}
?>