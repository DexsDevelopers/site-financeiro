<?php
require_once 'includes/db_connect.php';
session_start();

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit();
}

$id_usuario = $_SESSION['user_id'];

// Verifica se foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Requisição inválida.']);
    exit();
}

// Sanitização e validação dos dados
$id_tarefa = $_POST['id'] ?? null;
$descricao = trim($_POST['descricao'] ?? '');
$prioridade = $_POST['prioridade'] ?? 'Média';
$data_limite = $_POST['data_limite'] ?? null;
$hora_inicio = $_POST['hora_inicio'] ?? null;
$hora_fim = $_POST['hora_fim'] ?? null;
$tempo_horas = isset($_POST['tempo_horas']) ? (int) $_POST['tempo_horas'] : 0;
$tempo_minutos = isset($_POST['tempo_minutos']) ? (int) $_POST['tempo_minutos'] : 0;

if (empty($id_tarefa) || empty($descricao)) {
    echo json_encode(['success' => false, 'message' => 'Campos obrigatórios não preenchidos.']);
    exit();
}

// Cálculo do tempo estimado total em minutos
$tempo_estimado = ($tempo_horas * 60) + $tempo_minutos;

// Converte campos vazios em NULL (para não quebrar o banco)
$data_limite = $data_limite !== '' ? $data_limite : null;
$hora_inicio = $hora_inicio !== '' ? $hora_inicio : null;
$hora_fim = $hora_fim !== '' ? $hora_fim : null;

try {
    $sql = "UPDATE tarefas SET 
                descricao = ?, 
                prioridade = ?, 
                data_limite = ?, 
                hora_inicio = ?, 
                hora_fim = ?, 
                tempo_estimado = ?
            WHERE id = ? AND id_usuario = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $descricao,
        $prioridade,
        $data_limite,
        $hora_inicio,
        $hora_fim,
        $tempo_estimado,
        $id_tarefa,
        $id_usuario
    ]);

    echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar tarefa: ' . $e->getMessage()]);
}
