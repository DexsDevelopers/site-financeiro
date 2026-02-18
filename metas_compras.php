<?php
// metas_compras.php (Versão Final com CRUD Completo)

require_once 'templates/header.php';

$metas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM metas_compra WHERE id_usuario = ? ORDER BY data_criacao DESC");
    $stmt->execute([$userId]);
    $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar metas: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Metas de Compras</h1>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaMeta"><i class="bi bi-plus-lg me-2"></i>Nova Meta</button>
</div>

<div class="row g-4" id="lista-metas">
    <?php if (empty($metas)): ?>
        <div class="col-12" id="empty-state-metas">
            <div class="card card-custom"><div class="card-body text-center p-5"><h5 class="text-muted">Nenhuma meta de compra criada.</h5><p class="text-muted mb-0">Defina seus objetivos financeiros para começar a poupar!</p></div></div>
        </div>
    <?php else: ?>
        <?php foreach($metas as $meta): 
            $progresso = ($meta['valor_total'] > 0) ? ($meta['valor_poupado'] / $meta['valor_total']) * 100 : 0;
        ?>
            <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" id="meta-card-<?php echo $meta['id']; ?>">
                <div class="card card-custom h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <h5 class="card-title"><?php echo htmlspecialchars($meta['nome_item']); ?></h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary border-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li><button class="dropdown-item btn-editar-meta" data-id="<?php echo $meta['id']; ?>" data-nome="<?php echo htmlspecialchars($meta['nome_item']); ?>" data-valor="<?php echo $meta['valor_total']; ?>"><i class="bi bi-pencil-fill me-2"></i>Editar</button></li>
                                    <li><button class="dropdown-item btn-excluir-meta" data-id="<?php echo $meta['id']; ?>" data-nome="<?php echo htmlspecialchars($meta['nome_item']); ?>"><i class="bi bi-trash-fill me-2"></i>Excluir</button></li>
                                </ul>
                            </div>
                        </div>
                        <div class="progress my-2" style="height: 20px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $progresso; ?>%;" aria-valuenow="<?php echo $progresso; ?>"><b><?php echo round($progresso); ?>%</b></div>
                        </div>
                        <div class="d-flex justify-content-between"><small class="text-muted">Poupado: <span class="valor-sensivel">R$ <?php echo number_format($meta['valor_poupado'], 2, ',', '.'); ?></span></small><small class="text-muted">Faltam: <span class="valor-sensivel">R$ <?php echo number_format($meta['valor_total'] - $meta['valor_poupado'], 2, ',', '.'); ?></span></small></div>
                        <div class="mt-auto pt-3 text-end"><button class="btn btn-sm btn-outline-success btn-adicionar-valor" data-bs-toggle="modal" data-bs-target="#modalAdicionarValor" data-meta-id="<?php echo $meta['id']; ?>" data-meta-nome="<?php echo htmlspecialchars($meta['nome_item']); ?>"><i class="bi bi-plus-circle-fill"></i> Adicionar Valor</button></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalNovaMeta" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Criar Nova Meta de Compra</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formNovaMeta" action="adicionar_meta.php" method="POST"><div class="modal-body"><div class="mb-3"><label for="nome_item" class="form-label">O que você quer comprar?</label><input type="text" name="nome_item" class="form-control" placeholder="Ex: Novo Celular" required></div><div class="mb-3"><label for="valor_total" class="form-label">Qual o valor total?</label><input type="number" name="valor_total" class="form-control" step="0.01" min="0" placeholder="Ex: 3500.00" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Criar Meta</button></div></form></div></div>
</div>

<div class="modal fade" id="modalAdicionarValor" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Adicionar Valor para: <span id="modalMetaNome"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formAdicionarValor" action="atualizar_meta.php" method="POST"><div class="modal-body"><input type="hidden" name="meta_id" id="modal_meta_id"><div class="mb-3"><label for="valor_adicionado" class="form-label">Valor a adicionar</label><input type="number" name="valor_adicionado" class="form-control" step="0.01" min="0" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Adicionar</button></div></form></div></div>
</div>

