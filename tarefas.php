<?php
// tarefas.php - Design Premium & Funcionalidade Aprimorada
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

$tarefas_pendentes = [];
$tarefas_concluidas = [];

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
} catch (PDOException $e) {
    die("Erro ao buscar tarefas: " . $e->getMessage());
}

$totalPendentes = count($tarefas_pendentes);
$totalConcluidas = count($tarefas_concluidas);
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

.btn-delete-sub {
    opacity: 0;
    transition: opacity 0.2s;
    color: var(--danger);
}

.subtask-item:hover .btn-delete-sub { opacity: 1; }

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
            <h1 class="page-title">Tarefas & Rotina</h1>
            <p class="text-muted mb-0">Gerencie seu dia com eficiência</p>
        </div>
        <button class="btn-premium" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
            <i class="bi bi-plus-lg"></i> Nova Tarefa
        </button>
    </div>

    <!-- Estatísticas Rápidas -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-value text-accent"><?php echo $totalPendentes; ?></span>
            <span class="stat-label">Pendentes</span>
        </div>
        <div class="stat-card">
            <span class="stat-value text-success"><?php echo $totalConcluidas; ?></span>
            <span class="stat-label">Concluídas hoje</span>
        </div>
    </div>

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
                                <button class="btn-icon delete btn-delete-sub" data-id="<?php echo $sub['id']; ?>"><i class="bi bi-x-lg"></i></button>
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
                        <button class="btn-icon delete btn-delete-sub" data-id="${data.subtarefa.id}"><i class="bi bi-x-lg"></i></button>
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
