<?php
require_once 'templates/header.php';

// O header.php já verifica autenticação e define $userId
// Não precisa incluir db_connect.php pois já está no header

$dataHoje = date('Y-m-d');

// ===== CRIAR TABELAS =====
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rotinas_fixas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            horario_sugerido TIME DEFAULT NULL,
            descricao TEXT DEFAULT NULL,
            ordem INT DEFAULT 0,
            ativo BOOLEAN DEFAULT TRUE,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rotina_controle_diario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            id_rotina_fixa INT NOT NULL,
            data_execucao DATE NOT NULL,
            status ENUM('pendente', 'concluido', 'pulado') DEFAULT 'pendente',
            horario_execucao TIME DEFAULT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (id_rotina_fixa) REFERENCES rotinas_fixas(id) ON DELETE CASCADE,
            UNIQUE KEY unique_controle (id_usuario, id_rotina_fixa, data_execucao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("Erro: " . $e->getMessage());
}

// ===== BUSCAR ROTINAS =====
$rotinasFixas = [];
try {
    $stmt = $pdo->prepare("
        SELECT rf.*, rcd.status as status_hoje
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd 
            ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY rf.ordem, rf.horario_sugerido
    ");
    $stmt->execute([$dataHoje, $userId]);
    $rotinasFixas = $stmt->fetchAll();
    
    foreach ($rotinasFixas as &$rotina) {
        if ($rotina['status_hoje'] === null) {
            $stmt = $pdo->prepare("INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) VALUES (?, ?, ?, 'pendente')");
            $stmt->execute([$userId, $rotina['id'], $dataHoje]);
            $rotina['status_hoje'] = 'pendente';
        }
    }
} catch (PDOException $e) {
    error_log("Erro: " . $e->getMessage());
}

// ===== BUSCAR TAREFAS =====
$tarefas_pendentes = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'pendente' ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC");
    $stmt->execute([$userId]);
    $tarefas_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro: " . $e->getMessage());
}

function getPrioridadeBadge($prioridade) {
    return match($prioridade) {
        'Alta' => 'badge-danger',
        'Média' => 'badge-warning',
        'Baixa' => 'badge-success',
        default => 'badge-secondary'
    };
}
?>

<style>
/* ===== DESIGN SYSTEM MODERNO ===== */
:root {
    --primary: #dc3545;
    --primary-light: rgba(220, 53, 69, 0.15);
    --secondary: #6c757d;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    
    --bg-primary: #0a0a0a;
    --bg-secondary: #141414;
    --bg-glass: rgba(20, 20, 20, 0.7);
    --bg-glass-hover: rgba(30, 30, 30, 0.8);
    
    --text-primary: #ffffff;
    --text-secondary: #b0b0b0;
    --text-muted: #808080;
    
    --border-glass: rgba(255, 255, 255, 0.1);
    --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
    --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.4);
    --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.5);
    --shadow-glow: 0 0 20px rgba(220, 53, 69, 0.3);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #0a0a0a 0%, #1a0505 50%, #0a0a0a 100%);
    background-attachment: fixed;
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    overflow-x: hidden;
}

/* ===== ANIMATED BACKGROUND ===== */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(220, 53, 69, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(220, 53, 69, 0.05) 0%, transparent 50%);
    pointer-events: none;
    animation: bgMove 20s ease-in-out infinite;
}

@keyframes bgMove {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.6; }
}

/* ===== GLASSMORPHISM CONTAINER ===== */
.glass-container {
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid var(--border-glass);
    border-radius: 24px;
    padding: 2rem;
    box-shadow: var(--shadow-lg);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.glass-container:hover {
    background: var(--bg-glass-hover);
    border-color: rgba(220, 53, 69, 0.3);
    box-shadow: var(--shadow-lg), var(--shadow-glow);
    transform: translateY(-2px);
}

/* ===== PAGE HEADER ===== */
.page-header {
    text-align: center;
    padding: 3rem 0;
    position: relative;
}

.page-title {
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 800;
    background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1rem;
    letter-spacing: -0.5px;
    animation: fadeInDown 0.8s ease;
}

.page-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
    font-weight: 300;
    animation: fadeInUp 0.8s ease 0.2s both;
}

