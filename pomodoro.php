<?php
// pomodoro.php - Sistema de Pomodoro Evoluído

require_once 'templates/header.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Buscar tarefas pendentes para associar ao pomodoro
$stmt = $pdo->prepare("
    SELECT id, descricao, prioridade, tempo_estimado 
    FROM tarefas 
    WHERE id_usuario = ? AND status = 'pendente' 
    ORDER BY prioridade DESC, data_criacao ASC
");
$stmt->execute([$userId]);
$tarefas = $stmt->fetchAll();

// Buscar sessão ativa de pomodoro
$stmt = $pdo->prepare("
    SELECT ps.*, t.descricao as tarefa_descricao
    FROM pomodoro_sessions ps
    LEFT JOIN tarefas t ON ps.id_tarefa = t.id
    WHERE ps.id_usuario = ? AND ps.status = 'ativo'
    ORDER BY ps.inicio DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$sessaoAtiva = $stmt->fetch();

// Buscar estatísticas da semana
$dataInicioSemana = date('Y-m-d', strtotime('monday this week'));
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sessoes,
        SUM(duracao_minutos) as tempo_total,
        AVG(duracao_minutos) as media_duracao
    FROM pomodoro_sessions 
    WHERE id_usuario = ? AND inicio >= ? AND status = 'concluido'
");
$stmt->execute([$userId, $dataInicioSemana]);
$statsSemana = $stmt->fetch();
?>

<style>
.pomodoro-container {
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.9) 0%, rgba(50, 30, 30, 0.9) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 2rem;
    position: relative;
    overflow: hidden;
}

.pomodoro-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 50% 50%, rgba(220, 53, 69, 0.1) 0%, transparent 70%);
    pointer-events: none;
}

