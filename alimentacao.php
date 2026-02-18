<?php
// alimentacao.php - Diário Alimentar Completo com Edição e Exclusão

require_once 'templates/header.php';

$refeicoes_hoje = [];
$total_calorias = 0;
try {
    $sql = "SELECT id, tipo_refeicao, descricao, calorias 
            FROM registros_alimentacao 
            WHERE id_usuario = ? AND data_refeicao = CURDATE()
            ORDER BY FIELD(tipo_refeicao, 'Café da Manhã', 'Lanche da Manhã', 'Almoço', 'Lanche da Tarde', 'Jantar', 'Ceia')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $refeicoes_hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular total de calorias
    foreach ($refeicoes_hoje as $refeicao) {
        if ($refeicao['calorias']) {
            $total_calorias += (int)$refeicao['calorias'];
        }
    }
} catch (PDOException $e) {
    die("Erro ao buscar registros de alimentação: " . $e->getMessage());
}

$refeicoes_agrupadas = [];
foreach ($refeicoes_hoje as $refeicao) {
    $refeicoes_agrupadas[$refeicao['tipo_refeicao']][] = $refeicao;
}

$tipos_de_refeicao = ['Café da Manhã', 'Lanche da Manhã', 'Almoço', 'Lanche da Tarde', 'Jantar', 'Ceia'];
?>

