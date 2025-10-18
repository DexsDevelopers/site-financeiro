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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0a0a0a;
            color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 14px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stat {
            background: #141414;
            border: 1px solid rgba(255,255,255,0.08);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .stat-value {
            font-weight: 700;
            font-size: 16px;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            background: #dc3545;
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

        .btn:hover {
            background: #c4080f;
            transform: translateY(-2px);
        }

        .btn.secondary {
            background: #6bcf7f;
        }

        .btn.secondary:hover {
            background: #5ab86b;
        }

        /* SEARCH BAR */
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-input, .filter-select {
            background: #141414;
            border: 1px solid rgba(255,255,255,0.08);
            color: #ffffff;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
        }

        .filter-select {
            min-width: 150px;
        }

        .search-input::placeholder {
            color: #666;
        }

        /* SECTIONS */
        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: #dc3545;
        }

        .items-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* ITEMS */
        .item {
            background: #141414;
            border: 1px solid rgba(255,255,255,0.08);
            padding: 12px;
            border-radius: 6px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            transition: all 0.2s;
        }

        .item:hover {
            background: rgba(20,20,20,0.8);
            border-color: rgba(255,255,255,0.12);
        }

        .item.rotina {
            background: rgba(107,207,127,0.05);
            border-color: rgba(107,207,127,0.2);
        }

        .item.concluido {
            opacity: 0.5;
        }

        .item-checkbox {
            width: 20px;
            height: 20px;
            min-width: 20px;
            margin-top: 2px;
            cursor: pointer;
            accent-color: #dc3545;
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
            margin-bottom: 4px;
            word-break: break-word;
        }

        .item-meta {
            display: flex;
            gap: 10px;
            font-size: 12px;
            color: #b0b0b0;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
        }

        .badge-alta {
            background: rgba(255,107,107,0.2);
            color: #ff6b6b;
        }

        .badge-media {
            background: rgba(255,217,61,0.2);
            color: #ffd93d;
        }

        .badge-baixa {
            background: rgba(107,207,127,0.2);
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
            color: #b0b0b0;
            cursor: pointer;
            padding: 4px 8px;
            font-size: 14px;
            transition: color 0.2s;
        }

        .btn-icon:hover {
            color: #ffffff;
        }

        /* SUBTASKS */
        .subtasks {
            margin-top: 10px;
            padding-left: 32px;
            border-left: 2px solid rgba(107,207,127,0.3);
        }

        .subtasks-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: #6bcf7f;
            font-size: 12px;
            cursor: pointer;
            font-weight: 600;
        }

        .subtasks-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .subtask-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: rgba(107,207,127,0.05);
            border-left: 3px solid #dc3545;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.2s;
        }

        .subtask-item:hover {
            background: rgba(107,207,127,0.1);
        }

        .subtask-checkbox {
            width: 16px;
            height: 16px;
            min-width: 16px;
            cursor: pointer;
            accent-color: #6bcf7f;
        }

        .subtask-label {
            flex: 1;
            cursor: pointer;
            user-select: none;
            color: #ffffff;
        }

        .subtask-label.completed {
            text-decoration: line-through;
            color: #6bcf7f;
            opacity: 0.7;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #b0b0b0;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* MODAL */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-box {
            background: #141414;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            background: #0a0a0a;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .modal-close {
            background: none;
            border: none;
            color: #b0b0b0;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #dc3545;
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
            font-size: 12px;
            color: #b0b0b0;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-input {
            background: #0a0a0a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 6px;
            padding: 10px 12px;
            color: #ffffff;
            font-size: 13px;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            border-color: #dc3545;
            outline: none;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
            background: #0a0a0a;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel {
            background: none;
            border: 1px solid rgba(255,255,255,0.08);
            color: #b0b0b0;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: rgba(255,255,255,0.05);
            color: #ffffff;
        }

        .btn-submit {
            background: #dc3545;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-submit:hover {
            background: #c4080f;
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* SPINNER */
        .spinner {
            display: inline-flex;
            gap: 4px;
        }

        .spinner span {
            width: 4px;
            height: 4px;
            background: white;
            border-radius: 50%;
            animation: pulse 1s infinite;
        }

        .spinner span:nth-child(2) { animation-delay: 0.2s; }
        .spinner span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 1; }
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .actions {
                width: 100%;
            }

            .actions .btn {
                flex: 1;
                justify-content: center;
            }

            .search-bar {
                flex-direction: column;
            }

            .search-input, .filter-select {
                width: 100%;
            }

            .item-actions {
                opacity: 1;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .modal-box {
                width: 95%;
            }
        }
    </style>
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

                        <!-- SUBTAREFAS -->
                        <?php $subs = $subtarefasPorTarefa[$task['id']] ?? []; ?>
                        <?php if (!empty($subs)): ?>
                            <div class="subtasks">
                                <div class="subtasks-header" onclick="toggleSubtarefasVisibilidade(this)">
                                    <i class="bi bi-chevron-down"></i>
                                    <span style="flex: 1;">📋 Subtarefas (<?php 
                                        $concluidas = count(array_filter($subs, fn($s) => $s['status'] === 'concluida'));
                                        echo $concluidas . '/' . count($subs);
                                    ?>)</span>
                                    <button class="btn-icon" onclick="abrirAdicionarSubtarefa(<?php echo $task['id']; ?>); event.stopPropagation();" style="margin-left: auto;" title="Adicionar Subtarefa">
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                </div>
                                
                                <div class="subtasks-list">
                                    <?php foreach ($subs as $sub): ?>
                                        <div class="subtask-item">
                                            <input type="checkbox" class="subtask-checkbox" 
                                                   data-sub-id="<?php echo $sub['id']; ?>"
                                                   <?php echo $sub['status'] === 'concluida' ? 'checked' : ''; ?>
                                                   onchange="marcarSubtarefaConcluida(<?php echo $sub['id']; ?>)">
                                            <label class="subtask-label <?php echo $sub['status'] === 'concluida' ? 'completed' : ''; ?>" 
                                                   onclick="marcarSubtarefaConcluida(<?php echo $sub['id']; ?>)">
                                                <?php echo htmlspecialchars($sub['descricao']); ?>
                                            </label>
                                            <button class="btn-icon" onclick="deletarSubtarefaRapido(<?php echo $sub['id']; ?>); event.stopPropagation();" title="Deletar" style="opacity: 0.5;">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="subtasks">
                                <div class="subtasks-header" style="color: #888; justify-content: space-between;">
                                    <span>📋 Sem subtarefas</span>
                                    <button class="btn-icon" onclick="abrirAdicionarSubtarefa(<?php echo $task['id']; ?>)" style="margin-left: auto; color: #6bcf7f;" title="Adicionar Subtarefa">
                                        <i class="bi bi-plus-circle"></i> Adicionar
                                    </button>
                                </div>
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
