<?php
// concluir_tarefa_ajax.php - Versão AJAX para conclusão de tarefas

session_start();
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

// Obter dados do JSON
$input = json_decode(file_get_contents('php://input'), true);
$tarefaId = $input['id'] ?? null;

if (!$tarefaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da tarefa não fornecido.']);
    exit();
}

// Conectar ao banco
try {
    require_once 'includes/db_connect.php';
    $userId = $_SESSION['user_id'];
    
    // Atualizar status da tarefa
    $stmt = $pdo->prepare("UPDATE tarefas SET status = 'concluida', data_atualizacao = NOW() WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$tarefaId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Tarefa concluída com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada ou não pertence ao usuário.']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>
