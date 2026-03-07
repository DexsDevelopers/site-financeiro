<?php
// tarefas.php - Design Premium & Funcionalidade Aprimorada
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

$tarefas_pendentes = [];
$tarefas_concluidas = [];

try {
    // Garante que a coluna data_conclusao exista, caso não tenha sido criada ainda..
    $pdo->exec("ALTER TABLE tarefas ADD COLUMN data_conclusao DATETIME DEFAULT NULL");
    
    // FORÇAR CRIAÇÃO DA COLUNA DIAS_SEMANA
    try {
        $pdo->exec("ALTER TABLE rotinas_fixas ADD COLUMN dias_semana VARCHAR(20) DEFAULT NULL");
    } catch (PDOException $e) { /* Coluna já existe */ }
} catch (PDOException $e) {
    // Silênciado.
}

try {
    // Busca tarefas pendentes por prioridade e ordem
    $sql_pendentes = "SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'pendente' ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC";
    $stmt_pendentes = $pdo->prepare($sql_pendentes);
    $stmt_pendentes->execute([$userId]);
    $tarefas_pendentes = $stmt_pendentes->fetchAll(PDO::FETCH_ASSOC);

    // Busca tarefas concluídas recentes
    $sql_concluidas = "SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'concluida' ORDER BY data_criacao DESC LIMIT 10";
    $stmt_concluidas = $pdo->prepare($sql_concluidas);
    $stmt_concluidas->execute([$userId]);
    $tarefas_concluidas = $stmt_concluidas->fetchAll(PDO::FETCH_ASSOC);

    // Processamento de subtarefas em lote
    $todos_ids = array_merge(array_column($tarefas_pendentes, 'id'), array_column($tarefas_concluidas, 'id'));
    if (!empty($todos_ids)) {
        $placeholders = implode(',', array_fill(0, count($todos_ids), '?'));
        $sql_subtarefas = "SELECT * FROM subtarefas WHERE id_tarefa_principal IN ($placeholders) ORDER BY id ASC";
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

    // --- Buscar rotinas (Hábitos) ---
    $dataHoje = date('Y-m-d');
    $rotinasFixas = [];
    $rotinasConcluidasCount = 0;
    
    $stmtHabitos = $pdo->prepare("
        SELECT rf.*, 
               rcd.status as status_hoje,
               rcd.id as controle_id
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd 
            ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        AND (rf.dias_semana IS NULL OR rf.dias_semana = '' OR FIND_IN_SET(DAYOFWEEK(CURDATE()), rf.dias_semana))
        ORDER BY 
            CASE 
                WHEN rf.prioridade = 'Alta' THEN 1 
                WHEN rf.prioridade = 'Média' THEN 2 
                ELSE 3 
            END,
            COALESCE(rf.horario_sugerido, '23:59:59'), 
            rf.nome
    ");
    $stmtHabitos->execute([$dataHoje, $userId]);
    $rotinasFixas = $stmtHabitos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rotinasFixas as $rotina) {
        if ($rotina['status_hoje'] === 'concluido') {
            $rotinasConcluidasCount++;
        }
    }
    // Conta tarefas concluídas HOJE
    $stmt_hoje = $pdo->prepare("SELECT COUNT(*) FROM tarefas WHERE id_usuario = ? AND status = 'concluida' AND DATE(data_conclusao) = ?");
    $stmt_hoje->execute([$userId, $dataHoje]);
    $tarefas_concluidas_hoje = $stmt_hoje->fetchColumn();
    
} catch (PDOException $e) {
    die("Erro ao buscar tarefas: " . $e->getMessage());
}

$totalPendentes = count($tarefas_pendentes);
$totalConcluidasHoje = $tarefas_concluidas_hoje + $rotinasConcluidasCount;

?>

<style>
/* --- Variáveis de Tema (Baseadas no Header) --- */
:root {
    --glass-bg: rgba(26, 26, 26, 0.85);
    --glass-border: rgba(255, 255, 255, 0.08);
    --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    --accent-color: #e50914; /* Vermelho Netflix/Financeiro */
    --accent-hover: #b20710;
    --text-primary: #f5f5f1;
    --text-secondary: #b3b3b7;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
}

body {
    background-color: #0f0f0f; /* Fundo base escuro */
}

.tasks-container {
    padding-bottom: 4rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* --- Variáveis e Estilos Rotinas (Lux) --- */
.grid-lux-habitos {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.card-lux-habit {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.card-lux-habit:hover {
    border-color: rgba(255,255,255,0.2);
    transform: translateY(-5px);
}

.card-lux-habit.concluido {
    border-color: var(--success);
    background: rgba(16, 185, 129, 0.05); /* success alpha */
}

.btn-complete-lux {
    width: 100%;
    margin-top: 1.5rem;
    padding: 0.8rem;
    border-radius: 12px;
    border: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.05);
    color: white;
    font-weight: 700;
    transition: 0.3s;
}

.btn-complete-lux:hover {
    background: var(--accent-color);
    border-color: var(--accent-color);
}

.btn-complete-lux.is-done {
    background: var(--success);
    border-color: var(--success);
}

.prio-pill {
    padding: 0.3rem 0.8rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}
.prio-Alta { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
.prio-Média, .prio-Media { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
.prio-Baixa { background: rgba(16, 185, 129, 0.2); color: var(--success); }

/* --- Header e Stats --- */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 0 0.5rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(90deg, #fff, #ccc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.btn-premium {
    background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(229, 9, 20, 0.4);
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}

.stat-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: rgba(255,255,255,0.15);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 500;
}

/* --- Lista de Tarefas --- */
.section-heading {
    color: var(--text-secondary);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-heading::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--glass-border);
}

.task-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.task-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 0; /* Padding movido para elementos internos */
    display: flex;
    flex-direction: column;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.task-card:hover {
    border-color: rgba(255,255,255,0.2);
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    transform: translateY(-2px);
}

.task-main {
    display: flex;
    align-items: stretch;
    padding: 1.25rem;
    gap: 1rem;
}

/* Indicador de Prioridade */
.priority-strip {
    width: 6px;
    border-radius: 4px;
    margin-right: -0.5rem;
}
.priority-alta { background: var(--danger); box-shadow: 0 0 10px rgba(239, 68, 68, 0.4); }
.priority-media { background: var(--warning); }
.priority-baixa { background: var(--success); }

.task-checkbox-wrapper {
    display: flex;
    align-items: flex-start;
    padding-top: 0.2rem;
}

.custom-checkbox {
    width: 24px;
    height: 24px;
    border: 2px solid var(--text-secondary);
    border-radius: 8px;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    color: transparent;
}

.custom-checkbox:hover {
    border-color: var(--accent-color);
}

.custom-checkbox.completed {
    background: var(--accent-color);
    border-color: var(--accent-color);
    color: white;
}

.task-info {
    flex: 1;
    min-width: 0; /* Text truncate fix */
}

.task-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1.4;
    transition: color 0.3s ease;
}

.task-card.completed .task-title {
    text-decoration: line-through;
    color: var(--text-secondary);
}

.task-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
    background: rgba(255,255,255,0.05);
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
}

.tag i { font-size: 0.9rem; }

.task-actions {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    opacity: 0.6;
    transition: opacity 0.3s ease;
}

.task-card:hover .task-actions { opacity: 1; }

.btn-icon {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: rgba(255,255,255,0.1);
    color: var(--text-primary);
}

.btn-icon.delete:hover {
    background: rgba(239, 68, 68, 0.15);
    color: var(--danger);
}

/* --- Subtarefas --- */
.subtasks-container {
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid var(--glass-border);
    padding: 0 1.25rem 1.25rem 3.5rem; /* Indentação visual */
}

.btn-toggle-sub {
    width: 100%;
    background: transparent;
    border: none;
    border-top: 1px solid var(--glass-border);
    color: var(--text-secondary);
    font-size: 0.8rem;
    padding: 0.5rem;
    cursor: pointer;
    text-align: center;
    transition: background 0.2s;
}

.btn-toggle-sub:hover {
    background: rgba(255,255,255,0.02);
    color: var(--text-primary);
}

.subtask-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.03);
    transition: background 0.2s;
}

