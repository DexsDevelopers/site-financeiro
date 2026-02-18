<?php
// notas_cursos.php - Sistema de Notas e Anotações para Cursos

require_once 'templates/header.php';

// Buscar cursos e notas
$cursos = [];
$notas = [];
$categoria_selecionada = $_GET['categoria'] ?? 'todas';

try {
    // Buscar cursos
    $stmt_cursos = $pdo->prepare("SELECT * FROM cursos WHERE id_usuario = ? ORDER BY nome_curso ASC");
    $stmt_cursos->execute([$userId]);
    $cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar notas
    $sql_notas = "SELECT n.*, c.nome_curso FROM notas_cursos n 
                  LEFT JOIN cursos c ON n.id_curso = c.id 
                  WHERE n.id_usuario = ?";
    $params = [$userId];
    
    if ($categoria_selecionada !== 'todas') {
        $sql_notas .= " AND n.categoria = ?";
        $params[] = $categoria_selecionada;
    }
    
    $sql_notas .= " ORDER BY n.data_criacao DESC";
    
    $stmt_notas = $pdo->prepare($sql_notas);
    $stmt_notas->execute($params);
    $notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Se as tabelas não existirem, continuar com arrays vazios
    $cursos = [];
    $notas = [];
}

// Categorias disponíveis
$categorias = ['todas' => 'Todas', 'conceitos' => 'Conceitos', 'exercicios' => 'Exercícios', 'dicas' => 'Dicas', 'resumos' => 'Resumos', 'outros' => 'Outros'];
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
    .note-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }
    .note-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .note-content {
        max-height: 200px;
        overflow-y: auto;
    }
    .note-content::-webkit-scrollbar {
        width: 6px;
    }
    .note-content::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }
    .note-content::-webkit-scrollbar-thumb {
        background: var(--accent-red);
        border-radius: 3px;
    }
    .category-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    .search-box {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 0.75rem 1rem;
    }
    .search-box:focus {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--accent-red);
        box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
    }
    .course-selector {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 1rem;
    }
    .stats-card {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(229, 9, 20, 0.05) 100%);
        border: 1px solid rgba(229, 9, 20, 0.3);
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-journal-text display-1 text-danger mb-4"></i>
        <h1 class="display-5">Notas e Anotações</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Organize suas anotações por curso, crie resumos, guarde conceitos importantes e mantenha tudo organizado para maximizar seu aprendizado.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Estatísticas -->
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-journal-text feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count($notas); ?></h5>
                <p class="text-white-50 mb-0">Total de Notas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-book feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count($cursos); ?></h5>
                <p class="text-white-50 mb-0">Cursos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-tags feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count(array_unique(array_column($notas, 'categoria'))); ?></h5>
                <p class="text-white-50 mb-0">Categorias</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-calendar feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count(array_filter($notas, function($n) { return date('Y-m-d', strtotime($n['data_criacao'])) === date('Y-m-d'); })); ?></h5>
                <p class="text-white-50 mb-0">Hoje</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Filtros e Busca -->
    <div class="col-12">
        <div class="card note-card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control search-box" id="busca-notas" placeholder="Buscar nas notas...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filtro-categoria">
                            <?php foreach ($categorias as $key => $nome): ?>
                                <option value="<?php echo $key; ?>" <?php echo $categoria_selecionada === $key ? 'selected' : ''; ?>>
                                    <?php echo $nome; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filtro-curso">
                            <option value="todos">Todos os Cursos</option>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?php echo $curso['id']; ?>">
                                    <?php echo htmlspecialchars($curso['nome_curso']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#modalNovaNota">
                            <i class="bi bi-plus-lg me-2"></i>Nova Nota
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Notas -->
    <div class="col-12">
        <div class="card note-card">
            <div class="card-header">
                <h4 class="card-title mb-0">Suas Anotações</h4>
            </div>
            <div class="card-body">
                <?php if (empty($notas)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-journal-x display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhuma nota ainda</h5>
                        <p class="text-muted">Comece criando sua primeira anotação para organizar seu aprendizado.</p>
                    </div>
                <?php else: ?>
                    <div id="lista-notas" class="row g-3">
                        <?php foreach ($notas as $nota): ?>
                            <div class="col-md-6 col-lg-4 nota-item" 
                                 data-categoria="<?php echo $nota['categoria']; ?>"
                                 data-curso="<?php echo $nota['id_curso']; ?>"
                                 data-conteudo="<?php echo strtolower($nota['titulo'] . ' ' . $nota['conteudo']); ?>">
                                <div class="card note-card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="card-title mb-0"><?php echo htmlspecialchars($nota['titulo']); ?></h6>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary btn-editar-nota" 
                                                    data-id="<?php echo $nota['id']; ?>"
                                                    data-titulo="<?php echo htmlspecialchars($nota['titulo']); ?>"
                                                    data-conteudo="<?php echo htmlspecialchars($nota['conteudo']); ?>"
                                                    data-categoria="<?php echo $nota['categoria']; ?>"
                                                    data-curso="<?php echo $nota['id_curso']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-excluir-nota" data-id="<?php echo $nota['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="note-content">
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($nota['conteudo'], 0, 200))); ?><?php echo strlen($nota['conteudo']) > 200 ? '...' : ''; ?></p>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <span class="badge category-badge bg-<?php echo $nota['categoria'] === 'conceitos' ? 'primary' : ($nota['categoria'] === 'exercicios' ? 'success' : ($nota['categoria'] === 'dicas' ? 'warning text-dark' : ($nota['categoria'] === 'resumos' ? 'info' : 'secondary'))); ?>">
                                                <?php echo ucfirst($nota['categoria']); ?>
                                            </span>
                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($nota['data_criacao'])); ?></small>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-book me-1"></i>
                                                <?php echo htmlspecialchars($nota['nome_curso'] ?? 'Curso não encontrado'); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Nota -->
