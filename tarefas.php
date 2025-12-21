<?php
// tarefas.php - Redesign Premium
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

$tarefas_pendentes = [];
$tarefas_concluidas = [];

try {
    $sql_pendentes = "SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'pendente' ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC";
    $stmt_pendentes = $pdo->prepare($sql_pendentes);
    $stmt_pendentes->execute([$userId]);
    $tarefas_pendentes = $stmt_pendentes->fetchAll(PDO::FETCH_ASSOC);

    $sql_concluidas = "SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'concluida' ORDER BY data_criacao DESC LIMIT 10";
    $stmt_concluidas = $pdo->prepare($sql_concluidas);
    $stmt_concluidas->execute([$userId]);
    $tarefas_concluidas = $stmt_concluidas->fetchAll(PDO::FETCH_ASSOC);

    $todos_ids = array_merge(array_column($tarefas_pendentes, 'id'), array_column($tarefas_concluidas, 'id'));
    if (!empty($todos_ids)) {
        $placeholders = implode(',', array_fill(0, count($todos_ids), '?'));
        $sql_subtarefas = "SELECT * FROM subtarefas WHERE id_tarefa_principal IN ($placeholders)";
        $stmt_subtarefas = $pdo->prepare($sql_subtarefas);
        $stmt_subtarefas->execute($todos_ids);
        $todas_as_subtarefas = $stmt_subtarefas->fetchAll(PDO::FETCH_ASSOC);
        $subtarefas_mapeadas = [];
        foreach ($todas_as_subtarefas as $subtarefa) {
            $subtarefas_mapeadas[$subtarefa['id_tarefa_principal']][] = $subtarefa;
        }
        foreach ($tarefas_pendentes as $key => $tarefa) {
            $tarefas_pendentes[$key]['subtarefas'] = $subtarefas_mapeadas[$tarefa['id']] ?? [];
        }
        foreach ($tarefas_concluidas as $key => $tarefa) {
            $tarefas_concluidas[$key]['subtarefas'] = $subtarefas_mapeadas[$tarefa['id']] ?? [];
        }
    }
} catch (PDOException $e) {
    die("Erro ao buscar tarefas: " . $e->getMessage());
}

// Estatísticas
$totalPendentes = count($tarefas_pendentes);
$totalConcluidas = count($tarefas_concluidas);
$altaPrioridade = count(array_filter($tarefas_pendentes, fn($t) => $t['prioridade'] === 'Alta'));
$comDataLimite = count(array_filter($tarefas_pendentes, fn($t) => !empty($t['data_limite'])));

function getPrioridadeInfo($prioridade) {
    switch ($prioridade) {
        case 'Alta': return ['class' => 'priority-high', 'icon' => 'fire', 'color' => '#ef4444'];
        case 'Média': return ['class' => 'priority-medium', 'icon' => 'dash-circle', 'color' => '#f59e0b'];
        case 'Baixa': return ['class' => 'priority-low', 'icon' => 'arrow-down-circle', 'color' => '#22c55e'];
        default: return ['class' => 'priority-default', 'icon' => 'circle', 'color' => '#6b7280'];
    }
}
?>

<style>
/* ================================================== */
/* TAREFAS - DESIGN PREMIUM */
/* ================================================== */

@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
    --task-primary: #6366f1;
    --task-primary-dark: #4f46e5;
    --task-danger: #ef4444;
    --task-success: #22c55e;
    --task-warning: #f59e0b;
    --task-bg: #0a0a0b;
    --task-card: #141416;
    --task-card-hover: #1c1c1f;
    --task-border: rgba(255, 255, 255, 0.08);
    --task-text: #f5f5f5;
    --task-text-muted: #71717a;
}

.tasks-page {
    font-family: 'Space Grotesk', sans-serif;
    background: var(--task-bg);
    min-height: 100vh;
    padding-bottom: 2rem;
}

/* Header */
.tasks-header {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.tasks-header::before {
    content: '';
    position: absolute;
    top: -100px;
    right: -100px;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, transparent 70%);
    pointer-events: none;
}

.tasks-title {
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.tasks-title-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--task-primary), #8b5cf6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.tasks-subtitle {
    color: rgba(255, 255, 255, 0.6);
    margin: 0.5rem 0 0;
    font-size: 1rem;
}

