<?php
require_once 'templates/header.php';
require_once 'includes/db_connect.php';


// ===== ROTINA DIÁRIA FIXA INTEGRADA =====
$dataHoje = date("Y-m-d");

// Buscar rotinas fixas do usuário
$rotinasFixas = [];
$progressoRotina = 0;
try {
    // Buscar rotinas fixas ativas com controle diário
    $stmt = $pdo->prepare("
        SELECT rf.*, 
               rcd.status as status_hoje,
               rcd.horario_execucao,
               rcd.observacoes
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY rf.ordem, rf.horario_sugerido
    ");
    $stmt->execute([$dataHoje, $userId]);
    $rotinasFixas = $stmt->fetchAll();
    
    // Se não há controle para hoje, criar automaticamente
    foreach ($rotinasFixas as $rotina) {
        if ($rotina["status_hoje"] === null) {
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                VALUES (?, ?, ?, "pendente")
            ");
            $stmt->execute([$userId, $rotina["id"], $dataHoje]);
        }
    }
    
    // Buscar novamente com os controles criados
    $stmt = $pdo->prepare("
        SELECT rf.*, 
               rcd.status as status_hoje,
               rcd.horario_execucao,
               rcd.observacoes
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY rf.ordem, rf.horario_sugerido
    ");
    $stmt->execute([$dataHoje, $userId]);
    $rotinasFixas = $stmt->fetchAll();
    
    // Calcular progresso
    $totalRotinas = count($rotinasFixas);
    $rotinasConcluidas = array_filter($rotinasFixas, function($r) { 
        return $r["status_hoje"] === "concluido"; 
    });
    $progressoRotina = $totalRotinas > 0 ? (count($rotinasConcluidas) / $totalRotinas) * 100 : 0;
    
} catch (PDOException $e) {
    $rotinasFixas = [];
    $progressoRotina = 0;
    error_log("Erro ao buscar rotinas fixas: " . $e->getMessage());
}


// Buscar estatísticas para o dashboard
$stats = [];
try {
    // Tarefas de hoje - CORRIGIDO
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas FROM tarefas WHERE id_usuario = ? AND DATE(data_criacao) = CURDATE()");
    $stmt->execute([$userId]);
    $hoje = $stmt->fetch();
    
    // Tarefas da semana - CORRIGIDO
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas FROM tarefas WHERE id_usuario = ? AND YEARWEEK(data_criacao, 1) = YEARWEEK(CURDATE(), 1)");
    $stmt->execute([$userId]);
    $semana = $stmt->fetch();
    
    // Tempo total estimado vs gasto
    $stmt = $pdo->prepare("SELECT SUM(tempo_estimado) as estimado FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
    $stmt->execute([$userId]);
    $tempo = $stmt->fetch();
    
    $stats = [
        'hoje' => $hoje,
        'semana' => $semana,
        'tempo_pendente' => $tempo['estimado'] ?? 0
    ];
} catch (PDOException $e) {
    $stats = ['hoje' => ['total' => 0, 'concluidas' => 0], 'semana' => ['total' => 0, 'concluidas' => 0], 'tempo_pendente' => 0];
}

// Garantir que os valores são números
$stats['hoje']['total'] = (int)($stats['hoje']['total'] ?? 0);
$stats['hoje']['concluidas'] = (int)($stats['hoje']['concluidas'] ?? 0);
$stats['semana']['total'] = (int)($stats['semana']['total'] ?? 0);
$stats['semana']['concluidas'] = (int)($stats['semana']['concluidas'] ?? 0);
$stats['tempo_pendente'] = (int)($stats['tempo_pendente'] ?? 0);

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
        foreach ($todas_as_subtarefas as $subtarefa) { $subtarefas_mapeadas[$subtarefa['id_tarefa_principal']][] = $subtarefa; }
        foreach ($tarefas_pendentes as $key => $tarefa) { $tarefas_pendentes[$key]['subtarefas'] = $subtarefas_mapeadas[$tarefa['id']] ?? []; }
        foreach ($tarefas_concluidas as $key => $tarefa) { $tarefas_concluidas[$key]['subtarefas'] = $subtarefas_mapeadas[$tarefa['id']] ?? []; }
    }
} catch (PDOException $e) { die("Erro ao buscar tarefas: " . $e->getMessage()); }

function getPrioridadeBadge($prioridade) { 
    switch ($prioridade) { 
        case 'Alta': return 'bg-danger'; 
        case 'Média': return 'bg-warning text-dark'; 
        case 'Baixa': return 'bg-success'; 
        default: return 'bg-secondary'; 
    } 
}

function formatarTempo($minutos) {
    if ($minutos <= 0) return '0min';
    $h = floor($minutos / 60);
    $m = $minutos % 60;
    $resultado = '';
    if ($h > 0) $resultado .= $h . 'h ';
    if ($m > 0) $resultado .= $m . 'min';
    return trim($resultado);
}
?>

<style>
/* ===== DESIGN SYSTEM MODERNO ===== */
:root {
    --primary-red: #e50914;
    --dark-bg: #0d1117;
    --card-bg: #161b22;
    --border-color: #30363d;
    --text-primary: #f0f6fc;
    --text-secondary: #8b949e;
    --success: #238636;
    --warning: #d29922;
    --danger: #da3633;
    --radius: 12px;
    --shadow: 0 4px 12px rgba(0,0,0,0.15);
    --shadow-hover: 0 8px 25px rgba(0,0,0,0.25);
}

/* ===== LAYOUT ORGANIZADO ===== */
.page-header {
    text-align: center;
    margin-bottom: 2rem;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.page-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

/* ===== SEÇÕES ORGANIZADAS ===== */
.section-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.section-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: 600;
}

.section-badge {
    background: var(--primary-red);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.section-actions {
    display: flex;
    gap: 0.5rem;
}

/* ===== ROTINA DIÁRIA ORGANIZADA ===== */
.rotina-card {
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.8) 0%, rgba(50, 30, 30, 0.8) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.habits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.habit-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.habit-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(0, 123, 255, 0.3);
    transform: translateY(-2px);
}

.habit-item.completed {
    background: rgba(40, 167, 69, 0.1);
    border-color: rgba(40, 167, 69, 0.3);
}

.habit-icon {
    font-size: 1.5rem;
    color: var(--text-secondary);
}

.habit-item.completed .habit-icon {
    color: var(--success);
}

.habit-content {
    flex: 1;
}

.habit-name {
    margin: 0;
    color: var(--text-primary);
}

/* Estilos para os botões de ação dos hábitos */
.habit-item {
    position: relative;
}

.habit-main {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.habit-actions {
    display: flex;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.habit-item:hover .habit-actions {
    opacity: 1;
}

.habit-actions .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.habit-actions .btn:hover {
    transform: scale(1.1);
}

.habit-actions .btn-outline-warning:hover {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #000;
}

.habit-actions .btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
    color: #fff;
}
    font-weight: 600;
}

.habit-time {
    color: var(--text-secondary);
    font-size: 0.85rem;
}


/* ===== BUSCA E FILTROS ORGANIZADOS ===== */
.search-filters-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1.5rem;
}

