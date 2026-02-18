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

<style>
/* =========================================== */
/* DESIGN MODERNO - EXTRATO COMPLETO */
/* =========================================== */

.extrato-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}

.extrato-page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.extrato-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.extrato-actions .btn {
    border-radius: 10px;
    padding: 0.6rem 1rem;
    font-weight: 500;
    font-size: 0.9rem;
}

.extrato-card {
    border-radius: 20px !important;
    overflow: hidden !important;
    border: none !important;
    background: rgba(20, 20, 25, 0.9) !important;
    backdrop-filter: blur(20px) !important;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4) !important;
}

.extrato-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(48, 43, 99, 0.1) 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.extrato-titulo {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    display: flex;
    align-items: center;
}

.extrato-titulo i {
    color: var(--accent-red);
}

.extrato-contador {
    background: rgba(255, 255, 255, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
}

.extrato-body {
    padding: 1rem !important;
    overflow-x: hidden !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

.extrato-lista {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
    box-sizing: border-box !important;
}

/* Item de extrato */
.extrato-item {
    display: flex;
    align-items: stretch;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.06);
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

.extrato-item:hover {
    background: rgba(255, 255, 255, 0.06);
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

/* Indicador lateral colorido */
.extrato-indicador {
    width: 4px;
    flex-shrink: 0;
}

.extrato-item.receita .extrato-indicador {
    background: linear-gradient(180deg, #00b894 0%, #00a885 100%);
}

.extrato-item.despesa .extrato-indicador {
    background: linear-gradient(180deg, #e50914 0%, #c4080f 100%);
}

/* Conteúdo do extrato */
.extrato-conteudo {
    flex: 1;
    padding: 1rem 1.25rem;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}

.extrato-principal {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.extrato-descricao {
    font-size: 1rem;
    font-weight: 500;
    color: #fff;
    flex: 1;
    min-width: 0;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.extrato-valor {
    font-family: 'Roboto Mono', monospace;
    font-size: 1.1rem;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
}

.extrato-valor.receita {
    color: #00b894;
}

.extrato-valor.despesa {
    color: #e50914;
}

.extrato-detalhes {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.extrato-data {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.extrato-data i {
    font-size: 0.75rem;
}

.extrato-categoria {
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
}

/* Botões de ação */
.extrato-acoes {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0 1rem;
    border-left: 1px solid rgba(255, 255, 255, 0.06);
}

.extrato-acoes .btn {
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.extrato-acoes .btn:hover {
    transform: scale(1.1);
}

.extrato-acoes .btn-editar {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.3);
    color: #60a5fa;
}

.extrato-acoes .btn-editar:hover {
    background: rgba(59, 130, 246, 0.25);
    border-color: #60a5fa;
}

.extrato-acoes .btn-excluir {
    background: rgba(229, 9, 20, 0.15);
    border-color: rgba(229, 9, 20, 0.3);
    color: #f87171;
}

.extrato-acoes .btn-excluir:hover {
    background: rgba(229, 9, 20, 0.25);
    border-color: #f87171;
}

/* Estado vazio */
.extrato-vazio {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255, 255, 255, 0.4);
}

.extrato-vazio i {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

.extrato-vazio p {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: rgba(255, 255, 255, 0.6);
}

.extrato-vazio span {
    font-size: 0.9rem;
}

/* =========================================== */
/* RESPONSIVO - MOBILE */
/* =========================================== */
@media (max-width: 767.98px) {
    .extrato-page-header {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    
    .extrato-actions {
        justify-content: center;
    }
    
    .extrato-actions .btn {
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
    }
    
    .extrato-header {
        flex-direction: column;
        gap: 1rem;
        padding: 1.25rem;
        text-align: center;
    }
    
    .extrato-titulo {
        font-size: 1.1rem;
    }
    
    .extrato-body {
        padding: 0.75rem !important;
    }
    
    .extrato-item {
        flex-direction: column;
    }
    
    .extrato-indicador {
        width: 100%;
        height: 4px;
    }
    
    .extrato-conteudo {
        padding: 1rem;
    }
    
    .extrato-principal {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .extrato-descricao {
        font-size: 0.95rem;
    }
    
    .extrato-valor {
        font-size: 1.25rem;
    }
    
    .extrato-detalhes {
        gap: 0.75rem;
    }
    
    .extrato-acoes {
        border-left: none;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        padding: 1rem;
        justify-content: flex-end;
    }
}
</style>

<div class="extrato-page-header">
    <h1 class="extrato-page-title">Extrato Detalhado</h1>
    <div class="extrato-actions">
        <a href="importar_extrato_pdf.php" class="btn btn-primary">
            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
        <a href="importar_extrato_csv.php" class="btn btn-success">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
        </a>
        <a href="exportar_csv.php" id="btnExportarCsv" class="btn btn-outline-success">
            <i class="bi bi-download me-1"></i>Exportar
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<div class="card extrato-card" data-aos="fade-up">
    <div class="extrato-header">
        <h4 class="extrato-titulo"><i class="bi bi-list-ul me-2"></i>Todas as Transações</h4>
        <span class="extrato-contador"><?php echo count($transacoes); ?> itens</span>
    </div>
    <div class="card-body extrato-body">
        <div id="tabela-extrato-completo" class="extrato-lista">
            <?php if (empty($transacoes)): ?>
                <div class="extrato-vazio">
                    <i class="bi bi-inbox"></i>
                    <p>Nenhuma transação encontrada</p>
                    <span>Adicione suas primeiras transações</span>
                </div>
            <?php else: ?>
                <?php foreach ($transacoes as $t): ?>
                    <div class="extrato-item <?php echo $t['tipo']; ?>" id="transacao-row-<?php echo $t['id']; ?>">
                        <div class="extrato-indicador"></div>
                        <div class="extrato-conteudo">
                            <div class="extrato-principal">
                                <span class="extrato-descricao"><?php echo htmlspecialchars($t['descricao']); ?></span>
                                <span class="extrato-valor <?php echo ($t['tipo'] == 'receita') ? 'receita' : 'despesa'; ?>">
                                    <?php echo ($t['tipo'] == 'receita' ? '+' : '-'); ?> R$ <?php echo number_format($t['valor'], 2, ',', '.'); ?>
                                </span>
                            </div>
                            <div class="extrato-detalhes">
                                <span class="extrato-data"><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($t['data_transacao'])); ?></span>
                                <span class="extrato-categoria"><?php echo htmlspecialchars($t['nome_categoria'] ?? 'Sem Categoria'); ?></span>
                            </div>
                        </div>
                        <div class="extrato-acoes">
                            <button class="btn btn-editar btn-editar-transacao" data-id="<?php echo $t['id']; ?>" data-bs-toggle="modal" data-bs-target="#modalEditarTransacao" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-excluir btn-excluir-transacao" data-id="<?php echo $t['id']; ?>" data-nome="<?php echo htmlspecialchars($t['descricao']); ?>" title="Excluir">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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

    const extratoLista = document.getElementById('tabela-extrato-completo');
    const modalEditarTransacaoEl = document.getElementById('modalEditarTransacao');
    const modalEditarTransacao = new bootstrap.Modal(modalEditarTransacaoEl);
    const corpoModalEditar = document.getElementById('corpoModalEditar');
    const formEditarTransacao = document.getElementById('formEditarTransacao');

    // --- LÓGICA PARA EXCLUIR E EDITAR (DELEGAÇÃO DE EVENTOS) ---
    if (extratoLista) {
        extratoLista.addEventListener('click', function(event) {
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