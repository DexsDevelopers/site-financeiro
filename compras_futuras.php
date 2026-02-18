<?php
// compras_futuras.php - Gestão de Metas de Compra (Premium UI)
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

// Inicialização de Arrays
$compras_planejando = [];
$compras_concluidas = [];

// 1. Buscar Compras Futuras
try {
    $stmt = $pdo->prepare("
        SELECT * FROM compras_futuras 
        WHERE id_usuario = ? 
        ORDER BY status, ordem ASC, data_criacao DESC
    ");
    $stmt->execute([$userId]);
    $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($todas as $c) {
        if ($c['status'] === 'planejando') {
            $compras_planejando[] = $c;
        } else {
            $compras_concluidas[] = $c;
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar compras: " . $e->getMessage());
}

// 2. Calcular Saldo Atual Disponível (Receitas - Despesas do Mês)
// Este valor ajuda a indicar se o usuário JÁ pode comprar o item.
$saldoDisponivel = 0;
try {
    $stmt_saldo = $pdo->prepare("
        SELECT 
            (SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) - 
             SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END)) as saldo 
        FROM transacoes 
        WHERE id_usuario = ? 
        AND MONTH(data_transacao) = MONTH(CURDATE()) 
        AND YEAR(data_transacao) = YEAR(CURDATE())
    ");
    $stmt_saldo->execute([$userId]);
    $saldoDisponivel = (float) $stmt_saldo->fetchColumn();
} catch (PDOException $e) {
    $saldoDisponivel = 0.0;
}

?>

<style>
/* --- Variáveis & Tema (Consistente com Tarefas) --- */
:root {
    --glass-bg: rgba(26, 26, 26, 0.85);
    --glass-border: rgba(255, 255, 255, 0.08);
    --accent-color: #e50914; 
    --accent-hover: #b20710;
    --success-color: #10b981;
    --text-primary: #f5f5f1;
    --text-secondary: #b3b3b7;
}

body {
    background-color: #0f0f0f;
}

.shopping-container {
    max-width: 1200px;
    margin: 0 auto;
    padding-bottom: 4rem;
}

/* --- Header Section --- */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.title-area h1 {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(90deg, #fff, #ccc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.btn-add-goal {
    background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-add-goal:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(229, 9, 20, 0.4);
    color: white;
}

/* --- Cards Grid --- */
.goals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}

.goal-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

.goal-card:hover {
    transform: translateY(-5px);
    border-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

/* Faixa lateral de status */
.status-stripe {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--glass-border);
    transition: background 0.3s;
}

.goal-card.achievable .status-stripe {
    background: var(--success-color);
    box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
}

.card-header-area {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.item-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
}

.item-price {
    font-size: 1.1rem;
    color: var(--text-secondary);
    font-weight: 500;
    margin-top: 0.25rem;
    display: block;
}

.menu-trigger {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    cursor: pointer;
}

.menu-trigger:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

/* --- Progress Bar --- */
.progress-area {
    margin: 1rem 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.progress-track {
    height: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #e50914, #ff5f6d);
    border-radius: 4px;
    transition: width 1s ease-out;
}

.goal-card.achievable .progress-fill {
    background: linear-gradient(90deg, #10b981, #34d399);
}

/* --- Actions Footer --- */
.card-actions {
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid rgba(255,255,255,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-link-ext {
    color: var(--text-secondary);
    font-size: 0.9rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: color 0.2s;
}

.btn-link-ext:hover { color: var(--accent-color); }

.btn-buy-now {
    background: rgba(255,255,255,0.1);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.goal-card.achievable .btn-buy-now {
    background: rgba(16, 185, 129, 0.2);
    color: var(--success-color);
}

.goal-card.achievable .btn-buy-now:hover {
    background: var(--success-color);
    color: white;
}

.btn-buy-now:hover:not(.achievable .btn-buy-now) {
    background: rgba(255,255,255,0.2);
}

/* --- Empty State --- */
.empty-goals {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem;
    background: rgba(255,255,255,0.02);
    border: 1px dashed var(--glass-border);
    border-radius: 16px;
    color: var(--text-secondary);
}

.empty-goals i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* --- Concluídos Section --- */
.section-completed {
    margin-top: 4rem;
    opacity: 0.7;
    transition: opacity 0.3s;
}

.section-completed:hover { opacity: 1; }

.section-title {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.completed-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.completed-card .name {
    text-decoration: line-through;
    color: var(--text-secondary);
}

/* --- Modais --- */
.modal-content {
    background-color: #1a1a1a;
    border: 1px solid var(--glass-border);
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}
.modal-header { border-bottom-color: var(--glass-border); }
.modal-footer { border-top-color: var(--glass-border); }
.form-control {
    background-color: rgba(0,0,0,0.3);
    border-color: var(--glass-border);
    color: var(--text-primary);
}
.form-control:focus {
    background-color: rgba(0,0,0,0.4);
    border-color: var(--accent-color);
    color: white;
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.15);
}

@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; }
    .btn-add-goal { width: 100%; justify-content: center; }
}
</style>

<div class="shopping-container pt-4">
    <!-- Header -->
    <div class="page-header">
        <div class="title-area">
            <h1>Compras Futuras</h1>
            <p class="text-muted mb-0">Planeje seus desejos e controle seu orçamento</p>
        </div>
        <button class="btn-add-goal" data-bs-toggle="modal" data-bs-target="#modalNovaCompra">
            <i class="bi bi-bag-plus"></i> Novo Planejamento
        </button>
    </div>

    <!-- Lista de Planejamento -->
    <div class="goals-grid" id="grid-planejando">
        <?php if (empty($compras_planejando)): ?>
            <div class="empty-goals">
                <i class="bi bi-cart-x"></i>
                <h3>Lista Vazia</h3>
                <p>O que você está querendo comprar recentemente?</p>
            </div>
        <?php else: ?>
            <?php foreach($compras_planejando as $compra): 
                $valor = (float)$compra['valor_estimado'];
                // Se saldo >= valor, 100%. Senão, calcula porcentagem.
                // Se valor for 0 (evitar div por zero), assume 100% se saldo > 0
                if ($valor <= 0) {
                    $percent = 100;
                    $achievable = true;
                } else {
                    $percent = min(100, ($saldoDisponivel / $valor) * 100);
                    $achievable = ($saldoDisponivel >= $valor);
                }
                
                // Formatação
                $valorFmt = number_format($valor, 2, ',', '.');
                $saldoFmt = number_format($saldoDisponivel, 2, ',', '.');
            ?>
            <div class="goal-card <?php echo $achievable ? 'achievable' : ''; ?>" id="card-<?php echo $compra['id']; ?>">
                <div class="status-stripe"></div>
                
                <div class="card-header-area">
                    <div>
                        <h3 class="item-name"><?php echo htmlspecialchars($compra['nome_item']); ?></h3>
                        <span class="item-price">R$ <?php echo $valorFmt; ?></span>
                    </div>
                    
                    <div class="dropdown">
                        <button class="menu-trigger" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li>
                                <button class="dropdown-item btn-editar" 
                                    data-id="<?php echo $compra['id']; ?>"
                                    data-nome="<?php echo htmlspecialchars($compra['nome_item']); ?>"
                                    data-valor="<?php echo $compra['valor_estimado']; ?>"
                                    data-link="<?php echo htmlspecialchars($compra['link_referencia'] ?? ''); ?>">
                                    <i class="bi bi-pencil me-2"></i> Editar
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item text-danger btn-excluir" 
                                    data-id="<?php echo $compra['id']; ?>"
                                    data-nome="<?php echo htmlspecialchars($compra['nome_item']); ?>">
                                    <i class="bi bi-trash me-2"></i> Excluir
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Barra de Progresso Financeira -->
                <div class="progress-area">
                    <div class="progress-label">
                        <span>Potencial de Compra</span>
                        <span><?php echo round($percent); ?>%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    <?php if ($achievable): ?>
                        <small class="text-success mt-1 d-block"><i class="bi bi-check-circle-fill"></i> Saldo disponível!</small>
                    <?php endif; ?>
                </div>

                <div class="card-actions">
                    <?php if (!empty($compra['link_referencia'])): ?>
                        <a href="<?php echo htmlspecialchars($compra['link_referencia']); ?>" target="_blank" class="btn-link-ext">
                            <i class="bi bi-link-45deg"></i> Ver Oferta
                        </a>
                    <?php else: ?>
                        <span></span> <!-- Spacer -->
                    <?php endif; ?>

                    <button class="btn-buy-now btn-concluir" 
                        data-id="<?php echo $compra['id']; ?>"
                        data-nome="<?php echo htmlspecialchars($compra['nome_item']); ?>">
                        <i class="bi bi-bag-check"></i> Comprar
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Lista de Concluídas -->
    <?php if (!empty($compras_concluidas)): ?>
    <div class="section-completed">
        <div class="section-title">
            <i class="bi bi-archive"></i> Histórico de Compras Sucessos
        </div>
        <div class="row g-3">
            <?php foreach($compras_concluidas as $compra): ?>
            <div class="col-md-4 col-sm-6">
                <div class="completed-card">
                    <div>
                        <div class="name"><?php echo htmlspecialchars($compra['nome_item']); ?></div>
                        <small class="text-muted">Concluído</small>
                    </div>
                    <i class="bi bi-check-circle text-success fs-4"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Nova Compra -->
<div class="modal fade" id="modalNovaCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-bag-heart me-2 text-danger"></i>Novo Desejo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formNovaCompra">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">O que você quer comprar?</label>
                        <input type="text" name="nome_item" class="form-control form-control-lg" placeholder="Ex: Novo Monitor" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor Estimado (R$)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-secondary text-light">R$</span>
                            <input type="number" name="valor_estimado" class="form-control" step="0.01" min="0" placeholder="0,00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link do Produto (Opcional)</label>
                        <input type="url" name="link_referencia" class="form-control" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-decoration-none text-muted" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-add-goal">Salvar Meta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Compra -->
<div class="modal fade" id="modalEditarCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Planejamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditarCompra">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" name="nome_item" id="edit-nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valor (R$)</label>
                        <input type="number" name="valor_estimado" id="edit-valor" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link</label>
                        <input type="url" name="link_referencia" id="edit-link" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-decoration-none text-muted" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-add-goal">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Configuração SweetAlert Toast ---
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#1a1a1a',
        color: '#fff'
    });

    // --- Nova Compra ---
    const modalNova = new bootstrap.Modal(document.getElementById('modalNovaCompra'));
    document.getElementById('formNovaCompra')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
        btn.disabled = true;

        fetch('adicionar_compra_futura.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                Toast.fire({ icon: 'success', title: data.message });
                setTimeout(() => location.reload(), 800);
            } else {
                Toast.fire({ icon: 'error', title: data.message });
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
    });

    // --- Editar Compra (Abrir Modal) ---
    const modalEditarEl = document.getElementById('modalEditarCompra');
    const modalEditar = new bootstrap.Modal(modalEditarEl);
    
    document.body.addEventListener('click', function(e) {
        const btnEdit = e.target.closest('.btn-editar');
        if (btnEdit) {
            document.getElementById('edit-id').value = btnEdit.dataset.id;
            document.getElementById('edit-nome').value = btnEdit.dataset.nome;
            document.getElementById('edit-valor').value = btnEdit.dataset.valor;
            document.getElementById('edit-link').value = btnEdit.dataset.link;
            modalEditar.show();
        }
    });

    // --- Salvar Edição ---
    document.getElementById('formEditarCompra')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;

        fetch('editar_compra.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                Toast.fire({ icon: 'success', title: 'Atualizado com sucesso!' });
                setTimeout(() => location.reload(), 800);
            } else {
                Toast.fire({ icon: 'error', title: data.message });
                btn.disabled = false;
            }
        });
    });

    // --- Excluir Compra ---
    document.body.addEventListener('click', function(e) {
        const btnDel = e.target.closest('.btn-excluir');
        if (btnDel) {
            Swal.fire({
                title: 'Desistir da compra?',
                text: `Remover "${btnDel.dataset.nome}" da lista?`,
                icon: 'warning',
                background: '#1a1a1a',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#3f3f46',
                confirmButtonText: 'Sim, remover'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('excluir_compra.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id: btnDel.dataset.id })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            const card = document.getElementById(`card-${btnDel.dataset.id}`);
                            if(card) {
                                card.style.opacity = '0';
                                card.style.transform = 'scale(0.8)';
                                setTimeout(() => card.remove(), 300);
                            }
                            Toast.fire({ icon: 'success', title: 'Item removido!' });
                        }
                    });
                }
            });
        }
    });

    // --- Concluir Compra ---
    document.body.addEventListener('click', function(e) {
        const btnConcluir = e.target.closest('.btn-concluir');
        if (btnConcluir) {
            Swal.fire({
                title: 'Compra Realizada!',
                text: `Legal! Você comprou "${btnConcluir.dataset.nome}".`,
                icon: 'success',
                background: '#1a1a1a',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#3f3f46',
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Ainda não'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('concluir_compra.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id: btnConcluir.dataset.id })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            location.reload();
                        } else {
                            Toast.fire({ icon: 'error', title: data.message });
                        }
                    });
                }
            });
        }
    });

});
</script>

<?php require_once 'templates/footer.php'; ?>