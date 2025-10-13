<?php
// automatizacao_horario.php - Sistema de automatização baseada no horário

require_once 'templates/header.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$horaAtual = (int)date('H');

// Determinar período do dia
$periodo = '';
$icone = '';
$cor = '';
$mensagem = '';

if ($horaAtual >= 5 && $horaAtual < 12) {
    $periodo = 'manha';
    $icone = '🌅';
    $cor = 'linear-gradient(135deg, #ffd700 0%, #ffed4e 100%)';
    $mensagem = 'Bom dia! Que tal começar com energia?';
} elseif ($horaAtual >= 12 && $horaAtual < 18) {
    $periodo = 'tarde';
    $icone = '☀️';
    $cor = 'linear-gradient(135deg, #ff6b35 0%, #f7931e 100%)';
    $mensagem = 'Boa tarde! Continue focado nas suas metas!';
} else {
    $periodo = 'noite';
    $icone = '🌙';
    $cor = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
    $mensagem = 'Boa noite! Hora de revisar o dia e planejar o amanhã.';
}

// Buscar tarefas por período
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        CASE 
            WHEN t.hora_inicio IS NULL THEN 'sem_horario'
            WHEN TIME(t.hora_inicio) BETWEEN '05:00:00' AND '11:59:59' THEN 'manha'
            WHEN TIME(t.hora_inicio) BETWEEN '12:00:00' AND '17:59:59' THEN 'tarde'
            WHEN TIME(t.hora_inicio) BETWEEN '18:00:00' AND '23:59:59' THEN 'noite'
            ELSE 'sem_horario'
        END as periodo_tarefa
    FROM tarefas t
    WHERE t.id_usuario = ? AND t.status = 'pendente'
    ORDER BY t.prioridade DESC, t.data_criacao ASC
");
$stmt->execute([$userId]);
$todasTarefas = $stmt->fetchAll();

// Filtrar tarefas por período
$tarefasManha = array_filter($todasTarefas, function($t) { return $t['periodo_tarefa'] === 'manha'; });
$tarefasTarde = array_filter($todasTarefas, function($t) { return $t['periodo_tarefa'] === 'tarde'; });
$tarefasNoite = array_filter($todasTarefas, function($t) { return $t['periodo_tarefa'] === 'noite'; });
$tarefasSemHorario = array_filter($todasTarefas, function($t) { return $t['periodo_tarefa'] === 'sem_horario'; });

// Contar tarefas concluídas hoje
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_concluidas
    FROM tarefas 
    WHERE id_usuario = ? AND status = 'concluida' AND DATE(data_atualizacao) = CURDATE()
");
$stmt->execute([$userId]);
$tarefasConcluidasHoje = $stmt->fetch()['total_concluidas'];

// Buscar estatísticas do dia
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_tarefas,
        COUNT(CASE WHEN status = 'concluida' THEN 1 END) as concluidas,
        COUNT(CASE WHEN status = 'pendente' THEN 1 END) as pendentes
    FROM tarefas 
    WHERE id_usuario = ? AND DATE(data_criacao) = CURDATE()
");
$stmt->execute([$userId]);
$statsDia = $stmt->fetch();

$progressoDia = $statsDia['total_tarefas'] > 0 ? 
    ($statsDia['concluidas'] / $statsDia['total_tarefas']) * 100 : 0;
?>

<style>
.periodo-header {
    background: <?php echo $cor; ?>;
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.periodo-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.1);
    pointer-events: none;
}

.periodo-content {
    position: relative;
    z-index: 2;
}