.search-container {
    margin-bottom: 1rem;
}

.search-input-group {
    position: relative;
    max-width: 400px;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    z-index: 2;
}

.search-input-group .form-control {
    padding-left: 2.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.search-input-group .form-control:focus {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--primary-red);
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
}

.filters-container {
    overflow-x: auto;
}

.filter-chips {
    display: flex;
    gap: 0.5rem;
    flex-wrap: nowrap;
}

.filter-chip {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    font-size: 0.9rem;
}

.filter-chip:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.filter-chip.active {
    background: var(--primary-red);
    color: white;
    border-color: var(--primary-red);
}

/* ===== SEÇÃO DE TAREFAS ORGANIZADA ===== */
.tasks-section {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1.5rem;
}

.tasks-list {
    margin-top: 1rem;
}

.progress-circular {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: conic-gradient(var(--primary-red) var(--progress, 0%), rgba(255, 255, 255, 0.1) var(--progress, 0%));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.progress-circular::before {
    content: '';
    position: absolute;
    width: 40px;
    height: 40px;
    background: var(--card-bg);
    border-radius: 50%;
}

.progress-text {
    position: relative;
    z-index: 1;
    font-weight: 600;
    color: var(--text-primary);
}
}

/* ===== DASHBOARD DE ESTATÍSTICAS ===== */
.stats-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}





/* ===== BARRA DE BUSCA E FILTROS ===== */
.search-filters {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.search-bar {
    position: relative;
    margin-bottom: 1rem;
}

.search-bar input {
    background: var(--dark-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding-left: 3rem;
    border-radius: var(--radius);
    transition: all 0.3s ease;
}

.search-bar input:focus {
    border-color: var(--primary-red);
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.1);
    background: var(--card-bg);
}

.search-bar .search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    z-index: 2;
}

.filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filter-chip {
    background: var(--dark-bg);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
}

.filter-chip:hover, .filter-chip.active {
    background: var(--primary-red);
    color: white;
    border-color: var(--primary-red);
}

/* ===== TIMER POMODORO ===== */
.pomodoro-timer {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 80px;
    height: 80px;
    background: var(--primary-red);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: var(--shadow-hover);
    transition: all 0.3s ease;
    z-index: 1000;
    color: white;
    font-weight: bold;
    font-size: 0.9rem;
}

.pomodoro-timer:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 30px rgba(229, 9, 20, 0.4);
}

.pomodoro-timer.active {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(229, 9, 20, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(229, 9, 20, 0); }
    100% { box-shadow: 0 0 0 0 rgba(229, 9, 20, 0); }
}

/* ===== CARDS DE TAREFA MODERNOS ===== */
.task-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.task-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--text-secondary);
    transition: all 0.3s ease;
}

.task-card.prioridade-Alta::before { background: var(--danger); }
.task-card.prioridade-Média::before { background: var(--warning); }
.task-card.prioridade-Baixa::before { background: var(--success); }

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary-red);
}

.task-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.task-content {
    flex: 1;
}

.task-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.task-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.btn-icon:hover {
    background: var(--primary-red);
    color: white;
    border-color: var(--primary-red);
}

/* ===== MELHORIAS MOBILE ===== */
@media (max-width: 768px) {
    .stats-dashboard {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    
    
    .search-filters {
        padding: 1rem;
    }
    
    .filter-chips {
        justify-content: center;
    }
    
    .task-header {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .task-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .btn-icon {
        flex: 1;
        max-width: 60px;
    }
    
    .pomodoro-timer {
        width: 60px;
        height: 60px;
        bottom: 1rem;
        right: 1rem;
        font-size: 0.8rem;
    }
}

/* ===== GESTOS SWIPE MOBILE ===== */
.task-card.swiping {
    transform: translateX(var(--swipe-x, 0));
    transition: none;
}

.swipe-actions {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    background: var(--success);
    color: white;
    padding: 0 1rem;
    transform: translateX(100%);
    transition: transform 0.3s ease;
}

.task-card.swiped .swipe-actions {
    transform: translateX(0);
}

/* ===== ANIMAÇÕES E MICROINTERAÇÕES ===== */
.fade-in {
    animation: fadeIn 0.5s ease forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.bounce-in {
    animation: bounceIn 0.6s ease forwards;
}

@keyframes bounceIn {
    0% { opacity: 0; transform: scale(0.3); }
    50% { opacity: 1; transform: scale(1.05); }
    70% { transform: scale(0.9); }
    100% { opacity: 1; transform: scale(1); }
}

/* ===== MODO ESCURO APRIMORADO ===== */
.dark-mode {
    background: var(--dark-bg);
    color: var(--text-primary);
}

/* ===== SCROLLBAR PERSONALIZADA ===== */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--dark-bg);
}

::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}

/* ===== RESPONSIVIDADE ORGANIZADA ===== */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .section-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .habits-grid {
        grid-template-columns: 1fr;
    }
    
    
    
    .search-filters-section {
        padding: 1rem;
    }
    
    .filter-chips {
        gap: 0.25rem;
    }
    
    .filter-chip {
        padding: 0.375rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .tasks-section {
        padding: 1rem;
    }
}

@media (max-width: 576px) {
    .page-title {
        font-size: 1.75rem;
    }
    
    
    .habit-item {
        padding: 0.75rem;
    }
    
    
    .section-card {
        padding: 1rem;
    }
}

/* ===== ESTILOS DO MODAL DE ESTATÍSTICAS ===== */
.stat-card-small {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
}

.stat-card-small:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.stat-icon-small {
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
}

.stat-content-small {
    flex: 1;
}

