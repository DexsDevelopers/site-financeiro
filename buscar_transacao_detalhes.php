<?php
// /buscar_transacao_detalhes.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado.']));
}

require_once 'includes/db_connect.php';

$transacaoId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($transacaoId)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'ID da transação não fornecido.']));
}

try {
    // Busca a transação específica
    $stmt = $pdo->prepare("SELECT * FROM transacoes WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$transacaoId, $userId]);
    $transacao = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($transacao) {
        // Busca todas as categorias do usuário para o dropdown
        $stmt_cats = $pdo->prepare("SELECT id, nome, tipo FROM categorias WHERE id_usuario = ? ORDER BY tipo, nome");
        $stmt_cats->execute([$userId]);
        $categorias = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'transacao' => $transacao, 'categorias' => $categorias]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transação não encontrada.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
?>