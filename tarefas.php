<?php
// tarefas.php - Design Ultra Moderno
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
$percentualConcluido = ($totalPendentes + $totalConcluidas) > 0 ? round(($totalConcluidas / ($totalPendentes + $totalConcluidas)) * 100) : 0;

function getPrioridadeInfo($prioridade) {
    switch ($prioridade) {
        case 'Alta': return ['class' => 'priority-high', 'icon' => 'fire', 'color' => '#ef4444', 'gradient' => 'linear-gradient(135deg, #ef4444, #f97316)'];
        case 'Média': return ['class' => 'priority-medium', 'icon' => 'dash-circle', 'color' => '#f59e0b', 'gradient' => 'linear-gradient(135deg, #f59e0b, #eab308)'];
        case 'Baixa': return ['class' => 'priority-low', 'icon' => 'arrow-down-circle', 'color' => '#22c55e', 'gradient' => 'linear-gradient(135deg, #22c55e, #10b981)'];
        default: return ['class' => 'priority-default', 'icon' => 'circle', 'color' => '#6b7280', 'gradient' => 'linear-gradient(135deg, #6b7280, #9ca3af)'];
    }
}
?>

<style>
/* ================================================== */
/* TAREFAS - DESIGN ULTRA MODERNO */
/* ================================================== */

@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap');

* {
    font-family: 'Outfit', sans-serif;
}

:root {
    --t-bg: #09090b;
    --t-surface: #18181b;
    --t-surface-2: #1f1f23;
    --t-surface-3: #27272a;
    --t-border: rgba(255, 255, 255, 0.08);
    --t-border-hover: rgba(255, 255, 255, 0.15);
    --t-text: #fafafa;
    --t-text-secondary: #a1a1aa;
    --t-text-muted: #71717a;
    --t-primary: #8b5cf6;
    --t-primary-glow: rgba(139, 92, 246, 0.4);
    --t-danger: #ef4444;
    --t-success: #22c55e;
    --t-warning: #f59e0b;
    --t-info: #3b82f6;
}

.tasks-container {
    min-height: 100vh;
    background: var(--t-bg);
    position: relative;
    overflow-x: hidden;
}

/* Animated Background */
.tasks-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    z-index: 0;
    overflow: hidden;
}

.tasks-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: 
        radial-gradient(circle at 20% 20%, rgba(139, 92, 246, 0.08) 0%, transparent 40%),
        radial-gradient(circle at 80% 80%, rgba(236, 72, 153, 0.06) 0%, transparent 40%),
        radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.04) 0%, transparent 50%);
    animation: bgFloat 20s ease-in-out infinite;
}

@keyframes bgFloat {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    33% { transform: translate(-20px, 20px) rotate(2deg); }
    66% { transform: translate(20px, -20px) rotate(-2deg); }
}

.tasks-content {
    position: relative;
    z-index: 1;
    padding-bottom: 3rem;
}

/* ==================== HERO SECTION ==================== */
.tasks-hero {
    background: linear-gradient(135deg, 
        rgba(139, 92, 246, 0.15) 0%, 
        rgba(236, 72, 153, 0.1) 50%, 
        rgba(59, 130, 246, 0.1) 100%);
    border: 1px solid var(--t-border);
    border-radius: 28px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(20px);
}

.tasks-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.5), transparent);
}

.tasks-hero::after {
    content: '';
    position: absolute;
    top: -100px;
    right: -100px;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(139, 92, 246, 0.2);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 100px;
    font-size: 0.8rem;
    font-weight: 500;
    color: #c4b5fd;
    margin-bottom: 1rem;
}

.hero-badge i {
    font-size: 0.9rem;
}

