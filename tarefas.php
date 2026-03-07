<?php
// tarefas.php - Design Premium & Funcionalidade Aprimorada
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

// CSS para esta página com cache buster inteligente
echo '<link rel="stylesheet" href="' . asset('tarefas.css') . '">';

$tarefas_pendentes = [];
$tarefas_concluidas = [];

try {
    // Garante que a coluna data_conclusao exista
    $pdo->exec("ALTER TABLE tarefas ADD COLUMN data_conclusao DATETIME DEFAULT NULL");
} catch (PDOException $e) { /* Já existe */ }

try {
    // FORÇAR CRIAÇÃO DA COLUNA DIAS_SEMANA
    $pdo->exec("ALTER TABLE rotinas_fixas ADD COLUMN dias_semana VARCHAR(20) DEFAULT NULL");
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
            <?php 
                $diaSemanaHoje = date('w') + 1; // MySQL DAYOFWEEK: 1=Sun...7=Sat
                foreach ($rotinasFixas as $rotina):
                    $isConcluido = ($rotina['status_hoje'] === 'concluido');
                    $hojeAgendado = (empty($rotina['dias_semana']) || strpos($rotina['dias_semana'], (string)$diaSemanaHoje) !== false);
            ?>
            <div class="card-lux-habit <?= $isConcluido ? 'concluido' : '' ?> <?= !$hojeAgendado ? 'opacity-50' : '' ?>" 
                 data-id="<?= $rotina['id']; ?>" 
                 data-controle-id="<?= $rotina['controle_id'] ?? ''; ?>">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <div class="d-flex gap-2 align-items: center;">
                        <span class="prio-pill prio-<?= $rotina['prioridade'] ?>">
                            <?= $rotina['prioridade'] ?>
                        </span>
                        <?php if (!$hojeAgendado): ?>
                            <span class="badge rounded-pill bg-secondary text-white-50" style="font-size: 0.65rem; padding: 0.4rem 0.6rem;">Fora da Agenda</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($rotina['horario_sugerido']): ?>
                        <span style="color: var(--text-secondary); font-size: 0.85rem;">
                            <i class="bi bi-clock me-1"></i> <?= date('H:i', strtotime($rotina['horario_sugerido'])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h4 style="margin: 0 0 0.5rem 0; font-size: 1.15rem; color: var(--text-primary); <?= !$hojeAgendado ? 'font-style: italic;' : ''?>">
                    <?= htmlspecialchars($rotina['nome']) ?>
                </h4>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 40px; opacity: 0.7;">
                    <?= htmlspecialchars($rotina['descricao']) ?>
                </p>

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-bottom:0.5rem;">
                     <button class="btn-icon edit" onclick="window.location.href='editar_rotina_fixa.php?id=<?= $rotina['id'] ?>'" title="Editar"><i class="bi bi-pencil"></i></button>
                     <button class="btn-icon delete" onclick="excluirRotina(<?= $rotina['id'] ?>, '<?= addslashes($rotina['nome']) ?>')" title="Excluir"><i class="bi bi-trash"></i></button>
                </div>

                <?php if ($hojeAgendado): ?>
                    <button class="btn-complete-lux <?= $isConcluido ? 'is-done' : '' ?>" onclick="toggleRotina(<?= $rotina['id'] ?>, '<?= $rotina['status_hoje'] ?? 'pendente' ?>')">
                        <?= $isConcluido ? '<i class="bi bi-check-circle-fill me-2"></i> Concluído' : 'Marcar como feito' ?>
                    </button>
                <?php else: ?>
                    <button class="btn-complete-lux border-white-5 opacity-25" disabled title="Não agendado para hoje">
                        Indisponível hoje
                    </button>
                <?php endif; ?>
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
                        <style>
                            .dias-selecao .btn-check:checked + .btn {
                                background-color: #e50914 !important;
                                border-color: #e50914 !important;
                                color: white !important;
                                font-weight: bold;
                                box-shadow: 0 0 15px rgba(229, 9, 20, 0.6);
                            }
                            .dias-selecao .btn-outline-light {
                                border-color: rgba(255,255,255,0.3) !important;
                                color: #ffffff !important;
                                background-color: rgba(255,255,255,0.05);
                            }
                            .dias-selecao .btn-outline-light:hover {
                                background-color: rgba(255,255,255,0.1);
                            }
                            .dia-item {
                                min-width: 48px;
                                flex: 1;
                            }
                            #novaRotinaModal .form-label {
                                color: #ffffff !important;
                                font-weight: 600 !important;
                                opacity: 1 !important;
                                margin-bottom: 8px;
                            }
                        </style>
                        <div class="d-flex flex-wrap gap-2 dias-selecao">
                            <?php 
                            $diasNome = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                            for($i=1; $i<=7; $i++): 
                            ?>
                            <div class="dia-item text-center">
                                <input type="checkbox" class="btn-check" name="dias_semana[]" id="dia_<?= $i ?>" value="<?= $i ?>">
                                <label class="btn btn-outline-light w-100 py-2 px-0" for="dia_<?= $i ?>" style="font-size: 0.85rem; transition: all 0.2s;"><?= $diasNome[$i-1] ?></label>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <small class="text-white-50 d-block mt-1">Se nenhum for selecionado, aparecerá todos os dias.</small>
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
