<?php
session_start();

if (!isset($_SESSION['admin_auth'])) {
    die("Você não está em modo de representação.");
}

// Restaura os dados do administrador
$adminData = $_SESSION['admin_auth'];

$_SESSION['user_id'] = $adminData['user_id'];
$_SESSION['user_name'] = $adminData['user_name'];
$_SESSION['user_email'] = $adminData['user_email'];
$_SESSION['user_type'] = $adminData['user_type'];
$_SESSION['user'] = $adminData['user'];

// Remove o state flag de representação
unset($_SESSION['admin_auth']);

// Redireciona para o painel de administração
header("Location: index.php");
exit();
