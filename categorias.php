<?php
// categorias.php (Versão Final Corrigida)

require_once 'templates/header.php';
require_once 'includes/db_connect.php';

$categorias_receita = [];
$categorias_despesa = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id_usuario = ? ORDER BY tipo, nome ASC");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $cat) {
        if ($cat['tipo'] == 'receita') {
            $categorias_receita[] = $cat;
        } else {
            $categorias_despesa[] = $cat;
        }
    }
} catch (PDOException $e) {
    die("Erro ao buscar categorias: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Gerenciar Categorias</h1>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaCategoria">
        <i class="bi bi-plus-lg me-2"></i>Nova Categoria
    </button>
</div>

<div class="row g-4">
    <div class="col-md-6" data-aos="fade-up">
        <div class="card card-custom h-100">
            <div class="card-body p-4">
                <h4 class="card-title mb-3"><i class="bi bi-graph-up-arrow text-success"></i> Categorias de Receita</h4>
                <ul class="list-group list-group-flush" id="lista-cat-receita">
                    <?php if (empty($categorias_receita)): ?>
                        <div class="text-center text-muted p-4" id="empty-state-receita"><i class="bi bi-piggy-bank fs-1"></i><p class="mt-2 mb-0">Nenhuma categoria de receita.</p></div>
                    <?php else: ?>
                        <?php foreach($categorias_receita as $cat): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center" id="cat-row-<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['nome']); ?>
                                <button class="btn btn-sm btn-outline-danger btn-excluir-categoria" data-id="<?php echo $cat['id']; ?>" data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"><i class="bi bi-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
        <div class="card card-custom h-100">
            <div class="card-body p-4">
                <h4 class="card-title mb-3"><i class="bi bi-bag-dash-fill text-danger"></i> Categorias de Despesa</h4>
                <ul class="list-group list-group-flush" id="lista-cat-despesa">
                     <?php if (empty($categorias_despesa)): ?>
                        <div class="text-center text-muted p-4" id="empty-state-despesa"><i class="bi bi-cart-x fs-1"></i><p class="mt-2 mb-0">Nenhuma categoria de despesa.</p></div>
                    <?php else: ?>
                        <?php foreach($categorias_despesa as $cat): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center" id="cat-row-<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['nome']); ?>
                                <button class="btn btn-sm btn-outline-danger btn-excluir-categoria" data-id="<?php echo $cat['id']; ?>" data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"><i class="bi bi-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovaCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Adicionar Nova Categoria</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="formNovaCategoria" action="adicionar_categoria.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3"><label for="nome" class="form-label">Nome da Categoria</label><input type="text" name="nome" class="form-control" placeholder="Ex: Alimentação" required></div>
                    <div class="mb-3"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option value="despesa" selected>Despesa</option><option value="receita">Receita</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar Categoria</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 800, once: true });

    const modalNovaCategoriaEl = document.getElementById('modalNovaCategoria');
    const modalNovaCategoria = new bootstrap.Modal(modalNovaCategoriaEl);
    const formNovaCategoria = document.getElementById('formNovaCategoria');

    // --- LÓGICA PARA ADICIONAR CATEGORIA COM AJAX ---
    if (formNovaCategoria) {
        formNovaCategoria.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formNovaCategoria);
            const tipo = formData.get('tipo');
            const button = formNovaCategoria.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Salvando...`;

            fetch('adicionar_categoria.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    modalNovaCategoria.hide();
                    formNovaCategoria.reset();

                    const novaCategoria = data.categoria;
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                    li.id = `cat-row-${novaCategoria.id}`;
                    li.style.background = 'transparent';
                    li.innerHTML = `
                        ${escapeHTML(novaCategoria.nome)}
                        <button class="btn btn-sm btn-outline-danger btn-excluir-categoria" data-id="${novaCategoria.id}" data-nome="${escapeHTML(novaCategoria.nome)}">
                            <i class="bi bi-trash"></i>
                        </button>`;
                    
                    const lista = document.getElementById(tipo === 'receita' ? 'lista-cat-receita' : 'lista-cat-despesa');
                    const emptyState = document.getElementById(tipo === 'receita' ? 'empty-state-receita' : 'empty-state-despesa');
                    if (emptyState) { emptyState.remove(); }
                    
                    lista.appendChild(li);
                    gsap.from(li, { duration: 0.5, opacity: 0, x: -20, ease: 'power3.out' });

                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = 'Salvar Categoria';
            });
        });
    }

    // --- LÓGICA PARA EXCLUIR CATEGORIA COM AJAX ---
    document.body.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.btn-excluir-categoria');
        if (deleteButton) {
            const catId = deleteButton.dataset.id;
            const catNome = deleteButton.dataset.nome;

            Swal.fire({
                title: 'Tem certeza?',
                text: `Excluir a categoria "${catNome}"? As transações existentes ficarão sem categoria.`,
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
                    fetch('excluir_categoria.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: catId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Sucesso!', data.message);
                            const rowToRemove = document.getElementById(`cat-row-${catId}`);
                            if (rowToRemove) {
                                gsap.to(rowToRemove, { duration: 0.5, opacity: 0, x: 20, ease: 'power3.in', onComplete: () => rowToRemove.remove() });
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
});
</script>

<?php
require_once 'templates/footer.php';
?>