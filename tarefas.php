<?php
// tarefas.php — Gerenciador de Tarefas
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

echo '<link rel="stylesheet" href="' . asset('tarefas.css') . '">';

$tarefas_pendentes = [];
$tarefas_concluidas = [];

try {
    $pdo->exec("ALTER TABLE tarefas ADD COLUMN data_conclusao DATETIME DEFAULT NULL");
} catch (PDOException $e) { /* Já existe */ }

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

    // Stats extras
    $dataHoje = date('Y-m-d');
    $stmt_hoje = $pdo->prepare("SELECT COUNT(*) FROM tarefas WHERE id_usuario = ? AND status = 'concluida' AND DATE(data_conclusao) = ?");
    $stmt_hoje->execute([$userId, $dataHoje]);
    $tarefas_concluidas_hoje = (int)$stmt_hoje->fetchColumn();

    $stmt_alta = $pdo->prepare("SELECT COUNT(*) FROM tarefas WHERE id_usuario = ? AND status = 'pendente' AND prioridade = 'Alta'");
    $stmt_alta->execute([$userId]);
    $tarefas_alta = (int)$stmt_alta->fetchColumn();

} catch (PDOException $e) {
    die("Erro ao buscar tarefas: " . $e->getMessage());
}

$totalPendentes  = count($tarefas_pendentes);
$totalTarefas    = $totalPendentes + count($tarefas_concluidas);
$progresso       = $totalTarefas > 0 ? round(($tarefas_concluidas_hoje / max($totalTarefas, 1)) * 100) : 0;

?>

<div class="tasks-container pt-4">
    <!-- Cabeçalho -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Tarefas</h1>
            <p class="page-subtitle"><?php echo date('l, d \d\e F', strtotime($dataHoje)); ?></p>
        </div>
        <div class="header-actions">
            <button class="btn-elite-primary" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                <i class="bi bi-plus-lg"></i> Nova Tarefa
            </button>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card stat-pendentes">
            <div class="stat-icon-wrap"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <span class="stat-value"><?php echo $totalPendentes; ?></span>
                <span class="stat-label">Pendentes</span>
            </div>
        </div>
        <div class="stat-card stat-alta">
            <div class="stat-icon-wrap"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div>
                <span class="stat-value"><?php echo $tarefas_alta; ?></span>
                <span class="stat-label">Alta Prioridade</span>
            </div>
        </div>
        <div class="stat-card stat-concluidas">
            <div class="stat-icon-wrap"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <span class="stat-value"><?php echo $tarefas_concluidas_hoje; ?></span>
                <span class="stat-label">Concluídas Hoje</span>
            </div>
        </div>
        <div class="stat-card stat-progresso">
            <div class="stat-icon-wrap"><i class="bi bi-bar-chart-fill"></i></div>
            <div>
                <span class="stat-value"><?php echo $progresso; ?>%</span>
                <span class="stat-label">Progresso</span>
            </div>
            <div class="stat-progress-bar"><div class="stat-progress-fill" style="width:<?php echo $progresso; ?>%"></div></div>
        </div>
    </div>

    <!-- Busca e Filtros -->
    <div class="filter-bar">
        <div class="search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" class="search-field" placeholder="Buscar tarefa...">
        </div>
        <div class="filter-pills">
            <button class="pill active" data-filter="todas">Todas</button>
            <button class="pill" data-filter="Alta"><span class="dot dot-alta"></span>Alta</button>
            <button class="pill" data-filter="Média"><span class="dot dot-media"></span>Média</button>
            <button class="pill" data-filter="Baixa"><span class="dot dot-baixa"></span>Baixa</button>
        </div>
    </div>

    <!-- Seção: Pendentes -->
    <div class="section-heading">
        <i class="bi bi-list-check"></i> A Fazer
        <span class="section-count"><?php echo $totalPendentes; ?></span>
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
                <button class="btn-toggle-sub" type="button" data-bs-toggle="collapse" data-bs-target="#subtasks-<?php echo $tarefa['id']; ?>" aria-expanded="false">
                    <i class="bi bi-chevron-down"></i> <?php echo $hasSubtasks ? 'Ver Subtarefas ('. count($tarefa['subtarefas']) .')' : 'Adicionar Subtarefas'; ?>
                </button>

                <!-- Área de Subtarefas -->
                <div class="collapse" id="subtasks-<?php echo $tarefa['id']; ?>">
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
    <div class="section-heading mt-4">
        <i class="bi bi-check2-all"></i> Concluídas Recentemente
        <span class="section-count"><?php echo count($tarefas_concluidas); ?></span>
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
                <button type="button" class="btn-cancel-elite" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-elite-primary">Criar Tarefa</button>
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
                    <button type="button" class="btn-cancel-elite" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-elite-primary">Salvar Alterações</button>
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
        background: '#161618',
        color: '#fff',
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // --- Busca e Filtros ---
    const searchInput = document.getElementById('searchInput');
    const filterPills = document.querySelectorAll('.pill[data-filter]');
    let currentFilter = 'todas';

    function applyFilters() {
        const q = (searchInput?.value || '').toLowerCase();
        document.querySelectorAll('#lista-tarefas-pendentes .task-card').forEach(card => {
            const title = card.querySelector('.task-title')?.textContent.toLowerCase() || '';
            const prio  = (card.dataset.prio || '').trim();
            const matchSearch = !q || title.includes(q);
            const matchFilter = currentFilter === 'todas' || prio === currentFilter;
            card.style.display = (matchSearch && matchFilter) ? '' : 'none';
        });
    }

    searchInput?.addEventListener('input', applyFilters);

    filterPills.forEach(btn => {
        btn.addEventListener('click', () => {
            filterPills.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            applyFilters();
        });
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
                background: '#161618',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#e50914',
                cancelButtonColor: '#2c2c2e',
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
