<?php
header('Content-Type: application/json');

require_once 'templates/header.php';

try {
    // Verificar autenticação
    $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    // Validar dados do formulário
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $prioridade = trim($_POST['prioridade'] ?? 'Média');
    $data_limite = trim($_POST['data_limite'] ?? null);

    if (!$titulo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Título é obrigatório']);
        exit;
    }

    // Validar prioridade
    if (!in_array($prioridade, ['Baixa', 'Média', 'Alta'])) {
        $prioridade = 'Média';
    }

    // Inserir tarefa
    $stmt = $pdo->prepare("
        INSERT INTO tarefas (id_usuario, titulo, descricao, prioridade, data_limite, status, data_criacao)
        VALUES (?, ?, ?, ?, ?, 'pendente', NOW())
    ");
    
    $stmt->execute([
        $userId,
        $titulo,
        $descricao ?: null,
        $prioridade,
        $data_limite ?: null
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Tarefa adicionada com sucesso!'
    ]);

} catch (PDOException $e) {
    error_log("Erro ao adicionar tarefa: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao adicionar tarefa'
    ]);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição'
    ]);
}
?>