.subtask-item:last-child { border-bottom: none; }

.subtask-item:hover {
    background: rgba(255,255,255,0.02);
    border-radius: 8px;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
    margin: 0 -0.5rem;
}

.subtask-label {
    flex: 1;
    font-size: 0.9rem;
    color: var(--text-secondary);
    cursor: pointer;
    transition: color 0.2s;
}

.subtask-checkbox:checked + .subtask-label {
    text-decoration: line-through;
    opacity: 0.6;
}

.subtask-checkbox {
    appearance: none;
    width: 18px;
    height: 18px;
    border: 2px solid var(--text-secondary);
    border-radius: 4px;
    background: transparent;
    cursor: pointer;
    position: relative;
}

.subtask-checkbox:checked {
    background: var(--success);
    border-color: var(--success);
}

.subtask-checkbox:checked::after {
    content: '✓';
    position: absolute;
    color: white;
    font-size: 10px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.btn-delete-sub, .btn-edit-sub {
    opacity: 0;
    transition: opacity 0.2s;
}

.btn-delete-sub {
    color: var(--danger);
}

.btn-edit-sub {
    color: var(--text-secondary);
}

.btn-edit-sub:hover {
    color: var(--accent-color);
}

.subtask-item:hover .btn-delete-sub, .subtask-item:hover .btn-edit-sub { opacity: 1; }

.form-new-subtask {
    margin-top: 0.75rem;
    display: flex;
    gap: 0.5rem;
}

.input-subtask {
    flex: 1;
    background: rgba(255,255,255,0.05);
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    color: var(--text-primary);
    font-size: 0.85rem;
    transition: border 0.2s;
}

.input-subtask:focus {
    outline: none;
    border-color: var(--accent-color);
    background: rgba(255,255,255,0.08);

}

/* --- Modais --- */
.modal-content {
    background-color: #1a1a1a;
    border: 1px solid var(--glass-border);
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}

.modal-header { border-bottom-color: var(--glass-border); }
.modal-footer { border-top-color: var(--glass-border); }
.form-control, .form-select {
    background-color: rgba(0,0,0,0.3);
    border-color: var(--glass-border);
    color: var(--text-primary);
}
.form-control:focus, .form-select:focus {
    background-color: rgba(0,0,0,0.4);
    border-color: var(--accent-color);
    color: white;
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.15);
}