.tarefa-periodo {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.tarefa-periodo:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.periodo-card {
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.8) 0%, rgba(50, 30, 30, 0.8) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.periodo-manha {
    border-left: 4px solid #ffd700;
}

.periodo-tarde {
    border-left: 4px solid #ff6b35;
}

.periodo-noite {
    border-left: 4px solid #667eea;
}

.progress-circular {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: conic-gradient(#28a745 0deg, #28a745 var(--progress, 0deg), rgba(255, 255, 255, 0.1) var(--progress, 0deg));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.progress-circular::before {
    content: '';
    position: absolute;
    width: 60px;
    height: 60px;
    background: var(--dark-bg);
    border-radius: 50%;
    z-index: 1;
}

.progress-text {
    position: relative;
    z-index: 2;
    color: white;
    font-weight: bold;
    font-size: 0.9rem;
}

.auto-message {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.3);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    animation: slideInUp 0.5s ease;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .periodo-header {
        padding: 1.5rem;
    }
    
    .periodo-card {
        padding: 1.5rem;
    }
    
    .tarefa-periodo {
        padding: 1rem;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Header do Período -->
    <div class="periodo-header">
        <div class="periodo-content text-center text-white">
            <div class="display-1 mb-3"><?php echo $icone; ?></div>
            <h1 class="display-4 mb-3"><?php echo ucfirst($periodo); ?></h1>
            <p class="lead mb-4"><?php echo $mensagem; ?></p>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="progress-circular" style="--progress: <?php echo $progressoDia; * 3.6; ?>deg;">
                            <div class="progress-text">
                                <?php echo round($progressoDia, 0); ?>%
                            </div>
                        </div>
                        <div class="text-start">
                            <h4 class="mb-1"><?php echo $tarefasConcluidasHoje; ?> tarefas concluídas</h4>
                            <p class="mb-0 opacity-75">de <?php echo $statsDia['total_tarefas']; ?> hoje</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mensagem Automática -->
    <?php if ($progressoDia >= 80): ?>
    <div class="auto-message">
        <div class="d-flex align-items-center">
            <i class="bi bi-trophy-fill text-warning me-3" style="font-size: 2rem;"></i>
            <div>
                <h5 class="text-white mb-1">Parabéns! 🎉</h5>
                <p class="text-white-50 mb-0">Você já cumpriu <?php echo round($progressoDia, 0); ?>% do seu dia! Continue o ritmo!</p>
            </div>
        </div>
    </div>
    <?php elseif ($progressoDia >= 50): ?>
    <div class="auto-message">
        <div class="d-flex align-items-center">
            <i class="bi bi-lightning-fill text-warning me-3" style="font-size: 2rem;"></i>
            <div>
                <h5 class="text-white mb-1">Bom ritmo! ⚡</h5>
                <p class="text-white-50 mb-0">Você está no caminho certo! <?php echo round($progressoDia, 0); ?>% concluído.</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="auto-message">
        <div class="d-flex align-items-center">
            <i class="bi bi-rocket-takeoff text-info me-3" style="font-size: 2rem;"></i>
            <div>
                <h5 class="text-white mb-1">Vamos começar! 🚀</h5>
                <p class="text-white-50 mb-0">Que tal focar nas tarefas mais importantes primeiro?</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tarefas por Período -->
    <div class="row">
        <!-- Manhã -->
        <div class="col-lg-4 mb-4">
            <div class="periodo-card periodo-manha">
                <h4 class="text-white mb-3">
                    <i class="bi bi-sunrise me-2"></i>
                    Manhã (05h-12h)
                </h4>
                <div class="mb-3">
                    <span class="badge bg-warning text-dark">
                        <?php echo count($tarefasManha); ?> tarefas
                    </span>
                </div>
                <div class="tarefas-periodo">
                    <?php foreach ($tarefasManha as $tarefa): ?>
                    <div class="tarefa-periodo">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="text-white mb-2"><?php echo htmlspecialchars($tarefa['descricao']); ?></h6>
                                <div class="d-flex gap-2">
                                    <span class="badge <?php echo $tarefa['prioridade'] === 'Alta' ? 'bg-danger' : ($tarefa['prioridade'] === 'Média' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                        <?php echo $tarefa['prioridade']; ?>
                                    </span>
                                    <?php if ($tarefa['hora_inicio']): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($tarefa['hora_inicio'])); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-light" onclick="concluirTarefa(<?php echo $tarefa['id']; ?>)">
                                <i class="bi bi-check"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($tarefasManha)): ?>
                    <div class="text-center text-white-50 py-4">
                        <i class="bi bi-sunrise display-4 mb-3"></i>
                        <p>Nenhuma tarefa para a manhã</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Tarde -->
        <div class="col-lg-4 mb-4">
            <div class="periodo-card periodo-tarde">
                <h4 class="text-white mb-3">
                    <i class="bi bi-sun me-2"></i>
                    Tarde (12h-18h)
                </h4>
                <div class="mb-3">
                    <span class="badge bg-warning text-dark">
                        <?php echo count($tarefasTarde); ?> tarefas
                    </span>
                </div>
                <div class="tarefas-periodo">
                    <?php foreach ($tarefasTarde as $tarefa): ?>
                    <div class="tarefa-periodo">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="text-white mb-2"><?php echo htmlspecialchars($tarefa['descricao']); ?></h6>
                                <div class="d-flex gap-2">
                                    <span class="badge <?php echo $tarefa['prioridade'] === 'Alta' ? 'bg-danger' : ($tarefa['prioridade'] === 'Média' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                        <?php echo $tarefa['prioridade']; ?>
                                    </span>
                                    <?php if ($tarefa['hora_inicio']): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($tarefa['hora_inicio'])); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-light" onclick="concluirTarefa(<?php echo $tarefa['id']; ?>)">
                                <i class="bi bi-check"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($tarefasTarde)): ?>
                    <div class="text-center text-white-50 py-4">
                        <i class="bi bi-sun display-4 mb-3"></i>
                        <p>Nenhuma tarefa para a tarde</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Noite -->
        <div class="col-lg-4 mb-4">
            <div class="periodo-card periodo-noite">
                <h4 class="text-white mb-3">
                    <i class="bi bi-moon me-2"></i>
                    Noite (18h-23h)
                </h4>
                <div class="mb-3">
                    <span class="badge bg-warning text-dark">
                        <?php echo count($tarefasNoite); ?> tarefas
                    </span>
                </div>
                <div class="tarefas-periodo">
                    <?php foreach ($tarefasNoite as $tarefa): ?>
                    <div class="tarefa-periodo">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="text-white mb-2"><?php echo htmlspecialchars($tarefa['descricao']); ?></h6>
                                <div class="d-flex gap-2">
                                    <span class="badge <?php echo $tarefa['prioridade'] === 'Alta' ? 'bg-danger' : ($tarefa['prioridade'] === 'Média' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                        <?php echo $tarefa['prioridade']; ?>
                                    </span>
                                    <?php if ($tarefa['hora_inicio']): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($tarefa['hora_inicio'])); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-light" onclick="concluirTarefa(<?php echo $tarefa['id']; ?>)">
                                <i class="bi bi-check"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($tarefasNoite)): ?>
                    <div class="text-center text-white-50 py-4">
                        <i class="bi bi-moon display-4 mb-3"></i>
                        <p>Nenhuma tarefa para a noite</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tarefas sem horário -->
    <?php if (!empty($tarefasSemHorario)): ?>
    <div class="row">
        <div class="col-12">
            <div class="periodo-card">
                <h4 class="text-white mb-3">
                    <i class="bi bi-clock me-2"></i>
                    Tarefas sem horário definido
                </h4>
                <div class="mb-3">
                    <span class="badge bg-secondary">
                        <?php echo count($tarefasSemHorario); ?> tarefas
                    </span>
                </div>
                <div class="row">
                    <?php foreach ($tarefasSemHorario as $tarefa): ?>
                    <div class="col-md-6 mb-3">
                        <div class="tarefa-periodo">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="text-white mb-2"><?php echo htmlspecialchars($tarefa['descricao']); ?></h6>
                                    <div class="d-flex gap-2">
                                        <span class="badge <?php echo $tarefa['prioridade'] === 'Alta' ? 'bg-danger' : ($tarefa['prioridade'] === 'Média' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                            <?php echo $tarefa['prioridade']; ?>
                                        </span>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-outline-light" onclick="concluirTarefa(<?php echo $tarefa['id']; ?>)">
                                    <i class="bi bi-check"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Concluir tarefa
function concluirTarefa(tarefaId) {
    fetch('concluir_tarefa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: tarefaId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Tarefa concluída!');
            setTimeout(() => {
                location.reload();
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

// Show toast notification
function showToast(title, message, isError = false) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: isError ? 'error' : 'success',
            timer: 3000,
            showConfirmButton: false
        });
    } else {
        alert(title + ': ' + message);
    }
}

// Atualizar progresso circular
function updateProgress() {
    const progressElements = document.querySelectorAll('.progress-circular');
    progressElements.forEach(element => {
        const progress = element.style.getPropertyValue('--progress');
        element.style.setProperty('--progress', progress);
    });
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    updateProgress();
});
</script>

<?php require_once 'templates/footer.php'; ?>