.stat-value-small {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.stat-label-small {
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.priority-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.priority-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.priority-card.alta {
    border-left: 4px solid var(--danger);
}

.priority-card.media {
    border-left: 4px solid var(--warning);
}

.priority-card.baixa {
    border-left: 4px solid var(--success);
}

.priority-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-weight: 600;
}

.priority-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.priority-progress {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    height: 6px;
    overflow: hidden;
}

.priority-progress .progress-bar {
    height: 100%;
    transition: width 0.3s ease;
}

.priority-card.alta .progress-bar {
    background: var(--danger);
}

.priority-card.media .progress-bar {
    background: var(--warning);
}

.priority-card.baixa .progress-bar {
    background: var(--success);
}

.productivity-chart {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}

.task-item-small {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
}

.task-item-small:hover {
    background: rgba(255, 255, 255, 0.08);
}

.task-priority-small {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.task-priority-small.alta {
    background: var(--danger);
}

.task-priority-small.media {
    background: var(--warning);
}

.task-priority-small.baixa {
    background: var(--success);
}

.task-text-small {
    flex: 1;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.task-status-small {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
}

.task-status-small.concluida {
    background: rgba(40, 167, 69, 0.2);
    color: var(--success);
}

.task-status-small.pendente {
    background: rgba(255, 193, 7, 0.2);
    color: var(--warning);
}
</style>

<!-- ===== PÁGINA DE TAREFAS ORGANIZADA ===== -->
<div class="container-fluid py-4">
    
    <!-- ===== HEADER DA PÁGINA ===== -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="bi bi-check2-square me-3"></i>
                    Rotina de Tarefas
                </h1>
                <p class="page-subtitle">Organize suas tarefas e hábitos diários em um só lugar</p>
            </div>
        </div>
    </div>

    <!-- ===== SEÇÃO ROTINA DIÁRIA ===== -->
    <?php if (!empty($rotinasFixas)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="section-card rotina-card">
                <div class="section-header">
                    <div class="section-title">
                        <i class="bi bi-calendar-check me-2"></i>
                        <h3>Rotina Diária</h3>
                        <span class="section-badge"><?php echo count($rotinasConcluidas); ?>/<?php echo count($rotinasFixas); ?> concluídas</span>
                    </div>
                    <div class="section-progress">
                        <div class="progress-circular" style="--progress: <?php echo $progressoRotina; ?>%">
                            <span class="progress-text"><?php echo round($progressoRotina); ?>%</span>
                        </div>
                    </div>
                </div>
                
                <div class="habits-grid">
                    <?php foreach ($rotinasFixas as $rotina): ?>
                    <div class="habit-item <?php echo $rotina['status'] === 'concluido' ? 'completed' : ''; ?>">
                        <div class="habit-main" onclick="toggleRotina(<?php echo $rotina['id']; ?>, '<?php echo $rotina['status']; ?>')">
                            <div class="habit-icon">
                                <i class="bi bi-<?php echo $rotina['status'] === 'concluido' ? 'check-circle-fill' : 'circle'; ?>"></i>
                            </div>
                            <div class="habit-content">
                                <h6 class="habit-name"><?php echo htmlspecialchars($rotina['nome']); ?></h6>
                                <?php if ($rotina['horario']): ?>
                                <small class="habit-time">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo date('H:i', strtotime($rotina['horario'])); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="habit-actions">
                            <button class="btn btn-sm btn-outline-warning" onclick="editarRotina(<?php echo $rotina['id']; ?>, '<?php echo htmlspecialchars($rotina['nome'], ENT_QUOTES); ?>', '<?php echo $rotina['horario'] ? date('H:i', strtotime($rotina['horario'])) : ''; ?>')" title="Editar hábito">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirRotina(<?php echo $rotina['id']; ?>, '<?php echo htmlspecialchars($rotina['nome'], ENT_QUOTES); ?>')" title="Excluir hábito">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-actions">
                    <button class="btn btn-outline-primary btn-sm" onclick="adicionarHabit()">
                        <i class="bi bi-plus-circle me-1"></i>
                        Adicionar Hábito
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- ===== BARRA DE BUSCA E FILTROS ===== -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="search-filters-section">
                <div class="search-container">
                    <div class="search-input-group">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" id="searchTasks" class="form-control" placeholder="Buscar tarefas...">
                    </div>
                </div>
                
                <div class="filters-container">
                    <div class="filter-chips">
                        <span class="filter-chip active" data-filter="all">Todas</span>
                        <span class="filter-chip" data-filter="alta">Alta Prioridade</span>
                        <span class="filter-chip" data-filter="media">Média Prioridade</span>
                        <span class="filter-chip" data-filter="baixa">Baixa Prioridade</span>
                        <span class="filter-chip" data-filter="hoje">Hoje</span>
                        <span class="filter-chip" data-filter="atrasadas">Atrasadas</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SEÇÃO PRINCIPAL DE TAREFAS ===== -->
    <div class="row">
        <div class="col-12">
            <div class="tasks-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="bi bi-list-task me-2"></i>
                        <h3>Minhas Tarefas</h3>
                    </div>
                    <div class="section-actions">
                        <button class="btn btn-outline-info me-2" onclick="mostrarEstatisticas()">
                            <i class="bi bi-graph-up me-2"></i>Estatísticas
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                            <i class="bi bi-plus-circle me-2"></i>Nova Tarefa
                        </button>
                    </div>
                </div>
                
                <!-- Lista de Tarefas Pendentes -->
                <div id="lista-tarefas-pendentes" class="tasks-list">
    <?php if(empty($tarefas_pendentes)): ?>
        <div class="text-center py-5">
            <i class="bi bi-check-circle" style="font-size: 4rem; color: var(--success); margin-bottom: 1rem;"></i>
            <h5 style="color: var(--text-primary);">Nenhuma tarefa pendente!</h5>
            <p style="color: var(--text-secondary);">Parabéns! Você está em dia com suas tarefas.</p>
        </div>
    <?php else: ?>
        <?php foreach($tarefas_pendentes as $tarefa): ?>
            <div class="task-card prioridade-<?php echo $tarefa['prioridade']; ?> fade-in" data-id="<?php echo $tarefa['id']; ?>" data-priority="<?php echo strtolower(str_replace('é', 'e', $tarefa['prioridade'])); ?>">
                <div class="task-header">
                    <i class="bi bi-grip-vertical handle" style="color: var(--text-secondary); cursor: grab;"></i>
                    
                    <div class="task-content">
                        <div class="task-title"><?php echo htmlspecialchars($tarefa['descricao']); ?></div>
                        
                        <div class="task-meta">
                            <span class="badge <?php echo getPrioridadeBadge($tarefa['prioridade']); ?>">
                                <?php echo $tarefa['prioridade']; ?>
                            </span>
                            
                            <?php if ($tarefa['data_limite']): ?>
                                <span><i class="bi bi-calendar-event me-1"></i><?php echo date('d/m/Y', strtotime($tarefa['data_limite'])); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($tarefa['tempo_estimado'] > 0): ?>
                                <span><i class="bi bi-clock me-1"></i><?php echo formatarTempo($tarefa['tempo_estimado']); ?></span>
                            <?php endif; ?>
                            
                            <?php if (!empty($tarefa['subtarefas'])): ?>
                                <span><i class="bi bi-list-ul me-1"></i><?php echo count($tarefa['subtarefas']); ?> subtarefas</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="task-actions">
                        <button class="btn-icon btn-subtask" data-id="<?php echo $tarefa['id']; ?>" title="Adicionar Subtarefa">
                            <i class="bi bi-list-ul"></i>
                        </button>
                        <button class="btn-icon btn-timer" data-id="<?php echo $tarefa['id']; ?>" title="Iniciar Timer">
                            <i class="bi bi-play-fill"></i>
                        </button>
                        <button class="btn-icon btn-complete" data-id="<?php echo $tarefa['id']; ?>" title="Concluir">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn-icon btn-edit" data-id="<?php echo $tarefa['id']; ?>" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn-icon btn-delete" data-id="<?php echo $tarefa['id']; ?>" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Subtarefas (colapsível) -->
                <?php if (!empty($tarefa['subtarefas'])): ?>
                    <div class="subtasks mt-3 pt-3" style="border-top: 1px solid var(--border-color);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0" style="color: var(--text-secondary);">
                                <i class="bi bi-list-ul me-1"></i>Subtarefas
                            </h6>
                            <button class="btn btn-sm btn-outline-primary" onclick="toggleSubtasks(this)">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                        <div class="subtasks-list">
                            <?php foreach($tarefa['subtarefas'] as $sub): ?>
                                <div class="subtask-item d-flex align-items-center mb-2 p-2" 
                                     style="background: var(--dark-bg); border-radius: 8px; border: 1px solid var(--border-color);">
                                    <div class="form-check me-3">
                                        <input class="form-check-input subtask-checkbox" type="checkbox" 
                                               data-id="<?php echo $sub['id']; ?>" 
                                               <?php echo ($sub['status'] == 'concluida') ? 'checked' : ''; ?>>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="form-check-label mb-0 subtask-label <?php echo ($sub['status'] == 'concluida') ? 'text-decoration-line-through text-muted' : ''; ?>" 
                                                   style="color: var(--text-primary); cursor: pointer;"
                                                   data-id="<?php echo $sub['id']; ?>"
                                                   title="Clique para editar">
                                                <?php echo htmlspecialchars($sub['descricao']); ?>
                                            </label>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (isset($sub['prioridade']) && $sub['prioridade']): ?>
                                                    <span class="badge <?php echo $sub['prioridade'] === 'Alta' ? 'bg-danger' : ($sub['prioridade'] === 'Média' ? 'bg-warning text-dark' : 'bg-success'); ?>" 
                                                          style="font-size: 0.7rem;">
                                                        <?php echo $sub['prioridade']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (isset($sub['tempo_estimado']) && $sub['tempo_estimado'] > 0): ?>
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock me-1"></i><?php echo formatarTempo($sub['tempo_estimado']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                
                                                <!-- Botão de exclusão -->
                                                <button class="btn btn-sm btn-outline-danger btn-delete-subtask" 
                                                        data-id="<?php echo $sub['id']; ?>"
                                                        title="Excluir subtarefa"
                                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Swipe Actions (Mobile) -->
                <div class="swipe-actions">
                    <i class="bi bi-check-lg"></i>
                </div>
            </div>
        <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Timer Pomodoro Flutuante -->
<div class="pomodoro-timer" id="pomodoroTimer" title="Timer Pomodoro">
    <span id="timerDisplay">25:00</span>
</div>

<!-- Modal Nova Tarefa -->
<div class="modal fade" id="modalNovaTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="bi bi-plus-circle me-2"></i>Nova Tarefa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaTarefa" action="adicionar_tarefa.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--text-primary);">Descrição</label>
                        <input type="text" name="descricao" class="form-control" required 
                               style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Prioridade</label>
                            <select name="prioridade" class="form-select" 
                                    style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                                <option value="Baixa">Baixa</option>
                                <option value="Média" selected>Média</option>
                                <option value="Alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Data Limite</label>
                            <input type="date" name="data_limite" class="form-control"
                                   style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--text-primary);">Tempo Estimado</label>
                        <div class="input-group">
                            <input type="number" name="tempo_horas" class="form-control" min="0" placeholder="Horas"
                                   style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                            <span class="input-group-text" style="background: var(--border-color); color: var(--text-primary);">h</span>
                            <input type="number" name="tempo_minutos" class="form-control" min="0" max="59" placeholder="Min"
                                   style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                            <span class="input-group-text" style="background: var(--border-color); color: var(--text-primary);">min</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save me-2"></i>Salvar Tarefa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Tarefa -->
<div class="modal fade" id="modalEditarTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="bi bi-pencil-square me-2"></i>Editar Tarefa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarTarefa">
                <div class="modal-body">
                    <input type="hidden" id="edit_task_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--text-primary);">Descrição</label>
                        <textarea id="edit_descricao" name="descricao" class="form-control" rows="3" required 
                                  style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Prioridade</label>
                            <select id="edit_prioridade" name="prioridade" class="form-select" 
                                    style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                                <option value="Baixa">Baixa</option>
                                <option value="Média">Média</option>
                                <option value="Alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Status</label>
                            <select id="edit_status" name="status" class="form-select" 
                                    style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                                <option value="pendente">Pendente</option>
                                <option value="em_progresso">Em Progresso</option>
                                <option value="concluida">Concluída</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Tempo Estimado (min)</label>
                            <input type="number" id="edit_tempo_estimado" name="tempo_estimado" class="form-control" min="0"
                                   style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Data Limite</label>
                            <input type="date" id="edit_data_limite" name="data_limite" class="form-control"
                                   style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Hora Início</label>
                            <input type="time" id="edit_hora_inicio" name="hora_inicio" class="form-control"
                                   style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="color: var(--text-primary);">Hora Fim</label>
                            <input type="time" id="edit_hora_fim" name="hora_fim" class="form-control"
                                   style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-save me-2"></i>Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão -->
<div class="modal fade" id="modalExcluirTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Confirmar Exclusão
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="color: var(--text-secondary);">Tem certeza que deseja excluir esta tarefa?</p>
                <div class="alert alert-warning" style="background: rgba(210, 153, 34, 0.1); border: 1px solid var(--warning); color: var(--warning);">
                    <strong id="delete_task_title"></strong>
                </div>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    Esta ação não pode ser desfeita.
                </p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmarExclusao" class="btn btn-danger">
                    <i class="bi bi-trash me-2"></i>Sim, Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Subtarefa -->
<div class="modal fade" id="modalAdicionarSubtarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="bi bi-list-ul me-2"></i>Adicionar Subtarefa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAdicionarSubtarefa">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--text-primary);">Descrição da Subtarefa</label>
                        <input type="text" name="descricao" class="form-control" required 
                               style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);"
                               placeholder="Digite a descrição da subtarefa...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--text-primary);">Prioridade</label>
                        <select name="prioridade" class="form-select" 
                                style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                            <option value="Baixa">Baixa</option>
                            <option value="Média" selected>Média</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--text-primary);">Tempo Estimado (minutos)</label>
                        <input type="number" name="tempo_estimado" class="form-control" min="1" max="480" 
                               style="background: var(--dark-bg); border: 1px solid var(--border-color); color: var(--text-primary);"
                               placeholder="Ex: 30">
                    </div>
                    <input type="hidden" name="id_tarefa_principal" id="id_tarefa_principal">
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Adicionar Subtarefa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Estatísticas -->
<div class="modal fade" id="modalEstatisticas" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="bi bi-graph-up me-2"></i>Estatísticas Detalhadas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Estatísticas Gerais -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-bar-chart me-2"></i>Resumo Geral
                        </h6>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card-small">
                            <div class="stat-icon-small">
                                <i class="bi bi-check-circle text-success"></i>
                            </div>
                            <div class="stat-content-small">
                                <div class="stat-value-small" id="totalConcluidas"><?php echo $stats['hoje']['concluidas']; ?></div>
                                <div class="stat-label-small">Concluídas Hoje</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card-small">
                            <div class="stat-icon-small">
                                <i class="bi bi-clock text-warning"></i>
                            </div>
                            <div class="stat-content-small">
                                <div class="stat-value-small" id="totalPendentes"><?php echo $stats['hoje']['total'] - $stats['hoje']['concluidas']; ?></div>
                                <div class="stat-label-small">Pendentes Hoje</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card-small">
                            <div class="stat-icon-small">
                                <i class="bi bi-calendar-week text-info"></i>
                            </div>
                            <div class="stat-content-small">
                                <div class="stat-value-small" id="totalSemana"><?php echo $stats['semana']['total']; ?></div>
                                <div class="stat-label-small">Esta Semana</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card-small">
                            <div class="stat-icon-small">
                                <i class="bi bi-stopwatch text-danger"></i>
                            </div>
                            <div class="stat-content-small">
                                <div class="stat-value-small" id="tempoPendente"><?php echo formatarTempo($stats['tempo_pendente']); ?></div>
                                <div class="stat-label-small">Tempo Pendente</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarefas de Hoje -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-calendar-day me-2"></i>Tarefas de Hoje
                        </h6>
                        <div id="tarefasHojeContainer">
                            <!-- Conteúdo será carregado via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Progresso por Prioridade -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-pie-chart me-2"></i>Distribuição por Prioridade
                        </h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="priority-card alta">
                                    <div class="priority-header">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <span>Alta Prioridade</span>
                                    </div>
                                    <div class="priority-count" id="countAlta">0</div>
                                    <div class="priority-progress">
                                        <div class="progress-bar" id="progressAlta" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="priority-card media">
                                    <div class="priority-header">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <span>Média Prioridade</span>
                                    </div>
                                    <div class="priority-count" id="countMedia">0</div>
                                    <div class="priority-progress">
                                        <div class="progress-bar" id="progressMedia" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="priority-card baixa">
                                    <div class="priority-header">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Baixa Prioridade</span>
                                    </div>
                                    <div class="priority-count" id="countBaixa">0</div>
                                    <div class="priority-progress">
                                        <div class="progress-bar" id="progressBaixa" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de Produtividade -->
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-graph-up-arrow me-2"></i>Produtividade dos Últimos 7 Dias
                        </h6>
                        <div class="productivity-chart">
                            <canvas id="productivityChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="exportarEstatisticas()">
                    <i class="bi bi-download me-2"></i>Exportar Relatório
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Hábito -->
<div class="modal fade" id="modalEditarHabit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="bi bi-pencil me-2"></i>Editar Hábito
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarHabit">
                    <input type="hidden" id="editHabitId" name="id">
                    <div class="mb-3">
                        <label for="editHabitNome" class="form-label">Nome do Hábito</label>
                        <input type="text" class="form-control" id="editHabitNome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label for="editHabitHorario" class="form-label">Horário (opcional)</label>
                        <input type="time" class="form-control" id="editHabitHorario" name="horario">
                        <div class="form-text">Deixe em branco se não quiser definir um horário específico</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="salvarEdicaoHabit()">
                    <i class="bi bi-check-lg me-2"></i>Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Excluir Hábito -->