.hero-title {
    font-size: 2.75rem;
    font-weight: 800;
    color: var(--t-text);
    margin: 0 0 0.5rem;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.hero-title span {
    background: linear-gradient(135deg, #8b5cf6, #ec4899, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 1.1rem;
    color: var(--t-text-secondary);
    margin: 0;
    max-width: 500px;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn-hero-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: #fff;
    border: none;
    border-radius: 16px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 
        0 4px 20px rgba(139, 92, 246, 0.4),
        0 0 0 0 rgba(139, 92, 246, 0.4);
}

.btn-hero-primary:hover {
    transform: translateY(-3px);
    box-shadow: 
        0 8px 30px rgba(139, 92, 246, 0.5),
        0 0 0 4px rgba(139, 92, 246, 0.2);
    color: #fff;
}

.btn-hero-primary:active {
    transform: translateY(-1px);
}

.btn-hero-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    background: var(--t-surface-2);
    color: var(--t-text);
    border: 1px solid var(--t-border);
    border-radius: 16px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-hero-secondary:hover {
    background: var(--t-surface-3);
    border-color: var(--t-border-hover);
    color: var(--t-text);
}

/* Progress Ring */
.hero-progress {
    position: absolute;
    top: 2rem;
    right: 2.5rem;
    z-index: 2;
}

.progress-ring {
    width: 100px;
    height: 100px;
    position: relative;
}

.progress-ring svg {
    transform: rotate(-90deg);
    width: 100%;
    height: 100%;
}

.progress-ring circle {
    fill: none;
    stroke-width: 8;
    stroke-linecap: round;
}

.progress-ring .bg {
    stroke: var(--t-surface-3);
}

.progress-ring .progress {
    stroke: url(#progressGradient);
    stroke-dasharray: 251;
    stroke-dashoffset: <?php echo 251 - (251 * $percentualConcluido / 100); ?>;
    transition: stroke-dashoffset 1s ease;
}

.progress-ring-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.progress-ring-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--t-text);
    display: block;
}

.progress-ring-label {
    font-size: 0.7rem;
    color: var(--t-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ==================== STATS GRID ==================== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2.5rem;
}

.stat-card {
    background: var(--t-surface);
    border: 1px solid var(--t-border);
    border-radius: 20px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    border-radius: 20px 20px 0 0;
}

.stat-card.pending::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.stat-card.completed::before { background: linear-gradient(90deg, #22c55e, #4ade80); }
.stat-card.urgent::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.stat-card.scheduled::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }

.stat-card:hover {
    transform: translateY(-4px);
    border-color: var(--t-border-hover);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.stat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}

.stat-card.pending .stat-icon { background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(139, 92, 246, 0.1)); color: #a78bfa; }
.stat-card.completed .stat-icon { background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(34, 197, 94, 0.1)); color: #4ade80; }
.stat-card.urgent .stat-icon { background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1)); color: #f87171; }
.stat-card.scheduled .stat-icon { background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(245, 158, 11, 0.1)); color: #fbbf24; }

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
}

.stat-trend.up { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
.stat-trend.down { background: rgba(239, 68, 68, 0.15); color: #f87171; }

.stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--t-text);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--t-text-muted);
    font-weight: 500;
}

/* ==================== SECTION HEADERS ==================== */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--t-border);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--t-text);
    margin: 0;
}

