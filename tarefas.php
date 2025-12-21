<?php
// tarefas.php - Design Minimalista
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

$totalPendentes = count($tarefas_pendentes);
$totalConcluidas = count($tarefas_concluidas);
?>

<style>
:root {
    --bg: #0f0f0f;
    --card: #1a1a1a;
    --border: #2a2a2a;
    --text: #ffffff;
    --muted: #666;
    --accent: #7c3aed;
    --danger: #dc2626;
    --success: #16a34a;
    --warning: #ca8a04;
}

.tasks-page { padding-bottom: 2rem; }

/* Header */
.tasks-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.tasks-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}

.btn-add {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
}

.btn-add:hover { background: #6d28d9; color: #fff; }

/* Stats */
.stats-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.stat-box {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    min-width: 120px;
}

.stat-num {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text);
}

.stat-label {
    font-size: 0.8rem;
    color: var(--muted);
    margin-top: 0.25rem;
}

/* Section */
.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title span {
    background: var(--border);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

/* Task Card */
.task-list { display: flex; flex-direction: column; gap: 0.5rem; }

.task-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.task-card:hover { border-color: #3a3a3a; }

.task-priority {
    width: 4px;
    height: 100%;
    min-height: 40px;
    border-radius: 2px;
    flex-shrink: 0;
}

.task-priority.high { background: var(--danger); }
.task-priority.medium { background: var(--warning); }
.task-priority.low { background: var(--success); }

.task-check {
    width: 22px;
    height: 22px;
    border: 2px solid var(--border);
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}

.task-check:hover { border-color: var(--accent); }
.task-check i { display: none; color: var(--accent); font-size: 0.8rem; }
.task-check:hover i { display: block; }

.task-content { flex-grow: 1; min-width: 0; }

.task-name {
    font-size: 1rem;
    font-weight: 500;
    color: var(--text);
    margin: 0 0 0.35rem;
    word-break: break-word;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.8rem;
    color: var(--muted);
}

.task-meta i { margin-right: 0.25rem; }

.task-actions {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
}

.btn-action {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: var(--muted);
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action:hover { background: var(--border); color: var(--text); }
.btn-action.delete:hover { background: rgba(220, 38, 38, 0.2); color: var(--danger); }

/* Subtasks */
.task-expand {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
}

.btn-expand {
    background: none;
    border: none;
    color: var(--muted);
    font-size: 0.8rem;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-expand:hover { color: var(--text); }
.btn-expand[aria-expanded="true"] i { transform: rotate(180deg); }

.subtask-area { padding-top: 0.75rem; }

.subtask-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: 6px;
    margin-bottom: 0.25rem;
}

.subtask-item:hover { background: rgba(255,255,255,0.03); }

.subtask-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--accent);
}

.subtask-item label {
    flex-grow: 1;
    color: var(--text);
    font-size: 0.9rem;
    cursor: pointer;
}

.subtask-item label.done {
    text-decoration: line-through;
    color: var(--muted);
}

.subtask-item .btn-sub {
    width: 24px;
    height: 24px;
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    border-radius: 4px;
    opacity: 0;
}

.subtask-item:hover .btn-sub { opacity: 1; }
.subtask-item .btn-sub:hover { background: var(--border); }

.add-sub {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.add-sub input {
    flex-grow: 1;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    color: var(--text);
    font-size: 0.85rem;
}

.add-sub input:focus { outline: none; border-color: var(--accent); }

.add-sub button {
    padding: 0.5rem 0.75rem;
    background: var(--accent);
    border: none;
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
}

/* Completed Task */
.task-card.done { opacity: 0.5; }
.task-card.done .task-name { text-decoration: line-through; color: var(--muted); }

/* Empty */
.empty-box {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--muted);
    background: var(--card);
    border: 1px dashed var(--border);
    border-radius: 12px;
}

.empty-box .icon { font-size: 2rem; margin-bottom: 0.5rem; }

/* Modal */
.modal-content {
    background: var(--card) !important;
    border: 1px solid var(--border) !important;
    border-radius: 16px !important;
}

.modal-header {
    border-bottom: 1px solid var(--border);
    padding: 1.25rem;
}

.modal-title { color: var(--text); font-weight: 600; }
.modal-body { padding: 1.25rem; }
.modal-footer { border-top: 1px solid var(--border); padding: 1rem 1.25rem; }

.form-label { color: var(--text); font-weight: 500; margin-bottom: 0.35rem; }

.form-control, .form-select {
    background: var(--bg) !important;
    border: 1px solid var(--border) !important;
    color: var(--text) !important;
    border-radius: 8px !important;
    padding: 0.65rem 0.85rem !important;
}

.form-control:focus, .form-select:focus {
    border-color: var(--accent) !important;
    box-shadow: none !important;
}

.input-group-text {
    background: var(--border) !important;
    border: 1px solid var(--border) !important;
    color: var(--muted) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .tasks-header { flex-direction: column; align-items: stretch; }
    .btn-add { justify-content: center; }
    .stats-row { flex-direction: row; }
    .stat-box { flex: 1; min-width: 0; text-align: center; }
    .stat-num { font-size: 1.5rem; }
    
    .task-card { flex-wrap: wrap; }
    .task-content { width: 100%; order: 1; }
    .task-priority { order: 0; height: auto; min-height: 24px; }
    .task-check { order: 0; }
    .task-actions { order: 2; width: 100%; justify-content: flex-end; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border); }
    
    .subtask-item .btn-sub { opacity: 1; }
}

