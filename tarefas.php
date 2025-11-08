<?php
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

$tarefas_pendentes = [];
$tarefas_concluidas = [];
try {
    $sql_pendentes = "SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'pendente' ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC";
    $stmt_pendentes = $pdo->prepare($sql_pendentes);
    $stmt_pendentes->execute([$userId]);
    $tarefas_pendentes = $stmt_pendentes->fetchAll(PDO::FETCH_ASSOC);

    $sql_concluidas = "SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'concluida' ORDER BY data_criacao DESC";
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
        foreach ($todas_as_subtarefas as $subtarefa) { $subtarefas_mapeadas[$subtarefa['id_tarefa_principal']][] = $subtarefa; }
        foreach ($tarefas_pendentes as $key => $tarefa) { $tarefas_pendentes[$key]['subtarefas'] = $subtarefas_mapeadas[$tarefa['id']] ?? []; }
        foreach ($tarefas_concluidas as $key => $tarefa) { $tarefas_concluidas[$key]['subtarefas'] = $subtarefas_mapeadas[$tarefa['id']] ?? []; }
    }
} catch (PDOException $e) { die("Erro ao buscar tarefas: " . $e->getMessage()); }
function getPrioridadeBadge($prioridade) { switch ($prioridade) { case 'Alta': return 'bg-danger'; case 'Média': return 'bg-warning text-dark'; case 'Baixa': return 'bg-success'; default: return 'bg-secondary'; } }
?>

<style>
@media (max-width: 768px) {
  .task-card .d-flex.align-items-center {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto;
    gap: 0.5rem 1rem;
    align-items: center;
  }

  .task-card .handle {
    grid-row: span 2;
    align-self: start;
    margin-top: 0.4rem;
  }

  .task-card .btn-group-mobile {
    grid-column: 2;
    display: flex;
    gap: 0.5rem;
    width: 100%;
    justify-content: space-between;
  }

  .task-card .btn-group-mobile .btn {
    flex: 1;
    font-size: 0.85rem;
    padding: 0.4rem 0.6rem;
  }

  .task-card h5 {
    margin-bottom: 0.4rem;
    font-size: 1rem;
  }

  .task-card small.text-muted {
    font-size: 0.75rem;
  }
}


/* 🔥 Microinterações e feedback visual do drag */
.task-card {
    background: linear-gradient(145deg, #1a1a1a, #121212);
    border: 1px solid #2e2e2e;
    border-radius: 16px;
    padding: 1rem;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
}

.task-card:hover {
    transform: scale(1.015);
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.45);
    border-color: #d32f2f;
}

.task-card h5 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #f0f0f0;
    margin-bottom: 0.4rem;
}

.task-card small, .task-card .text-muted, .task-card .small {
    color: #a0a0a0 !important;
}

.task-card .badge {
    font-size: 0.75rem;
    padding: 0.35em 0.6em;
    border-radius: 8px;
    font-weight: 500;
}

.task-card .btn {
    border-radius: 8px;
    padding: 0.35rem 0.5rem;
}

.task-card .btn-outline-primary {
    border-color: #0d6efd;
    color: #0d6efd;
}
.task-card .btn-outline-primary:hover {
    background-color: #0d6efd;
    color: white;
}
.task-card .btn-outline-danger:hover {
    background-color: #dc3545;
    color: white;
}
.task-card .btn-outline-success:hover {
    background-color: #198754;
    color: white;
}
.handle { cursor: grab; }
.handle:active { cursor: grabbing; }

/* Estado durante o arrasto (SortableJS) */
.sortable-ghost { opacity: .35 !important; transform: rotate(.5deg) scale(.98); }
.sortable-chosen { box-shadow: 0 12px 28px rgba(0,0,0,.45) !important; transform: scale(1.02) !important; }
.sortable-drag { opacity: .9 !important; }