<div class="modal fade" id="modalNovaNota" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Nova Anotação
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaNota" action="salvar_nota_curso.php" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="titulo" class="form-label">Título</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Ex: Conceitos de JavaScript" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="categoria" class="form-label">Categoria</label>
                            <select class="form-select" name="categoria" id="categoria" required>
                                <option value="conceitos">Conceitos</option>
                                <option value="exercicios">Exercícios</option>
                                <option value="dicas">Dicas</option>
                                <option value="resumos">Resumos</option>
                                <option value="outros">Outros</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="id_curso" class="form-label">Curso</label>
                        <select class="form-select" name="id_curso" id="id_curso" required>
                            <option value="">Selecione um curso</option>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?php echo $curso['id']; ?>"><?php echo htmlspecialchars($curso['nome_curso']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="conteudo" class="form-label">Conteúdo</label>
                        <textarea class="form-control" id="conteudo" name="conteudo" rows="8" placeholder="Digite suas anotações aqui..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Salvar Anotação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Nota -->
<div class="modal fade" id="modalEditarNota" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Editar Anotação
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarNota" action="atualizar_nota_curso.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="edit_titulo" class="form-label">Título</label>
                            <input type="text" class="form-control" id="edit_titulo" name="titulo" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_categoria" class="form-label">Categoria</label>
                            <select class="form-select" name="categoria" id="edit_categoria" required>
                                <option value="conceitos">Conceitos</option>
                                <option value="exercicios">Exercícios</option>
                                <option value="dicas">Dicas</option>
                                <option value="resumos">Resumos</option>
                                <option value="outros">Outros</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_id_curso" class="form-label">Curso</label>
                        <select class="form-select" name="id_curso" id="edit_id_curso" required>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?php echo $curso['id']; ?>"><?php echo htmlspecialchars($curso['nome_curso']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_conteudo" class="form-label">Conteúdo</label>
                        <textarea class="form-control" id="edit_conteudo" name="conteudo" rows="8" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Atualizar Anotação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtros
    const buscaNotas = document.getElementById('busca-notas');
    const filtroCategoria = document.getElementById('filtro-categoria');
    const filtroCurso = document.getElementById('filtro-curso');
    const listaNotas = document.getElementById('lista-notas');
    
    function filtrarNotas() {
        const termoBusca = buscaNotas.value.toLowerCase();
        const categoria = filtroCategoria.value;
        const curso = filtroCurso.value;
        
        const notas = listaNotas.querySelectorAll('.nota-item');
        notas.forEach(nota => {
            const categoriaNota = nota.dataset.categoria;
            const cursoNota = nota.dataset.curso;
            const conteudoNota = nota.dataset.conteudo;
            
            const matchBusca = termoBusca === '' || conteudoNota.includes(termoBusca);
            const matchCategoria = categoria === 'todas' || categoriaNota === categoria;
            const matchCurso = curso === 'todos' || cursoNota === curso;
            
            if (matchBusca && matchCategoria && matchCurso) {
                nota.style.display = 'block';
            } else {
                nota.style.display = 'none';
            }
        });
    }
    
    buscaNotas.addEventListener('input', filtrarNotas);
    filtroCategoria.addEventListener('change', filtrarNotas);
    filtroCurso.addEventListener('change', filtrarNotas);
    
    // Editar nota
    document.querySelectorAll('.btn-editar-nota').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const titulo = this.dataset.titulo;
            const conteudo = this.dataset.conteudo;
            const categoria = this.dataset.categoria;
            const curso = this.dataset.curso;
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_titulo').value = titulo;
            document.getElementById('edit_conteudo').value = conteudo;
            document.getElementById('edit_categoria').value = categoria;
            document.getElementById('edit_id_curso').value = curso;
            
            new bootstrap.Modal(document.getElementById('modalEditarNota')).show();
        });
    });
    
    // Excluir nota
    document.querySelectorAll('.btn-excluir-nota').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Tem certeza que deseja excluir esta anotação?')) {
                const id = this.dataset.id;
                fetch('excluir_nota_curso.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erro ao excluir anotação: ' + data.message);
                    }
                });
            }
        });
    });
    
    // Formulários
    const formNova = document.getElementById('formNovaNota');
    const formEditar = document.getElementById('formEditarNota');
    
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
