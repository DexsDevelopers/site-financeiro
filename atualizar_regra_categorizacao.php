<?php
// atualizar_regra_categorizacao.php

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$padrao = trim($_POST['padrao'] ?? '');
$id_categoria = (int) ($_POST['id_categoria'] ?? 0);
$tipo = $_POST['tipo'] ?? '';
$prioridade = $_POST['prioridade'] ?? 'Média';
$ativa = isset($_POST['ativa']) ? 1 : 0;

// Validações
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da regra inválido']);
    exit;
}

if (empty($padrao)) {
    echo json_encode(['success' => false, 'message' => 'Padrão é obrigatório']);
    exit;
}

if ($id_categoria <= 0) {
    echo json_encode(['success' => false, 'message' => 'Categoria é obrigatória']);
    exit;
}

if (!in_array($tipo, ['receita', 'despesa'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    exit;
}

if (!in_array($prioridade, ['Baixa', 'Média', 'Alta'])) {
    echo json_encode(['success' => false, 'message' => 'Prioridade inválida']);
    exit;
}

// Validar se a expressão regular é válida
try {
    preg_match('/' . $padrao . '/i', 'teste');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Expressão regular inválida: ' . $e->getMessage()]);
    exit;
}

// Verificar se a regra pertence ao usuário
try {
    $stmt_check = $pdo->prepare("SELECT id FROM regras_categorizacao WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$id, $userId]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Regra não encontrada']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar regra']);
    exit;
}

// Verificar se a categoria pertence ao usuário
try {
    $stmt_cat = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND id_usuario = ?");
    $stmt_cat->execute([$id_categoria, $userId]);
    if (!$stmt_cat->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Categoria não encontrada']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar categoria']);
    exit;
}

// Verificar se já existe outra regra com o mesmo padrão
try {
    $stmt_duplicate = $pdo->prepare("SELECT id FROM regras_categorizacao WHERE padrao = ? AND id_usuario = ? AND id != ?");
    $stmt_duplicate->execute([$padrao, $userId, $id]);
    if ($stmt_duplicate->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe outra regra com este padrão']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar duplicatas']);
    exit;
}

// Atualizar regra
try {
    $stmt = $pdo->prepare("UPDATE regras_categorizacao SET padrao = ?, id_categoria = ?, tipo = ?, prioridade = ?, ativa = ? WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$padrao, $id_categoria, $tipo, $prioridade, $ativa, $id, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Regra atualizada com sucesso!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar regra: ' . $e->getMessage()]);
}
?>