.section-title-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.section-title-icon.pending { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.section-title-icon.completed { background: linear-gradient(135deg, #22c55e, #16a34a); }

.section-count {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
    padding: 0.35rem 0.75rem;
    background: var(--t-surface-2);
    border-radius: 8px;
    color: var(--t-text-secondary);
}

/* ==================== TASK CARDS ==================== */
.tasks-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.task-card {
    background: var(--t-surface);
    border: 1px solid var(--t-border);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.task-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    border-radius: 20px 0 0 20px;
}

.task-card.priority-high::before { background: linear-gradient(180deg, #ef4444, #f97316); }
.task-card.priority-medium::before { background: linear-gradient(180deg, #f59e0b, #eab308); }
.task-card.priority-low::before { background: linear-gradient(180deg, #22c55e, #10b981); }

.task-card:hover {
    border-color: var(--t-border-hover);
    transform: translateX(6px);
    box-shadow: 
        0 10px 40px rgba(0, 0, 0, 0.2),
        -6px 0 20px rgba(139, 92, 246, 0.1);
}

.task-main {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
}

.task-drag {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 0.5rem;
    cursor: grab;
    opacity: 0.4;
    transition: opacity 0.2s;
}

.task-drag:hover { opacity: 1; }
.task-drag:active { cursor: grabbing; }

.task-drag span {
    width: 18px;
    height: 3px;
    background: var(--t-text-muted);
    border-radius: 2px;
}

.task-checkbox {
    width: 24px;
    height: 24px;
    border-radius: 8px;
    border: 2px solid var(--t-border-hover);
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.task-checkbox:hover {
    border-color: var(--t-primary);
    background: rgba(139, 92, 246, 0.1);
}

.task-checkbox i {
    font-size: 0.9rem;
    color: var(--t-primary);
    opacity: 0;
    transform: scale(0);
    transition: all 0.2s ease;
}

.task-checkbox:hover i {
    opacity: 1;
    transform: scale(1);
}

.task-body {
    flex-grow: 1;
    min-width: 0;
}

.task-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--t-text);
    margin: 0 0 0.5rem;
    line-height: 1.4;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.task-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    background: var(--t-surface-2);
    border: 1px solid var(--t-border);
    border-radius: 10px;
    font-size: 0.8rem;
    color: var(--t-text-secondary);
    transition: all 0.2s ease;
}

.task-chip i { font-size: 0.85rem; }

.task-chip.priority-high {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.task-chip.priority-medium {
    background: rgba(245, 158, 11, 0.15);
    border-color: rgba(245, 158, 11, 0.3);
    color: #fcd34d;
}

.task-chip.priority-low {
    background: rgba(34, 197, 94, 0.15);
    border-color: rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.task-chip.subtasks {
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(139, 92, 246, 0.3);
    color: #c4b5fd;
}

.task-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-task {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: 1px solid var(--t-border);
    background: var(--t-surface-2);
    color: var(--t-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1rem;
}

.btn-task:hover {
    background: var(--t-surface-3);
    color: var(--t-text);
    transform: scale(1.05);
}

.btn-task.complete:hover {
    background: rgba(34, 197, 94, 0.2);
    border-color: var(--t-success);
    color: var(--t-success);
}

.btn-task.edit:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: var(--t-info);
    color: var(--t-info);
}

.btn-task.delete:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: var(--t-danger);
    color: var(--t-danger);
}

.btn-task.restore:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: var(--t-warning);
    color: var(--t-warning);
}

/* Task Details/Subtasks */
.task-expand {
    padding: 0 1.5rem 0.75rem;
}

.btn-expand {
    background: none;
    border: none;
    color: var(--t-text-muted);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    padding: 0.5rem 0;
    transition: color 0.2s;
}

.btn-expand:hover { color: var(--t-text-secondary); }
.btn-expand i { transition: transform 0.3s ease; }
.btn-expand[aria-expanded="true"] i { transform: rotate(180deg); }

.task-details {
    border-top: 1px solid var(--t-border);
    padding: 1.25rem 1.5rem;
    background: rgba(0, 0, 0, 0.2);
}

.task-time-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--t-surface-2);
    border-radius: 10px;
    margin-bottom: 1rem;
    font-size: 0.85rem;
    color: var(--t-text-secondary);
}

.subtasks-wrapper {
    margin-bottom: 1rem;
}

.subtask-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1rem;
    background: var(--t-surface);
    border: 1px solid var(--t-border);
    border-radius: 12px;
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}

.subtask-item:hover {
    border-color: var(--t-border-hover);
    background: var(--t-surface-2);
}

.subtask-check {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-grow: 1;
}

.subtask-check input[type="checkbox"] {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    accent-color: var(--t-primary);
    cursor: pointer;
}

.subtask-check label {
    color: var(--t-text);
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
}

.subtask-check label.done {
    text-decoration: line-through;
    color: var(--t-text-muted);
}

.subtask-actions {
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.subtask-item:hover .subtask-actions { opacity: 1; }

.btn-subtask {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--t-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.btn-subtask:hover {
    background: var(--t-surface-3);
    color: var(--t-text);
}

.btn-subtask.delete:hover {
    background: rgba(239, 68, 68, 0.2);
    color: var(--t-danger);
}

.add-subtask {
    display: flex;
    gap: 0.5rem;
}

.add-subtask input {
    flex-grow: 1;
    background: var(--t-surface);
    border: 1px solid var(--t-border);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    color: var(--t-text);
    font-size: 0.9rem;
    transition: all 0.2s;
}

.add-subtask input:focus {
    outline: none;
    border-color: var(--t-primary);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
}

.add-subtask input::placeholder { color: var(--t-text-muted); }

.add-subtask button {
    padding: 0.75rem 1.25rem;
    background: var(--t-primary);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.add-subtask button:hover {
    background: #7c3aed;
}

/* Completed Task Style */
.task-card.completed-task {
    opacity: 0.7;
}

.task-card.completed-task .task-title {
    text-decoration: line-through;
    color: var(--t-text-muted);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--t-surface);
    border: 2px dashed var(--t-border);
    border-radius: 24px;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    display: block;
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--t-text);
    margin-bottom: 0.5rem;
}

.empty-text {
    color: var(--t-text-muted);
    font-size: 1rem;
    max-width: 300px;
    margin: 0 auto;
}

/* Sortable States */
.sortable-ghost { opacity: 0.3; }
.sortable-chosen { transform: scale(1.02); box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4); z-index: 100; }

/* ==================== MODAL STYLES ==================== */
.modal-tasks .modal-content {
    background: var(--t-surface);
    border: 1px solid var(--t-border);
    border-radius: 24px;
    overflow: hidden;
}

.modal-tasks .modal-header {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(236, 72, 153, 0.1));
    border: none;
    padding: 1.75rem;
    position: relative;
}

.modal-tasks .modal-header::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--t-border), transparent);
}

.modal-tasks .modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--t-text);
}