<div class="modal fade" id="modalExcluirHabit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirmar Exclusão
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o hábito <strong id="excluirHabitNome"></strong>?</p>
                <p class="text-muted">Esta ação não pode ser desfeita.</p>
                <input type="hidden" id="excluirHabitId">
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmarExclusaoHabit()">
                    <i class="bi bi-trash me-2"></i>Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ===== SISTEMA DE BUSCA E FILTROS =====
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchTasks');
    const filterChips = document.querySelectorAll('.filter-chip');
    const taskCards = document.querySelectorAll('.task-card');
    
    // Busca em tempo real
    if (searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        taskCards.forEach(card => {
            const title = card.querySelector('.task-title').textContent.toLowerCase();
            const isVisible = title.includes(searchTerm);
            card.style.display = isVisible ? 'block' : 'none';
        });
    });
    }
    
    // Filtros por categoria
    filterChips.forEach(chip => {
        chip.addEventListener('click', function() {
            // Remove active de todos e adiciona no clicado
            filterChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            taskCards.forEach(card => {
                let isVisible = true;
                
                if (filter === 'all') {
                    isVisible = true;
                } else if (filter === 'alta' || filter === 'media' || filter === 'baixa') {
                    // Corrigir mapeamento de prioridades
                    const cardPriority = card.dataset.priority;
                    console.log('Card priority:', cardPriority, 'Filter:', filter); // Debug
                    isVisible = cardPriority === filter;
                } else if (filter === 'hoje') {
                    // Lógica para tarefas de hoje
                    const dataLimite = card.querySelector('[data-limite]');
                    isVisible = dataLimite && dataLimite.dataset.limite === new Date().toISOString().split('T')[0];
                }
                
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
    });
    
    // ===== TIMER POMODORO =====
    let pomodoroTimer = null;
    let timeLeft = 25 * 60; // 25 minutos
    let isRunning = false;
    let currentTaskId = null;
    
    const timerElement = document.getElementById('pomodoroTimer');
    const timerDisplay = document.getElementById('timerDisplay');
    
    function updateTimerDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    
    function startTimer(taskId = null) {
        if (isRunning) {
            // Pausar
            clearInterval(pomodoroTimer);
            isRunning = false;
            timerElement.classList.remove('active');
            return;
        }
        
        // Iniciar
        currentTaskId = taskId;
        isRunning = true;
        timerElement.classList.add('active');
        
        pomodoroTimer = setInterval(() => {
            timeLeft--;
            updateTimerDisplay();
            
            if (timeLeft <= 0) {
                clearInterval(pomodoroTimer);
                isRunning = false;
                timerElement.classList.remove('active');
                
                // Notificação
                if (Notification.permission === 'granted') {
                    new Notification('Pomodoro Concluído!', {
                        body: 'Hora de fazer uma pausa de 5 minutos.',
                        icon: '/favicon.ico'
                    });
                }
                
                // Reset timer
                timeLeft = 25 * 60;
                updateTimerDisplay();
                
                // Vibração (mobile)
                if (navigator.vibrate) {
                    navigator.vibrate([200, 100, 200]);
                }
            }
        }, 1000);
    }
    
    // Click no timer flutuante
    if (timerElement) {
    timerElement.addEventListener('click', () => startTimer());
    }
    
    // Botões de timer nas tarefas
    document.querySelectorAll('.btn-timer').forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = this.dataset.id;
            startTimer(taskId);
        });
    });
    
    // Solicitar permissão para notificações
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    
    // ===== AÇÕES DAS TAREFAS =====
    function completeTask(taskId) {
        fetch('atualizar_status_tarefa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, status: 'concluida' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Tarefa concluída!', data.message);
                
                // Remover tarefa da lista
                const card = document.querySelector(`[data-id="${taskId}"]`);
                if (card) {
                    card.style.transform = 'translateX(-100%)';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                }
                
                // Atualizar contadores em tempo real
                updateCounters();
            } else {
                showToast('Erro!', data.message, true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro de rede!', 'Não foi possível conectar ao servidor.', true);
        });
    }
    
    // Função para atualizar contadores em tempo real
    function updateCounters() {
        fetch('get_task_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
            }
        })
        .catch(error => console.error('Erro ao atualizar contadores:', error));
    }
    
    // Event listeners para botões
    document.querySelectorAll('.btn-complete').forEach(btn => {
        btn.addEventListener('click', function() {
            completeTask(this.dataset.id);
        });
    });
    
    // ===== ADICIONAR SUBTAREFA =====
    const btnSubtask = document.querySelectorAll('.btn-subtask');
    btnSubtask.forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = this.dataset.id;
            document.getElementById('id_tarefa_principal').value = taskId;
            
            // Abrir modal
            const modal = new bootstrap.Modal(document.getElementById('modalAdicionarSubtarefa'));
            modal.show();
        });
    });
    
    // Formulário de subtarefa
    const formAdicionarSubtarefa = document.getElementById('formAdicionarSubtarefa');
    if (formAdicionarSubtarefa) {
        formAdicionarSubtarefa.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const button = this.querySelector('button[type="submit"]');
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adicionando...';
            
            fetch('adicionar_subtarefa.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Adicionar Subtarefa';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de rede!', 'Não foi possível conectar ao servidor.', true);
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Adicionar Subtarefa';
            });
        });
    }
    
    // ===== FUNÇÕES DE SUBTAREFAS =====
    // Alternar exibição de subtarefas
    window.toggleSubtasks = function(button) {
        const subtasksList = button.closest('.subtasks').querySelector('.subtasks-list');
        const icon = button.querySelector('i');
        
        if (subtasksList.style.display === 'none') {
            subtasksList.style.display = 'block';
            icon.className = 'bi bi-chevron-down';
        } else {
            subtasksList.style.display = 'none';
            icon.className = 'bi bi-chevron-right';
        }
    };
    
    // ===== EDIÇÃO INLINE DE SUBTAREFAS =====
    // Permitir edição de subtarefas clicando nelas
    document.querySelectorAll('.subtask-label').forEach(label => {
        label.addEventListener('click', function() {
            const subtaskId = this.dataset.id;
            const currentText = this.textContent.trim();
            
            // Criar input para edição
            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentText;
            input.className = 'form-control form-control-sm';
            input.style.cssText = `
                background: var(--dark-bg);
                border: 1px solid var(--primary-red);
                color: var(--text-primary);
                font-size: 0.9rem;
                padding: 0.25rem 0.5rem;
            `;
            
            // Substituir label por input
            this.style.display = 'none';
            this.parentNode.insertBefore(input, this);
            input.focus();
            input.select();
            
            // Função para salvar
            const saveEdit = () => {
                const newText = input.value.trim();
                if (newText && newText !== currentText) {
                    // Enviar para servidor
                    fetch('atualizar_subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: subtaskId, descricao: newText })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.textContent = newText;
                            showToast('Sucesso!', 'Subtarefa atualizada com sucesso!');
                        } else {
                            showToast('Erro!', data.message || 'Não foi possível atualizar a subtarefa.', true);
                            this.textContent = currentText; // Reverter
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showToast('Erro!', 'Erro de conexão', true);
                        this.textContent = currentText; // Reverter
                    });
                } else {
                    this.textContent = currentText; // Reverter se não mudou
                }
                
                // Restaurar label
                this.style.display = '';
                input.remove();
            };
            
            // Função para cancelar
            const cancelEdit = () => {
                this.style.display = '';
                input.remove();
            };
            
            // Event listeners
            input.addEventListener('blur', saveEdit);
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveEdit();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelEdit();
                }
            });
        });
    });
    
    // Atualizar status de subtarefa
    document.querySelectorAll('.subtask-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const subtaskId = this.dataset.id;
            const status = this.checked ? 'concluida' : 'pendente';
            const label = this.closest('.subtask-item').querySelector('label');
            
            // Atualizar visual
            if (status === 'concluida') {
                label.classList.add('text-decoration-line-through', 'text-muted');
            } else {
                label.classList.remove('text-decoration-line-through', 'text-muted');
            }
            
            // Enviar para servidor
            fetch('atualizar_status_subtarefa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: subtaskId, status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Reverter se houver erro
                    this.checked = !this.checked;
                    if (status === 'concluida') {
                        label.classList.remove('text-decoration-line-through', 'text-muted');
                    } else {
                        label.classList.add('text-decoration-line-through', 'text-muted');
                    }
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                // Reverter se houver erro
                this.checked = !this.checked;
                showToast('Erro!', 'Erro de conexão', true);
            });
        });
    });
    
    // ===== EXCLUSÃO DE SUBTAREFAS =====
    // Excluir subtarefa
    document.querySelectorAll('.btn-delete-subtask').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Evitar conflito com edição inline
            const subtaskId = this.dataset.id;
            const subtaskItem = this.closest('.subtask-item');
            const subtaskText = subtaskItem.querySelector('.subtask-label').textContent.trim();
            
            // Confirmação antes de excluir
            if (confirm(`Tem certeza que deseja excluir a subtarefa "${subtaskText}"?`)) {
                // Mostrar loading no botão
                const originalContent = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
                // Enviar para servidor
                fetch('excluir_subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: subtaskId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', 'Subtarefa excluída com sucesso!');
                        
                        // Remover subtarefa da interface com animação
                        subtaskItem.style.transition = 'all 0.3s ease';
                        subtaskItem.style.transform = 'translateX(-100%)';
                        subtaskItem.style.opacity = '0';
                        
                        setTimeout(() => {
                            subtaskItem.remove();
                            
                            // Verificar se não há mais subtarefas e ocultar seção
                            const subtasksList = subtaskItem.closest('.subtasks-list');
                            if (subtasksList && subtasksList.children.length === 0) {
                                const subtasksSection = subtaskItem.closest('.subtasks');
                                if (subtasksSection) {
                                    subtasksSection.style.display = 'none';
                                }
                            }
                        }, 300);
                    } else {
                        showToast('Erro!', data.message || 'Não foi possível excluir a subtarefa.', true);
                        this.disabled = false;
                        this.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', true);
                    this.disabled = false;
                    this.innerHTML = originalContent;
                });
            }
        });
    });
    
    // ===== MODAL DE EDITAR TAREFA =====
    let currentEditTaskId = null;
    
    // Abrir modal de editar
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
                const taskId = this.dataset.id;
            currentEditTaskId = taskId;
            
            // Buscar dados da tarefa
            fetch(`buscar_tarefa_detalhes.php?id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tarefa = data.tarefa;
                    
                    // Preencher o formulário
                    document.getElementById('edit_task_id').value = tarefa.id;
                    document.getElementById('edit_descricao').value = tarefa.descricao || '';
                    document.getElementById('edit_prioridade').value = tarefa.prioridade || 'Média';
                    document.getElementById('edit_status').value = tarefa.status || 'pendente';
                    document.getElementById('edit_tempo_estimado').value = tarefa.tempo_estimado || '';
                    document.getElementById('edit_data_limite').value = tarefa.data_limite || '';
                    document.getElementById('edit_hora_inicio').value = tarefa.hora_inicio || '';
                    document.getElementById('edit_hora_fim').value = tarefa.hora_fim || '';
                    
                    // Abrir modal
                    const modal = new bootstrap.Modal(document.getElementById('modalEditarTarefa'));
                    modal.show();
                } else {
                    showToast('Erro!', data.message || 'Não foi possível carregar os dados da tarefa.', true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de rede!', 'Não foi possível conectar ao servidor.', true);
            });
        });
    });
    
    // Processar formulário de editar
    const formEditarTarefa = document.getElementById('formEditarTarefa');
    if (formEditarTarefa) {
        formEditarTarefa.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const button = this.querySelector('button[type="submit"]');
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
            
            fetch('atualizar_tarefa.php', {
            method: 'POST',
                body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                    showToast('Sucesso!', data.message || 'Tarefa atualizada com sucesso!');
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarTarefa'));
                    modal.hide();
                    
                    // Recarregar página após um tempo
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Erro!', data.message || 'Não foi possível atualizar a tarefa.', true);
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Alterações';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de rede!', 'Não foi possível conectar ao servidor.', true);
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Alterações';
            });
        });
    }
    
    // ===== MODAL DE EXCLUIR TAREFA =====
    let currentDeleteTaskId = null;
    
    // Abrir modal de excluir
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const taskId = this.dataset.id;
            currentDeleteTaskId = taskId;
            
            // Buscar título da tarefa para exibir no modal
            const taskCard = this.closest('.task-card');
            const taskTitle = taskCard.querySelector('.task-title').textContent;
            
            document.getElementById('delete_task_title').textContent = taskTitle;
            
            // Abrir modal
            const modal = new bootstrap.Modal(document.getElementById('modalExcluirTarefa'));
            modal.show();
        });
    });
    
    // Confirmar exclusão
    const btnConfirmarExclusao = document.getElementById('btnConfirmarExclusao');
    if (btnConfirmarExclusao) {
        btnConfirmarExclusao.addEventListener('click', function() {
            if (!currentDeleteTaskId) return;
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Excluindo...';
            
            fetch('excluir_tarefa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: currentDeleteTaskId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message || 'Tarefa excluída com sucesso!');
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalExcluirTarefa'));
                    modal.hide();
                    
                    // Remover tarefa da lista com animação
                    const card = document.querySelector(`[data-id="${currentDeleteTaskId}"]`);
                    if (card) {
                        card.style.transform = 'translateX(-100%)';
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            // Atualizar contadores
                            updateCounters();
                        }, 300);
                    }
                    
                    currentDeleteTaskId = null;
                } else {
                    showToast('Erro!', data.message || 'Não foi possível excluir a tarefa.', true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de rede!', 'Não foi possível conectar ao servidor.', true);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-trash me-2"></i>Sim, Excluir';
            });
        });
    }
    
    // ===== SORTABLE DRAG & DROP =====
    const listaTarefas = document.getElementById('lista-tarefas-pendentes');
    if (listaTarefas) {
        new Sortable(listaTarefas, {
            animation: 200,
            handle: '.handle',
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                const items = listaTarefas.querySelectorAll('.task-card');
                const novaOrdem = Array.from(items).map(item => item.dataset.id);
                
                fetch('atualizar_ordem_tarefas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ordem: novaOrdem })
                });
            }
        });
    }
    
    // ===== ADICIONAR NOVA TAREFA =====
    const formNovaTarefa = document.getElementById('formNovaTarefa');
    if (formNovaTarefa) {
        formNovaTarefa.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const button = this.querySelector('button[type="submit"]');
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
            
            fetch('adicionar_tarefa.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Tarefa';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de rede!', 'Não foi possível conectar ao servidor.', true);
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Tarefa';
            });
        });
    }
    
    // Inicializar timer display
    updateTimerDisplay();
});

// ===== FUNÇÃO TOAST (REUTILIZAR A EXISTENTE) =====
function showToast(title, message, isError = false) {
    // Implementar toast notification
    console.log(`${title}: ${message}`);
    
    // Criar toast visual simples
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${isError ? '#dc3545' : '#28a745'};
        color: white;
        padding: 1rem;
        border-radius: 8px;
        z-index: 9999;
        max-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    `;
    toast.innerHTML = `<strong>${title}</strong><br>${message}`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// ===== FUNÇÕES DO MODAL DE ESTATÍSTICAS =====
function mostrarEstatisticas() {
    const modal = new bootstrap.Modal(document.getElementById('modalEstatisticas'));
    modal.show();
    
    // Carregar dados das estatísticas
    carregarEstatisticas();
}

function carregarEstatisticas() {
    // Carregar tarefas de hoje
    carregarTarefasHoje();
    
    // Carregar distribuição por prioridade
    carregarDistribuicaoPrioridade();
    
    // Carregar gráfico de produtividade
    carregarGraficoProdutividade();
}

function carregarTarefasHoje() {
    fetch('api_tarefas_hoje.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('tarefasHojeContainer');
                container.innerHTML = '';
                
                if (data.tarefas.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-3">Nenhuma tarefa para hoje</div>';
                    return;
                }
                
                data.tarefas.forEach(tarefa => {
                    const taskItem = document.createElement('div');
                    taskItem.className = 'task-item-small';
                    taskItem.innerHTML = `
                        <div class="task-priority-small ${tarefa.prioridade.toLowerCase().replace('é', 'e')}"></div>
                        <div class="task-text-small">${tarefa.descricao}</div>
                        <div class="task-status-small ${tarefa.status === 'concluida' ? 'concluida' : 'pendente'}">
                            ${tarefa.status === 'concluida' ? 'Concluída' : 'Pendente'}
                        </div>
                    `;
                    container.appendChild(taskItem);
                });
            }
        })
        .catch(error => {
            console.error('Erro ao carregar tarefas de hoje:', error);
            document.getElementById('tarefasHojeContainer').innerHTML = 
                '<div class="text-center text-danger py-3">Erro ao carregar tarefas</div>';
        });
}

