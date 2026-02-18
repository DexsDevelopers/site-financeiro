<?php
require_once 'includes/db_connect.php';
session_start();

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit();
}

$id_usuario = $_SESSION['user_id'];
$id_tarefa = $_GET['id'] ?? null;

if (empty($id_tarefa)) {
    echo json_encode(['success' => false, 'message' => 'ID da tarefa não informado.']);
    exit();
}

try {
    $sql = "SELECT id, descricao, prioridade, data_limite, hora_inicio, hora_fim, tempo_estimado 
            FROM tarefas 
            WHERE id = ? AND id_usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_tarefa, $id_usuario]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tarefa) {
        // Formata horas para o padrão "HH:MM"
        if (!empty($tarefa['hora_inicio'])) {
            $tarefa['hora_inicio'] = substr($tarefa['hora_inicio'], 0, 5);
        }
        if (!empty($tarefa['hora_fim'])) {
            $tarefa['hora_fim'] = substr($tarefa['hora_fim'], 0, 5);
        }

        echo json_encode(['success' => true, 'tarefa' => $tarefa]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar tarefa: ' . $e->getMessage()]);
}