/* --- Empty state --- */
.empty-state {
    text-align: center;
    padding: 4rem 1rem;
    color: var(--text-secondary);
    background: rgba(255,255,255,0.02);
    border-radius: 16px;
    border: 1px dashed var(--glass-border);
}

.empty-state i {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .page-header { flex-direction: column; gap: 1rem; align-items: flex-start; }
    .btn-premium { width: 100%; justify-content: center; }
    .task-meta { flex-direction: column; gap: 0.25rem; }
    .priority-strip { display: none; }
    .task-card { border-left: 4px solid var(--glass-border); }
    .task-card[data-prio="Alta"] { border-left-color: var(--danger); }
    .task-card[data-prio="Média"] { border-left-color: var(--warning); }
    .task-card[data-prio="Baixa"] { border-left-color: var(--success); }
    .subtasks-container { padding-left: 1.25rem; }
}
</style>

<div class="tasks-container pt-4">
    <!-- Cabeçalho -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Tarefas & Hábitos</h1>
            <p class="text-muted mb-0">Gerencie seu dia e construa sua melhor versão</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-light" style="border-radius: 12px;" onclick="new bootstrap.Modal(document.getElementById('modalRotina')).show()">
                <i class="bi bi-calendar-heart"></i> Novo Hábito
            </button>
            <button class="btn-premium" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                <i class="bi bi-list-check"></i> Nova Tarefa
            </button>
        </div>
    </div>

    <!-- Estatísticas Rápidas -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-value text-accent"><?php echo $totalPendentes; ?></span>
            <span class="stat-label">Pendentes</span>
        </div>
        <div class="stat-card">
            <span class="stat-value text-success"><?php echo $totalConcluidasHoje; ?></span>
            <span class="stat-label">Concluídas hoje</span>
        </div>
    </div>

    <!-- Seção: Hábitos (Rotina Diária) -->
    <div class="section-heading mt-4">
        <i class="bi bi-calendar-heart"></i> Hábitos Diários (<?php echo $rotinasConcluidasCount . '/' . count($rotinasFixas); ?> concluídos)
    </div>

    <?php if (empty($rotinasFixas)): ?>
        <div class="empty-state mb-4 py-4">
            <i class="bi bi-calendar-event"></i>
            <h5>Nenhum hábito configurado</h5>
            <p class="text-muted small">Crie hábitos para acompanhar seu progresso diário.</p>
        </div>
    <?php else: ?>
        <div class="grid-lux-habitos">
            <?php foreach ($rotinasFixas as $rotina):
                $isConcluido = ($rotina['status_hoje'] === 'concluido');
            ?>
            <div class="card-lux-habit <?= $isConcluido ? 'concluido' : '' ?>" data-id="<?= $rotina['id']; ?>" data-controle-id="<?= $rotina['controle_id'] ?? ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <span class="prio-pill prio-<?= $rotina['prioridade'] ?>">
                        <?= $rotina['prioridade'] ?>
                    </span>
                    <?php if ($rotina['horario_sugerido']): ?>
                        <span style="color: var(--text-secondary); font-size: 0.85rem;">
                            <i class="bi bi-clock me-1"></i> <?= date('H:i', strtotime($rotina['horario_sugerido'])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h4 style="margin: 0 0 0.5rem 0; font-size: 1.15rem; color: var(--text-primary);"><?= htmlspecialchars($rotina['nome']) ?></h4>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 40px;">
                    <?= htmlspecialchars($rotina['descricao']) ?>
                </p>

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-bottom:0.5rem;">
                     <button class="btn-icon edit" onclick="window.location.href='editar_rotina_fixa.php?id=<?= $rotina['id'] ?>'" title="Editar"><i class="bi bi-pencil"></i></button>
                     <button class="btn-icon delete" onclick="excluirRotina(<?= $rotina['id'] ?>, '<?= addslashes($rotina['nome']) ?>')" title="Excluir"><i class="bi bi-trash"></i></button>
                </div>

                <button class="btn-complete-lux <?= $isConcluido ? 'is-done' : '' ?>" onclick="toggleRotina(<?= $rotina['id'] ?>, '<?= $rotina['status_hoje'] ?? 'pendente' ?>')">
                    <?= $isConcluido ? '<i class="bi bi-check-circle-fill me-2"></i> Concluído' : 'Marcar como feito' ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Seção: Pendentes -->
    <div class="section-heading">
        <i class="bi bi-clock-history"></i> A Fazer (<?php echo $totalPendentes; ?>)
    </div>

    <div id="lista-tarefas-pendentes" class="task-list mb-5">
        <?php if (empty($tarefas_pendentes)): ?>
            <div class="empty-state">
                <i class="bi bi-check-circle-fill"></i>
                <h3>Tudo limpo!</h3>
                <p>Aproveite seu tempo livre.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tarefas_pendentes as $tarefa): 
                $prio = $tarefa['prioridade'];
                $classePrio = match(strtolower($prio)) { 'alta' => 'priority-alta', 'média', 'media' => 'priority-media', default => 'priority-baixa' };
            ?>
            <div class="task-card" data-id="<?php echo $tarefa['id']; ?>" data-prio="<?php echo $prio; ?>">
                <div class="task-main">
                    <div class="priority-strip <?php echo $classePrio; ?>"></div>
                    
                    <div class="task-checkbox-wrapper">
                        <button class="custom-checkbox btn-concluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" title="Concluir Tarefa">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </div>

                    <div class="task-info">
                        <h3 class="task-title"><?php echo htmlspecialchars($tarefa['descricao']); ?></h3>
                        <div class="task-tags">
                            <?php if ($tarefa['data_limite']): ?>
                                <span class="tag <?php echo (strtotime($tarefa['data_limite']) < time()) ? 'text-danger' : ''; ?>">
                                    <i class="bi bi-calendar-event"></i> <?php echo date('d/m', strtotime($tarefa['data_limite'])); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($tarefa['hora_inicio']): ?>
                                <span class="tag">
                                    <i class="bi bi-clock"></i> 
                                    <?php echo date('H:i', strtotime($tarefa['hora_inicio'])); ?> 
                                    <?php echo $tarefa['hora_fim'] ? '- '.date('H:i', strtotime($tarefa['hora_fim'])) : ''; ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($tarefa['subtarefas'])): 
                                $totalSub = count($tarefa['subtarefas']);
                                $doneSub = count(array_filter($tarefa['subtarefas'], fn($s) => $s['status'] === 'concluida'));
                            ?>
                                <span class="tag">
                                    <i class="bi bi-list-task"></i> <?php echo "$doneSub/$totalSub"; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="task-actions">
                        <button class="btn-icon btn-editar-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-bs-toggle="modal" data-bs-target="#modalEditarTarefa" title="Editar">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button class="btn-icon delete btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-title="<?php echo htmlspecialchars($tarefa['descricao']); ?>" title="Excluir">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>
                </div>

                <!-- Footer do Card com Subtarefas Toggle -->
                <?php $hasSubtasks = !empty($tarefa['subtarefas']); ?>
                <button class="btn-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#subtasks-<?php echo $tarefa['id']; ?>" aria-expanded="<?php echo $hasSubtasks ? 'true' : 'false'; ?>">
                    <i class="bi bi-chevron-down"></i> <?php echo $hasSubtasks ? 'Ver Subtarefas' : 'Adicionar Subtarefas'; ?>
                </button>

                <!-- Área de Subtarefas -->
                <div class="collapse <?php echo $hasSubtasks ? 'show' : ''; ?>" id="subtasks-<?php echo $tarefa['id']; ?>">
                    <div class="subtasks-container">
                        <div class="lista-subs">
                            <?php foreach ($tarefa['subtarefas'] ?? [] as $sub): ?>
                            <div class="subtask-item" id="subtask-row-<?php echo $sub['id']; ?>">
                                <input type="checkbox" class="subtask-checkbox" id="sub-<?php echo $sub['id']; ?>" data-id="<?php echo $sub['id']; ?>" <?php echo $sub['status'] === 'concluida' ? 'checked' : ''; ?>>
                                <label for="sub-<?php echo $sub['id']; ?>" class="subtask-label"><?php echo htmlspecialchars($sub['descricao']); ?></label>
                                <button type="button" class="btn-icon edit btn-edit-sub" data-id="<?php echo $sub['id']; ?>" title="Editar subtarefa"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn-icon delete btn-delete-sub" data-id="<?php echo $sub['id']; ?>" title="Excluir subtarefa"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Form add sub inline -->
                        <form class="form-new-subtask">
                            <input type="hidden" name="id_tarefa_principal" value="<?php echo $tarefa['id']; ?>">
                            <input type="text" name="descricao" class="input-subtask" placeholder="+ Nova subtarefa (Enter para salvar)" required>
                            <button type="submit" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-return-left"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Seção: Concluídas -->
    <div class="section-heading mt-5">
        <i class="bi bi-check2-all"></i> Concluídas Recentemente
    </div>
    
    <div id="lista-tarefas-concluidas" class="task-list opacity-75">
        <?php foreach ($tarefas_concluidas as $tarefa): ?>
        <div class="task-card completed" data-id="<?php echo $tarefa['id']; ?>">
            <div class="task-main">
                <div class="task-checkbox-wrapper">
                    <button class="custom-checkbox completed btn-reabrir-tarefa" data-id="<?php echo $tarefa['id']; ?>" title="Reabrir Tarefa">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
                <div class="task-info">
                    <h3 class="task-title"><?php echo htmlspecialchars($tarefa['descricao']); ?></h3>
                </div>
                <div class="task-actions">
                     <button class="btn-icon delete btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-title="<?php echo htmlspecialchars($tarefa['descricao']); ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Novo Hábito -->