.timer-circle {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: conic-gradient(#dc3545 0deg, #dc3545 var(--progress, 0deg), rgba(255, 255, 255, 0.1) var(--progress, 0deg));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    margin: 0 auto;
    transition: all 0.3s ease;
}

.timer-circle::before {
    content: '';
    position: absolute;
    width: 160px;
    height: 160px;
    background: var(--dark-bg);
    border-radius: 50%;
    z-index: 1;
}

.timer-display {
    position: relative;
    z-index: 2;
    color: white;
    font-size: 2.5rem;
    font-weight: bold;
    text-align: center;
}

.timer-controls {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.btn-pomodoro {
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
}

.btn-pomodoro::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.btn-pomodoro:hover::before {
    left: 100%;
}

.btn-start {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.btn-pause {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: #000;
}

.btn-stop {
    background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    color: white;
}

.task-selector {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.stats-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stats-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
}

.pomodoro-active {
    animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 20px rgba(220, 53, 69, 0.3); }
    50% { box-shadow: 0 0 40px rgba(220, 53, 69, 0.6); }
}

@media (max-width: 768px) {
    .pomodoro-container {
        padding: 1.5rem;
    }
    
    .timer-circle {
        width: 150px;
        height: 150px;
    }
    
    .timer-circle::before {
        width: 120px;
        height: 120px;
    }
    
    .timer-display {
        font-size: 2rem;
    }
    
    .timer-controls {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn-pomodoro {
        width: 100%;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Header do Pomodoro -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="pomodoro-container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h2 class="text-white mb-3">
                            <i class="bi bi-stopwatch me-2"></i>
                            Pomodoro Timer
                        </h2>
                        <p class="text-white-50 mb-0">Técnica de produtividade com foco em 25 minutos</p>
                    </div>
                    <div class="col-lg-6">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="text-white-50 small">Esta Semana</div>
                                    <div class="text-white fw-bold fs-4"><?php echo $statsSemana['total_sessoes'] ?? 0; ?></div>
                                    <div class="text-white-50 small">sessões</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="text-white-50 small">Tempo Total</div>
                                    <div class="text-white fw-bold fs-4"><?php echo round(($statsSemana['tempo_total'] ?? 0) / 60, 1); ?>h</div>
                                    <div class="text-white-50 small">produtivo</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="text-white-50 small">Média</div>
                                    <div class="text-white fw-bold fs-4"><?php echo round($statsSemana['media_duracao'] ?? 0, 0); ?>m</div>
                                    <div class="text-white-50 small">por sessão</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Timer Principal -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="pomodoro-container text-center">
                <div class="timer-circle" id="timerCircle">
                    <div class="timer-display" id="timerDisplay">25:00</div>
                </div>
                
                <div class="timer-controls" id="timerControls">
                    <button class="btn btn-pomodoro btn-start" id="btnStart" onclick="startPomodoro()">
                        <i class="bi bi-play-fill me-2"></i>
                        Iniciar
                    </button>
                    <button class="btn btn-pomodoro btn-pause" id="btnPause" onclick="pausePomodoro()" style="display: none;">
                        <i class="bi bi-pause-fill me-2"></i>
                        Pausar
                    </button>
                    <button class="btn btn-pomodoro btn-stop" id="btnStop" onclick="stopPomodoro()" style="display: none;">
                        <i class="bi bi-stop-fill me-2"></i>
                        Parar
                    </button>
                </div>
                
                <div class="mt-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="task-selector">
                                <label class="form-label text-white">
                                    <i class="bi bi-list-task me-2"></i>
                                    Associar a uma tarefa (opcional)
                                </label>
                                <select class="form-select" id="taskSelect">
                                    <option value="">Pomodoro livre</option>
                                    <?php foreach ($tarefas as $tarefa): ?>
                                    <option value="<?php echo $tarefa['id']; ?>">
                                        <?php echo htmlspecialchars($tarefa['descricao']); ?>
                                        <?php if ($tarefa['prioridade'] === 'Alta'): ?>
                                        <span class="badge bg-danger ms-1">Alta</span>
                                        <?php elseif ($tarefa['prioridade'] === 'Média'): ?>
                                        <span class="badge bg-warning text-dark ms-1">Média</span>
                                        <?php else: ?>
                                        <span class="badge bg-success ms-1">Baixa</span>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="task-selector">
                                <label class="form-label text-white">
                                    <i class="bi bi-gear me-2"></i>
                                    Configurações
                                </label>
                                <div class="row">
                                    <div class="col-6">
                                        <label class="form-label text-white-50 small">Trabalho (min)</label>
                                        <input type="number" class="form-control" id="workDuration" value="25" min="1" max="60">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-white-50 small">Pausa (min)</label>
                                        <input type="number" class="form-control" id="breakDuration" value="5" min="1" max="30">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sessão Ativa -->
    <?php if ($sessaoAtiva): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="pomodoro-container">
                <h5 class="text-white mb-3">
                    <i class="bi bi-activity me-2"></i>
                    Sessão Ativa
                </h5>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white mb-1">
                            <?php if ($sessaoAtiva['tarefa_descricao']): ?>
                                <?php echo htmlspecialchars($sessaoAtiva['tarefa_descricao']); ?>
                            <?php else: ?>
                                Pomodoro Livre
                            <?php endif; ?>
                        </h6>
                        <small class="text-white-50">
                            Iniciado em <?php echo date('H:i', strtotime($sessaoAtiva['inicio'])); ?>
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-clock me-1"></i>
                            Em andamento
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Histórico Recente -->
    <div class="row">
        <div class="col-12">
            <div class="pomodoro-container">
                <h5 class="text-white mb-3">
                    <i class="bi bi-clock-history me-2"></i>
                    Histórico Recente
                </h5>
                <div id="pomodoroHistory">
                    <div class="text-center text-white-50 py-4">
                        <i class="bi bi-hourglass-split display-4 mb-3"></i>
                        <p>Nenhuma sessão encontrada</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let pomodoroInterval;
let currentTime = 25 * 60; // 25 minutos em segundos
let isRunning = false;
let isPaused = false;
let currentSessionId = null;

// Inicializar timer
function initTimer() {
    updateDisplay();
    loadHistory();
}

// Atualizar display do timer
function updateDisplay() {
    const minutes = Math.floor(currentTime / 60);
    const seconds = currentTime % 60;
    document.getElementById('timerDisplay').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    // Atualizar progresso circular
    const workDuration = parseInt(document.getElementById('workDuration').value) * 60;
    const progress = ((workDuration - currentTime) / workDuration) * 360;
    document.getElementById('timerCircle').style.setProperty('--progress', `${progress}deg`);
}

// Iniciar pomodoro
function startPomodoro() {
    if (isRunning) return;
    
    const taskId = document.getElementById('taskSelect').value;
    const workDuration = parseInt(document.getElementById('workDuration').value);
    
    currentTime = workDuration * 60;
    isRunning = true;
    isPaused = false;
    
    // Mostrar controles
    document.getElementById('btnStart').style.display = 'none';
    document.getElementById('btnPause').style.display = 'inline-block';
    document.getElementById('btnStop').style.display = 'inline-block';
    
    // Adicionar classe ativa
    document.getElementById('timerCircle').classList.add('pomodoro-active');
    
    // Criar sessão no servidor
    fetch('criar_sessao_pomodoro.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            id_tarefa: taskId || null,
            duracao_minutos: workDuration
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentSessionId = data.session_id;
        }
    });
    
    // Iniciar timer
    pomodoroInterval = setInterval(() => {
        if (!isPaused) {
            currentTime--;
            updateDisplay();
            
            if (currentTime <= 0) {
                completePomodoro();
            }
        }
    }, 1000);
}

// Pausar pomodoro
function pausePomodoro() {
    isPaused = !isPaused;
    
    const btnPause = document.getElementById('btnPause');
    if (isPaused) {
        btnPause.innerHTML = '<i class="bi bi-play-fill me-2"></i>Retomar';
    } else {
        btnPause.innerHTML = '<i class="bi bi-pause-fill me-2"></i>Pausar';
    }
}

// Parar pomodoro
function stopPomodoro() {
    if (pomodoroInterval) {
        clearInterval(pomodoroInterval);
    }
    
    isRunning = false;
    isPaused = false;
    currentTime = parseInt(document.getElementById('workDuration').value) * 60;
    
    // Restaurar controles
    document.getElementById('btnStart').style.display = 'inline-block';
    document.getElementById('btnPause').style.display = 'none';
    document.getElementById('btnStop').style.display = 'none';
    
    // Remover classe ativa
    document.getElementById('timerCircle').classList.remove('pomodoro-active');
    
    // Cancelar sessão no servidor
    if (currentSessionId) {
        fetch('cancelar_sessao_pomodoro.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentSessionId })
        });
        currentSessionId = null;
    }
    
    updateDisplay();
}

// Completar pomodoro
function completePomodoro() {
    if (pomodoroInterval) {
        clearInterval(pomodoroInterval);
    }
    
    isRunning = false;
    isPaused = false;
    
    // Restaurar controles
    document.getElementById('btnStart').style.display = 'inline-block';
    document.getElementById('btnPause').style.display = 'none';
    document.getElementById('btnStop').style.display = 'none';
    
    // Remover classe ativa
    document.getElementById('timerCircle').classList.remove('pomodoro-active');
    
    // Finalizar sessão no servidor
    if (currentSessionId) {
        fetch('finalizar_sessao_pomodoro.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentSessionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Pomodoro concluído!', 'Faça uma pausa de 5 minutos.');
                loadHistory();
            }
        });
        currentSessionId = null;
    }
    
    // Resetar timer
    currentTime = parseInt(document.getElementById('workDuration').value) * 60;
    updateDisplay();
    
    // Mostrar notificação
    showNotification('Pomodoro concluído!', 'Faça uma pausa de 5 minutos.');
}

// Carregar histórico
function loadHistory() {
    fetch('buscar_historico_pomodoro.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.history.length > 0) {
            displayHistory(data.history);
        }
    });
}

// Exibir histórico
function displayHistory(history) {
    const container = document.getElementById('pomodoroHistory');
    container.innerHTML = '';
    
    history.forEach(session => {
        const div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center py-2 border-bottom border-secondary';
        div.innerHTML = `
            <div>
                <h6 class="text-white mb-1">${session.tarefa_descricao || 'Pomodoro Livre'}</h6>
                <small class="text-white-50">${session.data_formatada}</small>
            </div>
            <div class="text-end">
                <span class="badge bg-success">${session.duracao_minutos}min</span>
            </div>
        `;
        container.appendChild(div);
    });
}

// Mostrar notificação
function showNotification(title, message) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, {
            body: message,
            icon: '/images/icon-192x192.png'
        });
    }
    
    // Solicitar permissão se necessário
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    initTimer();
    
    // Solicitar permissão para notificações
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
