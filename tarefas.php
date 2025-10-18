<?php
require_once 'templates/header.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit;
}

$dataHoje = date('Y-m-d');

// ===== BUSCAR DADOS =====
$rotinas = [];
$tarefas = [];
$subtarefasPorTarefa = [];
$stats = ['Alta' => 0, 'Média' => 0, 'Baixa' => 0, 'total' => 0];

try {
    // 1. Rotinas Fixas
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
} catch (Exception $e) {
    error_log("Erro ao buscar rotinas: " . $e->getMessage());
}

try {
    // 2. Tarefas Pendentes
    $stmt = $pdo->prepare("
        SELECT id, descricao, prioridade, data_limite, status, data_criacao
        FROM tarefas 
        WHERE id_usuario = ? AND status = 'pendente'
        ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), data_limite, data_criacao DESC
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['total'] = count($tarefas);
    foreach ($tarefas as $t) {
        $stats[$t['prioridade']]++;
    }
} catch (Exception $e) {
    error_log("Erro ao buscar tarefas: " . $e->getMessage());
}

try {
    // 3. Subtarefas
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
} catch (Exception $e) {
    error_log("Erro ao buscar subtarefas: " . $e->getMessage());
}

$rotinas_concluidas = count(array_filter($rotinas, fn($r) => $r['status_hoje'] === 'concluido'));
$rotinas_total = count($rotinas);
?>

<!DOCTYPE html>
<html lang="pt-BR" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarefas - Painel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/tarefas.css">
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1><i class="bi bi-check2-all"></i> Tarefas</h1>
            
            <div class="stats">
                <div class="stat">
                    <span class="stat-value" style="color: #ff6b6b;"><?php echo $stats['Alta']; ?></span>
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
                <button class="btn" onclick="abrirModalTarefa()">
                    <i class="bi bi-plus"></i> Nova Tarefa
                </button>
                <button class="btn secondary" onclick="abrirModalRotina()">
                    <i class="bi bi-plus"></i> Nova Rotina
                </button>
                <button class="btn-theme" onclick="toggleTheme()" title="Dark/Light">
                    <i class="bi bi-moon-fill"></i>
                </button>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="search-bar">
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Buscar tarefas...">
            <select id="filterPriority" class="filter-select">
                <option value="">Todas as prioridades</option>
                <option value="Alta">Alta</option>
                <option value="Média">Média</option>
                <option value="Baixa">Baixa</option>
            </select>
        </div>

        <!-- ROTINAS -->
        <div class="section">
            <div class="section-title">
                <i class="bi bi-calendar-check"></i>
                Rotinas Fixas (<?php echo $rotinas_concluidas; ?>/<?php echo $rotinas_total; ?>)
        </div>
        
            <div class="items-list">
                <?php if (empty($rotinas)): ?>
            <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <p>Nenhuma rotina configurada</p>
            </div>
        <?php else: ?>
                    <?php foreach ($rotinas as $rotina): ?>
                        <div class="item rotina <?php echo $rotina['status_hoje'] === 'concluido' ? 'concluido' : ''; ?>">
                            <input type="checkbox" class="item-checkbox" 
                                   <?php echo $rotina['status_hoje'] === 'concluido' ? 'checked' : ''; ?>
                                   onchange="completarRotina(<?php echo $rotina['controle_id']; ?>)">
                            
                            <div class="item-content">
                                <div class="item-title"><?php echo htmlspecialchars($rotina['nome']); ?></div>
                                <div class="item-meta">
                                    <?php if ($rotina['horario_sugerido']): ?>
                                        <span><i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($rotina['horario_sugerido'])); ?></span>
                                <?php endif; ?>
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

        <!-- TAREFAS -->
        <div class="section">
            <div class="section-title">
                <i class="bi bi-list-check"></i>
                Tarefas Pendentes
        </div>
        
            <div class="items-list">
                <?php if (empty($tarefas)): ?>
            <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <p>Sem tarefas pendentes! Você está em dia!</p>
            </div>
        <?php else: ?>
                    <?php foreach ($tarefas as $task): ?>
                        <div class="item" data-task-id="<?php echo $task['id']; ?>" draggable="true">
                            <input type="checkbox" class="item-checkbox" onchange="completarTarefa(<?php echo $task['id']; ?>)">
                            
                            <div class="item-content">
                                <div class="item-title"><?php echo htmlspecialchars($task['descricao']); ?></div>
                                <div class="item-meta">
                                    <span class="badge badge-<?php echo strtolower($task['prioridade']); ?>">
                                        <?php echo $task['prioridade']; ?>
                                </span>
                                    <?php if ($task['data_limite']): ?>
                                        <span><i class="bi bi-calendar"></i> <?php echo date('d/m', strtotime($task['data_limite'])); ?></span>
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

                        <!-- SUBTAREFAS - NOVO LAYOUT -->
                        <?php $subs = $subtarefasPorTarefa[$task['id']] ?? []; ?>
                    <?php if (!empty($subs)): ?>
                            <div class="subtasks-container">
                                <!-- Header com Expandir/Colapsar -->
                                <div class="subtasks-toolbar">
                                    <button class="subtasks-toggle" onclick="toggleSubtarefasVisibilidade(this)" title="Expandir/Recolher">
                                <i class="bi bi-chevron-down"></i>
                                        <span>Subtarefas</span>
                                        <span class="subtasks-count">(<?php 
                                            $concluidas = count(array_filter($subs, fn($s) => $s['status'] === 'concluida'));
                                            echo $concluidas . '/' . count($subs);
                                        ?>)</span>
                            </button>
                                    <button class="btn-icon btn-add-subtask" onclick="abrirModalSubtarefa(<?php echo $task['id']; ?>)" title="Adicionar Subtarefa">
                                        <i class="bi bi-plus"></i>
                                    </button>
                        </div>

                                <!-- Lista de Subtarefas -->
                                <div class="subtasks-content">
                            <?php foreach ($subs as $sub): ?>
                                        <div class="subtask-row <?php echo $sub['status'] === 'concluida' ? 'completed' : ''; ?>">
                                            <input type="checkbox" class="subtask-checkbox" 
                                                   data-sub-id="<?php echo $sub['id']; ?>"
                                                   <?php echo $sub['status'] === 'concluida' ? 'checked' : ''; ?>
                                                   onchange="marcarSubtarefaConcluida(<?php echo $sub['id']; ?>)">
                                            <label class="subtask-text" onclick="marcarSubtarefaConcluida(<?php echo $sub['id']; ?>)">
                                                <?php echo htmlspecialchars($sub['descricao']); ?>
                                </label>
                                            <button class="btn-icon btn-delete-sub" onclick="deletarSubtarefaRapido(<?php echo $sub['id']; ?>)" title="Deletar">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                        <?php else: ?>
                            <!-- Sem Subtarefas - Estado Vazio -->
                            <div class="subtasks-container subtasks-empty">
                                <button class="btn-add-subtask-empty" onclick="abrirModalSubtarefa(<?php echo $task['id']; ?>)" title="Adicionar Subtarefa">
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Adicionar Subtarefa</span>
                                </button>
                </div>
                        <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
            </div>
    </div>
