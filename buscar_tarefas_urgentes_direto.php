<?php
// buscar_tarefas_urgentes_direto.php - Endpoint alternativo para buscar tarefas urgentes diretamente

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit();
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];

try {
    $sql = "SELECT id, descricao, prioridade, data_limite,
            CASE 
                WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() THEN 'Vencida'
                WHEN data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'Urgente'
                ELSE 'Alta Prioridade'
            END as status_urgencia
            FROM tarefas 
            WHERE id_usuario = ? 
            AND status = 'pendente' 
            AND (
                prioridade = 'Alta' 
                OR (data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
            )
            ORDER BY 
                CASE WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() THEN 1 ELSE 2 END,
                CASE WHEN data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 2 END,
                FIELD(prioridade, 'Alta', 'Média', 'Baixa'),
                data_limite ASC
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar datas
    foreach ($tarefas as &$tarefa) {
        if (!empty($tarefa['data_limite'])) {
            $tarefa['data_limite'] = date('d/m/Y', strtotime($tarefa['data_limite']));
        }
    }
    unset($tarefa);
    
    echo json_encode([
        'success' => true,
        'tarefas' => $tarefas,
        'total' => count($tarefas)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar tarefas: ' . $e->getMessage()
    ]);
}
?>



