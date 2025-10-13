<?php
// compras_futuras.php (Versão Final com Lógica e Design Corrigidos)
require_once 'templates/header.php';

$compras_planejando = [];
$compras_concluidas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM compras_futuras WHERE id_usuario = ? ORDER BY status, ordem ASC, data_criacao DESC");
    $stmt->execute([$userId]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $compra) {
        if ($compra['status'] == 'planejando') {
            $compras_planejando[] = $compra;
        } else {
            $compras_concluidas[] = $compra;
        }
    }
} catch (PDOException $e) { die("Erro ao buscar compras: " . $e->getMessage()); }

$saldoMesAtual = 0;
try {
    $stmt_saldo = $pdo->prepare("SELECT (SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) - SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END)) as saldo FROM transacoes WHERE id_usuario = ? AND MONTH(data_transacao) = MONTH(CURDATE()) AND YEAR(data_transacao) = YEAR(CURDATE())");
    $stmt_saldo->execute([$userId]);
    $saldoMesAtual = $stmt_saldo->fetchColumn() ?? 0;
} catch (PDOException $e) { $saldoMesAtual = 0; }
?>
<style>
    .goal-card.pode-comprar { border-left: 5px solid #198754; }
    .goal-card.pode-comprar .card-title { color: #198754; }
</style>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Compras Futuras</h1>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaCompra"><i class="bi bi-plus-lg me-2"></i>Novo Planejamento</button>
</div>
<h4 class="mb-3">Planejando</h4>
<div class="row g-4" id="lista-planejando">
    <?php if (empty($compras_planejando)): ?>
        <div class="col-12" id="empty-state-planejando"><div class="card card-custom"><div class="card-body text-center p-5"><h5 class="text-muted">Nenhuma compra futura planejada.</h5></div></div></div>
    <?php else: ?>
        <?php foreach($compras_planejando as $compra): 
            $pode_comprar = $saldoMesAtual > 0 && $compra['valor_estimado'] <= $saldoMesAtual;
        ?>
            <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" id="compra-card-<?php echo $compra['id']; ?>">
                <div class="card card-custom h-100 goal-card <?php if ($pode_comprar) echo 'pode-comprar'; ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <h5 class="card-title mb-1 me-2"><?php echo htmlspecialchars($compra['nome_item']); ?></h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary border-0" type="button" data-bs-toggle="dropdown" data-bs-strategy="fixed"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li><button class="dropdown-item btn-editar-compra" data-compra='<?php echo json_encode($compra, JSON_HEX_QUOT); ?>'><i class="bi bi-pencil-fill me-2"></i>Editar</button></li>
                                    <li><button class="dropdown-item btn-excluir-compra" data-id="<?php echo $compra['id']; ?>" data-nome="<?php echo htmlspecialchars($compra['nome_item']); ?>"><i class="bi bi-trash-fill me-2"></i>Excluir</button></li>
                                </ul>
                            </div>
                        </div>
                        <h6 class="card-subtitle mb-2 text-muted">Valor Estimado: <span class="valor-sensivel">R$ <?php echo number_format($compra['valor_estimado'], 2, ',', '.'); ?></span></h6>
                        <?php if ($pode_comprar): ?>
                            <div class="alert alert-success small p-2 mt-2">
                                <i class="bi bi-check-circle-fill"></i> Com seu saldo atual, você já pode comprar!
                            </div>
                        <?php endif; ?>
                        <div class="mt-auto pt-3 d-flex justify-content-between align-items-center">
                             <?php if($compra['link_referencia']): ?><a href="<?php echo htmlspecialchars($compra['link_referencia']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Ver Link</a><?php else: ?><span></span><?php endif; ?>
                            <button class="btn btn-sm btn-success btn-concluir-compra" data-id="<?php echo $compra['id']; ?>" data-nome="<?php echo htmlspecialchars($compra['nome_item']); ?>"><i class="bi bi-check-lg"></i> Concluir</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<h4 class="mb-3 mt-5">Concluídas</h4>
<div class="row g-4">
    <?php if (empty($compras_concluidas)): ?>
        <div class="col-12"><div class="card card-custom"><div class="card-body text-center p-4"><p class="text-muted mb-0">Nenhuma compra foi concluída ainda.</p></div></div></div>
    <?php else: ?>
        <?php foreach($compras_concluidas as $compra): ?>
            <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up">
                <div class="card card-custom h-100 opacity-75">
                    <div class="card-body"><h5 class="card-title text-muted text-decoration-line-through"><?php echo htmlspecialchars($compra['nome_item']); ?></h5><h6 class="card-subtitle text-muted">Concluída!</h6></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<div class="modal fade" id="modalNovaCompra" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Planejar Nova Compra</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formNovaCompra"><div class="modal-body"><div class="mb-3"><label class="form-label">O que você quer comprar?</label><input type="text" name="nome_item" class="form-control" required></div><div class="mb-3"><label class="form-label">Valor Estimado (R$)</label><input type="number" name="valor_estimado" class="form-control" step="0.01" min="0"></div><div class="mb-3"><label class="form-label">Link de Referência (Opcional)</label><input type="url" name="link_referencia" class="form-control"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar</button></div></form></div></div></div>
<div class="modal fade" id="modalEditarCompra" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar Compra Futura</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formEditarCompra"><input type="hidden" name="id" id="edit_compra_id"><div class="modal-body"><div class="mb-3"><label class="form-label">O que você quer comprar?</label><input type="text" name="nome_item" id="edit_nome_item" class="form-control" required></div><div class="mb-3"><label class="form-label">Valor Estimado (R$)</label><input type="number" name="valor_estimado" id="edit_valor_estimado" class="form-control" step="0.01" min="0"></div><div class="mb-3"><label class="form-label">Link de Referência (Opcional)</label><input type="url" name="link_referencia" id="edit_link_referencia" class="form-control"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar Alterações</button></div></form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
    const modalNovaCompra = new bootstrap.Modal(document.getElementById('modalNovaCompra'));
    const formNovaCompra = document.getElementById('formNovaCompra');
    const modalEditarCompraEl = document.getElementById('modalEditarCompra');
    const modalEditarCompra = new bootstrap.Modal(modalEditarCompraEl);
    const formEditarCompra = document.getElementById('formEditarCompra');
    const listaPlanejando = document.getElementById('lista-planejando');

    if (formNovaCompra) {
        formNovaCompra.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(formNovaCompra);
            const button = formNovaCompra.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            fetch('adicionar_compra_futura.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if (data.success) { showToast('Sucesso!', data.message); setTimeout(() => window.location.reload(), 1000); }
                else { showToast('Erro!', data.message, true); }
            }).finally(() => { button.disabled = false; button.innerHTML = 'Salvar'; modalNovaCompra.hide(); });
        });
    }
    
    if (formEditarCompra) {
        formEditarCompra.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(formEditarCompra);
            const button = formEditarCompra.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            fetch('editar_compra.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if(data.success) { showToast('Sucesso!', data.message); setTimeout(() => window.location.reload(), 1000); }
                else { showToast('Erro!', data.message, true); }
            }).finally(() => { button.disabled = false; button.innerHTML = 'Salvar Alterações'; modalEditarCompra.hide(); });
        });
    }

    if(listaPlanejando) {
        listaPlanejando.addEventListener('click', function(e) {
            const editButton = e.target.closest('.btn-editar-compra');
            const deleteButton = e.target.closest('.btn-excluir-compra');
            const concludeButton = e.target.closest('.btn-concluir-compra');

            if(editButton) {
                const compraData = JSON.parse(editButton.dataset.compra);
                document.getElementById('edit_compra_id').value = compraData.id;
                document.getElementById('edit_nome_item').value = compraData.nome_item;
                document.getElementById('edit_valor_estimado').value = compraData.valor_estimado;
                document.getElementById('edit_link_referencia').value = compraData.link_referencia;
                modalEditarCompra.show();
            }

            if(deleteButton) {
                const compraId = deleteButton.dataset.id;
                const compraNome = deleteButton.dataset.nome;
                Swal.fire({ title: 'Tem certeza?', text: `Excluir "${compraNome}" da sua lista?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sim, excluir!', cancelButtonText: 'Cancelar' })
                .then(result => {
                    if (result.isConfirmed) {
                        fetch('excluir_compra.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: compraId})})
                        .then(res => res.json()).then(data => {
                            if(data.success) {
                                showToast('Sucesso!', data.message);
                                const card = document.getElementById(`compra-card-${compraId}`);
                                if(card) gsap.to(card, {duration: 0.5, opacity: 0, onComplete: () => card.remove()});
                            } else { showToast('Erro!', data.message, true); }
                        });
                    }
                });
            }

            if(concludeButton) {
                const compraId = concludeButton.dataset.id;
                const compraNome = concludeButton.dataset.nome;
                Swal.fire({ title: 'Meta Concluída?', text: `Marcar "${compraNome}" como comprado?`, icon: 'question', showCancelButton: true, confirmButtonColor: '#198754', confirmButtonText: 'Sim, comprei!', cancelButtonText: 'Ainda não' })
                .then(result => {
                    if (result.isConfirmed) {
                        fetch('concluir_compra.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: compraId})})
                        .then(res => res.json()).then(data => {
                            if(data.success) {
                                showToast('Parabéns!', data.message);
                                setTimeout(() => window.location.reload(), 1000);
                            } else { showToast('Erro!', data.message, true); }
                        });
                    }
                });
            }
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>