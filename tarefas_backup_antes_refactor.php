<?php
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

// ===== ROTINA DIÁRIA FIXA INTEGRADA =====
$dataHoje = date('Y-m-d');

// Verificar e criar tabelas se necessário
try {
    // Criar tabela rotinas_fixas se não existir
    $sql_rotinas_fixas = "
    CREATE TABLE IF NOT EXISTS rotinas_fixas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        horario_sugerido TIME DEFAULT NULL,
        descricao TEXT DEFAULT NULL,
        ordem INT DEFAULT 0,
        ativo BOOLEAN DEFAULT TRUE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY unique_rotina_usuario (id_usuario, nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_rotinas_fixas);
    
    // Adicionar coluna descricao se não existir
    try {
        $pdo->exec("ALTER TABLE rotinas_fixas ADD COLUMN descricao TEXT DEFAULT NULL AFTER horario_sugerido");
    } catch (PDOException $e) {
        // Coluna já existe, ignorar erro
    }
    
    // Criar tabela rotina_controle_diario se não existir
    $sql_controle = "
    CREATE TABLE IF NOT EXISTS rotina_controle_diario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_rotina_fixa INT NOT NULL,
        data_execucao DATE NOT NULL,
        status ENUM('pendente', 'concluido', 'pulado') DEFAULT 'pendente',
        horario_execucao TIME DEFAULT NULL,
        observacoes TEXT DEFAULT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (id_rotina_fixa) REFERENCES rotinas_fixas(id) ON DELETE CASCADE,
        UNIQUE KEY unique_controle_dia (id_usuario, id_rotina_fixa, data_execucao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sql_controle);
    
    // Rotinas de exemplo removidas - usuário deve criar suas próprias rotinas
} catch (PDOException $e) {
    // Log do erro para debug
    error_log("Erro ao criar tabelas de rotinas fixas: " . $e->getMessage());
}

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
                VALUES (?, ?, ?, 'pendente')
            ");
            $stmt->execute([$userId, $rotina["id"], $dataHoje]);
        }
    }
    
    // Verificar se precisa criar controles para amanhã (reset automático)
    $dataAmanha = date('Y-m-d', strtotime('+1 day'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM rotina_controle_diario 
        WHERE id_usuario = ? AND data_execucao = ?
    ");
    $stmt->execute([$userId, $dataAmanha]);
    $controlesAmanha = $stmt->fetchColumn();
    
    if ($controlesAmanha == 0) {
        // Criar controles para amanhã (reset automático)
        $stmt = $pdo->prepare("
            SELECT id FROM rotinas_fixas 
            WHERE id_usuario = ? AND ativo = TRUE
        ");
        $stmt->execute([$userId]);
        $rotinasAtivas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($rotinasAtivas as $rotinaId) {
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                VALUES (?, ?, ?, 'pendente')
            ");
            $stmt->execute([$userId, $rotinaId, $dataAmanha]);
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


// ===== ROTINAS DE HOJE (ESPECÍFICAS DO DIA) =====
$rotinasHoje = [];
$progressoRotinaHoje = 0;
try {
    // Buscar rotinas específicas de hoje
    $stmt = $pdo->prepare("
        SELECT rd.*, crp.horario_sugerido 
        FROM rotina_diaria rd 
        LEFT JOIN config_rotina_padrao crp ON rd.nome = crp.nome AND rd.id_usuario = crp.id_usuario
        WHERE rd.id_usuario = ? AND rd.data_execucao = ? 
        ORDER BY rd.ordem, crp.horario_sugerido
    ");
    $stmt->execute([$userId, $dataHoje]);
    $rotinasHoje = $stmt->fetchAll();
    
    // Se não há rotinas para hoje, criar baseadas na configuração padrão
    if (empty($rotinasHoje)) {
        $stmt = $pdo->prepare("
            SELECT nome, horario_sugerido, ordem 
            FROM config_rotina_padrao 
            WHERE id_usuario = ? AND ativo = TRUE 
            ORDER BY ordem
        ");
        $stmt->execute([$userId]);
        $rotinasPadrao = $stmt->fetchAll();
        
        foreach ($rotinasPadrao as $rotina) {
            $stmt = $pdo->prepare("
                INSERT INTO rotina_diaria (id_usuario, nome, data_execucao, horario, ordem) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $rotina['nome'], $dataHoje, $rotina['horario_sugerido'], $rotina['ordem']]);
        }
        
        // Buscar novamente as rotinas criadas
        $stmt = $pdo->prepare("
            SELECT rd.*, crp.horario_sugerido 
            FROM rotina_diaria rd 
            LEFT JOIN config_rotina_padrao crp ON rd.nome = crp.nome AND rd.id_usuario = crp.id_usuario
            WHERE rd.id_usuario = ? AND rd.data_execucao = ? 
            ORDER BY rd.ordem, crp.horario_sugerido
        ");
        $stmt->execute([$userId, $dataHoje]);
        $rotinasHoje = $stmt->fetchAll();
    }
    
    // Calcular progresso das rotinas de hoje
    $totalRotinasHoje = count($rotinasHoje);
    $rotinasConcluidasHoje = array_filter($rotinasHoje, function($r) { return $r['status'] === 'concluido'; });
    $progressoRotinaHoje = $totalRotinasHoje > 0 ? (count($rotinasConcluidasHoje) / $totalRotinasHoje) * 100 : 0;
    
} catch (PDOException $e) {
    $rotinasHoje = [];
    $progressoRotinaHoje = 0;
    error_log("Erro ao buscar rotinas de hoje: " . $e->getMessage());
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
    flex-direction: column;
    gap: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.habit-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.habit-handle {
    font-size: 1.2rem;
    cursor: grab;
    opacity: 0.6;
    transition: opacity 0.3s ease;
}

.habit-handle:hover {
    opacity: 1;
}

.habit-item:hover .habit-handle {
    opacity: 1;
}

/* ===== ESTILOS PARA DRAG & DROP ===== */
.sortable-ghost {
    opacity: 0.4;
    background: rgba(0, 123, 255, 0.1);
    border: 2px dashed rgba(0, 123, 255, 0.5);
}

.sortable-chosen {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.sortable-drag {
    transform: rotate(2deg);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.habit-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(0, 123, 255, 0.3);
    transform: translateY(-2px);
}

.habit-item.completed {
    background: rgba(40, 167, 69, 0.1);
    border-color: rgba(40, 167, 69, 0.3);
    transform: scale(0.98);
    transition: all 0.2s ease;
}

.habit-item.completed .habit-icon {
    color: var(--success);
    transform: scale(1.1);
    transition: all 0.2s ease;
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

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6c757d;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h5 {
    margin-bottom: 0.5rem;
    color: #495057;
}

.empty-state p {
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.habit-time {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.habit-description {
    color: var(--text-secondary);
    font-size: 0.8rem;
    font-style: italic;
    display: block;
    margin-top: 0.25rem;
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

    <!-- ===== SEÇÃO ROTINAS FIXAS ===== -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="section-card rotina-card">
                <div class="section-header">
                    <div class="section-title">
                        <i class="bi bi-calendar-check me-2"></i>
                        <h3>Rotinas Fixas (Hábitos Permanentes)</h3>
                        <?php if (!empty($rotinasFixas)): ?>
                        <span class="section-badge"><?php echo count($rotinasConcluidas); ?>/<?php echo count($rotinasFixas); ?> concluídas</span>
                        <?php else: ?>
                        <span class="section-badge">Nenhuma rotina fixa</span>
                        <?php endif; ?>
                    </div>
                    <div class="section-progress">
                        <div class="progress-circular" style="--progress: <?php echo $progressoRotina; ?>%">
                            <span class="progress-text"><?php echo round($progressoRotina); ?>%</span>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($rotinasFixas)): ?>
                <div class="habits-grid" id="lista-habitos-fixos">
                    <?php foreach ($rotinasFixas as $rotina): ?>
                    <div class="habit-item <?php echo $rotina['status_hoje'] === 'concluido' ? 'completed' : ''; ?>" data-rotina-id="<?php echo $rotina['id']; ?>" data-ordem="<?php echo $rotina['ordem']; ?>">
                        <div class="habit-header">
                            <i class="bi bi-grip-vertical habit-handle" style="color: var(--text-secondary); cursor: grab;"></i>
                            <div class="habit-main">
                            <div class="habit-icon">
                                    <i class="bi bi-<?php echo $rotina['status_hoje'] === 'concluido' ? 'check-circle-fill' : 'circle'; ?>"></i>
                            </div>
                            <div class="habit-content">
                                <h6 class="habit-name"><?php echo htmlspecialchars($rotina['nome']); ?></h6>
                                    <?php if ($rotina['horario_sugerido'] && $rotina['horario_sugerido'] !== '00:00:00'): ?>
                                <small class="habit-time">
                                    <i class="bi bi-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($rotina['horario_sugerido'])); ?>
                                    </small>
                                    <?php endif; ?>
                                    <?php if ($rotina['descricao']): ?>
                                    <small class="habit-description">
                                        <i class="bi bi-card-text me-1"></i>
                                        <?php echo htmlspecialchars($rotina['descricao']); ?>
                                </small>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="habit-actions">
                            <button class="btn btn-sm btn-outline-warning" onclick="editarRotina(<?php echo $rotina['id']; ?>, '<?php echo htmlspecialchars($rotina['nome'], ENT_QUOTES); ?>', '<?php echo $rotina['horario_sugerido'] ?: ''; ?>', '<?php echo htmlspecialchars($rotina['descricao'] ?: '', ENT_QUOTES); ?>')" title="Editar hábito">
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
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAdicionarRotinaFixa">
                        <i class="bi bi-plus-circle me-1"></i>
                        Adicionar Hábito
                    </button>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-calendar-check"></i>
            </div>
                    <h5>Nenhuma rotina fixa configurada</h5>
                    <p class="text-muted">Adicione hábitos permanentes que você quer fazer todos os dias</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdicionarRotinaFixa">
                        <i class="bi bi-plus-circle me-1"></i>
                        Adicionar Primeira Rotina Fixa
                    </button>
    </div>
    <?php endif; ?>
                
            </div>
        </div>
    </div>


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
                        <button class="btn btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#modalEstatisticas">
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
                    <div class="mb-3">
                        <label for="editHabitDescricao" class="form-label">Descrição (opcional)</label>
                        <textarea class="form-control" id="editHabitDescricao" name="descricao" rows="3" placeholder="Adicione uma descrição para o hábito..."></textarea>
                        <div class="form-text">Descrição opcional para o hábito</div>
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

<!-- Modal de Adicionar Rotina Fixa -->
<div class="modal fade" id="modalAdicionarRotinaFixa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h5 class="modal-title" style="color: var(--text-primary);">
                    <i class="bi bi-plus-circle me-2"></i>Adicionar Rotina Fixa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAdicionarRotinaFixa">
                    <div class="mb-3">
                        <label for="nomeRotinaFixa" class="form-label" style="color: var(--text-primary);">
                            <i class="bi bi-tag me-1"></i>Nome da Rotina
                        </label>
                        <input type="text" class="form-control" id="nomeRotinaFixa" placeholder="Ex: Treinar, Estudar, Meditar..." required style="background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                    </div>
                    <div class="mb-3">
                        <label for="horarioRotinaFixa" class="form-label" style="color: var(--text-primary);">
                            <i class="bi bi-clock me-1"></i>Horário Sugerido (Opcional)
                        </label>
                        <input type="time" class="form-control" id="horarioRotinaFixa" style="background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="form-text" style="color: var(--text-secondary);">
                            <i class="bi bi-info-circle me-1"></i>Defina um horário ideal para esta rotina
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="descricaoRotinaFixa" class="form-label" style="color: var(--text-primary);">
                            <i class="bi bi-card-text me-1"></i>Descrição (Opcional)
                        </label>
                        <textarea class="form-control" id="descricaoRotinaFixa" rows="2" placeholder="Adicione uma descrição ou observações sobre esta rotina..." style="background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text-primary);"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="salvarRotinaFixa()">
                    <i class="bi bi-check-circle me-1"></i>Adicionar Rotina
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ===== SISTEMA DE BUSCA E FILTROS =====
document.addEventListener('DOMContentLoaded', function() {
    
    // ===== EVENT DELEGATION PARA ROTINAS FIXAS =====
    // Usar event delegation para garantir que funcione mesmo após atualizações dinâmicas
    document.addEventListener('click', function(e) {
        // Verificar se é um clique em habit-main
        if (e.target.closest('.habit-main')) {
            const habitMain = e.target.closest('.habit-main');
            const habitItem = habitMain.closest('.habit-item');
            const rotinaId = habitItem.getAttribute('data-rotina-id');
            
            if (rotinaId) {
                // Encontrar o status atual
                const isCompleted = habitItem.classList.contains('completed');
                const statusAtual = isCompleted ? 'concluido' : 'pendente';
                
                // Chamar toggleRotina
                toggleRotina(parseInt(rotinaId), statusAtual);
            }
        }
    });
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
    
    // Event listener único para todos os botões
    document.addEventListener('click', function(e) {
        console.log('Clique detectado em:', e.target.tagName, e.target.className);
        
        // Botão de concluir tarefa
        if (e.target.closest('.btn-complete')) {
            const btn = e.target.closest('.btn-complete');
            console.log('✅ Botão de concluir encontrado:', btn.dataset.id);
            completeTask(btn.dataset.id);
            return;
        }
        
        // Botão de adicionar subtarefa
        if (e.target.closest('.btn-subtask')) {
            const btn = e.target.closest('.btn-subtask');
            const taskId = btn.dataset.id;
            document.getElementById('id_tarefa_principal').value = taskId;
            
            // Abrir modal
            const modal = new bootstrap.Modal(document.getElementById('modalAdicionarSubtarefa'));
            modal.show();
            return;
        }
        
        // Botão de editar tarefa
        if (e.target.closest('.btn-edit')) {
            const btn = e.target.closest('.btn-edit');
            const taskId = btn.dataset.id;
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
            return;
        }
        
        // Botão de excluir tarefa
        if (e.target.closest('.btn-delete')) {
            const btn = e.target.closest('.btn-delete');
            const taskId = btn.dataset.id;
            currentDeleteTaskId = taskId;
            
            // Buscar título da tarefa para exibir no modal
            const taskCard = btn.closest('.task-card');
            const taskTitle = taskCard.querySelector('.task-title').textContent;
            
            document.getElementById('delete_task_title').textContent = taskTitle;
            
            // Abrir modal
            const modal = new bootstrap.Modal(document.getElementById('modalExcluirTarefa'));
            modal.show();
            return;
        }
        
        // Botão de excluir subtarefa
        if (e.target.closest('.btn-delete-subtask')) {
            const btn = e.target.closest('.btn-delete-subtask');
            const subtaskId = btn.dataset.id;
            const subtaskItem = btn.closest('.subtask-item');
            const subtaskText = subtaskItem.querySelector('.subtask-label').textContent.trim();
            
            // Confirmação antes de excluir
            if (confirm(`Tem certeza que deseja excluir a subtarefa "${subtaskText}"?`)) {
                // Mostrar loading no botão
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
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
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', true);
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                });
            }
            return;
        }
    });
    
    // ===== ADICIONAR SUBTAREFA =====
    // Event listener já consolidado acima
    
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
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalAdicionarSubtarefa'));
                    modal.hide();
                    // Limpar formulário
                    formAdicionarSubtarefa.reset();
                    setTimeout(() => {
                        // Atualizar interface sem reload
                        atualizarListaTarefas();
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
            })
            .finally(() => {
                // Reabilitar botão em caso de erro
                if (button.disabled) {
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Adicionar Subtarefa';
                }
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
    
    // Atualizar status de subtarefa usando event delegation
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('subtask-checkbox')) {
            const checkbox = e.target;
            const subtaskId = checkbox.dataset.id;
            const status = checkbox.checked ? 'concluida' : 'pendente';
            const label = checkbox.closest('.subtask-item').querySelector('label');
            
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
                    checkbox.checked = !checkbox.checked;
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
                checkbox.checked = !checkbox.checked;
                showToast('Erro!', 'Erro de conexão', true);
            });
        }
    });
    
    // ===== EXCLUSÃO DE SUBTAREFAS =====
    // Excluir subtarefa usando event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-delete-subtask')) {
            e.stopPropagation(); // Evitar conflito com edição inline
            const btn = e.target.closest('.btn-delete-subtask');
            const subtaskId = btn.dataset.id;
            const subtaskItem = btn.closest('.subtask-item');
            const subtaskText = subtaskItem.querySelector('.subtask-label').textContent.trim();
            
            // Confirmação antes de excluir
            if (confirm(`Tem certeza que deseja excluir a subtarefa "${subtaskText}"?`)) {
                // Mostrar loading no botão
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
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
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', true);
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                });
            }
            }
    });
    
    // ===== MODAL DE EDITAR TAREFA =====
    let currentEditTaskId = null;
    
    // Abrir modal de editar usando event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-edit')) {
            const btn = e.target.closest('.btn-edit');
            const taskId = btn.dataset.id;
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
                    
                    // Atualizar interface sem reload
                    setTimeout(() => {
                        atualizarListaTarefas();
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
    
    // Abrir modal de excluir usando event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-delete')) {
            const btn = e.target.closest('.btn-delete');
            const taskId = btn.dataset.id;
            currentDeleteTaskId = taskId;
            
            // Buscar título da tarefa para exibir no modal
            const taskCard = btn.closest('.task-card');
            const taskTitle = taskCard.querySelector('.task-title').textContent;
            
            document.getElementById('delete_task_title').textContent = taskTitle;
            
            // Abrir modal
            const modal = new bootstrap.Modal(document.getElementById('modalExcluirTarefa'));
            modal.show();
        }
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
    
    // ===== SORTABLE DRAG & DROP PARA HÁBITOS FIXOS =====
    const listaHabitos = document.getElementById('lista-habitos-fixos');
    if (listaHabitos) {
        new Sortable(listaHabitos, {
            animation: 200,
            handle: '.habit-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                const items = listaHabitos.querySelectorAll('.habit-item');
                const novaOrdem = Array.from(items).map(item => item.dataset.rotinaId);
                
                fetch('atualizar_ordem_habitos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ordem: novaOrdem })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Ordem dos hábitos atualizada com sucesso');
                    } else {
                        console.error('Erro ao atualizar ordem:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
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
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovaTarefa'));
                    modal.hide();
                    // Limpar formulário
                    formNovaTarefa.reset();
                    setTimeout(() => {
                        // Atualizar interface sem reload
                        atualizarListaTarefas();
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
            })
            .finally(() => {
                // Reabilitar botão em caso de erro
                if (button.disabled) {
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-save me-2"></i>Salvar Tarefa';
                }
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
// Event listener para carregar estatísticas quando o modal abrir
document.addEventListener('DOMContentLoaded', function() {
    const modalEstatisticas = document.getElementById('modalEstatisticas');
    if (modalEstatisticas) {
        modalEstatisticas.addEventListener('show.bs.modal', function() {
    carregarEstatisticas();
        });
}
});

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

// ===== FUNÇÕES DE ATUALIZAÇÃO OTIMIZADAS =====
function atualizarSecaoRotinasFixas() {
    // Buscar dados atualizados das rotinas fixas
    fetch('api_rotinas_fixas.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar a seção de rotinas fixas
                const container = document.querySelector('.habits-grid');
                if (container) {
                    // Limpar completamente o container
                    container.innerHTML = '';
                    
                    if (data.rotinas.length === 0) {
                        // Mostrar estado vazio
                        const emptyState = document.querySelector('.empty-state');
                        if (emptyState) {
                            emptyState.style.display = 'block';
                        }
                    } else {
                        // Esconder estado vazio
                        const emptyState = document.querySelector('.empty-state');
                        if (emptyState) {
                            emptyState.style.display = 'none';
                        }
                        
                        // Renderizar rotinas uma por uma
                        data.rotinas.forEach((rotina, index) => {
                            const habitItem = criarElementoRotina(rotina);
                            container.appendChild(habitItem);
                        });
                    }
                    
                    // Atualizar contadores e progresso
                    atualizarContadoresRotinas(data.rotinas);
                }
            }
        })
        .catch(error => {
            console.error('Erro ao atualizar rotinas fixas:', error);
            // Em caso de erro, mostrar mensagem mas não recarregar
            showToast('Aviso', 'Erro ao atualizar hábitos. Recarregue a página se necessário.', false);
        });
}

// Função auxiliar para criar elemento de rotina
function criarElementoRotina(rotina) {
    const habitItem = document.createElement('div');
    habitItem.className = `habit-item ${rotina.status_hoje === 'concluido' ? 'completed' : ''}`;
    habitItem.setAttribute('data-rotina-id', rotina.id);
    habitItem.setAttribute('data-ordem', rotina.ordem || 0);
    
    const horarioHtml = (rotina.horario_sugerido && rotina.horario_sugerido !== '00:00:00') ? `
        <small class="habit-time">
            <i class="bi bi-clock me-1"></i>
            ${rotina.horario_sugerido.substring(0, 5)}
        </small>
    ` : '';
    
    const descricaoHtml = rotina.descricao ? `
        <small class="habit-description">
            <i class="bi bi-card-text me-1"></i>
            ${rotina.descricao}
        </small>
    ` : '';
    
    habitItem.innerHTML = `
        <div class="habit-header">
            <i class="bi bi-grip-vertical habit-handle" style="color: var(--text-secondary); cursor: grab;"></i>
            <div class="habit-main">
                <div class="habit-icon">
                    <i class="bi bi-${rotina.status_hoje === 'concluido' ? 'check-circle-fill' : 'circle'}"></i>
                </div>
                <div class="habit-content">
                    <h6 class="habit-name">${rotina.nome}</h6>
                    ${horarioHtml}
                    ${descricaoHtml}
                </div>
            </div>
        </div>
        <div class="habit-actions">
            <button class="btn btn-sm btn-outline-warning" onclick="editarRotina(${rotina.id}, '${rotina.nome.replace(/'/g, "\\'")}', '${rotina.horario_sugerido || ''}', '${(rotina.descricao || '').replace(/'/g, "\\'")}');" title="Editar hábito">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="excluirRotina(${rotina.id}, '${rotina.nome.replace(/'/g, "\\'")}');" title="Excluir hábito">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    return habitItem;
}

// Função auxiliar para atualizar contadores
function atualizarContadoresRotinas(rotinas) {
    const concluidas = rotinas.filter(r => r.status_hoje === 'concluido').length;
    const total = rotinas.length;
    const progresso = total > 0 ? (concluidas / total) * 100 : 0;
    
    // Atualizar badge
    const badge = document.querySelector('.section-badge');
    if (badge) {
        badge.textContent = `${concluidas}/${total} concluídas`;
    }
    
    // Atualizar progresso
    const progressElement = document.querySelector('.progress-circular');
    if (progressElement) {
        progressElement.style.setProperty('--progress', progresso + '%');
        const progressText = progressElement.querySelector('.progress-text');
        if (progressText) {
            progressText.textContent = Math.round(progresso) + '%';
        }
    }
}

function atualizarListaTarefas() {
    // Buscar dados atualizados das tarefas
    fetch('api_tarefas_pendentes.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
                const container = document.getElementById('lista-tarefas-pendentes');
                if (container) {
                    container.innerHTML = '';
                    
                    if (data.tarefas.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle" style="font-size: 4rem; color: var(--success); margin-bottom: 1rem;"></i>
                                <h5 style="color: var(--text-primary);">Nenhuma tarefa pendente!</h5>
                                <p style="color: var(--text-secondary);">Parabéns! Você está em dia com suas tarefas.</p>
                            </div>
                        `;
        } else {
                        data.tarefas.forEach(tarefa => {
                            const taskCard = document.createElement('div');
                            taskCard.className = `task-card prioridade-${tarefa.prioridade} fade-in`;
                            taskCard.setAttribute('data-id', tarefa.id);
                            taskCard.setAttribute('data-priority', tarefa.prioridade.toLowerCase().replace(/é/g, 'e'));
                            
                            taskCard.innerHTML = `
                                <div class="task-header">
                                    <i class="bi bi-grip-vertical handle" style="color: var(--text-secondary); cursor: grab;"></i>
                                    
                                    <div class="task-content">
                                        <div class="task-title">${tarefa.descricao}</div>
                                        
                                        <div class="task-meta">
                                            <span class="badge ${getPrioridadeBadgeClass(tarefa.prioridade)}">
                                                ${tarefa.prioridade}
                                            </span>
                                            
                                            ${tarefa.data_limite ? 
                                                `<span><i class="bi bi-calendar-event me-1"></i>${new Date(tarefa.data_limite).toLocaleDateString('pt-BR')}</span>` : 
                                                ''
                                            }
                                            
                                            ${tarefa.tempo_estimado > 0 ? 
                                                `<span><i class="bi bi-clock me-1"></i>${formatarTempo(tarefa.tempo_estimado)}</span>` : 
                                                ''
                                            }
                                            
                                            ${tarefa.subtarefas && tarefa.subtarefas.length > 0 ? 
                                                `<span><i class="bi bi-list-ul me-1"></i>${tarefa.subtarefas.length} subtarefas</span>` : 
                                                ''
                                            }
                                        </div>
                                    </div>
                                    
                                    <div class="task-actions">
                                        <button class="btn-icon btn-subtask" data-id="${tarefa.id}" title="Adicionar Subtarefa">
                                            <i class="bi bi-list-ul"></i>
                                        </button>
                                        <button class="btn-icon btn-timer" data-id="${tarefa.id}" title="Iniciar Timer">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                        <button class="btn-icon btn-complete" data-id="${tarefa.id}" title="Concluir">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button class="btn-icon btn-edit" data-id="${tarefa.id}" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn-icon btn-delete" data-id="${tarefa.id}" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                ${tarefa.subtarefas && tarefa.subtarefas.length > 0 ? 
                                    `<div class="subtasks mt-3 pt-3" style="border-top: 1px solid var(--border-color);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0" style="color: var(--text-secondary);">
                                                <i class="bi bi-list-ul me-1"></i>Subtarefas
                                            </h6>
                                            <button class="btn btn-sm btn-outline-primary" onclick="toggleSubtasks(this)">
                                                <i class="bi bi-chevron-down"></i>
                                            </button>
                                        </div>
                                        <div class="subtasks-list">
                                            ${tarefa.subtarefas.map(sub => 
                                                `<div class="subtask-item d-flex align-items-center mb-2 p-2" 
                                                     style="background: var(--dark-bg); border-radius: 8px; border: 1px solid var(--border-color);">
                                                    <div class="form-check me-3">
                                                        <input class="form-check-input subtask-checkbox" type="checkbox" 
                                                               data-id="${sub.id}" 
                                                               ${sub.status === 'concluida' ? 'checked' : ''}>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <label class="form-check-label mb-0 subtask-label ${sub.status === 'concluida' ? 'text-decoration-line-through text-muted' : ''}" 
                                                                   style="color: var(--text-primary); cursor: pointer;"
                                                                   data-id="${sub.id}"
                                                                   title="Clique para editar">
                                                                ${sub.descricao}
                                                            </label>
                                                            <div class="d-flex align-items-center gap-2">
                                                                ${sub.prioridade ? 
                                                                    `<span class="badge ${getPrioridadeBadgeClass(sub.prioridade)}" 
                                                                          style="font-size: 0.7rem;">
                                                                        ${sub.prioridade}
                                                                    </span>` : ''
                                                                }
                                                                ${sub.tempo_estimado > 0 ? 
                                                                    `<small class="text-muted">
                                                                        <i class="bi bi-clock me-1"></i>${formatarTempo(sub.tempo_estimado)}
                                                                    </small>` : ''
                                                                }
                                                                
                                                                <button class="btn btn-sm btn-outline-danger btn-delete-subtask" 
                                                                        data-id="${sub.id}"
                                                                        title="Excluir subtarefa"
                                                                        style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>`
                                            ).join('')}
                                        </div>
                                    </div>` : 
                                    ''
                                }
                                
                                <div class="swipe-actions">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                            `;
                            
                            container.appendChild(taskCard);
                        });
                        
                        // Event listeners já configurados globalmente com event delegation
                    }
                }
            }
        })
        .catch(error => {
            console.error('Erro ao atualizar lista de tarefas:', error);
        });
}

function getPrioridadeBadgeClass(prioridade) {
    switch (prioridade) {
        case 'Alta': return 'bg-danger';
        case 'Média': return 'bg-warning text-dark';
        case 'Baixa': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function formatarTempo(minutos) {
    if (minutos <= 0) return '0min';
    const h = Math.floor(minutos / 60);
    const m = minutos % 60;
    let resultado = '';
    if (h > 0) resultado += h + 'h ';
    if (m > 0) resultado += m + 'min';
    return resultado.trim();
}

// Função removida - agora usamos event delegation global

// ===== FUNÇÕES ROTINA DIÁRIA OTIMIZADAS =====
const rotinasEmProcessamento = new Set();

window.toggleRotina = function(id, statusAtual) {
    // Prevenir múltiplas requisições simultâneas
    if (rotinasEmProcessamento.has(id)) {
        return;
    }
    
    const novoStatus = statusAtual === 'concluido' ? 'pendente' : 'concluido';
    const acao = novoStatus === 'concluido' ? 'concluir' : 'pendente';
    
    // Marcar como em processamento
    rotinasEmProcessamento.add(id);
    
    // Encontrar o elemento da rotina de forma mais robusta
    let habitItem = null;
    let icon = null;
    
    // Tentar encontrar pelo data attribute primeiro
    habitItem = document.querySelector(`[data-rotina-id="${id}"]`);
    
    // Se não encontrar, tentar pelo onclick
    if (!habitItem) {
        const elements = document.querySelectorAll('.habit-main');
        for (let element of elements) {
            if (element.getAttribute('onclick') && element.getAttribute('onclick').includes(`toggleRotina(${id}`)) {
                habitItem = element.closest('.habit-item');
                break;
            }
        }
    }
    
    // Se ainda não encontrar, usar o evento atual
    if (!habitItem) {
        habitItem = event.currentTarget.closest('.habit-item');
    }
    
    if (!habitItem) {
        console.error('Elemento da rotina não encontrado');
        rotinasEmProcessamento.delete(id);
        return;
    }
    
    icon = habitItem.querySelector('.habit-icon i');
    const progressElement = document.querySelector('.progress-circular');
    const badge = document.querySelector('.section-badge');
    
    // Atualizar visual instantaneamente
    if (novoStatus === 'concluido') {
        habitItem.classList.add('completed');
        icon.className = 'bi bi-check-circle-fill';
    } else {
        habitItem.classList.remove('completed');
        icon.className = 'bi bi-circle';
    }
    
    // Atualizar progresso instantaneamente
    const totalRotinas = document.querySelectorAll('.habit-item').length;
    const rotinasConcluidas = document.querySelectorAll('.habit-item.completed').length;
    const progresso = totalRotinas > 0 ? (rotinasConcluidas / totalRotinas) * 100 : 0;
    
    if (progressElement) {
        progressElement.style.setProperty('--progress', progresso + '%');
        const progressText = progressElement.querySelector('.progress-text');
        if (progressText) {
            progressText.textContent = Math.round(progresso) + '%';
        }
    }
    
    if (badge) {
        badge.textContent = `${rotinasConcluidas}/${totalRotinas} concluídas`;
    }
    
    // Enviar para servidor em background
    console.log('Enviando requisição:', { acao, rotina_id: id });
    
    fetch('processar_rotina_fixa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `acao=${acao}&rotina_id=${id}`
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (!data.success) {
            // Reverter mudanças se houver erro
            if (novoStatus === 'concluido') {
                habitItem.classList.remove('completed');
                icon.className = 'bi bi-circle';
            } else {
                habitItem.classList.add('completed');
                icon.className = 'bi bi-check-circle-fill';
            }
            showToast('Erro!', data.message, true);
            if (data.debug) {
                console.error('Debug info:', data.debug);
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Reverter mudanças se houver erro de rede
        if (novoStatus === 'concluido') {
            habitItem.classList.remove('completed');
            icon.className = 'bi bi-circle';
        } else {
            habitItem.classList.add('completed');
            icon.className = 'bi bi-check-circle-fill';
        }
        showToast('Erro!', 'Erro de conexão', true);
    })
    .finally(() => {
        // Remover da lista de processamento
        rotinasEmProcessamento.delete(id);
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

// Função para salvar rotina fixa
window.salvarRotinaFixa = function() {
    const nome = document.getElementById('nomeRotinaFixa').value.trim();
    const horario = document.getElementById('horarioRotinaFixa').value;
    const descricao = document.getElementById('descricaoRotinaFixa').value.trim();
    
    if (!nome) {
        showToast('Erro!', 'Nome da rotina é obrigatório', true);
        return;
    }
    
    // Desabilitar botão durante o envio
    const btnSalvar = document.querySelector('#modalAdicionarRotinaFixa .btn-primary');
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Salvando...';
    
    fetch('processar_rotina_fixa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `acao=adicionar&nome=${encodeURIComponent(nome)}&horario=${encodeURIComponent(horario)}&descricao=${encodeURIComponent(descricao)}`
    })
    .then(response => response.json())
        .then(data => {
        if (data.success) {
            showToast('Sucesso!', data.message);
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAdicionarRotinaFixa'));
            modal.hide();
            // Recarregar página para garantir sincronização
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('Erro!', data.message, true);
        }
        })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    })
    .finally(() => {
        // Reabilitar botão
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = '<i class="bi bi-check-circle me-1"></i>Adicionar Rotina';
    });
}

// ===== FUNÇÕES DE EDIÇÃO E EXCLUSÃO DE HÁBITOS =====
window.editarRotina = function(id, nome, horario, descricao = '') {
    // Preencher o modal com os dados atuais
    document.getElementById('editHabitId').value = id;
    document.getElementById('editHabitNome').value = nome;
    document.getElementById('editHabitHorario').value = horario;
    document.getElementById('editHabitDescricao').value = descricao;
    
    // Abrir o modal
    const modal = new bootstrap.Modal(document.getElementById('modalEditarHabit'));
    modal.show();
}

function salvarEdicaoHabit() {
    const id = document.getElementById('editHabitId').value;
    const nome = document.getElementById('editHabitNome').value.trim();
    const horario = document.getElementById('editHabitHorario').value;
    const descricao = document.getElementById('editHabitDescricao') ? document.getElementById('editHabitDescricao').value.trim() : '';
    
    if (!nome) {
        showToast('Erro!', 'Nome do hábito é obrigatório', true);
        return;
    }
    
    fetch('editar_rotina_fixa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            id: id, 
            nome: nome, 
            horario: horario,
            descricao: descricao
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', data.message);
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarHabit'));
            modal.hide();
            // Recarregar a página para garantir sincronização
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    });
}

window.excluirRotina = function(id, nome) {
    // Preencher o modal com os dados
    document.getElementById('excluirHabitId').value = id;
    document.getElementById('excluirHabitNome').textContent = nome;
    
    // Abrir o modal
    const modal = new bootstrap.Modal(document.getElementById('modalExcluirHabit'));
    modal.show();
}

function confirmarExclusaoHabit() {
    const id = document.getElementById('excluirHabitId').value;
    
    fetch('excluir_rotina_fixa.php', {
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
            // Recarregar página para evitar problemas de sincronização
            setTimeout(() => {
                window.location.reload();
            }, 1000);
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