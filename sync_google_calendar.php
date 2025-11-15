<?php
// sync_google_calendar.php - Sincronizar eventos com Google Calendar

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
    
    if (!$manager->isServiceEnabled($userId, 'calendar')) {
        throw new Exception('Google Calendar não está ativado.');
    }
    
    // Buscar eventos do calendário local (tarefas com data_limite)
    $stmt = $pdo->prepare("
        SELECT id, descricao, data_limite, prioridade
        FROM tarefas
        WHERE id_usuario = ? 
        AND status = 'pendente'
        AND data_limite IS NOT NULL
        AND data_limite >= CURDATE()
        ORDER BY data_limite ASC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $eventsCreated = 0;
    $eventsUpdated = 0;
    
    foreach ($tarefas as $tarefa) {
        // Criar/atualizar evento no Google Calendar
        $eventData = [
            'summary' => $tarefa['descricao'] ?? 'Tarefa sem título',
            'description' => $tarefa['descricao'] ?? '',
            'start' => [
                'dateTime' => date('c', strtotime($tarefa['data_limite'])),
                'timeZone' => 'America/Sao_Paulo'
            ],
            'end' => [
                'dateTime' => date('c', strtotime($tarefa['data_limite'] . ' +1 hour')),
                'timeZone' => 'America/Sao_Paulo'
            ],
            'colorId' => $tarefa['prioridade'] === 'Alta' ? '11' : ($tarefa['prioridade'] === 'Média' ? '5' : '10')
        ];
        
        // Verificar se evento já existe (usando extendedProperties)
        $eventId = 'task_' . $tarefa['id'];
        
        try {
            // Tentar buscar evento existente
            $existingEvent = $manager->makeApiRequest(
                $userId,
                'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $eventId
            );
            
            // Atualizar evento existente
            $manager->makeApiRequest(
                $userId,
                'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $eventId,
                'PUT',
                $eventData
            );
            $eventsUpdated++;
        } catch (Exception $e) {
            // Criar novo evento
            $eventData['extendedProperties'] = [
                'private' => [
                    'task_id' => $tarefa['id']
                ]
            ];
            
            $result = $manager->makeApiRequest(
                $userId,
                'https://www.googleapis.com/calendar/v3/calendars/primary/events',
                'POST',
                $eventData
            );
            $eventsCreated++;
        }
    }
    
    // Atualizar última sincronização
    $manager->setServiceEnabled($userId, 'calendar', true, [
        'last_sync' => date('Y-m-d H:i:s')
    ]);
    
    $response['success'] = true;
    $response['message'] = "Sincronização concluída! {$eventsCreated} eventos criados, {$eventsUpdated} atualizados.";
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>

