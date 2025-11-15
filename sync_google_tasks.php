<?php
// sync_google_tasks.php - Sincronizar tarefas com Google Tasks

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro desconhecido'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';
require_once 'includes/google_integration_manager.php';

$userId = $_SESSION['user_id'];

try {
    $manager = new GoogleIntegrationManager($pdo);
    
    if (!$manager->isConnected($userId)) {
        throw new Exception('Conta Google não conectada.');
    }
    
    if (!$manager->isServiceEnabled($userId, 'tasks')) {
        throw new Exception('Google Tasks não está ativado.');
    }
    
    // Buscar lista de tarefas padrão do Google
    $taskLists = $manager->makeApiRequest(
        $userId,
        'https://www.googleapis.com/tasks/v1/users/@me/lists'
    );
    
    $defaultListId = null;
    if (isset($taskLists['items']) && !empty($taskLists['items'])) {
        // Usar a primeira lista ou criar uma nova
        $defaultListId = $taskLists['items'][0]['id'];
    } else {
        // Criar nova lista
        $newList = $manager->makeApiRequest(
            $userId,
            'https://www.googleapis.com/tasks/v1/users/@me/lists',
            'POST',
            ['title' => 'Painel Financeiro']
        );
        $defaultListId = $newList['id'];
    }
    
    // Buscar tarefas pendentes do painel
    $stmt = $pdo->prepare("
        SELECT id, descricao, data_limite, prioridade
        FROM tarefas
        WHERE id_usuario = ? 
        AND status = 'pendente'
        ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), data_limite ASC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $tasksCreated = 0;
    
    foreach ($tarefas as $tarefa) {
        $taskData = [
            'title' => $tarefa['descricao'] ?? 'Tarefa sem título',
            'notes' => $tarefa['descricao'] ?? '',
            'status' => 'needsAction'
        ];
        
        if ($tarefa['data_limite']) {
            $taskData['due'] = date('c', strtotime($tarefa['data_limite']));
        }
        
        try {
            $manager->makeApiRequest(
                $userId,
                "https://www.googleapis.com/tasks/v1/lists/{$defaultListId}/tasks",
                'POST',
                $taskData
            );
            $tasksCreated++;
        } catch (Exception $e) {
            error_log("Erro ao criar tarefa no Google: " . $e->getMessage());
        }
    }
    
    // Atualizar última sincronização
    $manager->setServiceEnabled($userId, 'tasks', true, [
        'last_sync' => date('Y-m-d H:i:s'),
        'list_id' => $defaultListId
    ]);
    
    $response['success'] = true;
    $response['message'] = "Sincronização concluída! {$tasksCreated} tarefas sincronizadas.";
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>