</div>

    <!-- MODAL NOVA TAREFA -->
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
                        <label>Descrição</label>
                        <input type="text" name="descricao" class="form-input" placeholder="O que precisa fazer?" required>
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
                            <label>Data Limite</label>
                            <input type="date" name="data_limite" class="form-input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalTarefa()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Salvar
                    </button>
                </div>
            </form>
    </div>
</div>

    <!-- MODAL NOVA ROTINA -->
    <div id="modalRotina" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h2><i class="bi bi-calendar-plus"></i> Nova Rotina</h2>
                <button class="modal-close" onclick="fecharModalRotina()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="formNovaRotina">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" class="form-input" placeholder="Ex: Exercício matinal" required>
                    </div>
                    <div class="form-group">
                        <label>Horário (opcional)</label>
                        <input type="time" name="horario" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" class="form-input" rows="3" placeholder="Detalhes da rotina"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalRotina()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Criar
                    </button>
                </div>
            </form>
    </div>
</div>

    <!-- MODAL NOVA SUBTAREFA -->
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
                        <label>Descrição</label>
                        <input type="text" name="descricao" class="form-input" placeholder="Passo a fazer" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalSubtarefa()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Adicionar
                    </button>
                </div>
            </form>
    </div>
</div>

    <!-- MODAL EDITAR ROTINA -->
    <div id="modalEditarRotina" class="modal-overlay">
        <div class="modal-box">
			<div class="modal-header">
                <h2><i class="bi bi-pencil"></i> Editar Rotina</h2>
                <button class="modal-close" onclick="fecharModalEditarRotina()">
                    <i class="bi bi-x"></i>
                </button>
			</div>
            <form id="formEditarRotina">
				<div class="modal-body">
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Horário</label>
                        <input type="time" name="horario" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" class="form-input" rows="3"></textarea>
					</div>
				</div>
				<div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="fecharModalEditarRotina()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-save"></i> Salvar
                    </button>
				</div>
			</form>
	</div>
</div>

    <script src="assets/js/tarefas-novo.js"></script>
    <script src="assets/js/melhorias-v2.js"></script>
</body>
</html>