/* ===== SECTION CARDS ===== */
.section-card {
    margin-bottom: 2rem;
    animation: fadeInUp 0.6s ease both;
}

.section-card:nth-child(2) { animation-delay: 0.1s; }
.section-card:nth-child(3) { animation-delay: 0.2s; }

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid;
    border-image: linear-gradient(90deg, var(--primary), transparent) 1;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}

.section-title i {
    font-size: 2rem;
    color: var(--primary);
    filter: drop-shadow(0 0 10px rgba(220, 53, 69, 0.5));
}

/* ===== NEUMORPHIC BUTTONS ===== */
.btn-neuro {
    position: relative;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
    border: none;
    border-radius: 12px;
    color: var(--text-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 
        5px 5px 10px rgba(0, 0, 0, 0.5),
        -5px -5px 10px rgba(40, 40, 40, 0.1);
}

.btn-neuro:hover {
    transform: translateY(-2px);
    box-shadow: 
        7px 7px 15px rgba(0, 0, 0, 0.6),
        -7px -7px 15px rgba(40, 40, 40, 0.2);
}

.btn-neuro:active {
    transform: translateY(0);
    box-shadow: 
        inset 3px 3px 6px rgba(0, 0, 0, 0.5),
        inset -3px -3px 6px rgba(40, 40, 40, 0.1);
}

.btn-neuro-danger {
    background: linear-gradient(145deg, #e63946, #c41e2c);
    box-shadow: 
        5px 5px 10px rgba(0, 0, 0, 0.5),
        -5px -5px 10px rgba(230, 57, 70, 0.2);
}

.btn-neuro-danger:hover {
    box-shadow: 
        7px 7px 15px rgba(0, 0, 0, 0.6),
        -7px -7px 15px rgba(230, 57, 70, 0.3),
        0 0 20px rgba(220, 53, 69, 0.4);
}

/* ===== HABITS GRID ===== */
.habits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.habit-item {
    position: relative;
    background: linear-gradient(145deg, rgba(30, 30, 30, 0.6), rgba(20, 20, 20, 0.6));
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-glass);
    border-radius: 20px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.habit-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.habit-item:hover {
    transform: translateY(-5px) scale(1.02);
    border-color: rgba(220, 53, 69, 0.5);
    box-shadow: 
        var(--shadow-md),
        0 0 30px rgba(220, 53, 69, 0.2);
}

.habit-item:hover::before {
    opacity: 1;
}

.habit-item.completed {
    background: linear-gradient(145deg, rgba(40, 167, 69, 0.2), rgba(30, 30, 30, 0.6));
    border-color: rgba(40, 167, 69, 0.5);
}

.habit-item.completed::after {
    content: '✓';
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 2rem;
    color: var(--success);
    opacity: 0.3;
    font-weight: bold;
}

.habit-main {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.habit-icon {
    font-size: 2rem;
    color: var(--text-muted);
    transition: all 0.3s ease;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.habit-item:hover .habit-icon {
    transform: scale(1.1);
    color: var(--primary);
}

.habit-item.completed .habit-icon {
    color: var(--success);
    transform: scale(1.1);
}

.habit-content {
    flex: 1;
}

.habit-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.habit-time, .habit-description {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

.habit-description {
    font-style: italic;
    opacity: 0.8;
}

.habit-actions {
    display: flex;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.btn-icon-neuro {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 
        3px 3px 6px rgba(0, 0, 0, 0.4),
        -3px -3px 6px rgba(40, 40, 40, 0.1);
}

.btn-icon-neuro:hover {
    transform: translateY(-2px);
    color: var(--text-primary);
    border-color: var(--primary);
    box-shadow: 
        4px 4px 8px rgba(0, 0, 0, 0.5),
        -4px -4px 8px rgba(40, 40, 40, 0.2),
        0 0 15px rgba(220, 53, 69, 0.3);
}

/* ===== TASK CARDS ===== */
.task-card {
    background: linear-gradient(145deg, rgba(30, 30, 30, 0.6), rgba(20, 20, 20, 0.6));
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-glass);
    border-left: 4px solid var(--secondary);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.task-card:hover {
    transform: translateX(5px);
    box-shadow: var(--shadow-md);
}

.task-card.prioridade-Alta {
    border-left-color: var(--danger);
    background: linear-gradient(145deg, rgba(220, 53, 69, 0.05), rgba(20, 20, 20, 0.6));
}

.task-card.prioridade-Média {
    border-left-color: var(--warning);
}

.task-card.prioridade-Baixa {
    border-left-color: var(--success);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
}

.task-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
}

.task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.task-meta span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.task-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

/* ===== BADGES ===== */
.badge {
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.badge-danger {
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.3), rgba(220, 53, 69, 0.2));
    color: #ff6b6b;
    border: 1px solid rgba(220, 53, 69, 0.5);
}

.badge-warning {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.3), rgba(255, 193, 7, 0.2));
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.5);
}

.badge-success {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.3), rgba(40, 167, 69, 0.2));
    color: #28a745;
    border: 1px solid rgba(40, 167, 69, 0.5);
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    opacity: 0.3;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.empty-state h5 {
    font-size: 1.5rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 2rem;
}