<style>
    .refeicao-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid var(--border-color, #333);
    }
    .refeicao-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }
    .refeicao-card .card-header {
        font-size: 1.1rem;
        font-weight: 600;
        background: linear-gradient(135deg, var(--primary-color, #dc3545) 0%, #c82333 100%);
        color: #fff;
        border-bottom: none;
    }
    .refeicao-item {
        transition: background-color 0.2s ease;
        border-left: 3px solid transparent;
    }
    .refeicao-item:hover {
        background-color: var(--hover-bg, rgba(220, 53, 69, 0.1)) !important;
        border-left-color: var(--primary-color, #dc3545);
    }
    .refeicao-actions {
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .refeicao-item:hover .refeicao-actions {
        opacity: 1;
    }
    .stats-card {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
    }
    .stats-value {
        font-size: 2.5rem;
        font-weight: bold;
        margin: 0.5rem 0;
    }
    .empty-state {
        padding: 2rem;
        text-align: center;
        color: var(--text-muted, #6c757d);
    }
    .empty-state i {
        font-size: 3rem;
        opacity: 0.5;
        margin-bottom: 1rem;
    }
</style>

<div class="container-fluid py-4">
    <!-- Cabeçalho -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h2 mb-1">
                <i class="bi bi-calendar-heart me-2 text-danger"></i>Meu Diário Alimentar
            </h1>
            <p class="text-muted mb-0">
                <i class="bi bi-calendar3 me-1"></i><?php echo date('d/m/Y'); ?>
            </p>
        </div>
        <button class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#modalNovaRefeicao">
            <i class="bi bi-plus-lg me-2"></i>Adicionar Refeição
        </button>
    </div>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up">
            <div class="stats-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <small class="opacity-75">Total de Calorias</small>
                        <div class="stats-value"><?php echo number_format($total_calorias, 0, ',', '.'); ?></div>
                        <small class="opacity-75">kcal</small>
                    </div>
                    <i class="bi bi-fire fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card card-custom">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted">Refeições Hoje</small>
                            <div class="h3 mb-0"><?php echo count($refeicoes_hoje); ?></div>
                        </div>
                        <i class="bi bi-egg-fried fs-1 text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
            <div class="card card-custom">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted">Média por Refeição</small>
                            <div class="h3 mb-0">
                                <?php 
                                $media = count($refeicoes_hoje) > 0 ? round($total_calorias / count($refeicoes_hoje)) : 0;
                                echo number_format($media, 0, ',', '.');
                                ?>
                            </div>
                            <small class="text-muted">kcal</small>
                        </div>
                        <i class="bi bi-graph-up fs-1 text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Refeições -->
    <div class="row g-4">
        <?php foreach ($tipos_de_refeicao as $tipo): ?>
            <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up">
                <div class="card card-custom refeicao-card h-100">
                    <div class="card-header">
                        <i class="bi bi-<?php 
                            $icons = [
                                'Café da Manhã' => 'cup-hot',
                                'Lanche da Manhã' => 'apple',
                                'Almoço' => 'egg-fried',
                                'Lanche da Tarde' => 'cookie',
                                'Jantar' => 'bowl',
                                'Ceia' => 'moon-stars'
                            ];
                            echo $icons[$tipo] ?? 'circle';
                        ?> me-2"></i><?php echo htmlspecialchars($tipo); ?>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush" id="lista-refeicao-<?php echo str_replace(' ', '-', strtolower($tipo)); ?>">
                            <?php if (isset($refeicoes_agrupadas[$tipo]) && !empty($refeicoes_agrupadas[$tipo])): ?>
                                <?php foreach ($refeicoes_agrupadas[$tipo] as $item): ?>
                                    <li class="list-group-item refeicao-item d-flex justify-content-between align-items-start" 
                                        data-refeicao-id="<?php echo $item['id']; ?>"
                                        style="background: transparent; border-color: var(--border-color, #333);">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold mb-1" style="color: var(--bs-body-color, #fff);">
                                                <?php echo htmlspecialchars($item['descricao']); ?>
                                            </div>
                                            <?php if($item['calorias']): ?>
                                                <span class="badge bg-danger rounded-pill">
                                                    <i class="bi bi-fire me-1"></i><?php echo $item['calorias']; ?> kcal
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="refeicao-actions ms-2 d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary btn-editar-refeicao" 
                                                    data-refeicao-id="<?php echo $item['id']; ?>"
                                                    title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-excluir-refeicao" 
                                                    data-refeicao-id="<?php echo $item['id']; ?>"
                                                    title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item empty-state" style="background: transparent; border-color: var(--border-color, #333);">
                                    <i class="bi bi-inbox"></i>
                                    <div>Nenhuma refeição registrada.</div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Nova Refeição -->
<div class="modal fade" id="modalNovaRefeicao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Adicionar Refeição
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaRefeicao">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tipo_refeicao" class="form-label">Tipo de Refeição</label>
                        <select name="tipo_refeicao" id="tipo_refeicao" class="form-select" required>
                            <?php foreach ($tipos_de_refeicao as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo); ?>">
                                    <?php echo htmlspecialchars($tipo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição da Refeição</label>
                        <textarea name="descricao" id="descricao" class="form-control" rows="4" 
                                  placeholder="Ex: 100g de frango grelhado, 150g de batata doce, salada verde..." required></textarea>
                        <small class="text-muted">Descreva os alimentos e quantidades consumidas.</small>
                    </div>
                    <div class="mb-3">
                        <label for="calorias" class="form-label">
                            Calorias <span class="text-muted">(Opcional)</span>
                        </label>
                        <input type="number" name="calorias" id="calorias" class="form-control" 
                               min="0" step="1" placeholder="Ex: 450">
                        <small class="text-muted">Informe o total de calorias da refeição.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-check-lg me-2"></i>Salvar Refeição
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Refeição -->
<div class="modal fade" id="modalEditarRefeicao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Editar Refeição
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarRefeicao">
                <input type="hidden" name="id" id="edit-refeicao-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-tipo_refeicao" class="form-label">Tipo de Refeição</label>
                        <select name="tipo_refeicao" id="edit-tipo_refeicao" class="form-select" required>
                            <?php foreach ($tipos_de_refeicao as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo); ?>">
                                    <?php echo htmlspecialchars($tipo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-descricao" class="form-label">Descrição da Refeição</label>
                        <textarea name="descricao" id="edit-descricao" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-calorias" class="form-label">
                            Calorias <span class="text-muted">(Opcional)</span>
                        </label>
                        <input type="number" name="calorias" id="edit-calorias" class="form-control" 
                               min="0" step="1" placeholder="Ex: 450">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>Atualizar Refeição
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });

    // Função para escapar HTML
    function escapeHTML(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Função para atualizar estatísticas
    function atualizarEstatisticas() {
        fetch('buscar_estatisticas_alimentacao.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector('.stats-value').textContent = 
                        parseInt(data.total_calorias).toLocaleString('pt-BR');
                    const refeicoesCount = document.querySelectorAll('.refeicao-item:not(.empty-state)').length;
                    // Atualizar contador de refeições se necessário
                }
            })
            .catch(error => console.error('Erro ao atualizar estatísticas:', error));
    }

    // Adicionar Nova Refeição
    const formNovaRefeicao = document.getElementById('formNovaRefeicao');
    const modalNovaRefeicao = new bootstrap.Modal(document.getElementById('modalNovaRefeicao'));

    if (formNovaRefeicao) {
        formNovaRefeicao.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formNovaRefeicao);
            const button = formNovaRefeicao.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';

            fetch('salvar_refeicao.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    modalNovaRefeicao.hide();
                    formNovaRefeicao.reset();

                    const refeicao = data.refeicao;
                    const listId = 'lista-refeicao-' + refeicao.tipo_refeicao.toLowerCase().replace(/ /g, '-');
                    const lista = document.getElementById(listId);

                    if (lista) {
                        const emptyState = lista.querySelector('.empty-state');
                        if (emptyState) {
                            emptyState.remove();
                        }

                        const newLi = document.createElement('li');
                        newLi.className = 'list-group-item refeicao-item d-flex justify-content-between align-items-start';
                        newLi.setAttribute('data-refeicao-id', refeicao.id);
                        newLi.style.background = 'transparent';
                        newLi.style.borderColor = 'var(--border-color, #333)';
                        
                        let badgeHtml = '';
                        if (refeicao.calorias) {
                            badgeHtml = `<span class="badge bg-danger rounded-pill"><i class="bi bi-fire me-1"></i>${refeicao.calorias} kcal</span>`;
                        }
                        
                        newLi.innerHTML = `
                            <div class="flex-grow-1">
                                <div class="fw-semibold mb-1" style="color: var(--bs-body-color, #fff);">${escapeHTML(refeicao.descricao)}</div>
                                ${badgeHtml}
                            </div>
                            <div class="refeicao-actions ms-2 d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary btn-editar-refeicao" 
                                        data-refeicao-id="${refeicao.id}" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-excluir-refeicao" 
                                        data-refeicao-id="${refeicao.id}" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                        
                        lista.appendChild(newLi);
                        gsap.from(newLi, { duration: 0.5, opacity: 0, y: 20, ease: 'power3.out' });
                        
                        // Adicionar event listeners aos novos botões
                        newLi.querySelector('.btn-editar-refeicao').addEventListener('click', editarRefeicao);
                        newLi.querySelector('.btn-excluir-refeicao').addEventListener('click', excluirRefeicao);
                        
                        // Atualizar estatísticas
                        setTimeout(() => window.location.reload(), 1000);
                    }
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de Rede!', 'Não foi possível se conectar.', true);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
        });
    }

    // Editar Refeição
    const modalEditarRefeicao = new bootstrap.Modal(document.getElementById('modalEditarRefeicao'));
    const formEditarRefeicao = document.getElementById('formEditarRefeicao');

    function editarRefeicao(e) {
        const refeicaoId = e.currentTarget.getAttribute('data-refeicao-id');
        
        fetch(`buscar_refeicao.php?id=${refeicaoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.refeicao) {
                    const refeicao = data.refeicao;
                    document.getElementById('edit-refeicao-id').value = refeicao.id;
                    document.getElementById('edit-tipo_refeicao').value = refeicao.tipo_refeicao;
                    document.getElementById('edit-descricao').value = refeicao.descricao;
                    document.getElementById('edit-calorias').value = refeicao.calorias || '';
                    modalEditarRefeicao.show();
                } else {
                    showToast('Erro!', data.message || 'Refeição não encontrada', true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro!', 'Erro de conexão', true);
            });
    }

    // Adicionar event listeners para botões de editar
    document.querySelectorAll('.btn-editar-refeicao').forEach(btn => {
        btn.addEventListener('click', editarRefeicao);
    });

    // Salvar Edição
    if (formEditarRefeicao) {
        formEditarRefeicao.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarRefeicao);
            const button = formEditarRefeicao.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Atualizando...';

            fetch('editar_refeicao.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    modalEditarRefeicao.hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro!', 'Erro de conexão', true);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
        });
    }

    // Excluir Refeição
    function excluirRefeicao(e) {
        const refeicaoId = e.currentTarget.getAttribute('data-refeicao-id');
        const refeicaoItem = e.currentTarget.closest('.refeicao-item');
        const descricao = refeicaoItem.querySelector('.fw-semibold').textContent.trim();
        
        if (!confirm(`Tem certeza que deseja excluir esta refeição?\n\n"${descricao}"`)) {
            return;
        }

        const formData = new FormData();
        formData.append('id', refeicaoId);

        fetch('excluir_refeicao.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', data.message);
                
                // Animação de saída
                gsap.to(refeicaoItem, {
                    duration: 0.3,
                    opacity: 0,
                    x: -20,
                    ease: 'power2.in',
                    onComplete: () => {
                        refeicaoItem.remove();
                        
                        // Verificar se a lista ficou vazia
                        const lista = refeicaoItem.closest('ul');
                        if (lista && lista.children.length === 0) {
                            const emptyLi = document.createElement('li');
                            emptyLi.className = 'list-group-item empty-state';
                            emptyLi.style.background = 'transparent';
                            emptyLi.style.borderColor = 'var(--border-color, #333)';
                            emptyLi.innerHTML = `
                                <i class="bi bi-inbox"></i>
                                <div>Nenhuma refeição registrada.</div>
                            `;
                            lista.appendChild(emptyLi);
                        }
                        
                        // Atualizar estatísticas
                        setTimeout(() => window.location.reload(), 1000);
                    }
                });
            } else {
                showToast('Erro!', data.message, true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro de conexão', true);
        });
    }

    // Adicionar event listeners para botões de excluir
    document.querySelectorAll('.btn-excluir-refeicao').forEach(btn => {
        btn.addEventListener('click', excluirRefeicao);
    });
});
</script>

<?php
require_once 'templates/footer.php';
?>