.btn-new-task {
    padding: 0.875rem 1.75rem;
    border-radius: 14px;
    font-weight: 600;
    background: linear-gradient(135deg, var(--task-primary), #8b5cf6);
    color: #fff;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
}

.btn-new-task:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(99, 102, 241, 0.5);
    color: #fff;
}

/* Stats */
.tasks-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--task-card);
    border-radius: 16px;
    padding: 1.25rem;
    text-align: center;
    border: 1px solid var(--task-border);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    border-color: var(--task-primary);
}

.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
    font-size: 1.25rem;
}

.stat-icon.pending { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.stat-icon.completed { background: linear-gradient(135deg, #22c55e, #16a34a); }
.stat-icon.urgent { background: linear-gradient(135deg, #ef4444, #dc2626); }
.stat-icon.scheduled { background: linear-gradient(135deg, #f59e0b, #d97706); }

.stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
}

.stat-label {
    color: var(--task-text-muted);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

/* Sections */
.tasks-section {
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--task-text);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title .badge {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.75rem;
    padding: 0.35em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

/* Task Cards */
.task-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.task-card {
    background: var(--task-card);
    border-radius: 16px;
    border: 1px solid var(--task-border);
    overflow: hidden;
    transition: all 0.25s ease;
}

.task-card:hover {
    border-color: rgba(99, 102, 241, 0.4);
    transform: translateX(4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}

.task-card.priority-high { border-left: 4px solid var(--task-danger); }
.task-card.priority-medium { border-left: 4px solid var(--task-warning); }
.task-card.priority-low { border-left: 4px solid var(--task-success); }

.task-main {
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.task-handle {
    cursor: grab;
    color: var(--task-text-muted);
    font-size: 1.25rem;
    padding: 0.5rem;
    transition: color 0.2s;
}

.task-handle:hover { color: var(--task-text); }
.task-handle:active { cursor: grabbing; }

.task-content {
    flex-grow: 1;
    min-width: 0;
}

.task-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--task-text);
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.task-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: var(--task-text-muted);
    padding: 0.25rem 0.6rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
}

.task-tag.priority-high { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
.task-tag.priority-medium { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
.task-tag.priority-low { background: rgba(34, 197, 94, 0.15); color: #86efac; }

.task-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-task-action {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--task-border);
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--task-text-muted);
}

.btn-task-action:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--task-text);
}

.btn-task-action.complete:hover { background: rgba(34, 197, 94, 0.2); color: var(--task-success); border-color: var(--task-success); }
.btn-task-action.edit:hover { background: rgba(99, 102, 241, 0.2); color: var(--task-primary); border-color: var(--task-primary); }
.btn-task-action.delete:hover { background: rgba(239, 68, 68, 0.2); color: var(--task-danger); border-color: var(--task-danger); }
.btn-task-action.restore:hover { background: rgba(245, 158, 11, 0.2); color: var(--task-warning); border-color: var(--task-warning); }

/* Subtasks */
.task-details {
    border-top: 1px solid var(--task-border);
    padding: 1rem 1.25rem;
    background: rgba(0, 0, 0, 0.2);
}

.subtask-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.subtask-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
    transition: all 0.2s;
}

.subtask-item:hover {
    background: rgba(255, 255, 255, 0.06);
}

.subtask-check {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-grow: 1;
}

.subtask-check input[type="checkbox"] {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    accent-color: var(--task-primary);
}

.subtask-check label {
    color: var(--task-text);
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
}

.subtask-check label.completed {
    text-decoration: line-through;
    color: var(--task-text-muted);
}

.subtask-actions {
    display: flex;
    gap: 0.35rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.subtask-item:hover .subtask-actions {
    opacity: 1;
}

.btn-subtask {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: var(--task-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8rem;
}

.btn-subtask:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--task-text);
}

.btn-subtask.delete:hover {
    background: rgba(239, 68, 68, 0.2);
    color: var(--task-danger);
}

.add-subtask-form {
    display: flex;
    gap: 0.5rem;
}

.add-subtask-form input {
    flex-grow: 1;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--task-border);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    color: var(--task-text);
    font-size: 0.875rem;
}

.add-subtask-form input:focus {
    outline: none;
    border-color: var(--task-primary);
}

.add-subtask-form button {
    padding: 0.5rem 1rem;
    background: var(--task-primary);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
}

/* Toggle Button */
.btn-toggle-details {
    background: transparent;
    border: none;
    color: var(--task-text-muted);
    font-size: 0.8rem;
    padding: 0.5rem 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: color 0.2s;
}

.btn-toggle-details:hover { color: var(--task-text); }
.btn-toggle-details i { transition: transform 0.2s; }
.btn-toggle-details[aria-expanded="true"] i { transform: rotate(180deg); }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--task-card);
    border-radius: 16px;
    border: 1px dashed var(--task-border);
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

.empty-state-title {
    color: var(--task-text);
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.empty-state-text {
    color: var(--task-text-muted);
    font-size: 0.9rem;
}

/* Completed Task */
.task-card.completed {
    opacity: 0.6;
}

.task-card.completed .task-title {
    text-decoration: line-through;
    color: var(--task-text-muted);
}

/* Sortable States */
.sortable-ghost { opacity: 0.4; transform: scale(0.98); }
.sortable-chosen { box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4); transform: scale(1.02); z-index: 100; }
.sortable-drag { opacity: 0.9; }

/* Modal Styles */
.modal-tasks .modal-content {
    background: var(--task-card);
    border: 1px solid var(--task-border);
    border-radius: 20px;
}

.modal-tasks .modal-header {
    background: linear-gradient(135deg, var(--task-primary), #8b5cf6);
    border-radius: 20px 20px 0 0;
    border: none;
    padding: 1.5rem;
}

.modal-tasks .modal-title { color: #fff; font-weight: 700; }

.modal-tasks .modal-body { padding: 1.5rem; }

.modal-tasks .form-control,
.modal-tasks .form-select {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--task-border);
    color: var(--task-text);
    border-radius: 10px;
    padding: 0.75rem 1rem;
}

.modal-tasks .form-control:focus,
.modal-tasks .form-select:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--task-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    color: var(--task-text);
}

.modal-tasks .form-label { color: var(--task-text); font-weight: 600; }
.modal-tasks .btn-close { filter: invert(1); }

/* Responsive */
@media (max-width: 992px) {
    .tasks-stats { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .tasks-header { padding: 1.5rem; }
    .tasks-title { font-size: 1.5rem; }
    .tasks-title-icon { width: 48px; height: 48px; font-size: 1.25rem; }
    
    .task-main {
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    
    .task-handle { display: none; }
    
    .task-actions {
        width: 100%;
        justify-content: flex-end;
        padding-top: 0.5rem;
        border-top: 1px solid var(--task-border);
        margin-top: 0.5rem;
    }
    
    .subtask-actions { opacity: 1; }
}

@media (max-width: 576px) {
    .tasks-stats { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .stat-card { padding: 1rem; }
    .stat-value { font-size: 1.5rem; }
    
    .btn-new-task {
        width: 100%;
        justify-content: center;
        margin-top: 1rem;
    }
}
</style>

<div class="tasks-page">
    <!-- Header -->
    <div class="tasks-header" data-aos="fade-down">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h1 class="tasks-title">
                    <span class="tasks-title-icon">✓</span>
                    Minhas Tarefas
                </h1>
                <p class="tasks-subtitle">Organize suas atividades e aumente sua produtividade</p>
            </div>
            <button class="btn-new-task" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                <i class="bi bi-plus-lg"></i>
                Nova Tarefa
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="tasks-stats" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-card">
            <div class="stat-icon pending"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value"><?php echo $totalPendentes; ?></div>
            <div class="stat-label">Pendentes</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon completed"><i class="bi bi-check-circle"></i></div>
            <div class="stat-value"><?php echo $totalConcluidas; ?></div>
            <div class="stat-label">Concluídas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon urgent"><i class="bi bi-fire"></i></div>
            <div class="stat-value"><?php echo $altaPrioridade; ?></div>
            <div class="stat-label">Urgentes</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon scheduled"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-value"><?php echo $comDataLimite; ?></div>
            <div class="stat-label">Agendadas</div>
        </div>
    </div>

    <!-- Pending Tasks -->
    <div class="tasks-section" data-aos="fade-up" data-aos-delay="200">
        <div class="section-header">
            <h2 class="section-title">
                <i class="bi bi-list-task"></i>
                Pendentes
                <span class="badge bg-primary"><?php echo $totalPendentes; ?></span>
            </h2>
        </div>

        <div id="lista-tarefas-pendentes" class="task-list">
            <?php if (empty($tarefas_pendentes)): ?>
                <div class="empty-state" id="empty-state-pendentes">
                    <div class="empty-state-icon">🎉</div>
                    <div class="empty-state-title">Tudo em dia!</div>
                    <div class="empty-state-text">Você não tem tarefas pendentes. Aproveite!</div>
                </div>
            <?php else: ?>
                <?php foreach ($tarefas_pendentes as $tarefa): 
                    $prioInfo = getPrioridadeInfo($tarefa['prioridade']);
                ?>
                <div class="task-card <?php echo $prioInfo['class']; ?>" data-id="<?php echo $tarefa['id']; ?>">
                    <div class="task-main">
                        <div class="task-handle handle">
                            <i class="bi bi-grip-vertical"></i>
                        </div>
                        <div class="task-content">
                            <h3 class="task-title"><?php echo htmlspecialchars($tarefa['descricao']); ?></h3>
                            <div class="task-meta">
                                <span class="task-tag <?php echo $prioInfo['class']; ?>">
                                    <i class="bi bi-<?php echo $prioInfo['icon']; ?>"></i>
                                    <?php echo $tarefa['prioridade']; ?>
                                </span>
                                <?php if ($tarefa['data_limite']): ?>
                                <span class="task-tag">
                                    <i class="bi bi-calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($tarefa['data_limite'])); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($tarefa['hora_inicio'] || $tarefa['hora_fim']): ?>
                                <span class="task-tag">
                                    <i class="bi bi-clock"></i>
                                    <?php
                                        $inicio = $tarefa['hora_inicio'] ? date('H:i', strtotime($tarefa['hora_inicio'])) : '--:--';
                                        $fim = $tarefa['hora_fim'] ? date('H:i', strtotime($tarefa['hora_fim'])) : '--:--';
                                        echo "{$inicio} - {$fim}";
                                    ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($tarefa['subtarefas'])): ?>
                                <span class="task-tag">
                                    <i class="bi bi-list-check"></i>
                                    <?php 
                                        $subConcluidas = count(array_filter($tarefa['subtarefas'], fn($s) => $s['status'] === 'concluida'));
                                        echo $subConcluidas . '/' . count($tarefa['subtarefas']);
                                    ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="task-actions">
                            <button class="btn-task-action complete btn-atualizar-status" data-id="<?php echo $tarefa['id']; ?>" data-status="concluida" title="Concluir">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button class="btn-task-action edit btn-editar-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-bs-toggle="modal" data-bs-target="#modalEditarTarefa" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-task-action delete btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-nome="<?php echo htmlspecialchars($tarefa['descricao']); ?>" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="px-3 pb-2">
                        <button class="btn-toggle-details" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?php echo $tarefa['id']; ?>">
                            <i class="bi bi-chevron-down"></i>
                            Detalhes e Subtarefas
                        </button>
                    </div>
                    
                    <div class="collapse" id="details-<?php echo $tarefa['id']; ?>">
                        <div class="task-details">
                            <?php if ($tarefa['tempo_estimado'] > 0): 
                                $h = floor($tarefa['tempo_estimado'] / 60);
                                $m = $tarefa['tempo_estimado'] % 60;
                                $tf = '';
                                if ($h > 0) $tf .= $h . 'h ';
                                if ($m > 0) $tf .= $m . 'min';
                            ?>
                            <p style="color: var(--task-text-muted); margin-bottom: 1rem;">
                                <i class="bi bi-stopwatch me-2"></i>Tempo estimado: <strong><?php echo trim($tf); ?></strong>
                            </p>
                            <?php endif; ?>
                            
                            <div class="subtask-list">
                                <?php if (!empty($tarefa['subtarefas'])): ?>
                                    <?php foreach ($tarefa['subtarefas'] as $sub): ?>
                                    <div class="subtask-item" id="subtask-item-<?php echo $sub['id']; ?>">
                                        <div class="subtask-check">
                                            <input type="checkbox" class="subtask-checkbox" data-id="<?php echo $sub['id']; ?>" id="sub-<?php echo $sub['id']; ?>" <?php echo ($sub['status'] == 'concluida') ? 'checked' : ''; ?>>
                                            <label for="sub-<?php echo $sub['id']; ?>" class="<?php echo ($sub['status'] == 'concluida') ? 'completed' : ''; ?>">
                                                <?php echo htmlspecialchars($sub['descricao']); ?>
                                            </label>
                                        </div>
                                        <div class="subtask-actions">
                                            <button class="btn-subtask btn-editar-subtarefa" data-id="<?php echo $sub['id']; ?>" data-descricao="<?php echo htmlspecialchars($sub['descricao']); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn-subtask delete btn-excluir-subtarefa" data-id="<?php echo $sub['id']; ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <form class="add-subtask-form form-add-subtask" action="adicionar_subtarefa.php" method="POST">
                                <input type="hidden" name="id_tarefa_principal" value="<?php echo $tarefa['id']; ?>">
                                <input type="text" name="descricao" placeholder="Adicionar subtarefa..." required>
                                <button type="submit"><i class="bi bi-plus"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Completed Tasks -->
    <div class="tasks-section" data-aos="fade-up" data-aos-delay="300">
        <div class="section-header">
            <h2 class="section-title">
                <i class="bi bi-check2-all"></i>
                Concluídas
                <span class="badge bg-success"><?php echo $totalConcluidas; ?></span>
            </h2>
        </div>

        <div id="lista-tarefas-concluidas" class="task-list">
            <?php if (empty($tarefas_concluidas)): ?>
                <div class="empty-state" id="empty-state-concluidas">
                    <div class="empty-state-icon">📋</div>
                    <div class="empty-state-title">Nenhuma tarefa concluída</div>
                    <div class="empty-state-text">Complete suas tarefas pendentes para vê-las aqui.</div>
                </div>
            <?php else: ?>
                <?php foreach ($tarefas_concluidas as $tarefa): ?>
                <div class="task-card completed" id="task-card-<?php echo $tarefa['id']; ?>">
                    <div class="task-main">
                        <div class="task-content">
                            <h3 class="task-title"><?php echo htmlspecialchars($tarefa['descricao']); ?></h3>
                        </div>
                        <div class="task-actions">
                            <button class="btn-task-action restore btn-atualizar-status" data-id="<?php echo $tarefa['id']; ?>" data-status="pendente" title="Restaurar">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                            <button class="btn-task-action delete btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-nome="<?php echo htmlspecialchars($tarefa['descricao']); ?>" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Nova Tarefa -->
<div class="modal fade modal-tasks" id="modalNovaTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nova Tarefa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaTarefa" action="adicionar_tarefa.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control" placeholder="O que você precisa fazer?" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="Baixa">🟢 Baixa</option>
                                <option value="Média" selected>🟡 Média</option>
                                <option value="Alta">🔴 Alta</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Data Limite</label>
                            <input type="date" name="data_limite" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Hora Início</label>
                            <input type="time" name="hora_inicio" class="form-control">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Hora Fim</label>
                            <input type="time" name="hora_fim" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tempo Estimado</label>
                        <div class="d-flex gap-2">
                            <div class="input-group">
                                <input type="number" name="tempo_horas" class="form-control" min="0" placeholder="0">
                                <span class="input-group-text">h</span>
                            </div>
                            <div class="input-group">
                                <input type="number" name="tempo_minutos" class="form-control" min="0" max="59" placeholder="0">
                                <span class="input-group-text">min</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-new-task">
                        <i class="bi bi-check-lg"></i> Criar Tarefa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Tarefa -->
<div class="modal fade modal-tasks" id="modalEditarTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Tarefa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTarefa" action="atualizar_tarefa.php" method="POST">
                <div class="modal-body" id="corpoModalEditar">
                    <div class="text-center p-5">
                        <div class="spinner-border" style="color: var(--task-primary);"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-new-task">
                        <i class="bi bi-check-lg"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, tag => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[tag] || tag);
}

document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });

    const formNovaTarefa = document.getElementById('formNovaTarefa');
    const formEditarTarefa = document.getElementById('formEditarTarefa');
    const modalEditarTarefaEl = document.getElementById('modalEditarTarefa');
    const corpoModalEditar = document.getElementById('corpoModalEditar');

    // Nova Tarefa
    if (formNovaTarefa) {
        formNovaTarefa.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(formNovaTarefa);
            const button = formNovaTarefa.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            
            fetch('adicionar_tarefa.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-check-lg"></i> Criar Tarefa';
                }
            })
            .catch(() => {
                showToast('Erro!', 'Erro de conexão.', true);
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check-lg"></i> Criar Tarefa';
            });
        });
    }

    // Modal Editar
    if (modalEditarTarefaEl) {
        modalEditarTarefaEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const tarefaId = button ? button.dataset.id : null;
            corpoModalEditar.innerHTML = '<div class="text-center p-5"><div class="spinner-border" style="color: var(--task-primary);"></div></div>';

            if (!tarefaId) {
                corpoModalEditar.innerHTML = '<p class="text-danger">ID não encontrado!</p>';
                return;
            }

            fetch(`buscar_tarefa_detalhes.php?id=${tarefaId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const t = data.tarefa;
                    const totalM = parseInt(t.tempo_estimado) || 0;
                    const h = Math.floor(totalM / 60);
                    const m = totalM % 60;

                    corpoModalEditar.innerHTML = `
                        <input type="hidden" name="id" value="${t.id}">
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" value="${escapeHTML(t.descricao)}" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Prioridade</label>
                                <select name="prioridade" class="form-select">
                                    <option value="Baixa" ${t.prioridade==='Baixa'?'selected':''}>🟢 Baixa</option>
                                    <option value="Média" ${t.prioridade==='Média'?'selected':''}>🟡 Média</option>
                                    <option value="Alta" ${t.prioridade==='Alta'?'selected':''}>🔴 Alta</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Data Limite</label>
                                <input type="date" name="data_limite" class="form-control" value="${t.data_limite ?? ''}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Hora Início</label>
                                <input type="time" name="hora_inicio" class="form-control" value="${t.hora_inicio ?? ''}">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Hora Fim</label>
                                <input type="time" name="hora_fim" class="form-control" value="${t.hora_fim ?? ''}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tempo Estimado</label>
                            <div class="d-flex gap-2">
                                <div class="input-group">
                                    <input type="number" name="tempo_horas" class="form-control" min="0" value="${h}">
                                    <span class="input-group-text">h</span>
                                </div>
                                <div class="input-group">
                                    <input type="number" name="tempo_minutos" class="form-control" min="0" max="59" value="${m}">
                                    <span class="input-group-text">min</span>
                                </div>
                            </div>
                        </div>`;
                } else {
                    corpoModalEditar.innerHTML = `<p class="text-danger">${data.message}</p>`;
                }
            });
        });
    }

    // Editar Tarefa Submit
    if (formEditarTarefa) {
        formEditarTarefa.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(formEditarTarefa);
            const button = formEditarTarefa.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            fetch('atualizar_tarefa.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check-lg"></i> Salvar';
                bootstrap.Modal.getInstance(modalEditarTarefaEl).hide();
            });
        });
    }

    // Click handlers
    document.body.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.btn-excluir-tarefa');
        const statusBtn = e.target.closest('.btn-atualizar-status');
        const deleteSubBtn = e.target.closest('.btn-excluir-subtarefa');
        const editSubBtn = e.target.closest('.btn-editar-subtarefa');

        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            const nome = deleteBtn.dataset.nome;
            Swal.fire({
                title: 'Excluir tarefa?',
                text: `"${nome}"`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar',
                background: '#1f1f23',
                color: '#fff'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('excluir_tarefa.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id})
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Sucesso!', data.message);
                            const card = document.querySelector(`.task-card[data-id='${id}']`);
                            if (card) {
                                card.style.transition = 'all 0.3s ease';
                                card.style.opacity = '0';
                                card.style.transform = 'translateX(-20px)';
                                setTimeout(() => card.remove(), 300);
                            }
                        } else {
                            showToast('Erro!', data.message, true);
                        }
                    });
                }
            });
        }

        if (statusBtn) {
            const id = statusBtn.dataset.id;
            const status = statusBtn.dataset.status;
            const card = statusBtn.closest('.task-card');
            
            fetch('atualizar_status_tarefa.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id, status})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast('Erro!', data.message, true);
                }
            });
        }

        if (deleteSubBtn) {
            const id = deleteSubBtn.dataset.id;
            const item = document.getElementById(`subtask-item-${id}`);
            
            Swal.fire({
                title: 'Excluir subtarefa?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sim',
                cancelButtonText: 'Não',
                background: '#1f1f23',
                color: '#fff'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('excluir_subtarefa.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id})
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Sucesso!', data.message);
                            if (item) {
                                item.style.transition = 'all 0.3s ease';
                                item.style.opacity = '0';
                                setTimeout(() => item.remove(), 300);
                            }
                        } else {
                            showToast('Erro!', data.message, true);
                        }
                    });
                }
            });
        }

        if (editSubBtn) {
            const id = editSubBtn.dataset.id;
            const desc = editSubBtn.dataset.descricao;
            
            Swal.fire({
                title: 'Editar Subtarefa',
                input: 'text',
                inputValue: desc,
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                background: '#1f1f23',
                color: '#fff',
                inputValidator: value => !value && 'Digite algo!'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('atualizar_subtarefa.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id, descricao: result.value})
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Sucesso!', data.message);
                            const label = document.querySelector(`#subtask-item-${id} label`);
                            const btn = document.querySelector(`.btn-editar-subtarefa[data-id='${id}']`);
                            if (label) label.textContent = result.value;
                            if (btn) btn.dataset.descricao = result.value;
                        } else {
                            showToast('Erro!', data.message, true);
                        }
                    });
                }
            });
        }
    });

    // Subtask checkbox
    document.body.addEventListener('change', function(e) {
        if (e.target.classList.contains('subtask-checkbox')) {
            const checkbox = e.target;
            const id = checkbox.dataset.id;
            const isChecked = checkbox.checked;
            const status = isChecked ? 'concluida' : 'pendente';
            const label = checkbox.nextElementSibling;
            
            fetch('atualizar_status_subtarefa.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id, status})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    label.classList.toggle('completed', isChecked);
                } else {
                    showToast('Erro!', data.message, true);
                    checkbox.checked = !isChecked;
                }
            });
        }
    });

    // Add subtask
    document.body.addEventListener('submit', function(e) {
        if (e.target.classList.contains('form-add-subtask')) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            fetch('adicionar_subtarefa.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    const list = form.previousElementSibling;
                    const newEl = document.createElement('div');
                    newEl.className = 'subtask-item';
                    newEl.id = `subtask-item-${data.subtarefa.id}`;
                    newEl.innerHTML = `
                        <div class="subtask-check">
                            <input type="checkbox" class="subtask-checkbox" data-id="${data.subtarefa.id}" id="sub-${data.subtarefa.id}">
                            <label for="sub-${data.subtarefa.id}">${escapeHTML(data.subtarefa.descricao)}</label>
                        </div>
                        <div class="subtask-actions">
                            <button class="btn-subtask btn-editar-subtarefa" data-id="${data.subtarefa.id}" data-descricao="${escapeHTML(data.subtarefa.descricao)}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-subtask delete btn-excluir-subtarefa" data-id="${data.subtarefa.id}">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>`;
                    list.appendChild(newEl);
                    newEl.style.opacity = '0';
                    newEl.style.transform = 'translateX(-10px)';
                    setTimeout(() => {
                        newEl.style.transition = 'all 0.3s ease';
                        newEl.style.opacity = '1';
                        newEl.style.transform = 'translateX(0)';
                    }, 10);
                    form.reset();
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-plus"></i>';
            });
        }
    });

    // Sortable
    const listaTarefas = document.getElementById('lista-tarefas-pendentes');
    if (listaTarefas && listaTarefas.querySelector('.task-card')) {
        new Sortable(listaTarefas, {
            animation: 200,
            handle: '.task-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            easing: 'cubic-bezier(.2,.8,.2,1)',
            onEnd: function() {
                const items = listaTarefas.querySelectorAll('.task-card');
                const ordem = Array.from(items).map(item => item.dataset.id);
                fetch('atualizar_ordem_tarefas.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ordem})
                });
            }
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