function carregarDistribuicaoPrioridade() {
    fetch('api_distribuicao_prioridade.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar contadores
                document.getElementById('countAlta').textContent = data.alta || 0;
                document.getElementById('countMedia').textContent = data.media || 0;
                document.getElementById('countBaixa').textContent = data.baixa || 0;
                
                // Calcular percentuais
                const total = (data.alta || 0) + (data.media || 0) + (data.baixa || 0);
                if (total > 0) {
                    const percentAlta = ((data.alta || 0) / total) * 100;
                    const percentMedia = ((data.media || 0) / total) * 100;
                    const percentBaixa = ((data.baixa || 0) / total) * 100;
                    
                    document.getElementById('progressAlta').style.width = percentAlta + '%';
                    document.getElementById('progressMedia').style.width = percentMedia + '%';
                    document.getElementById('progressBaixa').style.width = percentBaixa + '%';
                }
            }
        })
        .catch(error => {
            console.error('Erro ao carregar distribuição:', error);
        });
}

function carregarGraficoProdutividade() {
    fetch('api_produtividade_7_dias.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && typeof Chart !== 'undefined') {
                const ctx = document.getElementById('productivityChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Tarefas Concluídas',
                            data: data.tarefas,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: {
                                    color: '#f0f6fc'
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    color: '#8b949e'
                                },
                                grid: {
                                    color: 'rgba(139, 148, 158, 0.2)'
                                }
                            },
                            y: {
                                ticks: {
                                    color: '#8b949e'
                                },
                                grid: {
                                    color: 'rgba(139, 148, 158, 0.2)'
                                }
                            }
                        }
                    }
                });
            } else {
                document.getElementById('productivityChart').innerHTML = 
                    '<div class="text-muted">Gráfico não disponível</div>';
            }
        })
        .catch(error => {
            console.error('Erro ao carregar gráfico:', error);
            document.getElementById('productivityChart').innerHTML = 
                '<div class="text-danger">Erro ao carregar gráfico</div>';
        });
}

