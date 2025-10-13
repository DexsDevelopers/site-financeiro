<?php
// alimentacao.php (Versão Final com AJAX)

require_once 'templates/header.php';

$refeicoes_hoje = [];
try {
    $sql = "SELECT id, tipo_refeicao, descricao, calorias 
            FROM registros_alimentacao 
            WHERE id_usuario = ? AND data_refeicao = CURDATE()
            ORDER BY FIELD(tipo_refeicao, 'Café da Manhã', 'Lanche da Manhã', 'Almoço', 'Lanche da Tarde', 'Jantar', 'Ceia')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $refeicoes_hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    .refeicao-card .card-header { font-size: 1.1rem; font-weight: 600; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Meu Diário Alimentar (Hoje, <?php echo date('d/m/Y'); ?>)</h1>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaRefeicao">
        <i class="bi bi-plus-lg me-2"></i>Adicionar Refeição
    </button>
</div>

<div class="row g-4">
    <?php foreach ($tipos_de_refeicao as $tipo): ?>
        <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up">
            <div class="card card-custom h-100">
                <div class="card-header"><?php echo $tipo; ?></div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="lista-refeicao-<?php echo str_replace(' ', '-', strtolower($tipo)); ?>">
                        <?php if (isset($refeicoes_agrupadas[$tipo]) && !empty($refeicoes_agrupadas[$tipo])): ?>
                            <?php foreach ($refeicoes_agrupadas[$tipo] as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start" style="background: transparent; border-color: var(--border-color);">
                                    <div><?php echo htmlspecialchars($item['descricao']); ?></div>
                                    <?php if($item['calorias']): ?>
                                        <span class="badge bg-danger rounded-pill"><?php echo $item['calorias']; ?> kcal</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <li class="list-group-item text-muted empty-state" style="background: transparent; border-color: var(--border-color);">
                                Nenhuma refeição registrada.
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="modalNovaRefeicao" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Adicionar Refeição</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="formNovaRefeicao" action="salvar_refeicao.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3"><label for="tipo_refeicao" class="form-label">Tipo de Refeição</label><select name="tipo_refeicao" class="form-select" required><?php foreach ($tipos_de_refeicao as $tipo): ?><option value="<?php echo $tipo; ?>"><?php echo $tipo; ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label for="descricao" class="form-label">Descrição da Refeição</label><textarea name="descricao" class="form-control" rows="3" placeholder="Ex: 100g de frango grelhado, 150g de batata doce..." required></textarea></div>
                    <div class="mb-3"><label for="calorias" class="form-label">Calorias (Opcional)</label><input type="number" name="calorias" class="form-control" min="0" placeholder="Ex: 450"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar Refeição</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });

    const modalNovaRefeicao = new bootstrap.Modal(document.getElementById('modalNovaRefeicao'));
    const formNovaRefeicao = document.getElementById('formNovaRefeicao');

    if (formNovaRefeicao) {
        formNovaRefeicao.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formNovaRefeicao);
            const button = formNovaRefeicao.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Salvando...`;

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
                        newLi.className = 'list-group-item d-flex justify-content-between align-items-start';
                        newLi.style.background = 'transparent';
                        newLi.style.borderColor = 'var(--border-color)';
                        
                        let badgeHtml = '';
                        if (refeicao.calorias) {
                            badgeHtml = `<span class="badge bg-danger rounded-pill">${refeicao.calorias} kcal</span>`;
                        }
                        newLi.innerHTML = `<div>${escapeHTML(refeicao.descricao)}</div>${badgeHtml}`;
                        
                        lista.appendChild(newLi);
                        gsap.from(newLi, { duration: 0.5, opacity: 0, y: 20, ease: 'power3.out' });
                    }

                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = 'Salvar Refeição';
            });
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>