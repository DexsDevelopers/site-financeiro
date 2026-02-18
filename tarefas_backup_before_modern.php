<?php
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

// ===== VERIFICAR AUTENTICAÇÃO =====
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// ===== CONFIGURAÇÃO INICIAL =====
$dataHoje = date('Y-m-d');

// ===== CRIAR TABELAS SE NECESSÁRIO =====
try {
    // Tabela de rotinas fixas
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
    
    // Tabela de controle diário
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
    error_log("Erro ao criar tabelas: " . $e->getMessage());
}

// ===== BUSCAR ROTINAS FIXAS =====
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
    
    // Criar controles para hoje se não existirem
    foreach ($rotinasFixas as &$rotina) {
        if ($rotina['status_hoje'] === null) {
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario 
                (id_usuario, id_rotina_fixa, data_execucao, status) 
                VALUES (?, ?, ?, 'pendente')
            ");
            $stmt->execute([$userId, $rotina['id'], $dataHoje]);
            $rotina['status_hoje'] = 'pendente';
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar rotinas: " . $e->getMessage());
}

// ===== BUSCAR TAREFAS =====
$tarefas_pendentes = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM tarefas 
        WHERE id_usuario = ? AND status = 'pendente' 
        ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC
    ");
    $stmt->execute([$userId]);
    $tarefas_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar subtarefas
    if (!empty($tarefas_pendentes)) {
        $ids = array_column($tarefas_pendentes, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM subtarefas WHERE id_tarefa_principal IN ($placeholders)");
        $stmt->execute($ids);
        $subtarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $subtarefas_map = [];
        foreach ($subtarefas as $sub) {
            $subtarefas_map[$sub['id_tarefa_principal']][] = $sub;
        }
        
        foreach ($tarefas_pendentes as &$tarefa) {
            $tarefa['subtarefas'] = $subtarefas_map[$tarefa['id']] ?? [];
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar tarefas: " . $e->getMessage());
}

// ===== ESTATÍSTICAS =====
$stats = ['hoje' => ['total' => 0, 'concluidas' => 0]];
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas 
        FROM tarefas 
        WHERE id_usuario = ? AND DATE(data_criacao) = CURDATE()
    ");
    $stmt->execute([$userId]);
    $stats['hoje'] = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
}

function getPrioridadeBadge($prioridade) {
    return match($prioridade) {
        'Alta' => 'bg-danger',
        'Média' => 'bg-warning text-dark',
        'Baixa' => 'bg-success',
        default => 'bg-secondary'
    };
}

function formatarTempo($minutos) {
    if ($minutos <= 0) return '0min';
    $h = floor($minutos / 60);
    $m = $minutos % 60;
    return ($h > 0 ? $h . 'h ' : '') . ($m > 0 ? $m . 'min' : '');
}
?>

<style>
:root {
    --primary-red: #dc3545;
    --dark-bg: #0d0d0d;
    --card-bg: #1a1a1a;
    --border-color: #333;
    --text-primary: #ffffff;
    --text-secondary: #999;
    --success: #28a745;
    --warning: #ffc107;
}

body { background: var(--dark-bg); color: var(--text-primary); }

.page-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 2rem 0;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-red);
    margin-bottom: 0.5rem;
}

.section-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--primary-red);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: 600;
}