function exportarEstatisticas() {
    // Implementar exportação de relatório
    showToast('Em Desenvolvimento', 'Funcionalidade de exportação será implementada em breve.', false);
}

// ===== FUNÇÕES ROTINA DIÁRIA =====
function toggleRotina(id, statusAtual) {
    const novoStatus = statusAtual === 'concluido' ? 'pendente' : 'concluido';
    
    fetch('salvar_rotina_diaria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, status: novoStatus })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    });
}

function adicionarHabit() {
    const nome = prompt('Nome do novo hábito:');
    if (nome && nome.trim()) {
        fetch('adicionar_rotina_diaria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome: nome.trim() })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Erro!', data.message, true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro de conexão', true);
        });
    }
}

// ===== FUNÇÕES DE EDIÇÃO E EXCLUSÃO DE HÁBITOS =====
function editarRotina(id, nome, horario) {
    // Preencher o modal com os dados atuais
    document.getElementById('editHabitId').value = id;
    document.getElementById('editHabitNome').value = nome;
    document.getElementById('editHabitHorario').value = horario;
    
    // Abrir o modal
    const modal = new bootstrap.Modal(document.getElementById('modalEditarHabit'));
    modal.show();
}

function salvarEdicaoHabit() {
    const id = document.getElementById('editHabitId').value;
    const nome = document.getElementById('editHabitNome').value.trim();
    const horario = document.getElementById('editHabitHorario').value;
    
    if (!nome) {
        showToast('Erro!', 'Nome do hábito é obrigatório', true);
        return;
    }
    
    fetch('editar_rotina_diaria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, nome: nome, horario: horario })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', data.message);
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarHabit'));
            modal.hide();
            // Recarregar página
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    });
}

function excluirRotina(id, nome) {
    // Preencher o modal com os dados
    document.getElementById('excluirHabitId').value = id;
    document.getElementById('excluirHabitNome').textContent = nome;
    
    // Abrir o modal
    const modal = new bootstrap.Modal(document.getElementById('modalExcluirHabit'));
    modal.show();
}

function confirmarExclusaoHabit() {
    const id = document.getElementById('excluirHabitId').value;
    
    fetch('excluir_rotina_diaria.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', data.message);
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalExcluirHabit'));
            modal.hide();
            // Recarregar página
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    });
}
</script>

<?php require_once 'templates/footer.php'; ?>