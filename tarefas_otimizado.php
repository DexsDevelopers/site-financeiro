
<?php
require_once 'templates/header.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit;
}

// Buscar tarefas pendentes
try {
    $stmt = $pdo->prepare("
        SELECT id, titulo, prioridade, data_limite, descricao 
        FROM tarefas 
        WHERE id_usuario = ? AND status = 'pendente' 
        ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), COALESCE(ordem, 9999), data_limite
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tarefas = [];
    error_log("Erro ao buscar tarefas: " . $e->getMessage());
}

// Contar tarefas por prioridade
$stats = ['Alta' => 0, 'Média' => 0, 'Baixa' => 0, 'total' => count($tarefas)];
foreach ($tarefas as $t) {
    $stats[$t['prioridade']]++;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarefas - Painel Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #dc3545;
            --bg-dark: #0a0a0a;
            --bg-card: #141414;
            --border: rgba(255, 255, 255, 0.08);
            --text: #ffffff;
            --text-muted: #b0b0b0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 14px;
        }

        .container-main {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        .stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stat {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-value {
            font-weight: 700;
            font-size: 16px;
        }

        .stat-alta { color: #ff6b6b; }
        .stat-media { color: #ffd93d; }
        .stat-baixa { color: #6bcf7f; }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            background: var(--primary);
            border: none;
            color: white;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action:hover {
            background: #c4080f;
            transform: translateY(-2px);
        }

        .tasks-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 14px;
            border-radius: 8px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            transition: all 0.2s;
            cursor: pointer;
        }

        .task-item:hover {
            background: rgba(20, 20, 20, 0.8);
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateX(4px);
        }

        .task-checkbox {
            width: 20px;
            height: 20px;
            min-width: 20px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .task-content {
            flex: 1;
            min-width: 0;
        }

        .task-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            word-break: break-word;
        }

        .task-meta {
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 12px;
            color: var(--text-muted);
        }

        .task-priority {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
        }

        .priority-alta {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
        }

        .priority-media {
            background: rgba(255, 217, 61, 0.2);
            color: #ffd93d;
        }

        .priority-baixa {
            background: rgba(107, 207, 127, 0.2);
            color: #6bcf7f;
        }

        .task-date {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .task-actions {
            display: flex;
            gap: 6px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .task-item:hover .task-actions {
            opacity: 1;
        }

        .btn-icon {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px 8px;
            font-size: 14px;
            transition: color 0.2s;
        }

        .btn-icon:hover {
            color: var(--text);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--text);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .task-meta {
                flex-wrap: wrap;
            }

            .stats {
                width: 100%;
            }

            .task-actions {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container-main">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="bi bi-check2-all"></i> Tarefas</h1>
            </div>
            <div class="stats">
                <div class="stat">
                    <span class="stat-value stat-alta"><?php echo $stats['Alta']; ?></span>
                    <span>Alta</span>
                </div>
                <div class="stat">
                    <span class="stat-value stat-media"><?php echo $stats['Média']; ?></span>
                    <span>Média</span>
                </div>
                <div class="stat">
                    <span class="stat-value stat-baixa"><?php echo $stats['Baixa']; ?></span>
                    <span>Baixa</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo $stats['total']; ?></span>
                    <span>Total</span>
                </div>
            </div>
            <div class="actions">
                <button class="btn-action" onclick="window.location.href='adicionar_tarefa.php'">
                    <i class="bi bi-plus"></i> Nova
                </button>
            </div>
        </div>

        <!-- Tasks List -->
        <div class="tasks-container">
            <?php if (empty($tarefas)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>Nenhuma tarefa pendente</h3>
                    <p>Você está em dia! Crie uma nova tarefa para começar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tarefas as $task): ?>
                    <div class="task-item" data-task-id="<?php echo $task['id']; ?>">
                        <input type="checkbox" class="task-checkbox" 
                               onchange="completarTarefa(<?php echo $task['id']; ?>)">
                        
                        <div class="task-content">
                            <div class="task-title"><?php echo htmlspecialchars($task['titulo']); ?></div>
                            <div class="task-meta">
                                <span class="task-priority priority-<?php echo strtolower($task['prioridade']); ?>">
                                    <i class="bi bi-exclamation-circle-fill"></i> 
                                    <?php echo $task['prioridade']; ?>
                                </span>
                                <?php if ($task['data_limite']): ?>
                                    <span class="task-date">
                                        <i class="bi bi-calendar"></i>
                                        <?php echo date('d/m', strtotime($task['data_limite'])); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($task['descricao']): ?>
                                    <span title="<?php echo htmlspecialchars($task['descricao']); ?>">
                                        <i class="bi bi-file-text"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="task-actions">
                            <button class="btn-icon" onclick="editarTarefa(<?php echo $task['id']; ?>)" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-icon" onclick="deletarTarefa(<?php echo $task['id']; ?>)" title="Deletar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function completarTarefa(id) {
            fetch('concluir_tarefa_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-task-id="${id}"]`);
                    item.style.opacity = '0.6';
                    setTimeout(() => item.remove(), 300);
                }
            });
        }

        function editarTarefa(id) {
            window.location.href = `editar_tarefa.php?id=${id}`;
        }

        function deletarTarefa(id) {
            if (confirm('Tem certeza?')) {
                window.location.href = `excluir_tarefa.php?id=${id}`;
            }
        }
    </script>
</body>
</html>