<div class="modal fade" id="modalRotina" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-heart text-danger me-2"></i>Novo Hábito</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formNovaRotina">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome do Hábito</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Horário (Opcional)</label>
                            <input type="time" name="horario" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="Baixa">🟢 Baixa</option>
                                <option value="Média" selected>🟡 Média</option>
                                <option value="Alta">🔴 Alta</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dias da Semana</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php 
                            $diasNome = [1 => 'Dom', 2 => 'Seg', 3 => 'Ter', 4 => 'Qua', 5 => 'Qui', 6 => 'Sex', 7 => 'Sáb'];
                            foreach ($diasNome as $val => $nome): 
                            ?>
                                <div class="form-check form-check-inline m-0">
                                    <input class="btn-check" type="checkbox" name="dias_semana[]" value="<?= $val ?>" id="new_dia_<?= $val ?>">
                                    <label class="btn btn-outline-light btn-sm px-2" style="border-radius:8px; font-size: 0.75rem;" for="new_dia_<?= $val ?>">
                                        <?= $nome ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted mt-1 d-block" style="font-size: 0.7rem;">Se nenhum for selecionado, aparecerá todos os dias.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-decoration-none text-muted" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-premium px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nova Tarefa -->
<div class="modal fade" id="modalNovaTarefa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-stars text-danger me-2"></i>Nova Tarefa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formNovaTarefa">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">O que precisa ser feito?</label>
                    <input type="text" name="descricao" class="form-control form-control-lg" placeholder="Ex: Pagar a conta de luz" required autofocus>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Prioridade</label>
                        <select name="prioridade" class="form-select">
                            <option value="Baixa">🟢 Baixa</option>
                            <option value="Média" selected>🟡 Média</option>
                            <option value="Alta">🔴 Alta</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data Limite</label>
                        <input type="date" name="data_limite" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-6">
                        <label class="form-label">Início (Opcional)</label>
                        <input type="time" name="hora_inicio" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Fim (Opcional)</label>
                        <input type="time" name="hora_fim" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none text-muted" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-premium px-4">Criar Tarefa</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Tarefa -->
