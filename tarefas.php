<?php
require_once 'templates/header.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit;
}

$dataHoje = date('Y-m-d');

// Buscar rotinas fixas de hoje
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
    $rotinas = [];
    error_log("Erro ao buscar rotinas: " . $e->getMessage());
}

// Buscar tarefas pendentes
try {
    $stmt = $pdo->prepare("
        SELECT id, descricao, prioridade, data_limite, status 
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

// Buscar subtarefas
$subtarefasPorTarefa = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, id_tarefa_principal, descricao, status 
        FROM subtarefas 
        WHERE id_tarefa_principal IN (
            SELECT id FROM tarefas WHERE id_usuario = ? AND status = 'pendente'
        )
        ORDER BY id ASC
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

// Contar rotinas
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
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .subtasks-header {
    display: flex;
    align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: var(--text-muted);
            font-size: 12px;
            cursor: pointer;
        }

        .subtasks-header:hover {
            color: var(--text);
        }

        .subtasks-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .subtask-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 6px;
            font-size: 13px;
        }

        .subtask-checkbox {
            width: 16px;
            height: 16px;
            min-width: 16px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .subtask-label {
            flex: 1;
            cursor: pointer;
            user-select: none;
        }

        .subtask-label.completed {
            text-decoration: line-through;
            color: var(--text-muted);
        }

        .btn-delete-subtask {
            background: none;
            border: none;
    color: var(--text-muted);
            cursor: pointer;
            padding: 2px 4px;
            font-size: 12px;
            transition: color 0.2s;
        }

        .btn-delete-subtask:hover {
            color: #ff6b6b;
        }

        /* Modal Minimalista */
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

        .modal-header i {
            color: var(--primary);
            font-size: 24px;
            cursor: pointer;
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
            </div>
    </div>

        <!-- Rotinas Fixas Diárias -->
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
                                 <button class="btn-icon" onclick="editarRotina(<?php echo $rotina['id']; ?>)" title="Editar">
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

         <!-- Tarefas Pendentes -->
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
                                 <div class="item-title"><?php echo htmlspecialchars($task['descricao'] ?? $task['titulo'] ?? ''); ?></div>
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
                                     <?php if ($task['descricao']): ?>
                                         <span title="<?php echo htmlspecialchars($task['descricao']); ?>">
                                             <i class="bi bi-file-text"></i>
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
                         <?php if (!empty($subs)): ?>
                             <div class="subtasks">
                                 <div class="subtasks-header" onclick="toggleSubtasks(this)">
                                     <i class="bi bi-chevron-down"></i>
                                     <span>Subtarefas (<?php echo count($subs); ?>)</span>
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

<script>
        // Debug
        console.log('Script iniciado');

        function abrirModalTarefa() {
            console.log('abrirModalTarefa chamado');
            const modal = document.getElementById('modalTarefa');
            if (modal) {
                modal.classList.add('active');
                console.log('Modal exibido');
            } else {
                console.log('Modal não encontrado');
            }
        }

        function fecharModalTarefa() {
            console.log('fecharModalTarefa chamado');
            const modal = document.getElementById('modalTarefa');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        // Fechar modal ao clicar fora
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('modalTarefa');
            if (modal && event.target === modal) {
                fecharModalTarefa();
            }
        });

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
            // Buscar dados da tarefa
            fetch(`obter_tarefa.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const tarefa = data.tarefa;
                        // Criar modal de edição
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

                        // Submit do formulário
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
                                    btn.innerHTML = '<i class="bi bi-save"></i> Salvar Alterações';
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                alert('Erro ao salvar');
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-save"></i> Salvar Alterações';
                            });
                        });
                    } else {
                        alert('Erro ao carregar tarefa: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao carregar tarefa');
                });
        }

        // Função auxiliar para escapar HTML
        function htmlEscape(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function editarRotina(id) {
            window.location.href = `editar_rotina_fixa.php?id=${id}`;
        }

        function deletarTarefa(id) {
            // Criar modal de confirmação customizado
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
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir tarefa');
            })
            .finally(() => {
                const modal = document.getElementById('modalConfirm_' + id);
                if (modal) modal.remove();
            });
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
            })
            .catch(error => {
                console.error('Erro:', error);
                checkbox.checked = !checkbox.checked;
                alert('Erro ao atualizar subtarefa');
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
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao deletar subtarefa');
                });
            }
        }

        function deletarRotina(id) {
            if (confirm('Tem certeza?')) {
                window.location.href = `excluir_rotina_fixa.php?id=${id}`;
            }
        }

        // Form submit
        const form = document.getElementById('formNovaTarefa');
        if (form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                console.log('Form submit chamado');
                
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
                    console.log('Resposta:', data);
                    if (data.success) {
                        alert('Tarefa adicionada com sucesso!');
                        location.reload();
			} else {
                        alert('Erro: ' + (data.message || 'Erro desconhecido'));
				btn.disabled = false;
                        btn.textContent = 'Salvar Tarefa';
			}
		})
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao adicionar tarefa: ' + error);
			btn.disabled = false;
                    btn.textContent = 'Salvar Tarefa';
		});
	});
			} else {
            console.log('Formulário não encontrado');
        }
</script>
</body>
</html>