.modal-tasks .btn-close {
    filter: invert(1);
    opacity: 0.5;
}

.modal-tasks .btn-close:hover { opacity: 1; }

.modal-tasks .modal-body {
    padding: 1.75rem;
}

.modal-tasks .modal-footer {
    border-top: 1px solid var(--t-border);
    padding: 1.25rem 1.75rem;
    background: rgba(0, 0, 0, 0.2);
}

.modal-tasks .form-label {
    color: var(--t-text);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.modal-tasks .form-control,
.modal-tasks .form-select {
    background: var(--t-surface-2);
    border: 1px solid var(--t-border);
    border-radius: 12px;
    color: var(--t-text);
    padding: 0.875rem 1rem;
    transition: all 0.2s;
}

.modal-tasks .form-control:focus,
.modal-tasks .form-select:focus {
    background: var(--t-surface-3);
    border-color: var(--t-primary);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
    color: var(--t-text);
}

.modal-tasks .form-control::placeholder { color: var(--t-text-muted); }

.modal-tasks .input-group-text {
    background: var(--t-surface-3);
    border: 1px solid var(--t-border);
    color: var(--t-text-muted);
}

/* ==================== RESPONSIVE ==================== */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 992px) {
    .hero-progress { display: none; }
    .hero-title { font-size: 2.25rem; }
}

@media (max-width: 768px) {
    .tasks-hero { padding: 1.75rem; border-radius: 20px; }
    .hero-title { font-size: 1.875rem; }
    .hero-subtitle { font-size: 1rem; }
    .hero-actions { flex-direction: column; }
    .btn-hero-primary, .btn-hero-secondary { width: 100%; justify-content: center; }
    
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    .stat-card { padding: 1.25rem; border-radius: 16px; }
    .stat-value { font-size: 2rem; }
    
    .task-main { flex-wrap: wrap; padding: 1rem; }
    .task-drag { display: none; }
    .task-body { width: 100%; order: 1; }
    .task-checkbox { order: 0; }
    .task-actions { 
        width: 100%; 
        order: 2; 
        padding-top: 1rem;
        margin-top: 0.75rem;
        border-top: 1px solid var(--t-border);
        justify-content: flex-end;
    }
    
    .subtask-actions { opacity: 1; }
}

@media (max-width: 576px) {
    .hero-badge { font-size: 0.75rem; padding: 0.4rem 0.8rem; }
    .hero-title { font-size: 1.5rem; }
    
    .section-title { font-size: 1.25rem; }
    .section-title-icon { width: 36px; height: 36px; font-size: 1rem; }
    
    .task-chip { font-size: 0.75rem; padding: 0.3rem 0.6rem; }
    .btn-task { width: 36px; height: 36px; }
}
</style>