/* ===== MODALS ===== */
.modal-content {
    background: linear-gradient(145deg, rgba(30, 30, 30, 0.95), rgba(20, 20, 20, 0.95));
    backdrop-filter: blur(20px);
    border: 1px solid rgba(220, 53, 69, 0.3);
    border-radius: 24px;
    box-shadow: var(--shadow-lg), 0 0 50px rgba(220, 53, 69, 0.2);
}

.modal-header {
    border-bottom: 1px solid rgba(220, 53, 69, 0.2);
    padding: 1.5rem 2rem;
}

.modal-title {
    color: var(--text-primary);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid rgba(220, 53, 69, 0.2);
    padding: 1.5rem 2rem;
}

/* ===== FORM CONTROLS ===== */
.form-label {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: block;
}

.form-control, .form-select {
    background: rgba(10, 10, 10, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    background: rgba(10, 10, 10, 0.9);
    border-color: var(--primary);
    box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25), 0 0 20px rgba(220, 53, 69, 0.2);
    color: var(--text-primary);
    outline: none;
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .glass-container {
        padding: 1.5rem;
        border-radius: 20px;
    }
    
    .habits-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .section-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .task-header {
        flex-direction: column;
    }
    
    .task-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .page-title {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .page-header {
        padding: 2rem 0;
    }
    
    .section-title {
        font-size: 1.5rem;
    }
    
    .glass-container {
        padding: 1rem;
    }
}

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar {
    width: 10px;
}

::-webkit-scrollbar-track {
    background: rgba(10, 10, 10, 0.5);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #dc3545, #c41e2c);
    border-radius: 5px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, #e63946, #dc3545);
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-check2-square"></i>
            Rotina de Tarefas
        </h1>
        <p class="page-subtitle">Organize suas tarefas e hábitos com estilo profissional</p>
    </div>

    <!-- Rotinas Fixas -->
    <div class="section-card glass-container">
        <div class="section-header">
            <div class="section-title">
                <i class="bi bi-calendar-check"></i>
                <span>Hábitos Diários</span>
            </div>
            <button class="btn-neuro btn-neuro-danger" data-bs-toggle="modal" data-bs-target="#modalNovoHabito">
                <i class="bi bi-plus-circle me-2"></i>Novo Hábito
            </button>
        </div>
        
        <?php if (empty($rotinasFixas)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar-check"></i></div>
                <h5>Nenhum hábito cadastrado</h5>
                <p>Comece adicionando hábitos que você quer praticar diariamente</p>
                <button class="btn-neuro btn-neuro-danger" data-bs-toggle="modal" data-bs-target="#modalNovoHabito">
                    <i class="bi bi-plus-circle me-2"></i>Criar Primeiro Hábito
                </button>
            </div>
        <?php else: ?>
            <div class="habits-grid">
                <?php foreach ($rotinasFixas as $rotina): ?>
                    <div class="habit-item <?= $rotina['status_hoje'] === 'concluido' ? 'completed' : '' ?>" 
                         data-id="<?= $rotina['id'] ?>"
                         data-status="<?= $rotina['status_hoje'] ?>"
                         data-nome="<?= htmlspecialchars($rotina['nome']) ?>"
                         data-horario="<?= $rotina['horario_sugerido'] ?? '' ?>"
                         data-descricao="<?= htmlspecialchars($rotina['descricao']) ?>">
                        <div class="habit-main">
                            <div class="habit-icon">
                                <i class="bi bi-<?= $rotina['status_hoje'] === 'concluido' ? 'check-circle-fill' : 'circle' ?>"></i>
                            </div>
                            <div class="habit-content">
                                <div class="habit-name"><?= htmlspecialchars($rotina['nome']) ?></div>
                                <?php if ($rotina['horario_sugerido'] && $rotina['horario_sugerido'] !== '00:00:00'): ?>
                                    <div class="habit-time">
                                        <i class="bi bi-clock"></i>
                                        <span><?= date('H:i', strtotime($rotina['horario_sugerido'])) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($rotina['descricao']): ?>
                                    <div class="habit-description">
                                        <i class="bi bi-card-text"></i>
                                        <span><?= htmlspecialchars($rotina['descricao']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="habit-actions">
                            <button class="btn-icon-neuro btn-edit-habit" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-icon-neuro btn-delete-habit" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tarefas -->
    <div class="section-card glass-container">
        <div class="section-header">
            <div class="section-title">
                <i class="bi bi-list-task"></i>
                <span>Minhas Tarefas</span>
            </div>
            <button class="btn-neuro btn-neuro-danger" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                <i class="bi bi-plus-circle me-2"></i>Nova Tarefa
            </button>
        </div>
        
        <?php if (empty($tarefas_pendentes)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-check-circle"></i></div>
                <h5>Nenhuma tarefa pendente!</h5>
                <p>Parabéns! Você está em dia com suas tarefas.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tarefas_pendentes as $tarefa): ?>
                <div class="task-card prioridade-<?= $tarefa['prioridade'] ?>" data-id="<?= $tarefa['id'] ?>">
                    <div class="task-header">
                        <div class="task-content">
                            <div class="task-title"><?= htmlspecialchars($tarefa['descricao']) ?></div>
                            <div class="task-meta">
                                <span class="badge <?= getPrioridadeBadge($tarefa['prioridade']) ?>">
                                    <?= $tarefa['prioridade'] ?>
                                </span>
                                <?php if ($tarefa['data_limite']): ?>
                                    <span>
                                        <i class="bi bi-calendar-event"></i>
                                        <?= date('d/m/Y', strtotime($tarefa['data_limite'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="task-actions">
                            <button class="btn-icon-neuro" onclick="concluirTarefa(<?= $tarefa['id'] ?>)" title="Concluir">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button class="btn-icon-neuro" onclick="excluirTarefa(<?= $tarefa['id'] ?>)" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Novo Hábito -->
<div class="modal fade" id="modalNovoHabito" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i>
                    Novo Hábito
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovoHabito">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome do Hábito</label>
                        <input type="text" name="nome" class="form-control" required placeholder="Ex: Fazer exercícios">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Horário (opcional)</label>
                        <input type="time" name="horario" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição (opcional)</label>
                        <textarea name="descricao" class="form-control" rows="2" placeholder="Detalhes sobre o hábito..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-neuro" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-neuro btn-neuro-danger">
                        <i class="bi bi-save me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Hábito -->
<div class="modal fade" id="modalEditarHabito" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i>
                    Editar Hábito
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarHabito">
                <input type="hidden" name="id" id="editHabitoId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome do Hábito</label>
                        <input type="text" name="nome" id="editHabitoNome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Horário (opcional)</label>
                        <input type="time" name="horario" id="editHabitoHorario" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição (opcional)</label>
                        <textarea name="descricao" id="editHabitoDescricao" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-neuro" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-neuro btn-neuro-danger">
                        <i class="bi bi-save me-2"></i>Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nova Tarefa -->
<div class="modal fade" id="modalNovaTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i>
                    Nova Tarefa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaTarefa" action="adicionar_tarefa.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control" required placeholder="Descreva sua tarefa...">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="Baixa">Baixa</option>
                                <option value="Média" selected>Média</option>
                                <option value="Alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Limite</label>
                            <input type="date" name="data_limite" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-neuro" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-neuro btn-neuro-danger">
                        <i class="bi bi-save me-2"></i>Criar Tarefa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== EVENT DELEGATION PARA HÁBITOS =====
document.addEventListener('click', function(e) {
    // Toggle hábito (clique no card, exceto nos botões)
    const habitItem = e.target.closest('.habit-item');
    if (habitItem && !e.target.closest('.habit-actions')) {
        const id = habitItem.dataset.id;
        const statusAtual = habitItem.dataset.status;
        toggleHabito(parseInt(id), statusAtual);
        return;
    }
    
    // Botão editar
    if (e.target.closest('.btn-edit-habit')) {
        e.stopPropagation();
        const habitItem = e.target.closest('.habit-item');
        const id = habitItem.dataset.id;
        const nome = habitItem.dataset.nome;
        const horario = habitItem.dataset.horario;
        const descricao = habitItem.dataset.descricao;
        editarHabito(parseInt(id), nome, horario, descricao);
        return;
    }
    
    // Botão excluir
    if (e.target.closest('.btn-delete-habit')) {
        e.stopPropagation();
        const habitItem = e.target.closest('.habit-item');
        const id = habitItem.dataset.id;
        const nome = habitItem.dataset.nome;
        excluirHabito(parseInt(id), nome);
        return;
    }
});

// Toast notification ultra moderno
function showToast(title, message, type = 'success') {
    const colors = {
        success: 'linear-gradient(135deg, #28a745, #20c997)',
        error: 'linear-gradient(135deg, #dc3545, #ff6b6b)',
        warning: 'linear-gradient(135deg, #ffc107, #ffb700)'
    };
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type]};
        color: white;
        padding: 1.25rem 1.75rem;
        border-radius: 16px;
        z-index: 9999;
        box-shadow: 0 8px 32px rgba(0,0,0,0.4), 0 0 20px rgba(220,53,69,0.3);
        backdrop-filter: blur(10px);
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        max-width: 350px;
        font-weight: 500;
    `;
    toast.innerHTML = `<strong style="font-size: 1.1rem;">${title}</strong><br><span style="opacity: 0.95;">${message}</span>`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

// Toggle hábito com animação
function toggleHabito(id, statusAtual) {
    const novoStatus = statusAtual === 'concluido' ? 'pendente' : 'concluido';
    const habitItem = document.querySelector(`.habit-item[data-id="${id}"]`);
    
    // Atualização otimista da UI
    if (novoStatus === 'concluido') {
        habitItem.classList.add('completed');
        habitItem.querySelector('.habit-icon i').className = 'bi bi-check-circle-fill';
        habitItem.dataset.status = 'concluido';
    } else {
        habitItem.classList.remove('completed');
        habitItem.querySelector('.habit-icon i').className = 'bi bi-circle';
        habitItem.dataset.status = 'pendente';
    }
    
    // Enviar para servidor
    fetch('processar_rotina_fixa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `acao=${novoStatus === 'concluido' ? 'concluir' : 'pendente'}&rotina_id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            // Reverter se erro
            if (novoStatus === 'concluido') {
                habitItem.classList.remove('completed');
                habitItem.querySelector('.habit-icon i').className = 'bi bi-circle';
                habitItem.dataset.status = 'pendente';
            } else {
                habitItem.classList.add('completed');
                habitItem.querySelector('.habit-icon i').className = 'bi bi-check-circle-fill';
                habitItem.dataset.status = 'concluido';
            }
            showToast('Erro', data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        // Reverter em caso de erro de rede
        if (novoStatus === 'concluido') {
            habitItem.classList.remove('completed');
            habitItem.querySelector('.habit-icon i').className = 'bi bi-circle';
            habitItem.dataset.status = 'pendente';
        } else {
            habitItem.classList.add('completed');
            habitItem.querySelector('.habit-icon i').className = 'bi bi-check-circle-fill';
            habitItem.dataset.status = 'concluido';
        }
        showToast('Erro', 'Erro de conexão', 'error');
    });
}

// Forms
document.getElementById('formNovoHabito').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...';
    
    fetch('processar_rotina_fixa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `acao=adicionar&nome=${encodeURIComponent(formData.get('nome'))}&horario=${encodeURIComponent(formData.get('horario') || '')}&descricao=${encodeURIComponent(formData.get('descricao') || '')}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Hábito adicionado com sucesso!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Erro', data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-2"></i>Salvar';
        }
    });
});

