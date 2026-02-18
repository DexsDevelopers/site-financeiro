<?php
// /concluir_tarefa.php

session_start();

// 1. VERIFICAÇÕES DE SEGURANÇA
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

// 2. CONECTAR AO BANCO E OBTER DADOS
require_once 'includes/db_connect.php';
$tarefaId = $_GET['id'];
$userId = $_SESSION['user_id'];

// 3. ATUALIZAR NO BANCO DE DADOS (COM SEGURANÇA)
try {
    // Prepara a query para atualizar o status da tarefa para 'concluida'
    // Novamente, usamos "AND id_usuario = ?" pela mesma razão de segurança de antes.
    $stmt = $pdo->prepare("UPDATE tarefas SET status = 'concluida' WHERE id = ? AND id_usuario = ?");
    
    $stmt->execute([$tarefaId, $userId]);

} catch (PDOException $e) {
    die("Erro ao concluir tarefa: " . $e->getMessage());
}

// 4. REDIRECIONAR DE VOLTA PARA O DASHBOARD
header('Location: dashboard.php');
exit();
?>