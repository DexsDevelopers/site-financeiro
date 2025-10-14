<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Buscar tarefas pendentes
    $sql_pendentes = "SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'pendente' ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC";
    $stmt_pendentes = $pdo->prepare($sql_pendentes);
    $stmt_pendentes->execute([$userId]);
    $tarefas = $stmt_pendentes->fetchAll(PDO::FETCH_ASSOC);

    // Buscar subtarefas para cada tarefa
    if (!empty($tarefas)) {
        $todos_ids = array_column($tarefas, 'id');
        $placeholders = implode(',', array_fill(0, count($todos_ids), '?'));
        $sql_subtarefas = "SELECT * FROM subtarefas WHERE id_tarefa_principal IN ($placeholders)";
        $stmt_subtarefas = $pdo->prepare($sql_subtarefas);
        $stmt_subtarefas->execute($todos_ids);
        $todas_as_subtarefas = $stmt_subtarefas->fetchAll(PDO::FETCH_ASSOC);
        
        $subtarefas_mapeadas = [];
        foreach ($todas_as_subtarefas as $subtarefa) { 
            $subtarefas_mapeadas[$subtarefa['id_tarefa_principal']][] = $subtarefa; 
        }
        
        foreach ($tarefas as $key => $tarefa) { 
            $tarefas[$key]['subtarefas'] = $subtarefas_mapeadas[$tarefa['id']] ?? []; 
        }
    }
    
    echo json_encode([
        'success' => true,
        'tarefas' => $tarefas
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar tarefas: ' . $e->getMessage()
    ]);
}
?>