@media (max-width: 480px) {
    .tasks-title { font-size: 1.5rem; }
    .stat-box { padding: 0.75rem 1rem; }
    .stat-num { font-size: 1.25rem; }
}
</style>

<div class="tasks-page">
    <!-- Header -->
    <div class="tasks-header">
        <h1 class="tasks-title">Tarefas</h1>
        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
            <i class="bi bi-plus"></i> Nova Tarefa
        </button>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-num"><?php echo $totalPendentes; ?></div>
            <div class="stat-label">Pendentes</div>
        </div>
        <div class="stat-box">
            <div class="stat-num"><?php echo $totalConcluidas; ?></div>
            <div class="stat-label">Concluídas</div>
        </div>
    </div>

    <!-- Pendentes -->
    <div class="section-title">Pendentes <span><?php echo $totalPendentes; ?></span></div>
    <div id="lista-tarefas-pendentes" class="task-list mb-4">
        <?php if (empty($tarefas_pendentes)): ?>
            <div class="empty-box">
                <div class="icon">✓</div>
                <div>Nenhuma tarefa pendente</div>
            </div>
        <?php else: ?>
            <?php foreach ($tarefas_pendentes as $tarefa): 
                $prio = strtolower($tarefa['prioridade'] ?? 'media');
                $prioClass = $prio === 'alta' ? 'high' : ($prio === 'baixa' ? 'low' : 'medium');
            ?>
            <div class="task-card" data-id="<?php echo $tarefa['id']; ?>">
                <div class="task-priority <?php echo $prioClass; ?>"></div>
                <button class="task-check btn-atualizar-status" data-id="<?php echo $tarefa['id']; ?>" data-status="concluida">
                    <i class="bi bi-check"></i>
                </button>
                <div class="task-content">
                    <h3 class="task-name"><?php echo htmlspecialchars($tarefa['descricao']); ?></h3>
                    <div class="task-meta">
                        <?php if ($tarefa['data_limite']): ?>
                        <span><i class="bi bi-calendar3"></i><?php echo date('d/m', strtotime($tarefa['data_limite'])); ?></span>
                        <?php endif; ?>
                        <?php if ($tarefa['hora_inicio']): ?>
                        <span><i class="bi bi-clock"></i><?php echo date('H:i', strtotime($tarefa['hora_inicio'])); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($tarefa['subtarefas'])): 
                            $done = count(array_filter($tarefa['subtarefas'], fn($s) => $s['status'] === 'concluida'));
                        ?>
                        <span><i class="bi bi-list-check"></i><?php echo $done . '/' . count($tarefa['subtarefas']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($tarefa['subtarefas']) || true): ?>
                    <div class="task-expand">
                        <button class="btn-expand" type="button" data-bs-toggle="collapse" data-bs-target="#sub-<?php echo $tarefa['id']; ?>">
                            <i class="bi bi-chevron-down"></i> Subtarefas
                        </button>
                        <div class="collapse" id="sub-<?php echo $tarefa['id']; ?>">
                            <div class="subtask-area">
                                <?php foreach ($tarefa['subtarefas'] ?? [] as $sub): ?>
                                <div class="subtask-item" id="subtask-item-<?php echo $sub['id']; ?>">
                                    <input type="checkbox" class="subtask-checkbox" data-id="<?php echo $sub['id']; ?>" id="chk-<?php echo $sub['id']; ?>" <?php echo $sub['status'] == 'concluida' ? 'checked' : ''; ?>>
                                    <label for="chk-<?php echo $sub['id']; ?>" class="<?php echo $sub['status'] == 'concluida' ? 'done' : ''; ?>"><?php echo htmlspecialchars($sub['descricao']); ?></label>
                                    <button class="btn-sub btn-excluir-subtarefa" data-id="<?php echo $sub['id']; ?>"><i class="bi bi-x"></i></button>
                                </div>
                                <?php endforeach; ?>
                                <form class="add-sub form-add-subtask">
                                    <input type="hidden" name="id_tarefa_principal" value="<?php echo $tarefa['id']; ?>">
                                    <input type="text" name="descricao" placeholder="Nova subtarefa..." required>
                                    <button type="submit"><i class="bi bi-plus"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="task-actions">
                    <button class="btn-action btn-editar-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-bs-toggle="modal" data-bs-target="#modalEditarTarefa"><i class="bi bi-pencil"></i></button>
                    <button class="btn-action delete btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-nome="<?php echo htmlspecialchars($tarefa['descricao']); ?>"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Concluídas -->
    <div class="section-title">Concluídas <span><?php echo $totalConcluidas; ?></span></div>
    <div id="lista-tarefas-concluidas" class="task-list">
        <?php if (empty($tarefas_concluidas)): ?>
            <div class="empty-box">
                <div class="icon">📋</div>
                <div>Nenhuma tarefa concluída</div>
            </div>
        <?php else: ?>
            <?php foreach ($tarefas_concluidas as $tarefa): ?>
            <div class="task-card done" id="task-card-<?php echo $tarefa['id']; ?>">
                <div class="task-content">
                    <h3 class="task-name"><?php echo htmlspecialchars($tarefa['descricao']); ?></h3>
                </div>
                <div class="task-actions">
                    <button class="btn-action btn-atualizar-status" data-id="<?php echo $tarefa['id']; ?>" data-status="pendente"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button class="btn-action delete btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-nome="<?php echo htmlspecialchars($tarefa['descricao']); ?>"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nova Tarefa -->
