<?php
require_once 'templates/header.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit;
}

$dataHoje = date('Y-m-d');

// ===== BUSCAR TODAS AS INFORMAÇÕES =====
$rotinas = [];
$tarefas = [];
$subtarefasPorTarefa = [];
$stats = ['Alta' => 0, 'Média' => 0, 'Baixa' => 0, 'total' => 0];

// 1. Buscar Rotinas Fixas
try {
    $stmt = $pdo->prepare("
        SELECT rf.id, rf.nome, rf.horario_sugerido, rf.descricao,
               rcd.status as status_hoje, rcd.id as controle_id
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd 
            ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY COALESCE(rf.horario_sugerido, '23:59:59'), rf.nome
    ");
    $stmt->execute([$dataHoje, $userId]);
    $rotinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rotinas as &$rotina) {
        if ($rotina['status_hoje'] === null) {
            $stmtInsert = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                VALUES (?, ?, ?, 'pendente')
            ");
            $stmtInsert->execute([$userId, $rotina['id'], $dataHoje]);
            $rotina['status_hoje'] = 'pendente';
            $rotina['controle_id'] = $pdo->lastInsertId();
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar rotinas: " . $e->getMessage());
}

// 2. Buscar Tarefas Pendentes
try {
    $stmt = $pdo->prepare("
        SELECT id, descricao, prioridade, data_limite, status, tempo_estimado, data_criacao
        FROM tarefas 
        WHERE id_usuario = ? AND status = 'pendente'
        ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), COALESCE(ordem, 9999), data_limite
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar tarefas: " . $e->getMessage());
}

// 3. Contar tarefas por prioridade
$stats['total'] = count($tarefas);
foreach ($tarefas as $t) {
    $stats[$t['prioridade']]++;
}

// 4. Buscar Subtarefas
try {
    $stmt = $pdo->prepare("
        SELECT id, id_tarefa_principal, descricao, status 
        FROM subtarefas 
        WHERE id_tarefa_principal IN (
            SELECT id FROM tarefas WHERE id_usuario = ? AND status = 'pendente'
        )
        ORDER BY id_tarefa_principal, id ASC
    ");
    $stmt->execute([$userId]);
    $subtarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subtarefas as $sub) {
        $tid = (int)$sub['id_tarefa_principal'];
        if (!isset($subtarefasPorTarefa[$tid])) {
            $subtarefasPorTarefa[$tid] = [];
        }
        $subtarefasPorTarefa[$tid][] = $sub;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar subtarefas: " . $e->getMessage());
}

// 5. Contar rotinas
$rotinas_concluidas = count(array_filter($rotinas, fn($r) => $r['status_hoje'] === 'concluido'));
$rotinas_total = count($rotinas);
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
            max-width: 1000px;
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

        .section {
            margin-bottom: 35px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
            color: var(--text);
        }

        .section-title i {
            color: var(--primary);
        }

        .items-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 14px;
            border-radius: 8px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            transition: all 0.2s;
        }

        .item:hover {
            background: rgba(20, 20, 20, 0.8);
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateX(4px);
        }

        .item.rotina {
            background: rgba(102, 187, 106, 0.05);
            border-color: rgba(107, 207, 127, 0.2);
        }

        .item.rotina:hover {
            background: rgba(102, 187, 106, 0.1);
            border-color: rgba(107, 207, 127, 0.3);
        }

        .item.concluido {
            opacity: 0.6;
            background: rgba(20, 20, 20, 0.5);
        }

        .item-checkbox {
            width: 20px;
            height: 20px;
            min-width: 20px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .item.rotina .item-checkbox {
            accent-color: #6bcf7f;
        }

        .item-content {
            flex: 1;
            min-width: 0;
        }

        .item-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            word-break: break-word;
        }

        .item-meta {
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 12px;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .item-priority {
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

        .item-date {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .item-time {
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, 0.05);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .item-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
            background: rgba(107, 207, 127, 0.2);
            color: #6bcf7f;
        }

        .item-actions {
            display: flex;
            gap: 6px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .item:hover .item-actions {
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

        .progress-bar-mini {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            padding: 4px 10px;
            background: rgba(107, 207, 127, 0.15);
            border-radius: 4px;
            color: #6bcf7f;
            font-weight: 600;
        }

        /* Subtarefas */
        .subtasks {
            margin-top: 0;
            padding: 12px 0 0 0;
            margin-left: 0;
            border-top: none;
            padding-left: 32px;
            position: relative;
        }

        .subtasks::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary), transparent);
            border-radius: 2px;
        }

        .subtasks-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: #6bcf7f;
            font-size: 12px;
            cursor: pointer;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s;
        }

        .subtasks-header:hover {
            color: #7dd88f;
            transform: translateX(2px);
        }

        .subtasks-header i {
            transition: transform 0.2s;
            font-size: 14px;
        }

        .subtasks-header .btn-icon {
            opacity: 0;
            transition: opacity 0.2s;
            padding: 4px 8px;
            color: #6bcf7f;
            margin-left: auto !important;
        }

        .subtasks-header:hover .btn-icon {
            opacity: 1;
        }

        .subtasks-header .btn-icon:hover {
            color: #7dd88f;
            transform: scale(1.15);
        }

        .subtasks-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .subtask-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: linear-gradient(135deg, rgba(107, 207, 127, 0.05), rgba(107, 207, 127, 0.02));
            border-left: 3px solid var(--primary);
            border-radius: 4px;
            font-size: 13px;
            transition: all 0.2s;
            position: relative;
        }

        .subtask-item:hover {
            background: linear-gradient(135deg, rgba(107, 207, 127, 0.1), rgba(107, 207, 127, 0.05));
            border-left-color: #7dd88f;
            transform: translateX(4px);
        }

        .subtask-checkbox {
            width: 18px;
            height: 18px;
            min-width: 18px;
            cursor: pointer;
            accent-color: #6bcf7f;
            border-radius: 3px;
            transition: all 0.2s;
        }

        .subtask-checkbox:hover {
            transform: scale(1.1);
        }

        .subtask-label {
            flex: 1;
            cursor: pointer;
            user-select: none;
            color: var(--text);
            transition: color 0.2s;
            font-weight: 500;
        }

        .subtask-label.completed {
            text-decoration: line-through;
            color: #6bcf7f;
            opacity: 0.7;
        }

        .btn-delete-subtask {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px 6px;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
        }

        .subtask-item:hover .btn-delete-subtask {
            opacity: 1;
        }

        .btn-delete-subtask:hover {
            color: #ff6b6b;
            transform: scale(1.2) rotate(90deg);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 500px;
            position: relative;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-dark);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: var(--text);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            transition: all 0.2s;
        }

        .modal-close:hover {
            color: var(--primary);
        }

        .modal-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-input {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px 12px;
            color: var(--text);
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            border-color: var(--primary);
            outline: none;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            background: var(--bg-dark);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel, .btn-submit {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel {
            background: none;
            border: 1px solid var(--border);
            color: var(--text-muted);
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-submit {
            background: var(--primary);
            border: none;
            color: white;
        }

        .btn-submit:hover {
            background: #c4080f;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .item-meta {
                flex-wrap: wrap;
            }

            .stats {
                width: 100%;
            }

            .item-actions {
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
                <h1><i class="bi bi-check2-all"></i> Tarefas & Rotinas</h1>
            </div>
            <div class="stats">
                <div class="stat">
                    <span class="stat-value stat-alta"><?php echo $stats['Alta']; ?></span>
                    <span>Alta</span>
                </div>
                <div class="stat">
                    <span class="stat-value" style="color: #6bcf7f;"><?php echo $rotinas_concluidas; ?>/<?php echo $rotinas_total; ?></span>
                    <span>Rotinas</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo $stats['total']; ?></span>
                    <span>Tarefas</span>
                </div>
            </div>
            <div class="actions">
                <button class="btn-action" onclick="abrirModalTarefa()">
                    <i class="bi bi-plus"></i> Nova Tarefa
                </button>
                <button class="btn-action" style="background: #6bcf7f;" onclick="abrirModalRotina()">
                    <i class="bi bi-plus"></i> Nova Rotina
                </button>
            </div>
        </div>

        <!-- Rotinas Fixas -->
        <div class="section">
            <div class="section-title">
                <i class="bi bi-calendar-check"></i>
                Rotinas Fixas
                <span class="progress-bar-mini">
                    <?php echo $rotinas_concluidas; ?>/<?php echo $rotinas_total; ?> concluídas hoje
                </span>
            </div>

            <div class="items-container">
                <?php if (empty($rotinas)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <h3>Nenhuma rotina configurada</h3>
                        <p>Crie rotinas diárias para não esquecer tarefas importantes.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($rotinas as $rotina): ?>
                        <div class="item rotina <?php echo $rotina['status_hoje'] === 'concluido' ? 'concluido' : ''; ?>" 
                             data-rotina-id="<?php echo $rotina['id']; ?>">
                            <input type="checkbox" class="item-checkbox" 
                                   <?php echo $rotina['status_hoje'] === 'concluido' ? 'checked' : ''; ?>
                                   onchange="completarRotina(<?php echo $rotina['controle_id']; ?>)">
                            
                            <div class="item-content">
                                <div class="item-title"><?php echo htmlspecialchars($rotina['nome']); ?></div>
                                <div class="item-meta">
                                    <?php if ($rotina['horario_sugerido']): ?>
                                        <span class="item-time">
                                            <i class="bi bi-clock"></i>
                                            <?php echo date('H:i', strtotime($rotina['horario_sugerido'])); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($rotina['descricao']): ?>
                                        <span title="<?php echo htmlspecialchars($rotina['descricao']); ?>">
                                            <i class="bi bi-file-text"></i>
                                        </span>
                                    <?php endif; ?>
                                    <span class="item-status">
                                        <i class="bi bi-check-circle"></i>
                                        <?php echo ucfirst($rotina['status_hoje']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="item-actions">
                                <button class="btn-icon" onclick="abrirModalEditarRotina(<?php echo $rotina['id']; ?>)" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon" onclick="deletarRotina(<?php echo $rotina['id']; ?>)" title="Deletar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tarefas -->
        <div class="section">
            <div class="section-title">
                <i class="bi bi-list-check"></i>
                Tarefas Pendentes
            </div>

            <div class="items-container">
                <?php if (empty($tarefas)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <h3>Nenhuma tarefa pendente</h3>
                        <p>Você está em dia! Crie uma nova tarefa para começar.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tarefas as $task): ?>
                        <div class="item" data-task-id="<?php echo $task['id']; ?>">
                            <input type="checkbox" class="item-checkbox" 
                                   onchange="completarTarefa(<?php echo $task['id']; ?>)">
                            
                            <div class="item-content">
                                <div class="item-title"><?php echo htmlspecialchars($task['descricao']); ?></div>
                                <div class="item-meta">
                                    <span class="item-priority priority-<?php echo strtolower($task['prioridade']); ?>">
                                        <i class="bi bi-exclamation-circle-fill"></i> 
                                        <?php echo $task['prioridade']; ?>
                                    </span>
                                    <?php if ($task['data_limite']): ?>
                                        <span class="item-date">
                                            <i class="bi bi-calendar"></i>
                                            <?php echo date('d/m', strtotime($task['data_limite'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="item-actions">
                                <button class="btn-icon" onclick="editarTarefa(<?php echo $task['id']; ?>)" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon" onclick="deletarTarefa(<?php echo $task['id']; ?>)" title="Deletar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Subtarefas -->
                        <?php $subs = $subtarefasPorTarefa[$task['id']] ?? []; ?>
                        <?php if (!empty($subs) || true): ?>
                            <div class="subtasks">
                                <div class="subtasks-header" onclick="toggleSubtasks(this)">
                                    <i class="bi bi-chevron-down"></i>
                                    <span>Subtarefas (<?php echo count($subs); ?>)</span>
                                    <button type="button" class="btn-icon" onclick="abrirModalSubtarefa(<?php echo $task['id']; ?>)" title="Adicionar subtarefa" style="margin-left: auto; margin-top: 0;">
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                </div>
                                <div class="subtasks-list">
                                    <?php foreach ($subs as $sub): ?>
                                        <div class="subtask-item">
                                            <input type="checkbox" class="subtask-checkbox" 
                                                   data-id="<?php echo $sub['id']; ?>"
                                                   <?php echo $sub['status'] === 'concluida' ? 'checked' : ''; ?>
                                                   onchange="toggleSubtarefa(<?php echo $sub['id']; ?>)">
                                            <label class="subtask-label <?php echo $sub['status'] === 'concluida' ? 'completed' : ''; ?>">
                                                <?php echo htmlspecialchars($sub['descricao']); ?>
                                            </label>
                                            <button type="button" class="btn-delete-subtask" onclick="deletarSubtarefa(<?php echo $sub['id']; ?>, <?php echo $task['id']; ?>)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Nova Tarefa -->
    <div id="modalTarefa" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-plus-circle"></i> Nova Tarefa</h2>
                <button class="modal-close" onclick="fecharModalTarefa()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="formNovaTarefa">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Descrição da Tarefa</label>
                        <input type="text" name="descricao" class="form-input" placeholder="Ex: Revisar relatório" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Prioridade</label>
                            <select name="prioridade" class="form-input">
                                <option value="Baixa">Baixa</option>
                                <option value="Média" selected>Média</option>
                                <option value="Alta">Alta</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Data Limite (opcional)</label>
                            <input type="date" name="data_limite" class="form-input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalTarefa()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Salvar Tarefa
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nova Subtarefa -->
    <div id="modalSubtarefa" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-plus-circle"></i> Nova Subtarefa</h2>
                <button class="modal-close" onclick="fecharModalSubtarefa()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="formNovaSubtarefa">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Descrição da Subtarefa</label>
                        <input type="text" name="descricao" class="form-input" placeholder="Ex: Passo 1 - Preparar dados" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalSubtarefa()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Salvar Subtarefa
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nova Rotina Fixa -->
    <div id="modalRotina" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-calendar-plus"></i> Nova Rotina Fixa</h2>
                <button class="modal-close" onclick="fecharModalRotina()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="formNovaRotina">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nome da Rotina</label>
                        <input type="text" name="nome" class="form-input" placeholder="Ex: Exercício matinal" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Horário Sugerido (opcional)</label>
                            <input type="time" name="horario" class="form-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descrição (opcional)</label>
                        <textarea name="descricao" class="form-input" rows="3" placeholder="Ex: 30 minutos de musculação no ginásio"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalRotina()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Criar Rotina
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Rotina Fixa -->
    <div id="modalEditarRotina" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-pencil"></i> Editar Rotina Fixa</h2>
                <button class="modal-close" onclick="fecharModalEditarRotina()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="formEditarRotina">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nome da Rotina</label>
                        <input type="text" name="nome" class="form-input" placeholder="Ex: Exercício matinal" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Horário Sugerido (opcional)</label>
                            <input type="time" name="horario" class="form-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descrição (opcional)</label>
                        <textarea name="descricao" class="form-input" rows="3" placeholder="Ex: 30 minutos de musculação no ginásio"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalEditarRotina()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal Tarefa
        function abrirModalTarefa() {
            document.getElementById('modalTarefa').classList.add('active');
        }

        function fecharModalTarefa() {
            document.getElementById('modalTarefa').classList.remove('active');
            document.getElementById('formNovaTarefa').reset();
        }

        document.getElementById('modalTarefa').addEventListener('click', function(e) {
            if (e.target === this) fecharModalTarefa();
        });

        // Submissão de Tarefa
        document.getElementById('formNovaTarefa').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Salvando...';

            fetch('adicionar_tarefa.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Tarefa adicionada!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Salvar Tarefa';
                }
            })
            .catch(error => {
                alert('Erro ao salvar');
                btn.disabled = false;
                btn.textContent = 'Salvar Tarefa';
            });
        });

        // Tarefas
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
            fetch(`obter_tarefa.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const tarefa = data.tarefa;
                        const modalEdit = document.createElement('div');
                        modalEdit.id = 'modalEdit_' + id;
                        modalEdit.className = 'modal-overlay';
                        modalEdit.classList.add('active');
                        modalEdit.innerHTML = `
                            <div class="modal-box">
                                <div class="modal-header">
                                    <h2><i class="bi bi-pencil"></i> Editar Tarefa</h2>
                                    <button class="modal-close" onclick="document.getElementById('modalEdit_${id}').remove()">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                                <form id="formEditarTarefa_${id}">
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Descrição</label>
                                            <input type="text" class="form-input" name="descricao" value="${htmlEscape(tarefa.descricao)}" required>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Prioridade</label>
                                                <select class="form-input" name="prioridade">
                                                    <option value="Baixa" ${tarefa.prioridade === 'Baixa' ? 'selected' : ''}>Baixa</option>
                                                    <option value="Média" ${tarefa.prioridade === 'Média' ? 'selected' : ''}>Média</option>
                                                    <option value="Alta" ${tarefa.prioridade === 'Alta' ? 'selected' : ''}>Alta</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Data Limite</label>
                                                <input type="date" class="form-input" name="data_limite" value="${tarefa.data_limite || ''}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn-cancel" onclick="document.getElementById('modalEdit_${id}').remove()">Cancelar</button>
                                        <button type="submit" class="btn-submit">
                                            <i class="bi bi-save"></i> Salvar Alterações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        `;
                        document.body.appendChild(modalEdit);

                        document.getElementById('formEditarTarefa_' + id).addEventListener('submit', function(e) {
                            e.preventDefault();
                            const formData = new FormData(this);
                            const btn = this.querySelector('button[type="submit"]');
                            btn.disabled = true;
                            btn.textContent = 'Salvando...';

                            fetch('atualizar_tarefa.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    id: id,
                                    descricao: formData.get('descricao'),
                                    prioridade: formData.get('prioridade'),
                                    data_limite: formData.get('data_limite')
                                })
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Tarefa atualizada!');
                                    location.reload();
                                } else {
                                    alert('Erro: ' + data.message);
                                    btn.disabled = false;
                                    btn.textContent = 'Salvar Alterações';
                                }
                            });
                        });
                    }
                });
        }

        function deletarTarefa(id) {
            const modalConfirm = document.createElement('div');
            modalConfirm.id = 'modalConfirm_' + id;
            modalConfirm.className = 'modal-overlay';
            modalConfirm.classList.add('active');
            modalConfirm.innerHTML = `
                <div class="modal-box">
                    <div class="modal-header">
                        <h2><i class="bi bi-exclamation-triangle"></i> Confirmar Exclusão</h2>
                        <button class="modal-close" onclick="document.getElementById('modalConfirm_${id}').remove()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p style="color: var(--text); font-size: 14px;">Tem certeza que deseja excluir esta tarefa? Esta ação não pode ser desfeita.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="document.getElementById('modalConfirm_${id}').remove()">Cancelar</button>
                        <button type="button" class="btn-submit" onclick="confirmarDeletarTarefa(${id})">
                            <i class="bi bi-trash"></i> Deletar
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modalConfirm);
        }

        function confirmarDeletarTarefa(id) {
            fetch('excluir_tarefa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-task-id="${id}"]`);
                    item.style.opacity = '0.6';
                    setTimeout(() => item.remove(), 300);
                    alert('Tarefa excluída com sucesso!');
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .finally(() => {
                const modal = document.getElementById('modalConfirm_' + id);
                if (modal) modal.remove();
            });
        }

        // Rotinas
        function completarRotina(controleId) {
            fetch('processar_rotina_diaria.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `controle_id=${controleId}&status=concluido`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function editarRotina(id) {
            window.location.href = `editar_rotina_fixa.php?id=${id}`;
        }

        function deletarRotina(id) {
            if (confirm('Deletar esta rotina?')) {
                window.location.href = `excluir_rotina_fixa.php?id=${id}`;
            }
        }

        // Subtarefas
        function toggleSubtasks(header) {
            const list = header.closest('.subtasks').querySelector('.subtasks-list');
            const icon = header.querySelector('i');
            if (list.style.display === 'none') {
                list.style.display = 'flex';
                icon.className = 'bi bi-chevron-down';
            } else {
                list.style.display = 'none';
                icon.className = 'bi bi-chevron-right';
            }
        }

        function toggleSubtarefa(id) {
            const checkbox = document.querySelector(`[data-id="${id}"]`);
            const status = checkbox.checked ? 'concluida' : 'pendente';
            const label = checkbox.closest('.subtask-item').querySelector('.subtask-label');

            fetch('atualizar_subtarefa_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, status })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (status === 'concluida') {
                        label.classList.add('completed');
                    } else {
                        label.classList.remove('completed');
                    }
                } else {
                    checkbox.checked = !checkbox.checked;
                    alert('Erro: ' + data.message);
                }
            });
        }

        function deletarSubtarefa(subId, taskId) {
            if (confirm('Deletar esta subtarefa?')) {
                fetch('deletar_subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: subId })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const item = document.querySelector(`[data-id="${subId}"]`).closest('.subtask-item');
                        item.style.opacity = '0.5';
                        setTimeout(() => item.remove(), 200);
                    } else {
                        alert('Erro: ' + data.message);
                    }
                });
            }
        }

        // Subtarefa Modal
        let tarefaIdAtual = null;

        function abrirModalSubtarefa(tarefaId) {
            tarefaIdAtual = tarefaId;
            document.getElementById('modalSubtarefa').classList.add('active');
        }

        function fecharModalSubtarefa() {
            document.getElementById('modalSubtarefa').classList.remove('active');
            document.getElementById('formNovaSubtarefa').reset();
            tarefaIdAtual = null;
        }

        document.getElementById('modalSubtarefa').addEventListener('click', function(e) {
            if (e.target === this) fecharModalSubtarefa();
        });

        // Submissão de Subtarefa
        document.getElementById('formNovaSubtarefa').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!tarefaIdAtual) {
                alert('Erro: ID da tarefa não identificado');
                return;
            }

            const descricao = this.querySelector('input[name="descricao"]').value;
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Salvando...';

            const formData = new FormData();
            formData.append('id_tarefa_principal', tarefaIdAtual);
            formData.append('descricao', descricao);

            fetch('adicionar_subtarefa.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Subtarefa adicionada!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Salvar Subtarefa';
                }
            })
            .catch(error => {
                alert('Erro ao salvar');
                btn.disabled = false;
                btn.textContent = 'Salvar Subtarefa';
            });
        });

        // Rotina Fixa Modal
        function abrirModalRotina() {
            document.getElementById('modalRotina').classList.add('active');
        }

        function fecharModalRotina() {
            document.getElementById('modalRotina').classList.remove('active');
            document.getElementById('formNovaRotina').reset();
        }

        document.getElementById('modalRotina').addEventListener('click', function(e) {
            if (e.target === this) fecharModalRotina();
        });

        document.getElementById('formNovaRotina').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Salvando...';

            fetch('adicionar_rotina_fixa.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Rotina criada com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Criar Rotina';
                }
            })
            .catch(error => {
                alert('Erro ao salvar');
                btn.disabled = false;
                btn.textContent = 'Criar Rotina';
            });
        });

        // Modal Editar Rotina Fixa
        let rotinaEmEdicao = null; // Variável global para rastrear qual rotina está sendo editada
        
        function abrirModalEditarRotina(rotinaId) {
            rotinaEmEdicao = rotinaId; // Guardar ID da rotina que está sendo editada
            
            fetch(`obter_rotina_fixa.php?id=${rotinaId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const rotina = data.rotina;
                        document.getElementById('modalEditarRotina').classList.add('active');
                        
                        // Preencher campos com dados da rotina
                        document.querySelector('#formEditarRotina input[name="nome"]').value = rotina.nome;
                        
                        // Converter 06:00:00 para 06:00 (remover segundos)
                        let horarioFormatado = '';
                        if (rotina.horario_sugerido) {
                            horarioFormatado = rotina.horario_sugerido.substring(0, 5); // Pega apenas HH:mm
                        }
                        document.querySelector('#formEditarRotina input[name="horario"]').value = horarioFormatado;
                        
                        document.querySelector('#formEditarRotina textarea[name="descricao"]').value = rotina.descricao || '';
                        console.log('Carregando rotina ID:', rotinaId); // Debug
                    } else {
                        alert('Erro ao carregar dados da rotina: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Erro ao carregar dados da rotina: ' + error.message);
                });
        }

        function fecharModalEditarRotina() {
            document.getElementById('modalEditarRotina').classList.remove('active');
            document.getElementById('formEditarRotina').reset();
            rotinaEmEdicao = null; // Limpar ID da rotina
        }

        document.getElementById('modalEditarRotina').addEventListener('click', function(e) {
            if (e.target === this) fecharModalEditarRotina();
        });

        document.getElementById('formEditarRotina').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!rotinaEmEdicao) {
                alert('Erro: ID da rotina não identificado.');
                return;
            }

            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Salvando...';

            console.log('Salvando rotina ID:', rotinaEmEdicao); // Debug

            fetch('atualizar_rotina_fixa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: rotinaEmEdicao,
                    nome: formData.get('nome'),
                    horario: formData.get('horario'),
                    descricao: formData.get('descricao')
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Rotina atualizada com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao salvar alterações: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Salvar Alterações';
                }
            })
            .catch(error => {
                alert('Erro ao salvar alterações: ' + error.message);
                btn.disabled = false;
                btn.textContent = 'Salvar Alterações';
            });
        });

        function htmlEscape(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>
</html>
