<?php
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

$dataHoje = date('Y-m-d');

// Buscar rotinas fixas do usuário
$rotinasFixas = [];
$rotinasConcluidas = 0;
$progressoRotina = 0;

try {
    $stmt = $pdo->prepare("
        SELECT rf.*, 
               rcd.status as status_hoje,
               rcd.id as controle_id
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd 
            ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY COALESCE(rf.horario_sugerido, '23:59:59'), rf.nome
    ");
    $stmt->execute([$dataHoje, $userId]);
    $rotinasFixas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar concluídas
    foreach ($rotinasFixas as $rotina) {
        if ($rotina['status_hoje'] === 'concluido') {
            $rotinasConcluidas++;
        }
    }
    
    // Calcular progresso
    if (count($rotinasFixas) > 0) {
        $progressoRotina = ($rotinasConcluidas / count($rotinasFixas)) * 100;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar rotinas: " . $e->getMessage());
}
?>

<style>
:root {
    --rotina-primary: #d32f2f;
    --rotina-bg: #1a1a1a;
    --rotina-card: #222;
    --rotina-border: #333;
    --rotina-text: #f0f0f0;
    --rotina-text-secondary: #a0a0a0;
}

.rotinas-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.page-header-rotinas {
    text-align: center;
    margin-bottom: 3rem;
}

.page-header-rotinas h1 {
    color: var(--rotina-text);
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.page-header-rotinas p {
    color: var(--rotina-text-secondary);
    font-size: 1.1rem;
}

.rotinas-section {
    background: var(--rotina-card);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid var(--rotina-border);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.section-header-rotinas {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title-rotinas {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-title-rotinas h3 {
    color: var(--rotina-text);
    margin: 0;
    font-size: 1.5rem;
}

.section-badge-rotinas {
    background: var(--rotina-primary);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.progress-circular-rotinas {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: conic-gradient(var(--rotina-primary) 0deg, var(--rotina-primary) calc(var(--progress) * 3.6deg), var(--rotina-border) calc(var(--progress) * 3.6deg), var(--rotina-border) 360deg);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.progress-circular-rotinas::before {
    content: '';
    position: absolute;
    width: 60px;
    height: 60px;
    background: var(--rotina-card);
    border-radius: 50%;
}

.progress-text-rotinas {
    position: relative;
    z-index: 1;
    color: var(--rotina-text);
    font-weight: 700;
    font-size: 1.1rem;
}

.rotinas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.rotina-item {
    background: linear-gradient(145deg, #2a2a2a, #1f1f1f);
    border: 1px solid var(--rotina-border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.rotina-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--rotina-primary);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.rotina-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(211, 47, 47, 0.2);
    border-color: var(--rotina-primary);
}

.rotina-item:hover::before {
    transform: scaleY(1);
}

.rotina-item.completed {
    opacity: 0.7;
}

.rotina-item.completed .rotina-icon i {
    color: #28a745;
}

.rotina-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.rotina-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(211, 47, 47, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.rotina-icon i {
    font-size: 1.5rem;
    color: var(--rotina-primary);
    transition: all 0.3s ease;
}

.rotina-content h6 {
    color: var(--rotina-text);
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.rotina-content small {
    color: var(--rotina-text-secondary);
    display: block;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.rotina-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--rotina-border);
}

.btn-rotina {
    flex: 1;
    padding: 0.5rem;
    border-radius: 8px;
    border: 1px solid;
    background: transparent;
    color: var(--rotina-text);
    transition: all 0.3s ease;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-rotina:hover {
    transform: translateY(-2px);
}

.btn-rotina-outline-warning {
    border-color: #ffc107;
    color: #ffc107;
}

.btn-rotina-outline-warning:hover {
    background: #ffc107;
    color: #000;
}

.btn-rotina-outline-danger {
    border-color: #dc3545;
    color: #dc3545;
}

.btn-rotina-outline-danger:hover {
    background: #dc3545;
    color: white;
}

.empty-state-rotinas {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--rotina-text-secondary);
}

.empty-state-rotinas i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state-rotinas h5 {
    color: var(--rotina-text);
    margin-bottom: 0.5rem;
}

.btn-add-rotina {
    background: var(--rotina-primary);
    color: white;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
    margin-top: 1rem;
}

.btn-add-rotina:hover {
    background: #b71c1c;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(211, 47, 47, 0.4);
}

/* Modal Styles */
.modal-content-rotinas {
    background: var(--rotina-card);
    border: 1px solid var(--rotina-border);
    border-radius: 16px;
}

.modal-header-rotinas {
    border-bottom: 1px solid var(--rotina-border);
    padding: 1.5rem;
}

.modal-header-rotinas h5 {
    color: var(--rotina-text);
    font-weight: 600;
}

.modal-body-rotinas {
    padding: 1.5rem;
}

.modal-footer-rotinas {
    border-top: 1px solid var(--rotina-border);
    padding: 1.5rem;
}

.form-label-rotinas {
    color: var(--rotina-text);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-control-rotinas {
    background: var(--rotina-bg);
    border: 1px solid var(--rotina-border);
    color: var(--rotina-text);
    border-radius: 8px;
    padding: 0.75rem;
}

.form-control-rotinas:focus {
    background: var(--rotina-bg);
    border-color: var(--rotina-primary);
    color: var(--rotina-text);
    box-shadow: 0 0 0 0.2rem rgba(211, 47, 47, 0.25);
}

/* Responsive */
@media (max-width: 768px) {
    .rotinas-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header-rotinas {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header-rotinas h1 {
        font-size: 2rem;
    }
}

/* Toast Notification */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast-rotina {
    background: var(--rotina-card);
    border: 1px solid var(--rotina-border);
    border-radius: 8px;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast-rotina.success {
    border-left: 4px solid #28a745;
}

.toast-rotina.error {
    border-left: 4px solid #dc3545;
}

.toast-rotina.warning {
    border-left: 4px solid #ffc107;
}
</style>

<div class="rotinas-container">
    <div class="page-header-rotinas">
        <h1><i class="bi bi-calendar-check me-2"></i>Minhas Rotinas</h1>
        <p>Gerencie seus hábitos e rotinas diárias</p>
    </div>

    <div class="rotinas-section">
        <div class="section-header-rotinas">
            <div class="section-title-rotinas">
                <i class="bi bi-calendar-check" style="font-size: 2rem; color: var(--rotina-primary);"></i>
                <div>
                    <h3>Rotinas Fixas</h3>
                    <span class="section-badge-rotinas">
                        <?php echo $rotinasConcluidas; ?>/<?php echo count($rotinasFixas); ?> concluídas
                    </span>
                </div>
            </div>
            <?php if (count($rotinasFixas) > 0): ?>
            <div class="progress-circular-rotinas" style="--progress: <?php echo $progressoRotina; ?>">
                <span class="progress-text-rotinas"><?php echo round($progressoRotina); ?>%</span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($rotinasFixas)): ?>
        <div class="empty-state-rotinas">
            <i class="bi bi-calendar-x"></i>
            <h5>Nenhuma rotina cadastrada</h5>
            <p>Adicione rotinas que você quer fazer todos os dias</p>
            <button class="btn-add-rotina" onclick="abrirModalRotina()">
                <i class="bi bi-plus-circle me-2"></i>Adicionar Primeira Rotina
            </button>
        </div>
        <?php else: ?>
        <div class="rotinas-grid" id="lista-rotinas">
            <?php foreach ($rotinasFixas as $rotina): ?>
            <div class="rotina-item <?php echo $rotina['status_hoje'] === 'concluido' ? 'completed' : ''; ?>" 
                 data-rotina-id="<?php echo $rotina['id']; ?>"
                 data-controle-id="<?php echo $rotina['controle_id'] ?? ''; ?>">
                <div class="rotina-header">
                    <div class="rotina-icon" onclick="toggleRotina(<?php echo $rotina['id']; ?>, '<?php echo $rotina['status_hoje'] ?? 'pendente'; ?>')">
                        <i class="bi bi-<?php echo $rotina['status_hoje'] === 'concluido' ? 'check-circle-fill' : 'circle'; ?>"></i>
                    </div>
                    <div class="rotina-content">
                        <h6><?php echo htmlspecialchars($rotina['nome']); ?></h6>
                        <?php if ($rotina['horario_sugerido'] && $rotina['horario_sugerido'] !== '00:00:00'): ?>
                        <small><i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($rotina['horario_sugerido'])); ?></small>
                        <?php endif; ?>
                        <?php if ($rotina['descricao']): ?>
                        <small><i class="bi bi-card-text me-1"></i><?php echo htmlspecialchars($rotina['descricao']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rotina-actions">
                    <button class="btn-rotina btn-rotina-outline-warning" onclick="editarRotina(<?php echo $rotina['id']; ?>)">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </button>
                    <button class="btn-rotina btn-rotina-outline-danger" onclick="excluirRotina(<?php echo $rotina['id']; ?>, '<?php echo htmlspecialchars($rotina['nome'], ENT_QUOTES); ?>')">
                        <i class="bi bi-trash me-1"></i>Excluir
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 2rem;">
            <button class="btn-add-rotina" onclick="abrirModalRotina()">
                <i class="bi bi-plus-circle me-2"></i>Adicionar Nova Rotina
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Adicionar Rotina -->
<div class="modal fade" id="modalRotina" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-rotinas">
            <div class="modal-header modal-header-rotinas">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Adicionar Nova Rotina
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formNovaRotina">
                <div class="modal-body modal-body-rotinas">
                    <div class="mb-3">
                        <label for="nomeRotina" class="form-label form-label-rotinas">
                            <i class="bi bi-tag me-1"></i>Nome da Rotina *
                        </label>
                        <input type="text" 
                               class="form-control form-control-rotinas" 
                               id="nomeRotina" 
                               name="nome"
                               placeholder="Ex: Treinar, Estudar, Meditar..." 
                               required 
                               autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="horarioRotina" class="form-label form-label-rotinas">
                            <i class="bi bi-clock me-1"></i>Horário Sugerido (Opcional)
                        </label>
                        <input type="time" 
                               class="form-control form-control-rotinas" 
                               id="horarioRotina" 
                               name="horario">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>Defina um horário ideal para esta rotina
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="descricaoRotina" class="form-label form-label-rotinas">
                            <i class="bi bi-card-text me-1"></i>Descrição (Opcional)
                        </label>
                        <textarea class="form-control form-control-rotinas" 
                                  id="descricaoRotina" 
                                  name="descricao"
                                  rows="3" 
                                  placeholder="Adicione uma descrição ou observações sobre esta rotina..."></textarea>
                    </div>
                </div>
                <div class="modal-footer modal-footer-rotinas">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-check-circle me-1"></i>Adicionar Rotina
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// Funções de Modal
function abrirModalRotina() {
    const modal = new bootstrap.Modal(document.getElementById('modalRotina'));
    modal.show();
    document.getElementById('formNovaRotina').reset();
    setTimeout(() => {
        document.getElementById('nomeRotina').focus();
    }, 300);
}

// Submissão do Formulário
document.addEventListener('DOMContentLoaded', function() {
    const formRotina = document.getElementById('formNovaRotina');
    if (formRotina) {
        formRotina.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(formRotina);
            const nome = formData.get('nome')?.trim();
            
            if (!nome) {
                showToast('Nome é obrigatório', 'warning');
                return;
            }
            
            const btn = formRotina.querySelector('button[type="submit"]');
            const btnOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
            
            fetch('adicionar_rotina_fixa.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Rotina criada com sucesso!', 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalRotina'));
                    modal.hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Erro ao criar rotina', 'error');
                    btn.disabled = false;
                    btn.innerHTML = btnOriginal;
                }
            })
            .catch(error => {
                showToast('Erro ao salvar. Tente novamente.', 'error');
                btn.disabled = false;
                btn.innerHTML = btnOriginal;
            });
        });
    }
});

// Toggle Rotina (Concluir/Pendente)
function toggleRotina(rotinaId, statusAtual) {
    const novoStatus = statusAtual === 'concluido' ? 'pendente' : 'concluido';
    
    // Primeiro, buscar o controle_id da rotina
    const rotinaItem = document.querySelector(`[data-rotina-id="${rotinaId}"]`);
    const controleId = rotinaItem?.dataset?.controleId;
    
    if (!controleId) {
        // Se não tiver controle_id, criar um novo controle para hoje
        fetch('processar_rotina_diaria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `rotina_id=${rotinaId}&status=${novoStatus}&criar_controle=1`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                showToast(data.message || 'Erro ao atualizar rotina', 'error');
            }
        })
        .catch(error => {
            showToast('Erro ao atualizar rotina', 'error');
        });
    } else {
        // Usar o controle_id existente
        fetch('processar_rotina_diaria.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `controle_id=${controleId}&status=${novoStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                showToast(data.message || 'Erro ao atualizar rotina', 'error');
            }
        })
        .catch(error => {
            showToast('Erro ao atualizar rotina', 'error');
        });
    }
}

// Editar Rotina
function editarRotina(rotinaId) {
    window.location.href = `editar_rotina_fixa.php?id=${rotinaId}`;
}

// Excluir Rotina
function excluirRotina(rotinaId, nomeRotina) {
    if (confirm(`Tem certeza que deseja excluir a rotina "${nomeRotina}"?`)) {
        fetch('excluir_rotina_fixa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: rotinaId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Rotina excluída com sucesso!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Erro ao excluir rotina', 'error');
            }
        })
        .catch(error => {
            showToast('Erro ao excluir rotina', 'error');
        });
    }
}

// Toast Notification
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast-rotina ${type}`;
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
        </div>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php require_once 'templates/footer.php'; ?>