/* Linha de inserção sutil */
.sortable-insert-marker {
    height: 10px; border-radius: 999px;
    background: linear-gradient(90deg, #d32f2f, #ff6f61);
    margin: 6px 0;
    box-shadow: 0 0 18px rgba(211,47,47,.45);
}

/* Badges de prioridade com glow */
.prioridade-Alta .badge.bg-danger { box-shadow: 0 0 0 2px rgba(211,47,47,.25); }
.prioridade-Média .badge.bg-warning { box-shadow: 0 0 0 2px rgba(255,193,7,.25); }
.prioridade-Baixa .badge.bg-success { box-shadow: 0 0 0 2px rgba(25,135,84,.25); }

/* Chevron anima ao expandir subtarefas */
.btn[data-bs-toggle="collapse"] i { transition: transform .2s ease; }
.btn[aria-expanded="true"] i { transform: rotate(180deg); }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Minhas Tarefas</h4>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
        <i class="bi bi-plus-lg"></i> Nova Tarefa
            </button>
        </div>
        

<h4 class="mb-3">Pendentes</h4>
<div id="lista-tarefas-pendentes" class="mb-5">
    <?php if(empty($tarefas_pendentes)): ?><div id="empty-state-pendentes" class="card card-body text-center text-muted" style="background-color: #1f1f1f; border: 1px solid #303030;">Nenhuma tarefa pendente. Bom trabalho!</div><?php else: ?><?php foreach($tarefas_pendentes as $tarefa): ?><div class="card task-card prioridade-<?php echo $tarefa['prioridade']; ?> mb-3" data-id="<?php echo $tarefa['id']; ?>"><div class="card-body"><div class="d-flex align-items-center"><i class="bi bi-grip-vertical handle me-3 fs-4 text-muted"></i><div class="flex-grow-1"><h5 class="mb-1"><?php echo htmlspecialchars($tarefa['descricao']); ?></h5>
<div class="d-flex flex-wrap gap-2 align-items-center text-muted small">
    <span class="badge <?php echo getPrioridadeBadge($tarefa['prioridade']); ?>">
        <?php echo $tarefa['prioridade']; ?>
                                </span>
    
                                <?php if ($tarefa['data_limite']): ?>
        <span><i class="bi bi-calendar-event me-1"></i> <?php echo date('d/m/Y', strtotime($tarefa['data_limite'])); ?></span>
    <?php endif; ?>

    <?php if ($tarefa['hora_inicio'] || $tarefa['hora_fim']): ?>
                                    <span>
            <i class="bi bi-clock me-1"></i>
            <?php
                $inicio = $tarefa['hora_inicio'] ? date('H:i', strtotime($tarefa['hora_inicio'])) : '--:--';
                $fim = $tarefa['hora_fim'] ? date('H:i', strtotime($tarefa['hora_fim'])) : '--:--';
                echo "{$inicio} - {$fim}";
            ?>
                                    </span>
                                <?php endif; ?>
                            </div>
</div><div class="ms-3"><div class="btn-group-mobile">
    <button class="btn btn-sm btn-outline-success btn-atualizar-status" data-id="<?php echo $tarefa['id']; ?>" data-status="concluida" title="Concluir Tarefa">
                                <i class="bi bi-check-lg"></i>
                            </button>
    <button class="btn btn-sm btn-outline-primary btn-editar-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-bs-toggle="modal" data-bs-target="#modalEditarTarefa" title="Editar Tarefa">
        <i class="bi bi-pencil"></i>
    </button>
    <button class="btn btn-sm btn-outline-danger btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-nome="<?php echo htmlspecialchars($tarefa['descricao']); ?>" title="Excluir Tarefa">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
</div></div><button class="btn btn-link btn-sm text-decoration-none text-muted mt-2 p-0" type="button" data-bs-toggle="collapse" data-bs-target="#subtarefas-<?php echo $tarefa['id']; ?>"><i class="bi bi-chevron-down"></i> Detalhes e Subtarefas</button><div class="collapse mt-3" id="subtarefas-<?php echo $tarefa['id']; ?>"><hr style="border-color: #303030;"><?php if ($tarefa['tempo_estimado'] > 0): $h = floor($tarefa['tempo_estimado'] / 60); $m = $tarefa['tempo_estimado'] % 60; $tf = ''; if ($h > 0) $tf .= $h . 'h '; if ($m > 0) $tf .= $m . 'min'; ?><p class="text-muted mb-2 ps-4"><i class="bi bi-clock"></i> Tempo Estimado: <?php echo trim($tf); ?></p><?php endif; ?><div class="subtask-list ps-4"><?php if (!empty($tarefa['subtarefas'])): ?><?php foreach($tarefa['subtarefas'] as $sub): ?><div class="subtask-item" id="subtask-item-<?php echo $sub['id']; ?>"><div class="form-check"><input class="form-check-input subtask-checkbox" type="checkbox" data-id="<?php echo $sub['id']; ?>" id="sub-<?php echo $sub['id']; ?>" <?php echo ($sub['status'] == 'concluida') ? 'checked' : ''; ?>><label class="form-check-label <?php echo ($sub['status'] == 'concluida') ? 'text-decoration-line-through text-muted' : ''; ?>" for="sub-<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['descricao']); ?></label></div><div class="subtask-actions"><button class="btn btn-sm btn-outline-primary btn-editar-subtarefa" data-id="<?php echo $sub['id']; ?>" data-descricao="<?php echo htmlspecialchars($sub['descricao']); ?>"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-outline-danger btn-excluir-subtarefa" data-id="<?php echo $sub['id']; ?>"><i class="bi bi-x-lg"></i></button></div></div><?php endforeach; ?><?php endif; ?></div><form class="d-flex mt-3 ps-4 form-add-subtask" action="adicionar_subtarefa.php" method="POST"><input type="hidden" name="id_tarefa_principal" value="<?php echo $tarefa['id']; ?>"><input type="text" name="descricao" class="form-control form-control-sm" placeholder="Adicionar nova subtarefa..." required><button type="submit" class="btn btn-sm btn-secondary ms-2">Add</button></form></div></div></div><?php endforeach; ?><?php endif; ?>
</div>

<h4 class="mb-3 mt-5">Concluídas</h4>
<div id="lista-tarefas-concluidas">
    <?php if(empty($tarefas_concluidas)): ?><div id="empty-state-concluidas" class="card card-body text-center text-muted" style="background-color: #1f1f1f; border: 1px solid #303030;">Nenhuma tarefa foi concluída.</div><?php else: ?><?php foreach($tarefas_concluidas as $tarefa): ?><div class="card task-card mb-3 opacity-50" id="task-card-<?php echo $tarefa['id']; ?>"><div class="card-body"><div class="d-flex align-items-center"><div class="flex-grow-1"><h5 class="mb-1 text-decoration-line-through text-muted"><?php echo htmlspecialchars($tarefa['descricao']); ?></h5></div><div class="ms-3"><button class="btn btn-sm btn-outline-warning btn-atualizar-status" data-id="<?php echo $tarefa['id']; ?>" data-status="pendente" title="Restaurar Tarefa"><i class="bi bi-arrow-counterclockwise"></i></button><button class="btn btn-sm btn-outline-danger btn-excluir-tarefa" data-id="<?php echo $tarefa['id']; ?>" data-nome="<?php echo htmlspecialchars($tarefa['descricao']); ?>" title="Excluir Tarefa"><i class="bi bi-trash"></i></button></div></div></div></div><?php endforeach; ?><?php endif; ?>
</div>

<div class="modal fade" id="modalNovaTarefa" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Adicionar Nova Tarefa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formNovaTarefa" action="adicionar_tarefa.php" method="POST"><div class="modal-body"><div class="mb-3"><label class="form-label">Descrição</label><input type="text" name="descricao" class="form-control" required></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Prioridade</label><select name="prioridade" class="form-select"><option value="Baixa">Baixa</option><option value="Média" selected>Média</option><option value="Alta">Alta</option></select></div><div class="col-md-6 mb-3"><label class="form-label">Data Limite</label><input type="date" name="data_limite" class="form-control"></div></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Hora Início</label><input type="time" name="hora_inicio" class="form-control"></div><div class="col-md-6 mb-3"><label class="form-label">Hora Fim</label><input type="time" name="hora_fim" class="form-control"></div></div><div class="input-group"><input type="number" name="tempo_horas" class="form-control" min="0" placeholder="Horas"><span class="input-group-text">h</span><input type="number" name="tempo_minutos" class="form-control" min="0" max="59" placeholder="Minutos"><span class="input-group-text">min</span></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar Tarefa</button></div></form></div></div></div>
<div class="modal fade" id="modalEditarTarefa" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar Tarefa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formEditarTarefa" action="atualizar_tarefa.php" method="POST"><div class="modal-body" id="corpoModalEditar"><div class="text-center p-5"><div class="spinner-border text-danger"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar Alterações</button></div></form></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>


<script>
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, function(tag) {
        const chars = { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' };
        return chars[tag] || tag;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    AOS.init({ duration: 600, once: true });

    const modalNovaTarefa = new bootstrap.Modal(document.getElementById('modalNovaTarefa'));
    const modalEditarTarefaEl = document.getElementById('modalEditarTarefa');
    const modalEditarTarefa = new bootstrap.Modal(modalEditarTarefaEl);
    const formNovaTarefa = document.getElementById('formNovaTarefa');
    const formEditarTarefa = document.getElementById('formEditarTarefa');
    const corpoModalEditar = document.getElementById('corpoModalEditar');

    if (formNovaTarefa) {
        formNovaTarefa.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(formNovaTarefa);
            const button = formNovaTarefa.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Salvando...`;
            fetch('adicionar_tarefa.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) { showToast('Sucesso!', data.message); setTimeout(() => window.location.reload(), 1000); }
                else { showToast('Erro!', data.message, true); button.disabled = false; button.innerHTML = 'Salvar Tarefa'; }
            }).catch(error => { showToast('Erro de Rede!', 'Não foi possível se conectar.', true); button.disabled = false; button.innerHTML = 'Salvar Tarefa'; });
        });
    }

    if (modalEditarTarefaEl) {
    modalEditarTarefaEl.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const tarefaId = button ? button.dataset.id : null;
        corpoModalEditar.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-danger"></div></div>';

        if (!tarefaId) {
            corpoModalEditar.innerHTML = '<p class="text-danger">ID da tarefa não encontrado!</p>';
            return;
        }

        fetch(`buscar_tarefa_detalhes.php?id=${tarefaId}`)
        .then(response => response.json())
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="Baixa" ${t.prioridade==='Baixa'?'selected':''}>Baixa</option>
                                <option value="Média" ${t.prioridade==='Média'?'selected':''}>Média</option>
                                <option value="Alta" ${t.prioridade==='Alta'?'selected':''}>Alta</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Limite</label>
                            <input type="date" name="data_limite" class="form-control" value="${t.data_limite ?? ''}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora Início</label>
                            <input type="time" name="hora_inicio" class="form-control" value="${t.hora_inicio ?? ''}">
                </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hora Fim</label>
                            <input type="time" name="hora_fim" class="form-control" value="${t.hora_fim ?? ''}">
                </div>
        </div>
					<div class="mb-3">
                        <label class="form-label">Tempo Estimado</label>
                        <div class="input-group">
                            <input type="number" name="tempo_horas" class="form-control" min="0" value="${h}">
                            <span class="input-group-text">h</span>
                            <input type="number" name="tempo_minutos" class="form-control" min="0" max="59" value="${m}">
                            <span class="input-group-text">min</span>
					</div>
                    </div>`;
    } else {
                corpoModalEditar.innerHTML = `<p class="text-danger">${data.message}</p>`;
        }
    })
    .catch(err => {
            corpoModalEditar.innerHTML = `<p class="text-danger">Erro de rede.</p>`;
        });
    });
}

    if (formEditarTarefa) {
        formEditarTarefa.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarTarefa);
            const button = formEditarTarefa.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            fetch('atualizar_tarefa.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) { showToast('Sucesso!', data.message); setTimeout(() => window.location.reload(), 1000); }
                else { showToast('Erro!', data.message, true); }
            }).catch(error => showToast('Erro de Rede!', 'Não foi possível conectar.', true)).finally(() => { button.disabled = false; button.innerHTML = 'Salvar Alterações'; modalEditarTarefa.hide(); });
        });
    }

    document.body.addEventListener('click', function(event) {
        const target = event.target;
        const deleteButton = target.closest('.btn-excluir-tarefa');
        const statusButton = target.closest('.btn-atualizar-status');
        const deleteSubtaskButton = target.closest('.btn-excluir-subtarefa');
        const editSubtaskButton = target.closest('.btn-editar-subtarefa');

        if (deleteButton) {
            const tarefaId = deleteButton.dataset.id;
            const tarefaNome = deleteButton.dataset.nome;
            Swal.fire({ title: 'Tem certeza?', text: `Excluir a tarefa "${tarefaNome}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d', confirmButtonText: 'Sim, excluir!', cancelButtonText: 'Cancelar', background: '#222', color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('excluir_tarefa.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: tarefaId }) })
                    .then(res => res.json()).then(data => {
                        if (data.success) { showToast('Sucesso!', data.message); const card = document.querySelector(`.task-card[data-id='${tarefaId}']`); if(card) { gsap.to(card, {duration: 0.5, opacity: 0, x: -50, onComplete: () => card.remove()}); } }
                        else { showToast('Erro!', data.message, true); }
                    }).catch(err => showToast('Erro de Rede!', 'Não foi possível conectar.', true));
                }
            });
        }
        if (statusButton) {
            const tarefaId = statusButton.dataset.id;
            const novoStatus = statusButton.dataset.status;
            const taskCard = statusButton.closest('.task-card');
            fetch('atualizar_status_tarefa.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: tarefaId, status: novoStatus }) })
            .then(res => res.json()).then(data => {
                if (data.success) { showToast('Sucesso!', data.message); gsap.to(taskCard, {duration: 0.5, opacity: 0, onComplete: () => window.location.reload()}); }
                else { showToast('Erro!', data.message, true); }
            }).catch(err => showToast('Erro de Rede!', 'Não foi possível conectar.', true));
        }
        if (deleteSubtaskButton) {
            const subtaskId = deleteSubtaskButton.dataset.id;
            const subtaskItem = document.getElementById(`subtask-item-${subtaskId}`);
            Swal.fire({ title: 'Excluir subtarefa?', text: "Esta ação não pode ser desfeita.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d', confirmButtonText: 'Sim, excluir!', cancelButtonText: 'Cancelar', background: '#222', color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('excluir_subtarefa.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: subtaskId }) })
                    .then(response => response.json()).then(data => {
                        if (data.success) { showToast('Sucesso!', data.message); if (subtaskItem) { gsap.to(subtaskItem, { duration: 0.5, opacity: 0, x: 20, onComplete: () => subtaskItem.remove() }); } }
                        else { showToast('Erro!', data.message, true); }
                    }).catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true));
        }
    });
}
        if (editSubtaskButton) {
            const subtaskId = editSubtaskButton.dataset.id;
            const currentDesc = editSubtaskButton.dataset.descricao;
            Swal.fire({
                title: 'Editar Subtarefa', input: 'text', inputValue: currentDesc, showCancelButton: true,
                confirmButtonText: 'Salvar', cancelButtonText: 'Cancelar', background: '#222', color: '#fff',
                inputValidator: (value) => { if (!value) { return 'Você precisa escrever algo!' } }
            }).then((result) => {
                if (result.isConfirmed) {
                    const newDesc = result.value;
                    fetch('atualizar_subtarefa.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: subtaskId, descricao: newDesc }) })
                    .then(response => response.json()).then(data => {
            if (data.success) {
                            showToast('Sucesso!', data.message);
                            const label = document.querySelector(`#subtask-item-${subtaskId} .form-check-label`);
                            const button = document.querySelector(`.btn-editar-subtarefa[data-id='${subtaskId}']`);
                            if (label) label.textContent = newDesc;
                            if (button) button.dataset.descricao = newDesc;
            } else {
                            showToast('Erro!', data.message, true);
                        }
                    }).catch(error => showToast('Erro de Rede!', 'Não foi possível salvar.', true));
                }
            });
        }
    });

    document.body.addEventListener('submit', function(event){ if(event.target.classList.contains('form-add-subtask')){ event.preventDefault(); const form = event.target; const formData = new FormData(form); const button = form.querySelector('button[type="submit"]'); button.disabled = true; button.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`; fetch('adicionar_subtarefa.php', { method: 'POST', body: formData }).then(r => r.json()).then(d => { if (d.success) { showToast('Sucesso!', d.message); const list = form.previousElementSibling; const newEl = document.createElement('div'); newEl.className = 'subtask-item'; newEl.id = `subtask-item-${d.subtarefa.id}`; newEl.innerHTML = `<div class="form-check"><input class="form-check-input subtask-checkbox" type="checkbox" data-id="${d.subtarefa.id}" id="sub-${d.subtarefa.id}"><label class="form-check-label" for="sub-${d.subtarefa.id}">${escapeHTML(d.subtarefa.descricao)}</label></div><div class="subtask-actions"><button class="btn btn-sm btn-outline-primary btn-editar-subtarefa" data-id="${d.subtarefa.id}" data-descricao="${escapeHTML(d.subtarefa.descricao)}"><i class="bi bi-pencil"></i></button><button class="btn btn-sm btn-outline-danger btn-excluir-subtarefa" data-id="${d.subtarefa.id}"><i class="bi bi-x-lg"></i></button></div>`; list.appendChild(newEl); gsap.from(newEl, { duration: 0.5, opacity: 0, x: -20, ease: 'power3.out' }); form.reset(); } else { showToast('Erro!', d.message, true); } }).catch(err => showToast('Erro de Rede!', 'Não foi possível conectar.', true)).finally(() => { button.disabled = false; button.innerHTML = 'Add'; }); } });

    document.body.addEventListener('change', function(event) { if (event.target.classList.contains('subtask-checkbox')) { const checkbox = event.target; const subtaskId = checkbox.dataset.id; const isChecked = checkbox.checked; const novoStatus = isChecked ? 'concluida' : 'pendente'; const label = checkbox.nextElementSibling; fetch('atualizar_status_subtarefa.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: subtaskId, status: novoStatus }) }).then(response => response.json()).then(data => { if (data.success) { label.classList.toggle('text-decoration-line-through', isChecked); label.classList.toggle('text-muted', isChecked); } else { showToast('Erro!', data.message, true); checkbox.checked = !isChecked; } }).catch(error => { showToast('Erro de Rede!', 'Não foi possível se conectar.', true); checkbox.checked = !isChecked; }); } });

    const listaTarefas = document.getElementById('lista-tarefas-pendentes');
    if (listaTarefas) {
        // Placeholder dinâmico entre cards
        let marker = document.createElement('div');
        marker.className = 'sortable-insert-marker';

        // Debounce opcional para reduzir writes no backend
        const debounce = (fn, ms=220) => {
            let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this,args), ms); };
        };

        const persistOrder = () => {
            const items = listaTarefas.querySelectorAll('.task-card');
            const novaOrdem = Array.from(items).map(item => item.dataset.id);
            fetch('atualizar_ordem_tarefas.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ordem: novaOrdem })
            })
            .then(res => res.json())
            .then(data => { if (!data.success) console.error('Falha ao salvar a ordem.'); })
            .catch(err => console.error('Erro de rede ao salvar ordem.'));
        };

        const persistOrderDebounced = debounce(persistOrder, 250);

        new Sortable(listaTarefas, {
            animation: 160,
            handle: '.handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            forceFallback: false,
            fallbackOnBody: true,
            swapThreshold: 0.45,
            easing: 'cubic-bezier(.2,.8,.2,1)',
            onStart: function (evt) {
                const el = evt.item;
                gsap.to(el, { duration: .16, scale: 1.02, boxShadow: '0 14px 30px rgba(0,0,0,.5)' });
                if (navigator.vibrate) navigator.vibrate(6);
            },
            onMove: function (evt) {
                const to = evt.to;
                // Inserir marcador visual na posição alvo
                const related = evt.related;
                if (!related) return;
                // Insere acima ou abaixo conforme texto do Sortable
                if (evt.willInsertAfter) {
                    related.after(marker);
                } else {
                    related.before(marker);
                }
            },
            onEnd: function (evt) {
                const el = evt.item;
                marker.remove();
                gsap.fromTo(el, { scale: 1.02 }, { duration: .18, scale: 1, boxShadow: '0 8px 24px rgba(0,0,0,.35)' });
                persistOrderDebounced();
                if (navigator.vibrate) navigator.vibrate(5);
            }
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?> 
