<?php
// includes/tasks_helper.php - Funções helper para sistema de tarefas

/**
 * Adiciona uma nova tarefa
 */
function addTask(PDO $pdo, int $userId, string $description, string $priority = 'Média', ?string $dueDate = null): array {
    try {
        $sql = "INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, status, data_criacao) 
                VALUES (?, ?, ?, ?, 'pendente', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $description, $priority, $dueDate]);
        
        return [
            'success' => true,
            'task_id' => $pdo->lastInsertId(),
            'message' => 'Tarefa criada com sucesso'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao criar tarefa: ' . $e->getMessage()
        ];
    }
}

/**
 * Lista tarefas do usuário
 */
function getTasks(PDO $pdo, int $userId, ?string $status = 'pendente', int $limit = 20): array {
    try {
        $where = "id_usuario = ?";
        $params = [$userId];
        
        if ($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql = "SELECT id, descricao, prioridade, data_limite, status, data_criacao
                FROM tarefas 
                WHERE $where
                ORDER BY 
                    CASE WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() THEN 1 ELSE 2 END,
                    CASE WHEN data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 2 END,
                    FIELD(prioridade, 'Alta', 'Média', 'Baixa'),
                    data_limite ASC,
                    ordem ASC
                LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks)
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao buscar tarefas: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtém tarefas urgentes
 */
function getUrgentTasks(PDO $pdo, int $userId, int $limit = 10): array {
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
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $limit]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks)
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao buscar tarefas urgentes: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtém tarefas de hoje
 */
function getTodayTasks(PDO $pdo, int $userId): array {
    try {
        $sql = "SELECT id, descricao, prioridade, data_limite
                FROM tarefas
                WHERE id_usuario = ?
                AND status = 'pendente'
                AND (DATE(data_limite) = CURDATE() OR data_limite IS NULL)
                ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks)
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao buscar tarefas de hoje: ' . $e->getMessage()
        ];
    }
}

/**
 * Conclui uma tarefa
 */
function completeTask(PDO $pdo, int $taskId, int $userId): array {
    try {
        // Verificar se a tarefa pertence ao usuário
        $checkStmt = $pdo->prepare("SELECT id FROM tarefas WHERE id = ? AND id_usuario = ?");
        $checkStmt->execute([$taskId, $userId]);
        
        if ($checkStmt->rowCount() === 0) {
            return [
                'success' => false,
                'error' => 'Tarefa não encontrada ou não pertence a você'
            ];
        }
        
        // Verificar se a coluna status usa 'concluida' ou 'concluido'
        $stmt = $pdo->query("SHOW COLUMNS FROM tarefas WHERE Field = 'status'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        $statusValue = strpos($column['Type'], 'concluida') !== false ? 'concluida' : 'concluido';
        
        $sql = "UPDATE tarefas SET status = ? WHERE id = ? AND id_usuario = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$statusValue, $taskId, $userId]);
        
        return [
            'success' => true,
            'message' => 'Tarefa concluída com sucesso'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao concluir tarefa: ' . $e->getMessage()
        ];
    }
}

/**
 * Deleta uma tarefa
 */
function deleteTask(PDO $pdo, int $taskId, int $userId): array {
    try {
        // Verificar se a tarefa pertence ao usuário
        $checkStmt = $pdo->prepare("SELECT id FROM tarefas WHERE id = ? AND id_usuario = ?");
        $checkStmt->execute([$taskId, $userId]);
        
        if ($checkStmt->rowCount() === 0) {
            return [
                'success' => false,
                'error' => 'Tarefa não encontrada ou não pertence a você'
            ];
        }
        
        $sql = "DELETE FROM tarefas WHERE id = ? AND id_usuario = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$taskId, $userId]);
        
        return [
            'success' => true,
            'message' => 'Tarefa deletada com sucesso'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao deletar tarefa: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtém estatísticas de tarefas
 */
function getTaskStats(PDO $pdo, int $userId): array {
    try {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas,
                    SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as concluidas_alt,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN prioridade = 'Alta' AND status = 'pendente' THEN 1 ELSE 0 END) as alta_prioridade,
                    SUM(CASE WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() AND status = 'pendente' THEN 1 ELSE 0 END) as vencidas
                FROM tarefas 
                WHERE id_usuario = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $concluidas = (int)$stats['concluidas'] + (int)$stats['concluidas_alt'];
        
        return [
            'success' => true,
            'total' => (int)$stats['total'],
            'concluidas' => $concluidas,
            'pendentes' => (int)$stats['pendentes'],
            'alta_prioridade' => (int)$stats['alta_prioridade'],
            'vencidas' => (int)$stats['vencidas']
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
        ];
    }
}

/**
 * Formata data para exibição
 */
function formatTaskDate(?string $date): string {
    if (!$date) return 'Sem data limite';
    
    $dateObj = new DateTime($date);
    $today = new DateTime();
    $diff = $today->diff($dateObj);
    
    if ($dateObj < $today) {
        return 'Vencida (' . $dateObj->format('d/m/Y') . ')';
    } elseif ($diff->days <= 3) {
        return 'Urgente (' . $dateObj->format('d/m/Y') . ')';
    } else {
        return $dateObj->format('d/m/Y');
    }
}

/**
 * Formata prioridade com emoji
 */
function formatPriority(string $priority): string {
    $emojis = [
        'Alta' => '🔴',
        'Média' => '🟡',
        'Baixa' => '🟢'
    ];
    return ($emojis[$priority] ?? '⚪') . ' ' . $priority;
}