.habits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.habit-item {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.habit-item:hover {
    background: rgba(255,255,255,0.08);
    border-color: var(--primary-red);
    transform: translateY(-2px);
}

.habit-item.completed {
    background: rgba(40,167,69,0.1);
    border-color: var(--success);
}

.habit-main {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.habit-icon {
    font-size: 1.5rem;
    color: var(--text-secondary);
}

.habit-item.completed .habit-icon {
    color: var(--success);
}

.habit-name {
    margin: 0;
    font-weight: 600;
    color: var(--text-primary);
}

.habit-time {
    color: var(--text-secondary);
    font-size: 0.85rem;
    display: block;
    margin-top: 0.25rem;
}

.habit-description {
    color: var(--text-secondary);
    font-size: 0.8rem;
    font-style: italic;
    display: block;
    margin-top: 0.25rem;
}

.habit-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.task-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-left: 4px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.task-card.prioridade-Alta { border-left-color: var(--primary-red); }
.task-card.prioridade-Média { border-left-color: var(--warning); }
.task-card.prioridade-Baixa { border-left-color: var(--success); }

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
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
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.task-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-icon:hover {
    background: var(--primary-red);
    color: white;
    border-color: var(--primary-red);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.modal-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
}

.modal-header, .modal-footer {
    border-color: var(--border-color);
}

.modal-title {
    color: var(--text-primary);
}

.form-control, .form-select {
    background: #000;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.form-control:focus, .form-select:focus {
    background: #111;
    border-color: var(--primary-red);
    color: var(--text-primary);
    box-shadow: 0 0 0 0.25rem rgba(220,53,69,0.25);
}

.form-label {
    color: var(--text-primary);
}

.btn-primary {
    background: var(--primary-red);
    border-color: var(--primary-red);
}

.btn-primary:hover {
    background: #c82333;
    border-color: #bd2130;
}

@media (max-width: 768px) {
    .habits-grid {
        grid-template-columns: 1fr;
    }
    .task-header {
        flex-direction: column;
    }
    .task-actions {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-check2-square"></i>
            Rotina de Tarefas
        </h1>
        <p class="text-secondary">Organize suas tarefas e hábitos diários</p>
    </div>

    <!-- Rotinas Fixas -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-title">
                <i class="bi bi-calendar-check"></i>
                <span>Hábitos Diários</span>
            </div>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoHabito">
                <i class="bi bi-plus-circle"></i> Adicionar Hábito
            </button>
        </div>
        
        <?php if (empty($rotinasFixas)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar-check"></i></div>
                <h5>Nenhum hábito cadastrado</h5>
                <p>Adicione hábitos que você quer fazer todos os dias</p>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovoHabito">
                    <i class="bi bi-plus-circle"></i> Adicionar Primeiro Hábito
                </button>
            </div>
        <?php else: ?>
            <div class="habits-grid">
                <?php foreach ($rotinasFixas as $rotina): ?>
                    <div class="habit-item <?= $rotina['status_hoje'] === 'concluido' ? 'completed' : '' ?>" 
                         data-id="<?= $rotina['id'] ?>"
                         onclick="toggleHabito(<?= $rotina['id'] ?>, '<?= $rotina['status_hoje'] ?>')">
                        <div class="habit-main">
                            <div class="habit-icon">
                                <i class="bi bi-<?= $rotina['status_hoje'] === 'concluido' ? 'check-circle-fill' : 'circle' ?>"></i>
                            </div>
                            <div class="habit-content">
                                <div class="habit-name"><?= htmlspecialchars($rotina['nome']) ?></div>
                                <?php if ($rotina['horario_sugerido'] && $rotina['horario_sugerido'] !== '00:00:00'): ?>
                                    <small class="habit-time">
                                        <i class="bi bi-clock"></i> 
                                        <?= date('H:i', strtotime($rotina['horario_sugerido'])) ?>
                                    </small>
                                <?php endif; ?>
                                <?php if ($rotina['descricao']): ?>
                                    <small class="habit-description"><?= htmlspecialchars($rotina['descricao']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="habit-actions" onclick="event.stopPropagation()">
                            <button class="btn btn-sm btn-outline-warning" 
                                    onclick="editarHabito(<?= $rotina['id'] ?>, '<?= addslashes($rotina['nome']) ?>', '<?= $rotina['horario_sugerido'] ?>', '<?= addslashes($rotina['descricao']) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="excluirHabito(<?= $rotina['id'] ?>, '<?= addslashes($rotina['nome']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tarefas -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-title">
                <i class="bi bi-list-task"></i>
                <span>Minhas Tarefas</span>
            </div>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
                <i class="bi bi-plus-circle"></i> Nova Tarefa
            </button>
        </div>
        
        <div id="listaTarefas">
            <?php if (empty($tarefas_pendentes)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-check-circle"></i></div>
                    <h5>Nenhuma tarefa pendente!</h5>
                    <p>Parabéns! Você está em dia.</p>
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
                                        <span><i class="bi bi-calendar-event"></i> <?= date('d/m/Y', strtotime($tarefa['data_limite'])) ?></span>
                                    <?php endif; ?>
                                    <?php if ($tarefa['tempo_estimado'] > 0): ?>
                                        <span><i class="bi bi-clock"></i> <?= formatarTempo($tarefa['tempo_estimado']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($tarefa['subtarefas'])): ?>
                                        <span><i class="bi bi-list-ul"></i> <?= count($tarefa['subtarefas']) ?> subtarefas</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="task-actions">
                                <button class="btn-icon" onclick="concluirTarefa(<?= $tarefa['id'] ?>)" title="Concluir">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button class="btn-icon" onclick="editarTarefa(<?= $tarefa['id'] ?>)" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon" onclick="excluirTarefa(<?= $tarefa['id'] ?>)" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Novo Hábito -->
<div class="modal fade" id="modalNovoHabito" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Novo Hábito</h5>
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
                        <textarea name="descricao" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Hábito -->
<div class="modal fade" id="modalEditarHabito" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Hábito</h5>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nova Tarefa -->
<div class="modal fade" id="modalNovaTarefa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nova Tarefa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaTarefa" action="adicionar_tarefa.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control" required>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Salvar Tarefa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toast notification
function showToast(title, message, type = 'success') {
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107'
    };
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type]};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: slideIn 0.3s ease;
    `;
    toast.innerHTML = `<strong>${title}</strong><br>${message}`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Toggle hábito
function toggleHabito(id, statusAtual) {
    const novoStatus = statusAtual === 'concluido' ? 'pendente' : 'concluido';
    const habitItem = document.querySelector(`.habit-item[data-id="${id}"]`);
    
    // Atualização otimista
    if (novoStatus === 'concluido') {
        habitItem.classList.add('completed');
        habitItem.querySelector('.habit-icon i').className = 'bi bi-check-circle-fill';
    } else {
        habitItem.classList.remove('completed');
        habitItem.querySelector('.habit-icon i').className = 'bi bi-circle';
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
            } else {
                habitItem.classList.add('completed');
                habitItem.querySelector('.habit-icon i').className = 'bi bi-check-circle-fill';
            }
            showToast('Erro', data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Erro', 'Erro de conexão', 'error');
    });
}

// Novo hábito
document.getElementById('formNovoHabito').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('processar_rotina_fixa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `acao=adicionar&nome=${encodeURIComponent(formData.get('nome'))}&horario=${encodeURIComponent(formData.get('horario') || '')}&descricao=${encodeURIComponent(formData.get('descricao') || '')}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso', 'Hábito adicionado!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erro', data.message, 'error');
        }
    });
});

// Editar hábito
function editarHabito(id, nome, horario, descricao) {
    document.getElementById('editHabitoId').value = id;
    document.getElementById('editHabitoNome').value = nome;
    document.getElementById('editHabitoHorario').value = horario || '';
    document.getElementById('editHabitoDescricao').value = descricao || '';
    
    const modal = new bootstrap.Modal(document.getElementById('modalEditarHabito'));
    modal.show();
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
            showToast('Sucesso', 'Hábito atualizado!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erro', data.message, 'error');
        }
    });
});

// Excluir hábito
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
                showToast('Sucesso', 'Hábito excluído!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Erro', data.message, 'error');
            }
        });
    }
}

// Concluir tarefa
function concluirTarefa(id) {
    fetch('atualizar_status_tarefa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status: 'concluida' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso', 'Tarefa concluída!');
            document.querySelector(`.task-card[data-id="${id}"]`).remove();
        } else {
            showToast('Erro', data.message, 'error');
        }
    });
}

// Excluir tarefa
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
                showToast('Sucesso', 'Tarefa excluída!');
                document.querySelector(`.task-card[data-id="${id}"]`).remove();
            } else {
                showToast('Erro', data.message, 'error');
            }
        });
    }
}

// Editar tarefa
function editarTarefa(id) {
    showToast('Info', 'Funcionalidade em desenvolvimento', 'warning');
}

// Nova tarefa
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
            showToast('Sucesso', 'Tarefa adicionada!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erro', data.message, 'error');
        }
    });
});

// CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);
</script>

<?php require_once 'templates/footer.php'; ?>
