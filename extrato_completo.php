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
        <div class="btn-group me-2" role="group">
            <a href="importar_extrato_pdf.php" class="btn btn-primary">
                <i class="bi bi-file-earmark-pdf me-1"></i>PDF
            </a>
            <a href="importar_extrato_csv.php" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
            </a>
        </div>
        <a href="exportar_csv.php" id="btnExportarCsv" class="btn btn-outline-success me-2">
            <i class="bi bi-download me-1"></i>Exportar
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>
</div>

<div class="card card-custom" data-aos="fade-up" style="overflow-x: hidden; width: 100%; max-width: 100%; box-sizing: border-box;">
    <div class="card-body" style="overflow-x: hidden !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 1rem !important;">
        <div class="table-responsive" id="tabela-extrato-completo" style="overflow-x: hidden !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important;">
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
                                <td data-label="Data"><?php echo date('d/m/Y', strtotime($t['data_transacao'])); ?></td>
                                <td data-label="Descrição"><?php echo htmlspecialchars($t['descricao']); ?></td>
                                <td data-label="Categoria"><span class="badge bg-secondary"><?php echo htmlspecialchars($t['nome_categoria'] ?? 'Sem Categoria'); ?></span></td>
                                <td data-label="Valor" class="text-end fw-bold font-monospace <?php echo ($t['tipo'] == 'receita') ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($t['tipo'] == 'receita' ? '+' : '-'); ?> R$ <?php echo number_format($t['valor'], 2, ',', '.'); ?>
                                </td>
                                <td data-label="Ações" class="text-center">
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
        
        <style>
        /* =========================================== */
        /* SOBRESCREVER CSS GLOBAL - TABELA EXTRATO */
        /* =========================================== */
        
        /* Forçar largura máxima e sem overflow no container específico */
        #tabela-extrato-completo {
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
            overflow-x: hidden !important;
            overflow-y: visible !important;
            box-sizing: border-box !important;
        }
        
        /* Desktop: permite scroll horizontal se necessário */
        @media (min-width: 768px) {
            #tabela-extrato-completo {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        /* =========================================== */
        /* ESTILOS DESKTOP - TABELA ULTRA MODERNA */
        /* =========================================== */
        
        /* Sobrescrever min-width global que força 600px */
        #tabela-extrato-completo .table {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            margin-bottom: 0;
            color: var(--text-primary);
            border-collapse: separate;
            border-spacing: 0 0.5rem;
            background: transparent;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        /* Cabeçalho premium com design elegante */
        #tabela-extrato-completo .table thead {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.15) 0%, rgba(48, 43, 99, 0.15) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 16px 16px 0 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        #tabela-extrato-completo .table thead th {
            border: none;
            border-bottom: 3px solid var(--accent-red);
            color: #ffffff;
            font-weight: 700;
            padding: 1.5rem 1.25rem;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1.2px;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        #tabela-extrato-completo .table thead th:first-child {
            border-top-left-radius: 16px;
            padding-left: 1.5rem;
        }
        
        #tabela-extrato-completo .table thead th:last-child {
            border-top-right-radius: 16px;
            padding-right: 1.5rem;
        }
        
        /* Linhas da tabela com design premium */
        #tabela-extrato-completo .table tbody {
            display: table-row-group;
        }
        
        #tabela-extrato-completo .table tbody tr {
            display: table-row;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        #tabela-extrato-completo .table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }
        
        #tabela-extrato-completo .table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }
        
        #tabela-extrato-completo .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.12) 0%, rgba(229, 9, 20, 0.06) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(229, 9, 20, 0.3), 0 4px 12px rgba(0, 0, 0, 0.2);
            border-left: 4px solid var(--accent-red);
        }
        
        /* Células premium com tipografia melhorada */
        #tabela-extrato-completo .table tbody td {
            padding: 1.5rem 1.25rem;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            vertical-align: middle;
            display: table-cell;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.95);
            font-family: 'Poppins', sans-serif;
        }
        
        #tabela-extrato-completo .table tbody td:first-child {
            padding-left: 1.5rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
        }
        
        #tabela-extrato-completo .table tbody td:last-child {
            padding-right: 1.5rem;
        }
        
        /* Badge de categoria premium */
        #tabela-extrato-completo .table tbody td .badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        #tabela-extrato-completo .table tbody td .badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        /* Valores monetários premium */
        #tabela-extrato-completo .table tbody td.font-monospace {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            font-family: 'Roboto Mono', 'Courier New', monospace;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        /* Botões de ação */
        #tabela-extrato-completo .table tbody td .btn {
            margin: 0 0.25rem;
            transition: all 0.3s ease;
        }
        
        #tabela-extrato-completo .table tbody td .btn:hover {
            transform: scale(1.1);
        }
        
        /* Alternância de cores elegante */
        #tabela-extrato-completo .table tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.04);
        }
        
        #tabela-extrato-completo .table tbody tr:nth-child(even):hover {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.15) 0%, rgba(229, 9, 20, 0.08) 100%);
        }
        
        /* Descrições com melhor tipografia */
        #tabela-extrato-completo .table tbody td[data-label="Descrição"],
        #tabela-extrato-completo .table tbody td:nth-child(2) {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.05rem;
        }
        
        /* =========================================== */
        /* MOBILE - CARDS PREMIUM */
        /* =========================================== */
        @media (max-width: 767.98px) {
            /* SOBRESCREVER TODOS OS CSS GLOBAIS */
            #tabela-extrato-completo {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                overflow-x: hidden !important;
                overflow-y: visible !important;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                -webkit-overflow-scrolling: auto !important;
            }
            
            /* SOBRESCREVER min-width: 600px dos CSS globais */
            #tabela-extrato-completo .table {
                min-width: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                display: block !important;
                border-collapse: separate !important;
                border-spacing: 0 !important;
                overflow: visible !important;
            }
            
            /* Esconder cabeçalho */
            #tabela-extrato-completo .table thead {
                display: none !important;
            }
            
            /* Body como container */
            #tabela-extrato-completo .table tbody {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            /* Cards premium no mobile */
            #tabela-extrato-completo .table tbody tr {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                margin-bottom: 1.5rem !important;
                border: 1px solid rgba(255, 255, 255, 0.12) !important;
                border-left: 5px solid var(--accent-red) !important;
                border-radius: 16px !important;
                padding: 1.5rem !important;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%) !important;
                backdrop-filter: blur(10px) !important;
                -webkit-backdrop-filter: blur(10px) !important;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), 0 4px 12px rgba(229, 9, 20, 0.15) !important;
                box-sizing: border-box !important;
                overflow: visible !important;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            
            #tabela-extrato-completo .table tbody tr:active {
                transform: scale(0.97) !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
            }
            
            /* Células premium no mobile */
            #tabela-extrato-completo .table tbody td {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                padding: 1rem 0 !important;
                border: none !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
                box-sizing: border-box !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                word-break: break-word !important;
                overflow: hidden !important;
                hyphens: auto !important;
                font-family: 'Poppins', sans-serif !important;
            }
            
            #tabela-extrato-completo .table tbody tr td:first-child {
                padding-top: 0 !important;
                border-top: none !important;
            }
            
            #tabela-extrato-completo .table tbody td:last-child {
                border-bottom: none !important;
                padding-bottom: 0 !important;
            }
            
            /* Labels premium */
            #tabela-extrato-completo .table tbody td::before {
                content: attr(data-label);
                display: block !important;
                font-weight: 700 !important;
                font-size: 0.7rem !important;
                margin-bottom: 0.75rem !important;
                color: var(--accent-red) !important;
                text-transform: uppercase !important;
                letter-spacing: 1.2px !important;
                opacity: 1 !important;
                font-family: 'Poppins', sans-serif !important;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
            }
            
            /* Conteúdo abaixo */
            #tabela-extrato-completo .table tbody td > * {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                text-align: left !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                word-break: break-word !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                hyphens: auto !important;
            }
            
            /* Botões de ação no mobile */
            #tabela-extrato-completo .table tbody td[data-label="Ações"] {
                display: flex !important;
                gap: 0.5rem !important;
                justify-content: flex-start !important;
            }
            
            #tabela-extrato-completo .table tbody td[data-label="Ações"]::before {
                width: 100% !important;
                margin-bottom: 0.75rem !important;
            }
            
            #tabela-extrato-completo .table tbody td[data-label="Ações"] .btn {
                flex: 1 !important;
                max-width: 48% !important;
            }
            
            /* Badges premium */
            #tabela-extrato-completo .table tbody td .badge {
                display: inline-block !important;
                font-size: 0.7rem !important;
                padding: 0.5rem 1rem !important;
                white-space: normal !important;
                word-break: break-word !important;
                max-width: 100% !important;
                border-radius: 25px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.8px !important;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25) !important;
                font-family: 'Poppins', sans-serif !important;
                transition: all 0.3s ease !important;
            }
            
            /* Valores monetários premium */
            #tabela-extrato-completo .table tbody td.font-monospace {
                display: block !important;
                font-size: 1.3rem !important;
                font-weight: 800 !important;
                white-space: normal !important;
                word-break: break-word !important;
                letter-spacing: 0.8px !important;
                margin-top: 0.5rem !important;
                font-family: 'Roboto Mono', 'Courier New', monospace !important;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
            }
            
            /* Descrições premium */
            #tabela-extrato-completo .table tbody td[data-label="Descrição"],
            #tabela-extrato-completo .table tbody td:nth-child(2) {
                font-size: 1.1rem !important;
                line-height: 1.6 !important;
                font-weight: 500 !important;
                color: rgba(255, 255, 255, 0.95) !important;
                margin-top: 0.5rem !important;
            }
            
            /* Data com estilo premium */
            #tabela-extrato-completo .table tbody td[data-label="Data"] {
                font-weight: 500 !important;
                color: rgba(255, 255, 255, 0.8) !important;
                font-size: 0.95rem !important;
            }
            
            /* Forçar quebra em textos longos */
            #tabela-extrato-completo .table tbody td,
            #tabela-extrato-completo .table tbody td * {
                white-space: normal !important;
                overflow-wrap: anywhere !important;
            }
            
            /* Garantir que todos os elementos respeitem a largura */
            #tabela-extrato-completo * {
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
        }
        </style>
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
    
    // SOBRESCREVER CSS GLOBAL - Forçar estilos corretos
    function corrigirTabelaMobile() {
        const tabela = document.getElementById('tabela-extrato-completo');
        if (tabela && window.innerWidth <= 767.98) {
            // Forçar estilos no container
            tabela.style.overflowX = 'hidden';
            tabela.style.overflowY = 'visible';
            tabela.style.width = '100%';
            tabela.style.maxWidth = '100%';
            tabela.style.minWidth = '0';
            
            // Forçar estilos na tabela
            const tableEl = tabela.querySelector('.table');
            if (tableEl) {
                tableEl.style.minWidth = '0';
                tableEl.style.width = '100%';
                tableEl.style.maxWidth = '100%';
            }
            
            // Forçar estilos nas células
            const cells = tabela.querySelectorAll('tbody td');
            cells.forEach(cell => {
                cell.style.maxWidth = '100%';
                cell.style.wordWrap = 'break-word';
                cell.style.overflowWrap = 'break-word';
                cell.style.wordBreak = 'break-word';
                cell.style.overflow = 'hidden';
            });
        }
    }
    
    // Executar imediatamente
    corrigirTabelaMobile();
    
    // Executar após carregamento completo
    window.addEventListener('load', corrigirTabelaMobile);
    
    // Executar quando a tela redimensionar
    window.addEventListener('resize', corrigirTabelaMobile);
    
    // Executar após delays para garantir
    setTimeout(corrigirTabelaMobile, 100);
    setTimeout(corrigirTabelaMobile, 500);

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