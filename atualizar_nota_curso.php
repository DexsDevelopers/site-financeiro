<?php
// atualizar_nota_curso.php

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
$titulo = trim($_POST['titulo'] ?? '');
$conteudo = trim($_POST['conteudo'] ?? '');
$categoria = $_POST['categoria'] ?? '';
$id_curso = (int) ($_POST['id_curso'] ?? 0);

// Validações
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da nota inválido']);
    exit;
}

if (empty($titulo)) {
    echo json_encode(['success' => false, 'message' => 'Título é obrigatório']);
    exit;
}

if (empty($conteudo)) {
    echo json_encode(['success' => false, 'message' => 'Conteúdo é obrigatório']);
    exit;
}

if (empty($categoria)) {
    echo json_encode(['success' => false, 'message' => 'Categoria é obrigatória']);
    exit;
}

// Curso é opcional, pode ser null
if ($id_curso <= 0) {
    $id_curso = null;
}

$categorias_validas = ['conceitos', 'exercicios', 'dicas', 'resumos', 'formulas', 'definicoes', 'exemplos', 'outros'];
if (!in_array($categoria, $categorias_validas)) {
    echo json_encode(['success' => false, 'message' => 'Categoria inválida']);
    exit;
}

// Verificar se a nota pertence ao usuário
try {
    $stmt_check = $pdo->prepare("SELECT id FROM notas_cursos WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$id, $userId]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Nota não encontrada']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar nota']);
    exit;
}

// Verificar se o curso pertence ao usuário
try {
    $stmt_curso = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND id_usuario = ?");
    $stmt_curso->execute([$id_curso, $userId]);
    if (!$stmt_curso->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Curso não encontrado']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar curso']);
    exit;
}

// Verificar se o curso pertence ao usuário (se fornecido)
if ($id_curso) {
    try {
        $stmt_curso = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND id_usuario = ?");
        $stmt_curso->execute([$id_curso, $userId]);
        if (!$stmt_curso->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Curso não encontrado']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao verificar curso']);
        exit;
    }
}

// Atualizar nota
try {
    $prioridade = $_POST['prioridade'] ?? 'baixa';
    $stmt = $pdo->prepare("UPDATE notas_cursos SET titulo = ?, conteudo = ?, categoria = ?, id_curso = ?, prioridade = ? WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$titulo, $conteudo, $categoria, $id_curso, $prioridade, $id, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Anotação atualizada com sucesso!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar anotação: ' . $e->getMessage()]);
}
?>
