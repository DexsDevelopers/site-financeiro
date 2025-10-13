<?php
// extrato_completo.php (Versão Moderna com AJAX para Edição e Exclusão)

require_once 'templates/header.php';
require_once 'includes/db_connect.php';

try {
    // Busca todas as transações do usuário, com nome da categoria
    $stmt = $pdo->prepare(
        "SELECT t.id, t.descricao, t.valor, t.tipo, t.data_transacao, c.nome as nome_categoria 
         FROM transacoes t 
         LEFT JOIN categorias c ON t.id_categoria = c.id 
         WHERE t.id_usuario = ? 
         ORDER BY t.data_transacao DESC, t.id DESC"
    );
    $stmt->execute([$userId]);
    $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar extrato: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="h2 mb-0">Extrato Detalhado</h1>
    <div>
        <a href="exportar_csv.php" id="btnExportarCsv" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet-fill me-2"></i>Exportar</a>
        <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
    </div>
</div>

<div class="card card-custom" data-aos="fade-up">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th class="text-end">Valor (R$)</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabela-extrato-corpo">
                    <?php if (empty($transacoes)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma transação encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transacoes as $t): ?>
                            <tr id="transacao-row-<?php echo $t['id']; ?>">
                                <td><?php echo date('d/m/Y', strtotime($t['data_transacao'])); ?></td>
                                <td><?php echo htmlspecialchars($t['descricao']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($t['nome_categoria'] ?? 'Sem Categoria'); ?></span></td>
                                <td class="text-end fw-bold font-monospace <?php echo ($t['tipo'] == 'receita') ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($t['tipo'] == 'receita' ? '+' : '-'); ?> R$ <?php echo number_format($t['valor'], 2, ',', '.'); ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary btn-editar-transacao" data-id="<?php echo $t['id']; ?>" data-bs-toggle="modal" data-bs-target="#modalEditarTransacao" title="Editar">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btn-excluir-transacao" data-id="<?php echo $t['id']; ?>" data-nome="<?php echo htmlspecialchars($t['descricao']); ?>" title="Excluir">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarTransacao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Editar Lançamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="formEditarTransacao" action="atualizar_transacao.php" method="POST">
                <div class="modal-body" id="corpoModalEditar">
                    <div class="text-center p-5"><div class="spinner-border text-danger"></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 800, once: true });

    const tabelaCorpo = document.getElementById('tabela-extrato-corpo');
    const modalEditarTransacaoEl = document.getElementById('modalEditarTransacao');
    const modalEditarTransacao = new bootstrap.Modal(modalEditarTransacaoEl);
    const corpoModalEditar = document.getElementById('corpoModalEditar');
    const formEditarTransacao = document.getElementById('formEditarTransacao');

    // --- LÓGICA PARA EXCLUIR E EDITAR (DELEGAÇÃO DE EVENTOS) ---
    if (tabelaCorpo) {
        tabelaCorpo.addEventListener('click', function(event) {
            const target = event.target;
            const deleteButton = target.closest('.btn-excluir-transacao');
            
            // --- AÇÃO DE EXCLUIR ---
            if (deleteButton) {
                const transacaoId = deleteButton.dataset.id;
                const transacaoNome = deleteButton.dataset.nome;

                Swal.fire({
                    title: 'Tem certeza?',
                    text: `Excluir o lançamento "${transacaoNome}"? Esta ação não pode ser desfeita.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sim, excluir!',
                    cancelButtonText: 'Cancelar',
                    background: '#222',
                    color: '#fff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('excluir_transacao.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: transacaoId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('Sucesso!', data.message);
                                const rowToRemove = document.getElementById(`transacao-row-${transacaoId}`);
                                if (rowToRemove) {
                                    gsap.to(rowToRemove, { duration: 0.5, opacity: 0, x: -50, onComplete: () => rowToRemove.remove() });
                                }
                            } else {
                                showToast('Erro!', data.message, true);
                            }
                        })
                        .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true));
                    }
                });
            }
        });
    }

    // --- LÓGICA PARA PREENCHER O MODAL DE EDIÇÃO ---
    if (modalEditarTransacaoEl) {
        modalEditarTransacaoEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const transacaoId = button.dataset.id;
            corpoModalEditar.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-danger"></div></div>';

            fetch(`buscar_transacao_detalhes.php?id=${transacaoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const transacao = data.transacao;
                        const categorias = data.categorias;
                        
                        let optionsHtml = '<option value="">Selecione...</option><optgroup label="Despesas">';
                        categorias.forEach(cat => {
                            if (cat.tipo === 'despesa') {
                                const selected = cat.id == transacao.id_categoria ? 'selected' : '';
                                optionsHtml += `<option value="${cat.id}" ${selected}>${escapeHTML(cat.nome)}</option>`;
                            }
                        });
                        optionsHtml += '</optgroup><optgroup label="Receitas">';
                        categorias.forEach(cat => {
                            if (cat.tipo === 'receita') {
                                const selected = cat.id == transacao.id_categoria ? 'selected' : '';
                                optionsHtml += `<option value="${cat.id}" ${selected}>${escapeHTML(cat.nome)}</option>`;
                            }
                        });
                        optionsHtml += '</optgroup>';

                        corpoModalEditar.innerHTML = `
                            <input type="hidden" name="id" value="${transacao.id}">
                            <div class="mb-3"><label class="form-label">Descrição</label><input type="text" name="descricao" class="form-control" value="${escapeHTML(transacao.descricao)}" required></div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">Valor (R$)</label><input type="number" name="valor" class="form-control" step="0.01" min="0" value="${transacao.valor}" required></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Data</label><input type="date" name="data_transacao" class="form-control" value="${transacao.data_transacao.split(' ')[0]}" required></div>
                            </div>
                            <div class="mb-3"><label class="form-label">Categoria</label><select class="form-select" name="id_categoria" required>${optionsHtml}</select></div>`;
                    } else { corpoModalEditar.innerHTML = `<p class="text-danger">${data.message}</p>`; }
                }).catch(err => { corpoModalEditar.innerHTML = `<p class="text-danger">Erro de rede ao buscar dados.</p>`; });
        });
    }

    // --- LÓGICA PARA SALVAR A EDIÇÃO ---
    if (formEditarTransacao) {
        formEditarTransacao.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarTransacao);
            const button = formEditarTransacao.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

            fetch('atualizar_transacao.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        setTimeout(() => window.location.reload(), 1000); // Recarrega para ver as mudanças
                    } else {
                        showToast('Erro!', data.message, true);
                    }
                })
                .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true))
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = 'Salvar Alterações';
                    modalEditarTransacao.hide();
                });
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>