<div class="modal fade" id="modalNovaTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Tarefa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaTarefa">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control" placeholder="O que você precisa fazer?" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="Baixa">Baixa</option>
                                <option value="Média" selected>Média</option>
                                <option value="Alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="data_limite" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Início</label>
                            <input type="time" name="hora_inicio" class="form-control">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Fim</label>
                            <input type="time" name="hora_fim" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-add">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditarTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Tarefa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTarefa">
                <div class="modal-body" id="corpoModalEditar">
                    <div class="text-center p-4"><div class="spinner-border text-secondary"></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-add">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formNova = document.getElementById('formNovaTarefa');
    const formEditar = document.getElementById('formEditarTarefa');
    const modalEditarEl = document.getElementById('modalEditarTarefa');
    const corpoEditar = document.getElementById('corpoModalEditar');

    // Nova Tarefa
    formNova?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        fetch('adicionar_tarefa.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast('Sucesso!', d.message); setTimeout(() => location.reload(), 800); }
            else { showToast('Erro!', d.message, true); btn.disabled = false; }
        });
    });

    // Modal Editar
    modalEditarEl?.addEventListener('show.bs.modal', function(e) {
        const id = e.relatedTarget?.dataset.id;
        corpoEditar.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-secondary"></div></div>';
        if (!id) return;
        fetch(`buscar_tarefa_detalhes.php?id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const t = d.tarefa;
                corpoEditar.innerHTML = `
                    <input type="hidden" name="id" value="${t.id}">
                    <div class="mb-3"><label class="form-label">Descrição</label><input type="text" name="descricao" class="form-control" value="${t.descricao}" required></div>
                    <div class="row"><div class="col-6 mb-3"><label class="form-label">Prioridade</label><select name="prioridade" class="form-select"><option value="Baixa" ${t.prioridade==='Baixa'?'selected':''}>Baixa</option><option value="Média" ${t.prioridade==='Média'?'selected':''}>Média</option><option value="Alta" ${t.prioridade==='Alta'?'selected':''}>Alta</option></select></div><div class="col-6 mb-3"><label class="form-label">Data</label><input type="date" name="data_limite" class="form-control" value="${t.data_limite||''}"></div></div>
                    <div class="row"><div class="col-6 mb-3"><label class="form-label">Início</label><input type="time" name="hora_inicio" class="form-control" value="${t.hora_inicio||''}"></div><div class="col-6 mb-3"><label class="form-label">Fim</label><input type="time" name="hora_fim" class="form-control" value="${t.hora_fim||''}"></div></div>`;
            }
        });
    });

    // Editar Submit
    formEditar?.addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('atualizar_tarefa.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast('Sucesso!', d.message); setTimeout(() => location.reload(), 800); }
            else showToast('Erro!', d.message, true);
        });
    });

    // Clicks
    document.body.addEventListener('click', function(e) {
        const del = e.target.closest('.btn-excluir-tarefa');
        const status = e.target.closest('.btn-atualizar-status');
        const delSub = e.target.closest('.btn-excluir-subtarefa');

        if (del) {
            if (!confirm(`Excluir "${del.dataset.nome}"?`)) return;
            fetch('excluir_tarefa.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: del.dataset.id}) })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const card = document.querySelector(`.task-card[data-id='${del.dataset.id}']`) || document.getElementById(`task-card-${del.dataset.id}`);
                    card?.remove();
                    showToast('Sucesso!', d.message);
                }
            });
        }

        if (status) {
            fetch('atualizar_status_tarefa.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: status.dataset.id, status: status.dataset.status}) })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); });
        }

        if (delSub) {
            if (!confirm('Excluir subtarefa?')) return;
            fetch('excluir_subtarefa.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: delSub.dataset.id}) })
            .then(r => r.json())
            .then(d => { if (d.success) document.getElementById(`subtask-item-${delSub.dataset.id}`)?.remove(); });
        }
    });

    // Subtask checkbox
    document.body.addEventListener('change', function(e) {
        if (e.target.classList.contains('subtask-checkbox')) {
            const id = e.target.dataset.id;
            const status = e.target.checked ? 'concluida' : 'pendente';
            const label = e.target.nextElementSibling;
            fetch('atualizar_status_subtarefa.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id, status}) })
            .then(r => r.json())
            .then(d => { if (d.success) label.classList.toggle('done', e.target.checked); });
        }
    });

    // Add subtask
    document.body.addEventListener('submit', function(e) {
        if (e.target.classList.contains('form-add-subtask')) {
            e.preventDefault();
            const form = e.target;
            fetch('adicionar_subtarefa.php', { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const area = form.closest('.subtask-area');
                    const div = document.createElement('div');
                    div.className = 'subtask-item';
                    div.id = `subtask-item-${d.subtarefa.id}`;
                    div.innerHTML = `<input type="checkbox" class="subtask-checkbox" data-id="${d.subtarefa.id}" id="chk-${d.subtarefa.id}"><label for="chk-${d.subtarefa.id}">${d.subtarefa.descricao}</label><button class="btn-sub btn-excluir-subtarefa" data-id="${d.subtarefa.id}"><i class="bi bi-x"></i></button>`;
                    area.insertBefore(div, form);
                    form.reset();
                }
            });
        }
    });

    // Sortable
    const lista = document.getElementById('lista-tarefas-pendentes');
    if (lista?.querySelector('.task-card')) {
        new Sortable(lista, {
            animation: 150,
            ghostClass: 'opacity-50',
            onEnd: () => {
                const ordem = [...lista.querySelectorAll('.task-card')].map(c => c.dataset.id);
                fetch('atualizar_ordem_tarefas.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ordem}) });
            }
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
