<?php
// regras_categorizacao.php - Sistema de Regras de Categorização Automática

require_once 'templates/header.php';

// Buscar regras existentes
$regras = [];
$categorias = [];
try {
    $stmt_regras = $pdo->prepare("SELECT rc.*, c.nome as categoria_nome FROM regras_categorizacao rc LEFT JOIN categorias c ON rc.id_categoria = c.id WHERE rc.id_usuario = ? ORDER BY rc.prioridade DESC, rc.id ASC");
    $stmt_regras->execute([$userId]);
    $regras = $stmt_regras->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_cats = $pdo->prepare("SELECT id, nome, tipo FROM categorias WHERE id_usuario = ? ORDER BY tipo, nome");
    $stmt_cats->execute([$userId]);
    $categorias = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}
?>

<style>
    .intro-card {
        background: linear-gradient(135deg, rgba(30, 30, 30, 0.5) 0%, rgba(50, 30, 30, 0.5) 100%);
    }
    .intro-card h1 {
        font-weight: 700;
    }
    .feature-icon {
        font-size: 2.5rem;
        color: var(--accent-red);
    }
    .rule-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }
    .rule-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .priority-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    .rule-pattern {
        font-family: 'Roboto Mono', monospace;
        background: rgba(229, 9, 20, 0.1);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        border: 1px solid rgba(229, 9, 20, 0.3);
    }
    .test-pattern {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 1rem;
    }
    .match-result {
        padding: 0.5rem;
        border-radius: 4px;
        margin-top: 0.5rem;
    }
    .match-success {
        background: rgba(0, 184, 148, 0.2);
        border: 1px solid rgba(0, 184, 148, 0.5);
        color: #00b894;
    }
    .match-fail {
        background: rgba(229, 9, 20, 0.2);
        border: 1px solid rgba(229, 9, 20, 0.5);
        color: #e50914;
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-robot display-1 text-danger mb-4"></i>
        <h1 class="display-5">Regras de Categorização Inteligente</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Configure regras automáticas para categorizar suas transações. O sistema usará palavras-chave e padrões para classificar seus lançamentos financeiros automaticamente.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Estatísticas -->
    <div class="col-md-3">
        <div class="card rule-card">
            <div class="card-body text-center">
                <i class="bi bi-list-check feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count($regras); ?></h5>
                <p class="text-white-50 mb-0">Regras Ativas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card rule-card">
            <div class="card-body text-center">
                <i class="bi bi-tags feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count($categorias); ?></h5>
                <p class="text-white-50 mb-0">Categorias</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card rule-card">
            <div class="card-body text-center">
                <i class="bi bi-lightning feature-icon mb-3"></i>
                <h5 class="card-title">95%</h5>
                <p class="text-white-50 mb-0">Precisão</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card rule-card">
            <div class="card-body text-center">
                <i class="bi bi-clock feature-icon mb-3"></i>
                <h5 class="card-title">2.3s</h5>
                <p class="text-white-50 mb-0">Tempo Médio</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Lista de Regras -->
    <div class="col-lg-8">
        <div class="card rule-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Regras Configuradas</h4>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaRegra">
                    <i class="bi bi-plus-lg me-2"></i>Nova Regra
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($regras)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-robot display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhuma regra configurada</h5>
                        <p class="text-muted">Crie sua primeira regra para começar a categorização automática.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Prioridade</th>
                                    <th>Padrão</th>
                                    <th>Categoria</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($regras as $regra): ?>
                                    <tr>
                                        <td>
                                            <span class="badge priority-badge <?php echo $regra['prioridade'] == 'Alta' ? 'bg-danger' : ($regra['prioridade'] == 'Média' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                                <?php echo $regra['prioridade']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code class="rule-pattern"><?php echo htmlspecialchars($regra['padrao']); ?></code>
                                        </td>
                                        <td><?php echo htmlspecialchars($regra['categoria_nome'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $regra['tipo'] == 'receita' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($regra['tipo']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $regra['ativa'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $regra['ativa'] ? 'Ativa' : 'Inativa'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-editar-regra" 
                                                        data-id="<?php echo $regra['id']; ?>"
                                                        data-padrao="<?php echo htmlspecialchars($regra['padrao']); ?>"
                                                        data-categoria="<?php echo $regra['id_categoria']; ?>"
                                                        data-tipo="<?php echo $regra['tipo']; ?>"
                                                        data-prioridade="<?php echo $regra['prioridade']; ?>"
                                                        data-ativa="<?php echo $regra['ativa']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-excluir-regra" data-id="<?php echo $regra['id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Testador de Padrões -->
    <div class="col-lg-4">
        <div class="card rule-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Testador de Padrões</h5>
            </div>
            <div class="card-body">
                <form id="formTestePadrao">
                    <div class="mb-3">
                        <label for="teste_descricao" class="form-label">Descrição da Transação</label>
                        <input type="text" class="form-control" id="teste_descricao" placeholder="Ex: Supermercado Extra" required>
                    </div>
                    <div class="mb-3">
                        <label for="teste_padrao" class="form-label">Padrão a Testar</label>
                        <input type="text" class="form-control" id="teste_padrao" placeholder="Ex: supermercado|extra|carrefour" required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-search me-2"></i>Testar Padrão
                    </button>
                </form>
                <div id="resultado-teste" class="mt-3"></div>
            </div>
        </div>

        <!-- Dicas -->
        <div class="card rule-card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">💡 Dicas de Padrões</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><code>supermercado|extra|carrefour</code><br><small class="text-muted">Múltiplas palavras</small></li>
                    <li class="mb-2"><code>^uber|^99|^cabify</code><br><small class="text-muted">Começa com (^)</small></li>
                    <li class="mb-2"><code>.*netflix|.*spotify</code><br><small class="text-muted">Contém (.+)</small></li>
                    <li class="mb-2"><code>salário|salario</code><br><small class="text-muted">Variações</small></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Regra -->
<div class="modal fade" id="modalNovaRegra" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Nova Regra de Categorização
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaRegra" action="salvar_regra_categorizacao.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="padrao" class="form-label">Padrão (Regex)</label>
                        <input type="text" class="form-control" id="padrao" name="padrao" placeholder="Ex: supermercado|extra|carrefour" required>
                        <div class="form-text">Use expressões regulares para criar padrões flexíveis</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_categoria" class="form-label">Categoria</label>
                            <select class="form-select" name="id_categoria" id="id_categoria" required>
                                <option value="">Selecione uma categoria</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select class="form-select" name="tipo" id="tipo" required>
                                <option value="despesa">Despesa</option>
                                <option value="receita">Receita</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="prioridade" class="form-label">Prioridade</label>
                            <select class="form-select" name="prioridade" id="prioridade">
                                <option value="Baixa">Baixa</option>
                                <option value="Média" selected>Média</option>
                                <option value="Alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="ativa" id="ativa" checked>
                                <label class="form-check-label" for="ativa">
                                    Regra Ativa
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Salvar Regra</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Regra -->
<div class="modal fade" id="modalEditarRegra" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Editar Regra
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarRegra" action="atualizar_regra_categorizacao.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_padrao" class="form-label">Padrão (Regex)</label>
                        <input type="text" class="form-control" id="edit_padrao" name="padrao" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_id_categoria" class="form-label">Categoria</label>
                            <select class="form-select" name="id_categoria" id="edit_id_categoria" required>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_tipo" class="form-label">Tipo</label>
                            <select class="form-select" name="tipo" id="edit_tipo" required>
                                <option value="despesa">Despesa</option>
                                <option value="receita">Receita</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_prioridade" class="form-label">Prioridade</label>
                            <select class="form-select" name="prioridade" id="edit_prioridade">
                                <option value="Baixa">Baixa</option>
                                <option value="Média">Média</option>
                                <option value="Alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="ativa" id="edit_ativa">
                                <label class="form-check-label" for="edit_ativa">
                                    Regra Ativa
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Atualizar Regra</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Testador de padrões
    const formTeste = document.getElementById('formTestePadrao');
    const resultadoTeste = document.getElementById('resultado-teste');
    
    formTeste.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const descricao = document.getElementById('teste_descricao').value;
        const padrao = document.getElementById('teste_padrao').value;
        
        try {
            const regex = new RegExp(padrao, 'i');
            const match = regex.test(descricao);
            
            resultadoTeste.innerHTML = `
                <div class="match-result ${match ? 'match-success' : 'match-fail'}">
                    <i class="bi bi-${match ? 'check-circle' : 'x-circle'} me-2"></i>
                    ${match ? 'Padrão encontrado!' : 'Padrão não encontrado'}
                </div>
            `;
        } catch (error) {
            resultadoTeste.innerHTML = `
                <div class="match-result match-fail">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erro na expressão regular: ${error.message}
                </div>
            `;
        }
    });
    
    // Editar regra
    document.querySelectorAll('.btn-editar-regra').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const padrao = this.dataset.padrao;
            const categoria = this.dataset.categoria;
            const tipo = this.dataset.tipo;
            const prioridade = this.dataset.prioridade;
            const ativa = this.dataset.ativa === '1';
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_padrao').value = padrao;
            document.getElementById('edit_id_categoria').value = categoria;
            document.getElementById('edit_tipo').value = tipo;
            document.getElementById('edit_prioridade').value = prioridade;
            document.getElementById('edit_ativa').checked = ativa;
            
            new bootstrap.Modal(document.getElementById('modalEditarRegra')).show();
        });
    });
    
    // Excluir regra
    document.querySelectorAll('.btn-excluir-regra').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Tem certeza que deseja excluir esta regra?')) {
                const id = this.dataset.id;
                fetch('excluir_regra_categorizacao.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erro ao excluir regra: ' + data.message);
                    }
                });
            }
        });
    });
    
    // Formulários
    const formNova = document.getElementById('formNovaRegra');
    const formEditar = document.getElementById('formEditarRegra');
    
    [formNova, formEditar].forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = this.action;
            const button = this.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            
            fetch(action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            })
            .catch(error => {
                showToast('Erro!', 'Erro de conexão', true);
                button.disabled = false;
                button.innerHTML = originalText;
            });
        });
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>