<div class="modal fade" id="modalEditarMeta" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar Meta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formEditarMeta" action="editar_meta.php" method="POST"><input type="hidden" name="id" id="edit_meta_id"><div class="modal-body"><div class="mb-3"><label for="edit_nome_item" class="form-label">O que você quer comprar?</label><input type="text" name="nome_item" id="edit_nome_item" class="form-control" required></div><div class="mb-3"><label for="edit_valor_total" class="form-label">Qual o valor total?</label><input type="number" name="valor_total" id="edit_valor_total" class="form-control" step="0.01" min="0" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar Alterações</button></div></form></div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
    const modalNovaMeta = new bootstrap.Modal(document.getElementById('modalNovaMeta'));
    const formNovaMeta = document.getElementById('formNovaMeta');
    const modalAdicionarValorEl = document.getElementById('modalAdicionarValor');
    const modalAdicionarValor = new bootstrap.Modal(modalAdicionarValorEl);
    const formAdicionarValor = document.getElementById('formAdicionarValor');
    const modalEditarMetaEl = document.getElementById('modalEditarMeta');
    const modalEditarMeta = new bootstrap.Modal(modalEditarMetaEl);
    const formEditarMeta = document.getElementById('formEditarMeta');

    if (formNovaMeta) {
        formNovaMeta.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formNovaMeta);
            const button = formNovaMeta.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Criando...';
            fetch('adicionar_meta.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = 'Criar Meta';
                modalNovaMeta.hide();
            });
        });
    }

    if (modalAdicionarValorEl) {
        modalAdicionarValorEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const metaId = button.getAttribute('data-meta-id');
            const metaNome = button.getAttribute('data-meta-nome');
            modalAdicionarValorEl.querySelector('#modalMetaNome').textContent = metaNome;
            modalAdicionarValorEl.querySelector('#modal_meta_id').value = metaId;
        });
    }

    if (formAdicionarValor) {
        formAdicionarValor.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formAdicionarValor);
            const button = formAdicionarValor.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adicionando...';
            fetch('atualizar_meta.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    const meta = data.meta;
                    const card = document.getElementById(`meta-card-${meta.id}`);
                    if (card) {
                        const progresso = (meta.valor_total > 0) ? (meta.valor_poupado / meta.valor_total) * 100 : 0;
                        const progressBar = card.querySelector('.progress-bar');
                        const poupadoEl = card.querySelector('.d-flex.justify-content-between small:first-child .valor-sensivel');
                        const faltamEl = card.querySelector('.d-flex.justify-content-between small:last-child .valor-sensivel');
                        
                        progressBar.style.width = progresso + '%';
                        progressBar.setAttribute('aria-valuenow', progresso);
                        progressBar.querySelector('b').textContent = Math.round(progresso) + '%';
                        
                        poupadoEl.textContent = 'R$ ' + meta.valor_poupado.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                        faltamEl.textContent = 'R$ ' + (meta.valor_total - meta.valor_poupado).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    }
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = 'Adicionar';
                modalAdicionarValor.hide();
            });
        });
    }

    document.getElementById('lista-metas').addEventListener('click', function(event) {
        const editButton = event.target.closest('.btn-editar-meta');
        const deleteButton = event.target.closest('.btn-excluir-meta');
        if (editButton) {
            document.getElementById('edit_meta_id').value = editButton.dataset.id;
            document.getElementById('edit_nome_item').value = editButton.dataset.nome;
            document.getElementById('edit_valor_total').value = editButton.dataset.valor;
            modalEditarMeta.show();
        }
        if (deleteButton) {
            const metaId = deleteButton.dataset.id;
            const metaNome = deleteButton.dataset.nome;
            Swal.fire({
                title: 'Tem certeza?', text: `Excluir a meta "${metaNome}"?`, icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir!', cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('excluir_meta.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: metaId})})
                    .then(res => res.json()).then(data => {
                        if(data.success) {
                            showToast('Sucesso!', data.message);
                            const card = document.getElementById(`meta-card-${metaId}`);
                            if(card) gsap.to(card, {duration: 0.5, opacity: 0, scale: 0.9, onComplete: () => card.remove()});
                        } else {
                            showToast('Erro!', data.message, true);
                        }
                    });
                }
            });
        }
    });
    
    if(formEditarMeta) {
        formEditarMeta.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarMeta);
            const button = formEditarMeta.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            fetch('editar_meta.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if(data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            }).finally(() => {
                button.disabled = false; button.innerHTML = 'Salvar Alterações';
                modalEditarMeta.hide();
            });
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>