function editarHabito(id, nome, horario, descricao) {
    document.getElementById('editHabitoId').value = id;
    document.getElementById('editHabitoNome').value = nome;
    document.getElementById('editHabitoHorario').value = horario || '';
    document.getElementById('editHabitoDescricao').value = descricao || '';
    new bootstrap.Modal(document.getElementById('modalEditarHabito')).show();
}

document.getElementById('formEditarHabito').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('editar_rotina_fixa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: formData.get('id'),
            nome: formData.get('nome'),
            horario: formData.get('horario'),
            descricao: formData.get('descricao')
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Hábito atualizado!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Erro', data.message, 'error');
        }
    });
});

function excluirHabito(id, nome) {
    if (confirm(`Deseja excluir o hábito "${nome}"?`)) {
        fetch('excluir_rotina_fixa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Hábito excluído!');
                document.querySelector(`.habit-item[data-id="${id}"]`).style.animation = 'fadeOutUp 0.5s ease';
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('Erro', data.message, 'error');
            }
        });
    }
}

function concluirTarefa(id) {
    fetch('atualizar_status_tarefa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status: 'concluida' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Tarefa concluída!');
            const card = document.querySelector(`.task-card[data-id="${id}"]`);
            card.style.animation = 'fadeOutUp 0.5s ease';
            setTimeout(() => card.remove(), 500);
        } else {
            showToast('Erro', data.message, 'error');
        }
    });
}

function excluirTarefa(id) {
    if (confirm('Deseja excluir esta tarefa?')) {
        fetch('excluir_tarefa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Tarefa excluída!');
                const card = document.querySelector(`.task-card[data-id="${id}"]`);
                card.style.animation = 'fadeOutUp 0.5s ease';
                setTimeout(() => card.remove(), 500);
            } else {
                showToast('Erro', data.message, 'error');
            }
        });
    }
}

document.getElementById('formNovaTarefa').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('adicionar_tarefa.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Tarefa adicionada!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Erro', data.message, 'error');
        }
    });
});

// Animações CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    @keyframes fadeOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-30px); }
    }
`;
document.head.appendChild(style);
</script>

<?php require_once 'templates/footer.php'; ?>