<div class="modal fade" id="modalEditarTarefa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Tarefa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditarTarefa">
                <div class="modal-body" id="corpoModalEditar">
                    <div class="d-flex justify-content-center py-5">
                        <div class="spinner-border text-danger" role="status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-decoration-none text-muted" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-premium">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Toast Helper (usando SweetAlert Toast) ---
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#1a1a1a',
        color: '#fff',
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // --- Rotinas Javascript ---
    document.getElementById('formNovaRotina')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        fetch('adicionar_rotina_fixa.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Toast.fire({icon:'success', title:'Hábito criado!'});
                setTimeout(() => location.reload(), 800);
            } else {
                Toast.fire({icon:'error', title:data.message});
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    });

    window.toggleRotina = function(rotinaId, statusAtual) {
        const card = document.querySelector(`.card-lux-habit[data-id="${rotinaId}"]`);
        const controleId = card.dataset.controleId;
        const novoStatus = statusAtual === 'concluido' ? 'pendente' : 'concluido';
        
        const body = !controleId 
            ? `rotina_id=${rotinaId}&status=${novoStatus}&criar_controle=1`
            : `controle_id=${controleId}&status=${novoStatus}`;

        fetch('processar_rotina_diaria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
    };

    window.excluirRotina = function(id, nome) {
        Swal.fire({
            title: 'Excluir hábito?',
            text: `O hábito "${nome}" será apagado permanentemente.`,
            icon: 'warning',
            background: '#1a1a1a', color: '#fff',
            showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#3f3f46',
            confirmButtonText: 'Sim', cancelButtonText: 'Não'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('excluir_rotina_fixa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
            }
        });
    };

    // --- Nova Tarefa ---
    document.getElementById('formNovaTarefa')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Criando...';
        btn.disabled = true;

        fetch('adicionar_tarefa.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Toast.fire({ icon: 'success', title: data.message });
                setTimeout(() => location.reload(), 800);
            } else {
                Toast.fire({ icon: 'error', title: data.message });
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            Toast.fire({ icon: 'error', title: 'Erro de conexão' });
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });

    // --- Excluir Tarefa (Swal) ---
    document.body.addEventListener('click', function(e) {
        const btnDelete = e.target.closest('.btn-excluir-tarefa');
        if (btnDelete) {
            const id = btnDelete.dataset.id;
            const title = btnDelete.dataset.title;

            Swal.fire({
                title: 'Excluir Tarefa?',
                text: `"${title}" será removida permanentemente.`,
                icon: 'warning',
                background: '#1a1a1a',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#3f3f46',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('excluir_tarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const card = document.querySelector(`.task-card[data-id="${id}"]`);
                            if(card) {
                                card.style.transition = 'all 0.5s';
                                card.style.opacity = '0';
                                card.style.transform = 'scale(0.9)';
                                setTimeout(() => card.remove(), 500);
                            }
                            Toast.fire({ icon: 'success', title: 'Tarefa excluída!' });
                        } else {
                            Toast.fire({ icon: 'error', title: data.message });
                        }
                    });
                }
            });
        }
    });

    // --- Concluir Tarefa ---
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.btn-concluir-tarefa')) {
            const btn = e.target.closest('.btn-concluir-tarefa');
            updateTaskStatus(btn.dataset.id, 'concluida');
        }
        if (e.target.closest('.btn-reabrir-tarefa')) {
            const btn = e.target.closest('.btn-reabrir-tarefa');
            updateTaskStatus(btn.dataset.id, 'pendente');
        }
    });

    function updateTaskStatus(id, status) {
        fetch('atualizar_status_tarefa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, status: status })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Reload é mais simples para mover entre listas
            }
        });
    }

    // --- Subtarefas: Adicionar Inline ---
    document.body.addEventListener('submit', function(e) {
        if (e.target.classList.contains('form-new-subtask')) {
            e.preventDefault();
            const form = e.target;
            const input = form.querySelector('input[name="descricao"]');
            
            if(!input.value.trim()) return;

            fetch('adicionar_subtarefa.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    // Adicionar visualmente
                    const lista = form.closest('.subtasks-container').querySelector('.lista-subs');
                    const newItem = document.createElement('div');
                    newItem.className = 'subtask-item';
                    newItem.id = `subtask-row-${data.subtarefa.id}`;
                    newItem.innerHTML = `
                        <input type="checkbox" class="subtask-checkbox" id="sub-${data.subtarefa.id}" data-id="${data.subtarefa.id}">
                        <label for="sub-${data.subtarefa.id}" class="subtask-label">${data.subtarefa.descricao}</label>
                        <button type="button" class="btn-icon edit btn-edit-sub" data-id="${data.subtarefa.id}" title="Editar subtarefa"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn-icon delete btn-delete-sub" data-id="${data.subtarefa.id}" title="Excluir subtarefa"><i class="bi bi-x-lg"></i></button>
                    `;
                    lista.appendChild(newItem);
                    input.value = '';
                }
            });
        }
    });

    // --- Subtarefas: Checkbox ---
    document.body.addEventListener('change', function(e) {
        if (e.target.classList.contains('subtask-checkbox')) {
            const id = e.target.dataset.id;
            const status = e.target.checked ? 'concluida' : 'pendente';
            
            fetch('atualizar_status_subtarefa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, status: status })
            }); // Optimistic update, não precisa esperar retorno
        }
    });

    // --- Subtarefas: Excluir ---
    document.body.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete-sub');
        if (btn) {
            const id = btn.dataset.id;
            // Sem confirmação para subtarefas para ser rápido, ou adicionar uma leve
            fetch('excluir_subtarefa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    const row = document.getElementById(`subtask-row-${id}`);
                    if(row) row.remove();
                }
            });
        }
    });

    // --- Subtarefas: Editar ---
    document.body.addEventListener('click', function(e) {
        const btnEdit = e.target.closest('.btn-edit-sub');
        if (btnEdit) {
            const id = btnEdit.dataset.id;
            const item = document.getElementById(`subtask-row-${id}`);
            const label = item.querySelector('.subtask-label');
            const btnDelete = item.querySelector('.btn-delete-sub');
            const textoOriginal = label.textContent;

            // Evitar sobreposição/múltiplas edições abertas na mesma subtask
            if (item.querySelector('.input-edit-subtask')) return;

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'input-subtask input-edit-subtask';
            input.value = textoOriginal;
            input.style.flex = '1';
            input.style.marginRight = '8px';

            label.style.display = 'none';
            btnEdit.style.display = 'none';
            
            item.insertBefore(input, btnEdit);
            input.focus();

            const salvarEdicao = () => {
                const novoTexto = input.value.trim();
                if (!novoTexto || novoTexto === textoOriginal) {
                    cancelarEdicao();
                    return;
                }
                
                input.disabled = true;
                fetch('atualizar_subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, descricao: novoTexto })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        label.textContent = novoTexto;
                        cancelarEdicao();
                        Toast.fire({ icon: 'success', title: 'Subtarefa atualizada' });
                    } else {
                        Toast.fire({ icon: 'error', title: data.message || 'Erro ao atualizar' });
                        input.disabled = false;
                        input.focus();
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: 'Erro na conexão' });
                    input.disabled = false;
                });
            };

            const cancelarEdicao = () => {
                input.remove();
                label.style.display = '';
                btnEdit.style.display = '';
            };

            // Remover listener blur se já pressionou enter
            let foiSalvo = false;
            const handleSaveOnce = () => {
                if (!foiSalvo) {
                    foiSalvo = true;
                    salvarEdicao();
                }
            };

            input.addEventListener('blur', () => { setTimeout(handleSaveOnce, 100); });
            input.addEventListener('keydown', function(evt) {
                if (evt.key === 'Enter') {
                    evt.preventDefault();
                    handleSaveOnce();
                }
                if (evt.key === 'Escape') {
                    foiSalvo = true;
                    cancelarEdicao();
                }
            });
        }
    });

    // --- Modal Editar: Carregar Dados ---
    const modalEditar = document.getElementById('modalEditarTarefa');
    modalEditar?.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const modalBody = document.getElementById('corpoModalEditar');

        modalBody.innerHTML = '<div class="d-flex justify-content-center py-5"><div class="spinner-border text-danger"></div></div>';

        fetch(`buscar_tarefa_detalhes.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const t = data.tarefa;
                    modalBody.innerHTML = `
                        <input type="hidden" name="id" value="${t.id}">
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" value="${t.descricao}" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Prioridade</label>
                                <select name="prioridade" class="form-select">
                                    <option value="Baixa" ${t.prioridade === 'Baixa' ? 'selected' : ''}>🟢 Baixa</option>
                                    <option value="Média" ${t.prioridade === 'Média' ? 'selected' : ''}>🟡 Média</option>
                                    <option value="Alta" ${t.prioridade === 'Alta' ? 'selected' : ''}>🔴 Alta</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data Limite</label>
                                <input type="date" name="data_limite" class="form-control" value="${t.data_limite || ''}">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-6">
                                <label class="form-label">Início</label>
                                <input type="time" name="hora_inicio" class="form-control" value="${t.hora_inicio || ''}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Fim</label>
                                <input type="time" name="hora_fim" class="form-control" value="${t.hora_fim || ''}">
                            </div>
                        </div>
                    `;
                } else {
                    modalBody.innerHTML = '<p class="text-danger text-center">Erro ao carregar tarefa.</p>';
                }
            })
            .catch(() => {
                modalBody.innerHTML = '<p class="text-danger text-center">Mistério na conexão...</p>';
            });
    });

    // --- Salvar Edição ---
    document.getElementById('formEditarTarefa')?.addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('atualizar_tarefa.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
           if(data.success) {
               Toast.fire({ icon: 'success', title: 'Tarefa atualizada' });
               setTimeout(() => location.reload(), 500);
           }
        });
    });

    // --- Drag & Drop (Sortable) ---
    const listaPendentes = document.getElementById('lista-tarefas-pendentes');
    if (listaPendentes && window.innerWidth > 992) { // Apenas Desktop
        new Sortable(listaPendentes, {
            animation: 150,
            ghostClass: 'opacity-50',
            handle: '.task-main', // Pode arrastar clicando no corpo
            onEnd: function() {
                const order = Array.from(listaPendentes.querySelectorAll('.task-card')).map(el => el.dataset.id);
                fetch('atualizar_ordem_tarefas.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ ordem: order })
                });
            }
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
