<?php
/**
 * rotinas.php - Sistema de Rotinas Diárias (HÁBITOS)
 * REESCRITO PARA GARANTIR PERFORMANCE E DESIGN PREMIUM
 */
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

$dataHoje = date('Y-m-d');
$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'];

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
        ORDER BY 
            CASE 
                WHEN rf.prioridade = 'Alta' THEN 1 
                WHEN rf.prioridade = 'Média' THEN 2 
                ELSE 3 
            END,
            COALESCE(rf.horario_sugerido, '23:59:59'), 
            rf.nome
    ");
    $stmt->execute([$dataHoje, $userId]);
    $rotinasFixas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rotinasFixas as $rotina) {
        if ($rotina['status_hoje'] === 'concluido') {
            $rotinasConcluidas++;
        }
    }

    if (count($rotinasFixas) > 0) {
        $progressoRotina = ($rotinasConcluidas / count($rotinasFixas)) * 100;
    }
}
catch (PDOException $e) {
    error_log("Erro ao buscar rotinas: " . $e->getMessage());
}
?>

<style>
:root {
    --lux-primary: #ff4d4d;
    --lux-bg: #141414;
    --lux-card: rgba(255, 255, 255, 0.05);
    --lux-border: rgba(255, 255, 255, 0.1);
    --lux-text: #ffffff;
    --lux-text-dim: #b3b3b3;
}

.rotinas-lux-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 3rem 1.5rem;
}

.header-premium-lux {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4rem;
}

.header-premium-lux h1 {
    font-size: 3rem;
    font-weight: 800;
    letter-spacing: -1px;
    margin: 0;
}

.btn-add-lux {
    background: var(--lux-primary);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 50px;
    font-weight: 700;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    box-shadow: 0 10px 20px rgba(255, 77, 77, 0.3);
}

.btn-add-lux:hover {
    transform: scale(1.05);
    box-shadow: 0 15px 30px rgba(255, 77, 77, 0.5);
}

.stats-glass-lux {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    border: 1px solid var(--lux-border);
    border-radius: 30px;
    padding: 2.5rem;
    margin-bottom: 4rem;
    display: flex;
    align-items: center;
    gap: 3rem;
}

.circle-progress-lux {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: conic-gradient(var(--lux-primary) <?php echo $progressoRotina; ?>%, transparent 0);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.circle-progress-lux::after {
    content: '<?php echo round($progressoRotina); ?>%';
    width: 100px;
    height: 100px;
    background: var(--lux-bg);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: 800;
}

.grid-lux-habitos {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
}

.card-lux-habit {
    background: var(--lux-card);
    border: 1px solid var(--lux-border);
    border-radius: 24px;
    padding: 2rem;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
}

.card-lux-habit:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-10px);
    border-color: rgba(255, 77, 77, 0.4);
}

.card-lux-habit.concluido {
    border-color: #2ecc71;
    background: rgba(46, 204, 113, 0.05);
}

.prio-pill {
    padding: 0.4rem 1rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
}

.prio-Alta { background: rgba(255, 77, 77, 0.2); color: #ff4d4d; }
.prio-Média { background: rgba(241, 196, 15, 0.2); color: #f1c40f; }
.prio-Baixa { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }

.btn-complete-lux {
    width: 100%;
    margin-top: 1.5rem;
    padding: 0.8rem;
    border-radius: 15px;
    border: 1px solid var(--lux-border);
    background: rgba(255, 255, 255, 0.05);
    color: white;
    font-weight: 700;
    transition: 0.3s;
}

.btn-complete-lux:hover {
    background: var(--lux-primary);
    border-color: var(--lux-primary);
}

.btn-complete-lux.is-done {
    background: #2ecc71;
    border-color: #2ecc71;
}

.btn-action-lux {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--lux-border);
    color: var(--lux-text-dim);
    transition: all 0.2s ease;
    cursor: pointer;
    font-size: 1.2rem;
}

.btn-action-lux:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateY(-2px);
}

.btn-action-lux.delete {
    color: #ff4d4d;
}

.btn-action-lux.delete:hover {
    background: rgba(255, 77, 77, 0.15);
    border-color: rgba(255, 77, 77, 0.4);
    color: #ff6666;
}
</style>

<div class="rotinas-lux-container">
    <header class="header-premium-lux">
        <div>
            <h1>Rotina Diária</h1>
            <p style="color: var(--lux-text-dim); margin: 0.5rem 0 0;">Construa sua melhor versão, um passo de cada vez.</p>
        </div>
        <button class="btn-add-lux" onclick="abrirModalRotina()">
            <i class="bi bi-plus-lg"></i> Novo Hábito
        </button>
    </header>

    <?php if (!empty($rotinasFixas)): ?>
    <div class="stats-glass-lux">
        <div class="circle-progress-lux"></div>
        <div>
            <h2 style="margin: 0;">Você está no caminho!</h2>
            <p style="color: var(--lux-text-dim); margin-top: 0.5rem;">
                Concluídos: <strong><?php echo $rotinasConcluidas; ?></strong> de <?php echo count($rotinasFixas); ?>
            </p>
        </div>
    </div>

    <div class="grid-lux-habitos">
        <?php foreach ($rotinasFixas as $rotina):
        $isConcluido = ($rotina['status_hoje'] === 'concluido');