<div class="tasks-container">
    <div class="tasks-bg"></div>
    
    <div class="tasks-content">
        <!-- SVG Gradient for Progress Ring -->
        <svg width="0" height="0">
            <defs>
                <linearGradient id="progressGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#8b5cf6"/>
                    <stop offset="50%" style="stop-color:#ec4899"/>
                    <stop offset="100%" style="stop-color:#3b82f6"/>
                </linearGradient>
            </defs>
        </svg>

        <!-- Hero Section -->
        <div class="tasks-hero" data-aos="fade-down">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="bi bi-stars"></i>
                    Gerenciador de Tarefas
                </div>
                <h1 class="hero-title">Organize suas <span>tarefas</span></h1>
                <p class="hero-subtitle">Aumente sua produtividade com um sistema inteligente de gestão de atividades</p>
                
                <div class="hero-actions">
                    <button class="btn-hero-primary" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                        <i class="bi bi-plus-lg"></i>
                        Nova Tarefa
                    </button>
                </div>
            </div>
            
            <div class="hero-progress">
                <div class="progress-ring">
                    <svg viewBox="0 0 100 100">
                        <circle class="bg" cx="50" cy="50" r="40"/>
                        <circle class="progress" cx="50" cy="50" r="40"/>
                    </svg>
                    <div class="progress-ring-text">
                        <span class="progress-ring-value"><?php echo $percentualConcluido; ?>%</span>
                        <span class="progress-ring-label">Concluído</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                </div>
                <div class="stat-value"><?php echo $totalPendentes; ?></div>
                <div class="stat-label">Tarefas Pendentes</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-header">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                </div>
                <div class="stat-value"><?php echo $totalConcluidas; ?></div>
                <div class="stat-label">Tarefas Concluídas</div>
            </div>
            <div class="stat-card urgent">
                <div class="stat-header">
                    <div class="stat-icon"><i class="bi bi-fire"></i></div>
                </div>
                <div class="stat-value"><?php echo $altaPrioridade; ?></div>
                <div class="stat-label">Prioridade Alta</div>
            </div>
            <div class="stat-card scheduled">
                <div class="stat-header">
                    <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
                </div>
                <div class="stat-value"><?php echo $comDataLimite; ?></div>
                <div class="stat-label">Com Data Limite</div>
            </div>
        </div>

        <!-- Pending Tasks Section -->
        <div class="tasks-section" data-aos="fade-up" data-aos-delay="200">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="section-title-icon pending"><i class="bi bi-list-task"></i></span>
                    Pendentes
                </h2>
                <span class="section-count"><?php echo $totalPendentes; ?> tarefas</span>
            </div>

            <div id="lista-tarefas-pendentes" class="tasks-list">
                <?php if (empty($tarefas_pendentes)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">🎉</span>
                        <div class="empty-title">Tudo em dia!</div>
                        <p class="empty-text">Você não tem tarefas pendentes. Que tal adicionar uma nova?</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tarefas_pendentes as $tarefa): 
                        $prioInfo = getPrioridadeInfo($tarefa['prioridade']);
                    ?>
                    <div class="task-card <?php echo $prioInfo['class']; ?>" data-id="<?php echo $tarefa['id']; ?>">
                        <div class="task-main">
                            <div class="task-drag handle">
                                <span></span><span></span><span></span>
                            </div>
                            <button class="task-checkbox btn-atualizar-status" data-id="<?php echo $tarefa['id']; ?>" data-status="concluida">
                                <i class="bi bi-check"></i>
                            </button>
                            <div class="task-body">
                                <h3 class="task-title"><?php echo htmlspecialchars($tarefa['descricao']); ?></h3>
                                <div class="task-meta">
                                    <span class="task-chip <?php echo $prioInfo['class']; ?>">
                                        <i class="bi bi-<?php echo $prioInfo['icon']; ?>"></i>
                                        <?php echo $tarefa['prioridade']; ?>
                                    </span>
                                    <?php if ($tarefa['data_limite']): ?>
                                    <span class="task-chip">
                                        <i class="bi bi-calendar3"></i>
                                        <?php echo date('d/m/Y', strtotime($tarefa['data_limite'])); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($tarefa['hora_inicio'] || $tarefa['hora_fim']): ?>
                                    <span class="task-chip">
                                        <i class="bi bi-clock"></i>
                                        <?php
                                            $inicio = $tarefa['hora_inicio'] ? date('H:i', strtotime($tarefa['hora_inicio'])) : '--:--';
                                            $fim = $tarefa['hora_fim'] ? date('H:i', strtotime($tarefa['hora_fim'])) : '--:--';
                                            echo "{$inicio} - {$fim}";
                                        ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($tarefa['subtarefas'])): 
                                        $subConcluidas = count(array_filter($tarefa['subtarefas'], fn($s) => $s['status'] === 'concluida'));
                                    ?>
                                    <span class="task-chip subtasks">
                                        <i class="bi bi-list-check"></i>
                                        <?php echo $subConcluidas . '/' . count($tarefa['subtarefas']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="task-actions">
                                <button class="btn-task edit btn-editar-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-bs-toggle="modal" data-bs-target="#modalEditarTarefa" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-task delete btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-nome="<?php echo htmlspecialchars($tarefa['descricao']); ?>" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="task-expand">
                            <button class="btn-expand" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?php echo $tarefa['id']; ?>">
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
                                <div class="task-time-badge">
                                    <i class="bi bi-stopwatch"></i>
                                    Tempo estimado: <strong><?php echo trim($tf); ?></strong>
                                </div>
                                <?php endif; ?>
                                
                                <div class="subtasks-wrapper">
                                    <?php if (!empty($tarefa['subtarefas'])): ?>
                                        <?php foreach ($tarefa['subtarefas'] as $sub): ?>
                                        <div class="subtask-item" id="subtask-item-<?php echo $sub['id']; ?>">
                                            <div class="subtask-check">
                                                <input type="checkbox" class="subtask-checkbox" data-id="<?php echo $sub['id']; ?>" id="sub-<?php echo $sub['id']; ?>" <?php echo ($sub['status'] == 'concluida') ? 'checked' : ''; ?>>
                                                <label for="sub-<?php echo $sub['id']; ?>" class="<?php echo ($sub['status'] == 'concluida') ? 'done' : ''; ?>">
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
                                
                                <form class="add-subtask form-add-subtask">
                                    <input type="hidden" name="id_tarefa_principal" value="<?php echo $tarefa['id']; ?>">
                                    <input type="text" name="descricao" placeholder="Adicionar subtarefa..." required>
                                    <button type="submit"><i class="bi bi-plus"></i> Add</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completed Tasks Section -->
        <div class="tasks-section" data-aos="fade-up" data-aos-delay="300">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="section-title-icon completed"><i class="bi bi-check2-all"></i></span>
                    Concluídas
                </h2>
                <span class="section-count"><?php echo $totalConcluidas; ?> tarefas</span>
            </div>

            <div id="lista-tarefas-concluidas" class="tasks-list">
                <?php if (empty($tarefas_concluidas)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">📋</span>
                        <div class="empty-title">Nenhuma tarefa concluída</div>
                        <p class="empty-text">Complete suas tarefas pendentes para vê-las aqui.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tarefas_concluidas as $tarefa): ?>
                    <div class="task-card completed-task" id="task-card-<?php echo $tarefa['id']; ?>">
                        <div class="task-main">
                            <div class="task-body">
                                <h3 class="task-title"><?php echo htmlspecialchars($tarefa['descricao']); ?></h3>
                            </div>
                            <div class="task-actions">
                                <button class="btn-task restore btn-atualizar-status" data-id="<?php echo $tarefa['id']; ?>" data-status="pendente" title="Restaurar">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <button class="btn-task delete btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-nome="<?php echo htmlspecialchars($tarefa['descricao']); ?>" title="Excluir">
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
</div>

<!-- Modal Nova Tarefa -->
<div class="modal fade modal-tasks" id="modalNovaTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nova Tarefa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaTarefa">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">O que você precisa fazer?</label>
                        <input type="text" name="descricao" class="form-control" placeholder="Descreva sua tarefa..." required>
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
                    <div class="mb-0">
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
                    <button type="submit" class="btn-hero-primary">
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
            <form id="formEditarTarefa">
                <div class="modal-body" id="corpoModalEditar">
                    <div class="text-center p-5">
                        <div class="spinner-border" style="color: var(--t-primary);"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-hero-primary">
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
            corpoModalEditar.innerHTML = '<div class="text-center p-5"><div class="spinner-border" style="color: var(--t-primary);"></div></div>';

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
                        <div class="mb-0">
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
                background: '#18181b',
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
                            const card = document.querySelector(`.task-card[data-id='${id}']`) || document.getElementById(`task-card-${id}`);
                            if (card) {
                                card.style.transition = 'all 0.4s ease';
                                card.style.opacity = '0';
                                card.style.transform = 'translateX(-30px) scale(0.95)';
                                setTimeout(() => card.remove(), 400);
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
                    card.style.transition = 'all 0.4s ease';
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
                background: '#18181b',
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
                                item.style.transform = 'translateX(-10px)';
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
                background: '#18181b',
                color: '#fff',
                confirmButtonColor: '#8b5cf6',
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
                    label.classList.toggle('done', isChecked);
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
                    const wrapper = form.previousElementSibling;
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
                    wrapper.appendChild(newEl);
                    newEl.style.opacity = '0';
                    newEl.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        newEl.style.transition = 'all 0.3s ease';
                        newEl.style.opacity = '1';
                        newEl.style.transform = 'translateY(0)';
                    }, 10);
                    form.reset();
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-plus"></i> Add';
            });
        }
    });

    // Sortable
    const listaTarefas = document.getElementById('lista-tarefas-pendentes');
    if (listaTarefas && listaTarefas.querySelector('.task-card')) {
        new Sortable(listaTarefas, {
            animation: 250,
            handle: '.task-drag',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
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
