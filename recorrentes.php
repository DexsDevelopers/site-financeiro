<?php
// recorrentes.php (Versão Final com Cards, AJAX e CRUD Completo)

require_once 'templates/header.php';

$recorrentes = [];
$lista_categorias = [];
try {
    $sql_recorrentes = "SELECT tr.*, c.nome as nome_categoria, c.tipo as tipo_categoria
                        FROM transacoes_recorrentes tr
                        JOIN categorias c ON tr.id_categoria = c.id
                        WHERE tr.id_usuario = ?
                        ORDER BY tr.dia_execucao ASC, tr.descricao ASC";
    $stmt_recorrentes = $pdo->prepare($sql_recorrentes);
    $stmt_recorrentes->execute([$userId]);
    $recorrentes = $stmt_recorrentes->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_cats = $pdo->prepare("SELECT id, nome, tipo FROM categorias WHERE id_usuario = ? ORDER BY tipo, nome");
    $stmt_cats->execute([$userId]);
    $lista_categorias = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Transações Recorrentes</h1>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaRecorrente">
        <i class="bi bi-plus-lg me-2"></i>Nova Recorrência
    </button>
</div>

<div class="row g-3" id="lista-recorrentes">
    <?php if (empty($recorrentes)): ?>
        <div class="col-12" id="no-recorrentes-row">
            <div class="card card-custom">
                <div class="card-body text-center p-5">
                    <h5 class="text-muted">Nenhuma transação recorrente cadastrada.</h5>
                    <p class="text-muted mb-0">Adicione suas despesas e receitas fixas para automatizar seus lançamentos.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach($recorrentes as $rec): ?>
            <div class="col-12" id="recorrente-row-<?php echo $rec['id']; ?>" data-aos="fade-up">
                <div class="card card-custom">
                    <div class="card-body p-3">
                        <div class="row align-items-center g-2">
                            <div class="col">
                                <h5 class="mb-0"><?php echo htmlspecialchars($rec['descricao']); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($rec['nome_categoria']); ?></small>
                            </div>
                            <div class="col-12 col-sm-auto text-sm-end">
                                <span class="fw-bold fs-5 <?php echo ($rec['tipo_categoria'] == 'receita') ? 'text-success' : 'text-danger'; ?>" style="font-family: 'Roboto Mono', monospace;">
                                    R$ <?php echo number_format($rec['valor'], 2, ',', '.'); ?>
                                </span>
                            </div>
                            <div class="col-12 col-sm-auto text-sm-center text-muted">
                                <i class="bi bi-calendar-event"></i> Todo dia <?php echo $rec['dia_execucao']; ?>
                            </div>
                            <div class="col-12 col-sm-auto text-sm-end">
                                <button class="btn btn-sm btn-outline-primary btn-editar-recorrente" data-bs-toggle="modal" data-bs-target="#modalEditarRecorrente" data-recorrente='<?php echo json_encode($rec, JSON_HEX_QUOT | JSON_HEX_TAG); ?>'><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-excluir-recorrente" data-id="<?php echo $rec['id']; ?>" data-nome="<?php echo htmlspecialchars($rec['descricao']); ?>"><i class="bi bi-trash-fill"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


<div class="modal fade" id="modalNovaRecorrente" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Nova Transação Recorrente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formNovaRecorrente"><div class="modal-body"><div class="mb-3"><label class="form-label">Descrição</label><input type="text" name="descricao" class="form-control" required></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Valor (R$)</label><input type="number" name="valor" class="form-control" step="0.01" min="0" required></div><div class="col-md-6 mb-3"><label class="form-label">Dia do Mês</label><input type="number" name="dia_execucao" class="form-control" min="1" max="31" required></div></div><div class="mb-3"><label class="form-label">Categoria</label><select class="form-select" name="id_categoria" required><option value="">Selecione...</option><optgroup label="Despesas"><?php foreach($lista_categorias as $cat): if($cat['tipo'] == 'despesa'): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option><?php endif; endforeach; ?></optgroup><optgroup label="Receitas"><?php foreach($lista_categorias as $cat): if($cat['tipo'] == 'receita'): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option><?php endif; endforeach; ?></optgroup></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar</button></div></form></div></div></div>
<div class="modal fade" id="modalEditarRecorrente" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar Transação Recorrente</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formEditarRecorrente"><input type="hidden" name="id" id="edit-rec-id"><div class="modal-body"><div class="mb-3"><label class="form-label">Descrição</label><input type="text" name="descricao" id="edit-rec-descricao" class="form-control" required></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Valor (R$)</label><input type="number" name="valor" id="edit-rec-valor" class="form-control" step="0.01" min="0" required></div><div class="col-md-6 mb-3"><label class="form-label">Dia do Mês</label><input type="number" name="dia_execucao" id="edit-rec-dia" class="form-control" min="1" max="31" required></div></div><div class="mb-3"><label class="form-label">Categoria</label><select class="form-select" name="id_categoria" id="edit-rec-categoria" required><option value="">Selecione...</option><optgroup label="Despesas"><?php foreach($lista_categorias as $cat): if($cat['tipo'] == 'despesa'): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option><?php endif; endforeach; ?></optgroup><optgroup label="Receitas"><?php foreach($lista_categorias as $cat): if($cat['tipo'] == 'receita'): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option><?php endif; endforeach; ?></optgroup></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar Alterações</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
    const modalNovaRecorrente = new bootstrap.Modal(document.getElementById('modalNovaRecorrente'));
    const formNovaRecorrente = document.getElementById('formNovaRecorrente');
    const modalEditarRecorrenteEl = document.getElementById('modalEditarRecorrente');
    const modalEditarRecorrente = new bootstrap.Modal(modalEditarRecorrenteEl);
    const formEditarRecorrente = document.getElementById('formEditarRecorrente');

    if (formNovaRecorrente) {
        formNovaRecorrente.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formNovaRecorrente);
            const button = formNovaRecorrente.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Salvando...`;
            fetch('adicionar_recorrente.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) { showToast('Sucesso!', data.message); modalNovaRecorrente.hide(); setTimeout(() => window.location.reload(), 1000); } 
                else { showToast('Erro!', data.message, true); }
            }).catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true)).finally(() => { button.disabled = false; button.innerHTML = 'Salvar'; });
        });
    }

    if (modalEditarRecorrenteEl) {
        modalEditarRecorrenteEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const recorrenteData = JSON.parse(button.dataset.recorrente);
            document.getElementById('edit-rec-id').value = recorrenteData.id;
            document.getElementById('edit-rec-descricao').value = recorrenteData.descricao;
            document.getElementById('edit-rec-valor').value = recorrenteData.valor;
            document.getElementById('edit-rec-dia').value = recorrenteData.dia_execucao;
            document.getElementById('edit-rec-categoria').value = recorrenteData.id_categoria;
        });
    }
    
    if (formEditarRecorrente) {
        formEditarRecorrente.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarRecorrente);
            const button = formEditarRecorrente.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Salvando...`;
            fetch('editar_recorrente.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) { showToast('Sucesso!', data.message); modalEditarRecorrente.hide(); setTimeout(() => window.location.reload(), 1000); } 
                else { showToast('Erro!', data.message, true); }
            }).catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true)).finally(() => { button.disabled = false; button.innerHTML = 'Salvar Alterações'; });
        });
    }
    
    document.getElementById('lista-recorrentes').addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.btn-excluir-recorrente');
        if (deleteButton) {
            const recId = deleteButton.dataset.id;
            const recNome = deleteButton.dataset.nome;
            Swal.fire({ title: 'Tem certeza?', text: `Excluir a recorrência "${recNome}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d', confirmButtonText: 'Sim, excluir!', cancelButtonText: 'Cancelar' }).then((result) => {
                if (result.isConfirmed) {
                    fetch('excluir_recorrente.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: recId})})
                    .then(res => res.json()).then(data => {
                        if(data.success) {
                            showToast('Sucesso!', data.message);
                            const row = document.getElementById(`recorrente-row-${recId}`);
                            if(row) gsap.to(row, {duration: 0.5, opacity: 0, onComplete: () => row.remove()});
                        } else { showToast('Erro!', data.message, true); }
                    });
                }
            });
        }
    });
});
</script>

<?php
require_once 'templates/footer.php';
?>