?>
        <div class="card-lux-habit <?php echo $isConcluido ? 'concluido' : ''; ?>" data-id="<?php echo $rotina['id']; ?>" data-controle-id="<?php echo $rotina['controle_id'] ?? ''; ?>">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <span class="prio-pill prio-<?php echo $rotina['prioridade']; ?>">
                    <?php echo $rotina['prioridade']; ?>
                </span>
                <?php if ($rotina['horario_sugerido']): ?>
                    <span style="color: var(--lux-text-dim); font-size: 0.9rem;">
                        <i class="bi bi-clock me-1"></i> <?php echo date('H:i', strtotime($rotina['horario_sugerido'])); ?>
                    </span>
                <?php
        endif; ?>
            </div>
            
            <h3 style="margin-top: 0; color: white;"><?php echo htmlspecialchars($rotina['nome']); ?></h3>
            <p style="color: var(--lux-text-dim); font-size: 0.95rem; min-height: 45px;">
                <?php echo htmlspecialchars($rotina['descricao']); ?>
            </p>

            <button class="btn-complete-lux <?php echo $isConcluido ? 'is-done' : ''; ?>" onclick="toggleRotina(<?php echo $rotina['id']; ?>, '<?php echo $rotina['status_hoje'] ?? 'pendente'; ?>')">
                <?php echo $isConcluido ? '<i class="bi bi-check-circle-fill me-2"></i> Concluído' : 'Marcar como feito'; ?>
            </button>
            
            <div style="display: flex; gap: 0.8rem; margin-top: 1.5rem; justify-content: flex-end;">
                <button class="btn-action-lux" onclick="event.stopPropagation(); editarRotina(<?php echo $rotina['id']; ?>)" title="Editar">
                    <i class="bi bi-pencil-square"></i>
                </button>
                <button class="btn-action-lux delete" onclick="event.stopPropagation(); excluirRotina(<?php echo $rotina['id']; ?>, '<?php echo addslashes($rotina['nome']); ?>')" title="Excluir">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        </div>
        <?php
    endforeach; ?>
    </div>
    <?php
else: ?>
    <div style="text-align: center; padding: 5rem; background: rgba(255,255,255,0.02); border-radius: 30px; border: 2px dashed var(--lux-border);">
        <i class="bi bi-calendar-event" style="font-size: 4rem; color: var(--lux-text-dim); opacity: 0.3;"></i>
        <h3 style="margin-top: 2rem;">Nada por aqui ainda...</h3>
        <p style="color: var(--lux-text-dim);">Comece criando seu primeiro hábito diário!</p>
        <button class="btn-add-lux" style="margin: 2rem auto 0;" onclick="abrirModalRotina()">Criar Agora</button>
    </div>
    <?php
endif; ?>
</div>

<!-- Modal Elite -->
<div class="modal fade" id="modalRotina" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #1a1a1a; border: 1px solid var(--lux-border); border-radius: 30px; overflow: hidden;">
            <div class="p-4">
                <h2 style="margin: 0 0 2rem 0;">Novo Hábito</h2>
                <form id="formNovaRotina">
                    <div class="mb-3">
                        <label class="form-label text-dim">Nome do Hábito</label>
                        <input type="text" name="nome" class="form-control bg-dark text-white border-0 py-3" required style="border-radius: 12px;">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label text-dim">Horário</label>
                            <input type="time" name="horario" class="form-control bg-dark text-white border-0 py-3" style="border-radius: 12px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-dim">Prioridade</label>
                            <select name="prioridade" class="form-control bg-dark text-white border-0" style="border-radius: 12px;">
                                <option value="Alta">Alta</option>
                                <option value="Média" selected>Média</option>
                                <option value="Baixa">Baixa</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-dim">Descrição</label>
                        <textarea name="descricao" class="form-control bg-dark text-white border-0" rows="3" style="border-radius: 12px;"></textarea>
                    </div>
                    <button type="submit" class="btn-add-lux w-100 justify-content-center">Salvar Hábito</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalRotina() { new bootstrap.Modal(document.getElementById('modalRotina')).show(); }

document.getElementById('formNovaRotina').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = 'Salvando...';
    
    fetch('adicionar_rotina_fixa.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else { alert(data.message); btn.disabled = false; btn.innerHTML = 'Salvar Hábito'; }
    });
});

function toggleRotina(rotinaId, statusAtual) {
    const card = document.querySelector(`[data-id="${rotinaId}"]`);
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
}

function editarRotina(id) { window.location.href = `editar_rotina_fixa.php?id=${id}`; }

function excluirRotina(id, nome) {
    if (confirm(`Excluir "${nome}"?`)) {
        fetch('excluir_rotina_fixa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        }).then(r => r.json()).then(d => { if(d.success) location.reload(); });
    }
}
</script>

<?php require_once 'templates/footer.php'; ?>
