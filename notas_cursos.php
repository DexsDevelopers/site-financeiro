<?php
// notas_cursos.php - Sistema Profissional de Notas e Anotações com Mapa Mental (Canvas Customizado)
require_once 'templates/header.php';

// Buscar cursos e notas
$cursos = [];
$notas = [];
$categoria_selecionada = $_GET['categoria'] ?? 'todas';
$curso_selecionado = $_GET['curso'] ?? 'todos';
$busca = $_GET['busca'] ?? '';

try {
    // Buscar cursos
    $stmt_cursos = $pdo->prepare("SELECT * FROM cursos WHERE id_usuario = ? ORDER BY nome_curso ASC");
    $stmt_cursos->execute([$userId]);
    $cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar notas com filtros
    $sql_notas = "SELECT n.*, c.nome_curso FROM notas_cursos n 
                  LEFT JOIN cursos c ON n.id_curso = c.id 
                  WHERE n.id_usuario = ?";
    $params = [$userId];
    
    if ($categoria_selecionada !== 'todas') {
        $sql_notas .= " AND n.categoria = ?";
        $params[] = $categoria_selecionada;
    }
    
    if ($curso_selecionado !== 'todos') {
        $sql_notas .= " AND n.id_curso = ?";
        $params[] = $curso_selecionado;
    }
    
    if (!empty($busca)) {
        $sql_notas .= " AND (n.titulo LIKE ? OR n.conteudo LIKE ?)";
        $params[] = "%{$busca}%";
        $params[] = "%{$busca}%";
    }
    
    $sql_notas .= " ORDER BY n.data_criacao DESC";
    
    $stmt_notas = $pdo->prepare($sql_notas);
    $stmt_notas->execute($params);
    $notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $cursos = [];
    $notas = [];
    error_log("Erro ao buscar notas: " . $e->getMessage());
}

// Categorias disponíveis
$categorias = [
    'todas' => 'Todas as Categorias',
    'conceitos' => 'Conceitos',
    'exercicios' => 'Exercícios',
    'dicas' => 'Dicas',
    'resumos' => 'Resumos',
    'formulas' => 'Fórmulas',
    'definicoes' => 'Definições',
    'exemplos' => 'Exemplos',
    'outros' => 'Outros'
];

// Estatísticas
$stats = [
    'total_notas' => count($notas),
    'total_cursos' => count($cursos),
    'categorias_unicas' => count(array_unique(array_column($notas, 'categoria'))),
    'notas_hoje' => count(array_filter($notas, function($n) { 
        return date('Y-m-d', strtotime($n['data_criacao'])) === date('Y-m-d'); 
    }))
];
?>

<style>
    .intro-card {
        background: linear-gradient(135deg, rgba(30, 30, 30, 0.5) 0%, rgba(50, 30, 30, 0.5) 100%);
    }
    
    .stats-card {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(229, 9, 20, 0.05) 100%);
        border: 1px solid rgba(229, 9, 20, 0.3);
        transition: all 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(229, 9, 20, 0.2);
    }
    
    .note-card {
        background: var(--card-background, #1a1a1a);
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        color: var(--bs-body-color, #fff);
    }
    
    .note-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        border-color: var(--accent-red, #dc3545);
    }
    
    .note-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent-red, #dc3545), #ff6b6b);
    }
    
    .note-content {
        max-height: 150px;
        overflow-y: auto;
        line-height: 1.6;
        color: var(--bs-body-color, #fff);
    }
    
    .note-content::-webkit-scrollbar {
        width: 4px;
    }
    
    .note-content::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
    }
    
    .note-content::-webkit-scrollbar-thumb {
        background: var(--accent-red, #dc3545);
        border-radius: 2px;
    }
    
    .category-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: 600;
    }
    
    .search-box {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        color: var(--bs-body-color, #fff);
    }
    
    .search-box:focus {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--accent-red, #dc3545);
        box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
        color: var(--bs-body-color, #fff);
    }
    
    .filter-card {
        background: var(--card-background, #1a1a1a);
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        padding: 1.5rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-secondary, #999);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .note-actions {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .note-card:hover .note-actions {
        opacity: 1;
    }
    
    .note-priority {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    
    .priority-alta { background: #dc3545; }
    .priority-media { background: #ffc107; }
    .priority-baixa { background: #28a745; }
    
    /* Mapa Mental Styles - Canvas Customizado Moderno */
    #mindmap-container {
        width: 100%;
        height: 600px;
        min-height: 400px;
        border: 2px solid var(--border-color, #333);
        border-radius: 16px;
        background: linear-gradient(135deg, #1a1a1a 0%, #2d1b1b 100%);
        position: relative;
        overflow: hidden;
        cursor: grab;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        transition: all 0.3s ease;
    }
    
    #mindmap-container:active {
        cursor: grabbing;
    }
    
    #mindmap-container:hover {
        border-color: var(--accent-red, #dc3545);
        box-shadow: 0 12px 40px rgba(229, 9, 20, 0.3);
    }
    
    #mindmap-canvas {
        width: 100%;
        height: 100%;
        display: block;
        touch-action: none;
    }
    
    .mindmap-controls {
        position: absolute;
        top: 15px;
        right: 15px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: rgba(26, 26, 26, 0.95);
        backdrop-filter: blur(10px);
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(229, 9, 20, 0.3);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }
    
    .mindmap-controls button {
        min-width: 120px;
        transition: all 0.3s ease;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
    
    .mindmap-controls button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }
    
    .mindmap-controls button:active {
        transform: translateY(0);
    }
    
    .mindmap-node {
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
    }
    
    .mindmap-node:hover {
        transform: scale(1.05);
    }
    
    .mindmap-toolbar {
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(45, 27, 27, 0.95) 100%);
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        backdrop-filter: blur(10px);
    }
    
    .node-edit-input {
        position: absolute;
        background: rgba(26, 26, 26, 0.98);
        border: 2px solid #dc3545;
        border-radius: 8px;
        padding: 8px 12px;
        color: #fff;
        font-size: 14px;
        z-index: 10000;
        min-width: 200px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }
    
    /* Responsividade Mobile */
    @media (max-width: 768px) {
        #mindmap-container {
            height: 500px;
            min-height: 350px;
        }
        
        .mindmap-controls {
            top: 10px;
            right: 10px;
            padding: 8px;
            flex-direction: row;
            flex-wrap: wrap;
            max-width: calc(100% - 20px);
        }
        
        .mindmap-controls button {
            min-width: auto;
            flex: 1;
            font-size: 0.875rem;
            padding: 6px 10px;
        }
        
        .mindmap-controls button i {
            margin-right: 0 !important;
        }
        
        .mindmap-controls button span {
            display: none;
        }
    }
    
    @media (max-width: 576px) {
        #mindmap-container {
            height: 400px;
            min-height: 300px;
        }
        
        .mindmap-controls {
            position: relative;
            top: auto;
            right: auto;
            margin-top: 10px;
            width: 100%;
            justify-content: center;
        }
    }
    
    /* Indicador de zoom */
    .zoom-indicator {
        position: absolute;
        bottom: 15px;
        left: 15px;
        background: rgba(26, 26, 26, 0.9);
        backdrop-filter: blur(10px);
        padding: 8px 12px;
        border-radius: 8px;
        color: #fff;
        font-size: 0.875rem;
        border: 1px solid rgba(229, 9, 20, 0.3);
        z-index: 1000;
    }
    
    /* Loading overlay */
    .mindmap-loading {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(26, 26, 26, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        border-radius: 16px;
    }
    
    .mindmap-loading .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.3em;
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-journal-text display-1 text-danger mb-4"></i>
        <h1 class="display-5">📚 Notas e Anotações</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Sistema profissional de organização de anotações com categorização inteligente, busca avançada e mapas mentais interativos.</p>
    </div>
</div>

<!-- Estatísticas -->
<div class="row g-4 mt-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-journal-text feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo $stats['total_notas']; ?></h5>
                <p class="text-white-50 mb-0">Total de Notas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-book feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo $stats['total_cursos']; ?></h5>
                <p class="text-white-50 mb-0">Cursos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-tags feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo $stats['categorias_unicas']; ?></h5>
                <p class="text-white-50 mb-0">Categorias</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-calendar feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo $stats['notas_hoje']; ?></h5>
                <p class="text-white-50 mb-0">Criadas Hoje</p>
            </div>
        </div>
    </div>
</div>

<!-- Abas: Notas e Mapa Mental -->
<div class="row mt-4">
    <div class="col-12">
        <ul class="nav nav-tabs" id="notasTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="notas-tab" data-bs-toggle="tab" data-bs-target="#notas" type="button" role="tab">
                    <i class="bi bi-journal-text me-2"></i>Notas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mapa-tab" data-bs-toggle="tab" data-bs-target="#mapa" type="button" role="tab">
                    <i class="bi bi-diagram-3 me-2"></i>Mapa Mental
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content mt-4" id="notasTabsContent">
    <!-- Aba Notas -->
    <div class="tab-pane fade show active" id="notas" role="tabpanel">
        <!-- Filtros e Busca -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card filter-card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-search me-2"></i>Buscar
                                </label>
                                <input type="text" name="busca" class="form-control search-box" 
                                       placeholder="Digite para buscar..." value="<?php echo htmlspecialchars($busca); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-tags me-2"></i>Categoria
                                </label>
                                <select name="categoria" class="form-select">
                                    <?php foreach ($categorias as $key => $nome): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $categoria_selecionada === $key ? 'selected' : ''; ?>>
                                            <?php echo $nome; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-book me-2"></i>Curso
                                </label>
                                <select name="curso" class="form-select">
                                    <option value="todos" <?php echo $curso_selecionado === 'todos' ? 'selected' : ''; ?>>Todos os Cursos</option>
                                    <?php foreach ($cursos as $curso): ?>
                                        <option value="<?php echo $curso['id']; ?>" <?php echo $curso_selecionado == $curso['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($curso['nome_curso']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-funnel me-2"></i>Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botão Nova Nota -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="bi bi-journal-text me-2"></i>Suas Anotações
                        <span class="badge bg-secondary ms-2"><?php echo count($notas); ?></span>
                    </h4>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaNota">
                        <i class="bi bi-plus-circle me-2"></i>Nova Anotação
                    </button>
                </div>
            </div>
        </div>

        <!-- Lista de Notas -->
        <div class="row g-4 mt-3">
            <?php if (empty($notas)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body empty-state">
                            <i class="bi bi-journal-x"></i>
                            <h5>Nenhuma anotação encontrada</h5>
                            <p class="text-muted">Comece criando sua primeira anotação ou ajuste os filtros de busca.</p>
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaNota">
                                <i class="bi bi-plus-circle me-2"></i>Criar Primeira Anotação
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notas as $nota): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card note-card h-100">
                            <div class="note-priority priority-<?php echo $nota['prioridade'] ?? 'baixa'; ?>"></div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title mb-0 fw-bold" style="color: var(--bs-body-color, #fff);">
                                        <?php echo htmlspecialchars($nota['titulo']); ?>
                                    </h6>
                                    <div class="note-actions">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="editarNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-pencil me-2"></i>Editar
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="duplicarNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-files me-2"></i>Duplicar
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="criarMapaMental(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-diagram-3 me-2"></i>Criar Mapa Mental
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="excluirNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-trash me-2"></i>Excluir
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="note-content mb-3">
                                    <?php echo nl2br(htmlspecialchars(substr($nota['conteudo'], 0, 200))); ?>
                                    <?php if (strlen($nota['conteudo']) > 200): ?>
                                        <span class="text-muted">...</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="category-badge bg-<?php echo $nota['categoria'] === 'conceitos' ? 'primary' : ($nota['categoria'] === 'exercicios' ? 'success' : ($nota['categoria'] === 'dicas' ? 'warning' : 'secondary')); ?>">
                                            <?php echo ucfirst($nota['categoria']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($nota['data_criacao'])); ?>
                                    </small>
                                </div>
                                
                                <?php if ($nota['nome_curso']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-book me-1"></i>
                                            <?php echo htmlspecialchars($nota['nome_curso']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aba Mapa Mental -->
    <div class="tab-pane fade" id="mapa" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">
                        <i class="bi bi-diagram-3 me-2"></i>Mapas Mentais
                    </h4>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovoMapa">
                        <i class="bi bi-plus-circle me-2"></i>Novo Mapa Mental
                    </button>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div id="mapas-mentais-lista" class="row g-3">
                    <!-- Mapas mentais serão carregados aqui via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Nota -->
<div class="modal fade" id="modalNovaNota" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Nova Anotação
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovaNota">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Título da Anotação</label>
                            <input type="text" name="titulo" class="form-control" placeholder="Ex: Conceitos de JavaScript" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Categoria</label>
                            <select name="categoria" class="form-select" required>
                                <option value="">Selecione</option>
                                <?php foreach ($categorias as $key => $nome): ?>
                                    <?php if ($key !== 'todas'): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $nome; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Curso Relacionado</label>
                            <select name="id_curso" class="form-select">
                                <option value="">Selecione um curso (opcional)</option>
                                <?php foreach ($cursos as $curso): ?>
                                    <option value="<?php echo $curso['id']; ?>">
                                        <?php echo htmlspecialchars($curso['nome_curso']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="baixa">Baixa</option>
                                <option value="media">Média</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Conteúdo</label>
                            <textarea name="conteudo" class="form-control" rows="8" 
                                      placeholder="Digite sua anotação aqui..." required></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnSalvarNota">
                    <i class="bi bi-check-lg me-2"></i>Salvar Anotação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Nota -->
<div class="modal fade" id="modalEditarNota" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Editar Anotação
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarNota">
                    <input type="hidden" name="id" id="edit-nota-id">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Título da Anotação</label>
                            <input type="text" name="titulo" id="edit-nota-titulo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Categoria</label>
                            <select name="categoria" id="edit-nota-categoria" class="form-select" required>
                                <option value="">Selecione</option>
                                <?php foreach ($categorias as $key => $nome): ?>
                                    <?php if ($key !== 'todas'): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $nome; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Curso Relacionado</label>
                            <select name="id_curso" id="edit-nota-curso" class="form-select">
                                <option value="">Selecione um curso (opcional)</option>
                                <?php foreach ($cursos as $curso): ?>
                                    <option value="<?php echo $curso['id']; ?>">
                                        <?php echo htmlspecialchars($curso['nome_curso']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Prioridade</label>
                            <select name="prioridade" id="edit-nota-prioridade" class="form-select">
                                <option value="baixa">Baixa</option>
                                <option value="media">Média</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Conteúdo</label>
                            <textarea name="conteudo" id="edit-nota-conteudo" class="form-control" rows="8" required></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnAtualizarNota">
                    <i class="bi bi-check-lg me-2"></i>Atualizar Anotação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Mapa Mental -->
<div class="modal fade" id="modalNovoMapa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-diagram-3 me-2"></i>Novo Mapa Mental
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Título do Mapa Mental</label>
                    <input type="text" id="mapa-titulo" class="form-control" placeholder="Ex: Estrutura de Dados">
                </div>
                <div class="alert alert-info mb-3 border-0" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%); border-left: 4px solid #0d6efd !important;">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-info-circle me-2 fs-5"></i>
                        <div>
                            <strong class="d-block mb-1">Como usar:</strong>
                            <small class="d-block mb-1">• <strong>Duplo clique</strong> no canvas para adicionar nó</small>
                            <small class="d-block mb-1">• <strong>Duplo clique</strong> no nó para editar texto</small>
                            <small class="d-block mb-1">• <strong>Arraste</strong> para mover nós</small>
                            <small class="d-block">• <strong>Botão direito</strong> para excluir nó</small>
                        </div>
                    </div>
                </div>
                <div id="mindmap-container">
                    <canvas id="mindmap-canvas"></canvas>
                    <div class="zoom-indicator" id="zoom-indicator" style="display: none;">
                        <i class="bi bi-zoom-in me-1"></i><span id="zoom-value">100%</span>
                    </div>
                </div>
                <div class="mindmap-controls">
                    <button class="btn btn-sm btn-primary" onclick="adicionarNoMapa()" title="Adicionar Nó">
                        <i class="bi bi-plus-circle me-1"></i><span>Adicionar</span>
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="limparMapa()" title="Limpar">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><span>Limpar</span>
                    </button>
                    <button class="btn btn-sm btn-success" onclick="salvarMapaMental()" title="Salvar">
                        <i class="bi bi-save me-1"></i><span>Salvar</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="salvarMapaMental()">
                    <i class="bi bi-check-lg me-2"></i>Salvar Mapa Mental
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizar Mapa Mental -->
<div class="modal fade" id="modalVisualizarMapa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="mapa-visualizar-titulo">
                    <i class="bi bi-diagram-3 me-2"></i>Mapa Mental
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="mindmap-visualizar-container" style="width: 100%; height: 600px; border: 1px solid var(--border-color, #333); border-radius: 12px; position: relative; overflow: hidden;">
                    <canvas id="mindmap-visualizar-canvas"></canvas>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Sistema de Mapa Mental Customizado com Canvas (100% compatível)
class MindMap {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        
        this.ctx = this.canvas.getContext('2d');
        this.nodes = [];
        this.edges = [];
        this.selectedNode = null;
        this.dragging = false;
        this.dragOffset = { x: 0, y: 0 };
        this.offset = { x: 0, y: 0 };
        this.scale = 1;
        this.nodeIdCounter = 1;
        
        this.setupCanvas();
        this.setupEvents();
        this.addDefaultNode();
        this.draw();
    }
    
    setupCanvas() {
        const container = this.canvas.parentElement;
        const dpr = window.devicePixelRatio || 1;
        
        const resize = () => {
            const rect = container.getBoundingClientRect();
            this.canvas.width = rect.width * dpr;
            this.canvas.height = rect.height * dpr;
            this.canvas.style.width = rect.width + 'px';
            this.canvas.style.height = rect.height + 'px';
            this.ctx.scale(dpr, dpr);
            this.draw();
        };
        
        resize();
        
        // Debounce resize para performance
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(resize, 250);
        });
    }
    
    setupEvents() {
        // Mouse events
        this.canvas.addEventListener('mousedown', (e) => this.onMouseDown(e));
        this.canvas.addEventListener('mousemove', (e) => this.onMouseMove(e));
        this.canvas.addEventListener('mouseup', (e) => this.onMouseUp(e));
        this.canvas.addEventListener('mouseleave', (e) => this.onMouseLeave(e));
        this.canvas.addEventListener('dblclick', (e) => this.onDoubleClick(e));
        this.canvas.addEventListener('contextmenu', (e) => this.onRightClick(e));
        this.canvas.addEventListener('wheel', (e) => this.onWheel(e), { passive: false });
        
        // Touch events para mobile
        this.canvas.addEventListener('touchstart', (e) => this.onTouchStart(e), { passive: false });
        this.canvas.addEventListener('touchmove', (e) => this.onTouchMove(e), { passive: false });
        this.canvas.addEventListener('touchend', (e) => this.onTouchEnd(e));
    }
    
    addDefaultNode() {
        if (this.nodes.length === 0) {
            this.addNode('Tema Central', this.canvas.width / 2, this.canvas.height / 2, true);
        }
    }
    
    addNode(text, x, y, isCentral = false) {
        const node = {
            id: this.nodeIdCounter++,
            text: text || 'Novo Nó',
            x: x || (this.canvas.width / (window.devicePixelRatio || 1)) / 2,
            y: y || (this.canvas.height / (window.devicePixelRatio || 1)) / 2,
            width: 0, // Será calculado
            height: 50,
            isCentral: isCentral,
            color: isCentral ? this.colors.central.bg : this.colors.node.bg,
            borderColor: isCentral ? this.colors.central.border : this.colors.node.border,
            textColor: isCentral ? this.colors.central.text : this.colors.node.text,
            hover: false,
            pulse: 0
        };
        
        // Calcular largura baseada no texto
        this.ctx.font = isCentral ? 'bold 16px Arial' : '14px Arial';
        const metrics = this.ctx.measureText(node.text);
        node.width = Math.max(120, metrics.width + 30);
        
        this.nodes.push(node);
        
        // Animação de entrada
        node.pulse = 1;
        setTimeout(() => {
            node.pulse = 0;
        }, 300);
        
        return node;
    }
    
    removeNode(nodeId) {
        this.nodes = this.nodes.filter(n => n.id !== nodeId);
        this.edges = this.edges.filter(e => e.from !== nodeId && e.to !== nodeId);
        this.draw();
    }
    
    addEdge(fromId, toId) {
        // Verificar se já existe
        if (this.edges.some(e => e.from === fromId && e.to === toId)) {
            return;
        }
        this.edges.push({ from: fromId, to: toId });
        this.draw();
    }
    
    getNodeAt(x, y) {
        // Ajustar coordenadas para o offset e scale
        const adjustedX = (x - this.offset.x) / this.scale;
        const adjustedY = (y - this.offset.y) / this.scale;
        
        for (let i = this.nodes.length - 1; i >= 0; i--) {
            const node = this.nodes[i];
            const nodeWidth = node.width * (node.hover ? 1.05 : 1);
            const nodeHeight = node.height * (node.hover ? 1.05 : 1);
            
            if (adjustedX >= node.x - nodeWidth/2 && adjustedX <= node.x + nodeWidth/2 &&
                adjustedY >= node.y - nodeHeight/2 && adjustedY <= node.y + nodeHeight/2) {
                return node;
            }
        }
        return null;
    }
    
    onMouseDown(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / this.scale;
        const y = (e.clientY - rect.top) / this.scale;
        
        const node = this.getNodeAt(x, y);
        if (node) {
            this.selectedNode = node;
            this.dragging = true;
            this.dragOffset.x = x - node.x;
            this.dragOffset.y = y - node.y;
        } else {
            this.selectedNode = null;
        }
    }
    
    onMouseMove(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / this.scale;
        const y = (e.clientY - rect.top) / this.scale;
        
        if (this.dragging && this.selectedNode) {
            this.selectedNode.x = x - this.dragOffset.x;
            this.selectedNode.y = y - this.dragOffset.y;
        } else {
            // Verificar hover
            const node = this.getNodeAt(x, y);
            if (node !== this.hoveredNode) {
                if (this.hoveredNode) {
                    this.hoveredNode.hover = false;
                }
                this.hoveredNode = node;
                if (node) {
                    node.hover = true;
                    this.canvas.style.cursor = 'pointer';
                } else {
                    this.canvas.style.cursor = 'grab';
                }
            }
        }
    }
    
    onMouseUp(e) {
        this.dragging = false;
        this.canvas.style.cursor = 'grab';
    }
    
    onMouseLeave(e) {
        if (this.hoveredNode) {
            this.hoveredNode.hover = false;
            this.hoveredNode = null;
        }
        this.dragging = false;
    }
    
    // Touch events para mobile
    onTouchStart(e) {
        e.preventDefault();
        if (e.touches.length === 1) {
            const touch = e.touches[0];
            const rect = this.canvas.getBoundingClientRect();
            const x = (touch.clientX - rect.left) / this.scale;
            const y = (touch.clientY - rect.top) / this.scale;
            
            const node = this.getNodeAt(x, y);
            if (node) {
                this.selectedNode = node;
                this.dragging = true;
                this.dragOffset.x = x - node.x;
                this.dragOffset.y = y - node.y;
            }
        }
    }
    
    onTouchMove(e) {
        e.preventDefault();
        if (this.dragging && this.selectedNode && e.touches.length === 1) {
            const touch = e.touches[0];
            const rect = this.canvas.getBoundingClientRect();
            const x = (touch.clientX - rect.left) / this.scale;
            const y = (touch.clientY - rect.top) / this.scale;
            
            this.selectedNode.x = x - this.dragOffset.x;
            this.selectedNode.y = y - this.dragOffset.y;
        }
    }
    
    onTouchEnd(e) {
        this.dragging = false;
    }
    
    onDoubleClick(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / this.scale;
        const y = (e.clientY - rect.top) / this.scale;
        
        const node = this.getNodeAt(x, y);
        if (node) {
            // Editar nó existente
            const novoTexto = prompt('Editar texto do nó:', node.text);
            if (novoTexto !== null && novoTexto.trim() !== '') {
                node.text = novoTexto.trim();
                this.draw();
            }
        } else {
            // Adicionar novo nó
            const novoTexto = prompt('Digite o texto do novo nó:', 'Novo Nó');
            if (novoTexto !== null && novoTexto.trim() !== '') {
                const newNode = this.addNode(novoTexto.trim(), x, y);
                // Conectar ao nó central se existir
                const centralNode = this.nodes.find(n => n.isCentral);
                if (centralNode && centralNode.id !== newNode.id) {
                    this.addEdge(centralNode.id, newNode.id);
                }
            }
        }
    }
    
    onRightClick(e) {
        e.preventDefault();
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / this.scale;
        const y = (e.clientY - rect.top) / this.scale;
        
        const node = this.getNodeAt(x, y);
        if (node && !node.isCentral) {
            if (confirm('Deseja excluir este nó?')) {
                this.removeNode(node.id);
            }
        }
    }
    
    onWheel(e) {
        e.preventDefault();
        const now = Date.now();
        if (now - this.lastZoomTime < 50) return; // Throttle zoom
        this.lastZoomTime = now;
        
        const rect = this.canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        
        const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
        const newScale = Math.max(0.5, Math.min(2, this.scale * zoomFactor));
        
        // Zoom em direção ao mouse
        const scaleChange = newScale / this.scale;
        this.offset.x = mouseX - (mouseX - this.offset.x) * scaleChange;
        this.offset.y = mouseY - (mouseY - this.offset.y) * scaleChange;
        
        this.scale = newScale;
        
        // Atualizar indicador de zoom
        const zoomIndicator = document.getElementById('zoom-indicator');
        const zoomValue = document.getElementById('zoom-value');
        if (zoomIndicator && zoomValue) {
            zoomIndicator.style.display = 'block';
            zoomValue.textContent = Math.round(this.scale * 100) + '%';
            setTimeout(() => {
                zoomIndicator.style.display = 'none';
            }, 2000);
        }
    }
    
    draw() {
        const dpr = window.devicePixelRatio || 1;
        const width = this.canvas.width / dpr;
        const height = this.canvas.height / dpr;
        
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.save();
        this.ctx.translate(this.offset.x, this.offset.y);
        this.ctx.scale(this.scale, this.scale);
        
        // Grid de fundo sutil
        this.drawGrid(width, height);
        
        // Desenhar arestas
        this.edges.forEach(edge => {
            const fromNode = this.nodes.find(n => n.id === edge.from);
            const toNode = this.nodes.find(n => n.id === edge.to);
            if (fromNode && toNode) {
                this.drawEdge(fromNode, toNode);
            }
        });
        
        // Desenhar nós (central primeiro)
        const centralNodes = this.nodes.filter(n => n.isCentral);
        const otherNodes = this.nodes.filter(n => !n.isCentral);
        [...centralNodes, ...otherNodes].forEach(node => {
            this.drawNode(node);
        });
        
        this.ctx.restore();
    }
    
    drawGrid(width, height) {
        this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.03)';
        this.ctx.lineWidth = 1;
        
        const gridSize = 50;
        const startX = -this.offset.x % gridSize;
        const startY = -this.offset.y % gridSize;
        
        for (let x = startX; x < width; x += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(x, 0);
            this.ctx.lineTo(x, height);
            this.ctx.stroke();
        }
        
        for (let y = startY; y < height; y += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(0, y);
            this.ctx.lineTo(width, y);
            this.ctx.stroke();
        }
    }
    
    drawEdge(fromNode, toNode) {
        const isHovered = fromNode.hover || toNode.hover;
        const edgeColor = isHovered ? this.colors.edgeHover : this.colors.edge;
        
        // Gradiente para a linha
        const gradient = this.ctx.createLinearGradient(
            fromNode.x, fromNode.y,
            toNode.x, toNode.y
        );
        gradient.addColorStop(0, edgeColor);
        gradient.addColorStop(1, edgeColor + '80');
        
        this.ctx.strokeStyle = gradient;
        this.ctx.lineWidth = isHovered ? 3 : 2;
        this.ctx.shadowBlur = isHovered ? 8 : 4;
        this.ctx.shadowColor = edgeColor + '40';
        
        // Linha curva suave
        this.ctx.beginPath();
        const midX = (fromNode.x + toNode.x) / 2;
        const midY = (fromNode.y + toNode.y) / 2;
        const cpX = midX + (toNode.y - fromNode.y) * 0.2;
        const cpY = midY - (toNode.x - fromNode.x) * 0.2;
        
        this.ctx.moveTo(fromNode.x, fromNode.y);
        this.ctx.quadraticCurveTo(cpX, cpY, toNode.x, toNode.y);
        this.ctx.stroke();
        
        // Seta moderna
        const angle = Math.atan2(toNode.y - cpY, toNode.x - cpX);
        const arrowLength = 12;
        const arrowAngle = Math.PI / 6;
        const arrowX = toNode.x - Math.cos(angle) * (toNode.width / 2 + 5);
        const arrowY = toNode.y - Math.sin(angle) * (toNode.height / 2 + 5);
        
        this.ctx.beginPath();
        this.ctx.moveTo(arrowX, arrowY);
        this.ctx.lineTo(
            arrowX - arrowLength * Math.cos(angle - arrowAngle),
            arrowY - arrowLength * Math.sin(angle - arrowAngle)
        );
        this.ctx.lineTo(
            arrowX - arrowLength * Math.cos(angle + arrowAngle),
            arrowY - arrowLength * Math.sin(angle + arrowAngle)
        );
        this.ctx.closePath();
        this.ctx.fillStyle = edgeColor;
        this.ctx.fill();
        
        this.ctx.shadowBlur = 0;
    }
    
    drawNode(node) {
        const scale = node.hover ? 1.05 : (1 + node.pulse * 0.1);
        const currentWidth = node.width * scale;
        const currentHeight = node.height * scale;
        
        // Sombra moderna
        this.ctx.shadowColor = node.hover ? 'rgba(229, 9, 20, 0.4)' : 'rgba(0, 0, 0, 0.3)';
        this.ctx.shadowBlur = node.hover ? 15 : 8;
        this.ctx.shadowOffsetX = 0;
        this.ctx.shadowOffsetY = node.hover ? 4 : 2;
        
        // Gradiente de fundo
        const gradient = this.ctx.createLinearGradient(
            node.x - currentWidth / 2, node.y - currentHeight / 2,
            node.x + currentWidth / 2, node.y + currentHeight / 2
        );
        
        if (node.hover) {
            gradient.addColorStop(0, this.colors.hover.bg);
            gradient.addColorStop(1, this.colors.hover.bg + 'dd');
        } else {
            gradient.addColorStop(0, node.color);
            gradient.addColorStop(1, node.color + 'dd');
        }
        
        this.ctx.fillStyle = gradient;
        this.ctx.strokeStyle = node.hover ? this.colors.hover.border : node.borderColor;
        this.ctx.lineWidth = node.hover ? 3 : 2;
        
        // Retângulo arredondado
        const radius = 12;
        this.ctx.beginPath();
        this.ctx.roundRect(
            node.x - currentWidth / 2,
            node.y - currentHeight / 2,
            currentWidth,
            currentHeight,
            radius
        );
        this.ctx.fill();
        this.ctx.stroke();
        
        // Resetar sombra
        this.ctx.shadowColor = 'transparent';
        this.ctx.shadowBlur = 0;
        
        // Texto com sombra
        this.ctx.fillStyle = node.hover ? this.colors.hover.text : node.textColor;
        this.ctx.font = node.isCentral ? 'bold 16px Arial' : '14px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'middle';
        
        // Sombra no texto para melhor legibilidade
        this.ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
        this.ctx.shadowBlur = 2;
        this.ctx.shadowOffsetX = 1;
        this.ctx.shadowOffsetY = 1;
        
        // Quebrar texto se muito longo
        const maxWidth = currentWidth - 20;
        const words = node.text.split(' ');
        let line = '';
        let y = node.y - (words.length > 1 ? 8 : 0);
        
        for (let i = 0; i < words.length; i++) {
            const testLine = line + words[i] + ' ';
            const metrics = this.ctx.measureText(testLine);
            if (metrics.width > maxWidth && i > 0) {
                this.ctx.fillText(line, node.x, y);
                line = words[i] + ' ';
                y += 18;
            } else {
                line = testLine;
            }
        }
        this.ctx.fillText(line, node.x, y);
        
        this.ctx.shadowColor = 'transparent';
        this.ctx.shadowBlur = 0;
        
        // Reduzir pulse
        if (node.pulse > 0) {
            node.pulse *= 0.9;
        }
    }
    
    getData() {
        return {
            nodes: this.nodes.map(n => ({
                id: n.id,
                text: n.text,
                x: n.x,
                y: n.y,
                isCentral: n.isCentral,
                color: n.color
            })),
            edges: this.edges
        };
    }
    
    loadData(data) {
        if (data && data.nodes) {
            this.nodes = data.nodes.map(n => ({
                ...n,
                width: 120,
                height: 40
            }));
            this.edges = data.edges || [];
            if (this.nodes.length > 0) {
                this.nodeIdCounter = Math.max(...this.nodes.map(n => n.id)) + 1;
            }
            this.draw();
        }
    }
    
    clear() {
        this.nodes = [];
        this.edges = [];
        this.nodeIdCounter = 1;
        this.hoveredNode = null;
        this.selectedNode = null;
        this.addDefaultNode();
    }
    
    destroy() {
        this.stopAnimation();
        this.nodes = [];
        this.edges = [];
    }
}

// Variáveis globais
let mindMapInstance = null;
let mindMapVisualizar = null;

document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
    // Salvar nova nota
    const btnSalvarNota = document.getElementById('btnSalvarNota');
    if (btnSalvarNota) {
        btnSalvarNota.addEventListener('click', function() {
            const form = document.getElementById('formNovaNota');
            const formData = new FormData(form);
            const button = this;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
            
            fetch('salvar_nota_curso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Anotação salva com sucesso!');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovaNota'));
                    if (modal) modal.hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Anotação';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro!', 'Erro de conexão', true);
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Anotação';
            });
        });
    }
    
    // Atualizar nota
    const btnAtualizarNota = document.getElementById('btnAtualizarNota');
    if (btnAtualizarNota) {
        btnAtualizarNota.addEventListener('click', function() {
            const form = document.getElementById('formEditarNota');
            const formData = new FormData(form);
            const button = this;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Atualizando...';
            
            fetch('atualizar_nota_curso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Anotação atualizada com sucesso!');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarNota'));
                    if (modal) modal.hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Atualizar Anotação';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro!', 'Erro de conexão', true);
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Atualizar Anotação';
            });
        });
    }
    
    // Inicializar mapa mental quando modal abrir
    const modalNovoMapa = document.getElementById('modalNovoMapa');
    if (modalNovoMapa) {
        modalNovoMapa.addEventListener('shown.bs.modal', function() {
            if (!mindMapInstance) {
                mindMapInstance = new MindMap('mindmap-canvas');
            }
        });
        
        modalNovoMapa.addEventListener('hidden.bs.modal', function() {
            const tituloInput = document.getElementById('mapa-titulo');
            if (tituloInput) tituloInput.value = '';
            if (mindMapInstance) {
                mindMapInstance.clear();
            }
        });
    }
    
    // Carregar mapas mentais salvos
    window.carregarMapasMentais = function() {
        fetch('buscar_mapas_mentais.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.mapas) {
                    const container = document.getElementById('mapas-mentais-lista');
                    if (container) {
                        if (data.mapas.length === 0) {
                            container.innerHTML = `
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-body text-center p-5">
                                            <i class="bi bi-diagram-3 fs-1 text-muted"></i>
                                            <h5 class="mt-3 text-muted">Nenhum mapa mental criado</h5>
                                            <p class="text-muted mb-0">Crie seu primeiro mapa mental para visualizar conceitos de forma interativa.</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            container.innerHTML = data.mapas.map(mapa => `
                                <div class="col-md-6 col-lg-4">
                                    <div class="card note-card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title fw-bold" style="color: var(--bs-body-color, #fff);">${escapeHTML(mapa.titulo)}</h6>
                                            <p class="text-muted small mb-2">
                                                <i class="bi bi-calendar me-1"></i>
                                                ${new Date(mapa.data_criacao).toLocaleDateString('pt-BR')}
                                            </p>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-primary" onclick="visualizarMapa(${mapa.id}, '${escapeHTML(mapa.titulo)}', ${escapeHTML(JSON.stringify(mapa.dados))})">
                                                    <i class="bi bi-eye me-1"></i>Visualizar
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="excluirMapa(${mapa.id})">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar mapas:', error);
            });
    };
    
    // Carregar mapas quando a aba for ativada
    const mapaTab = document.getElementById('mapa-tab');
    if (mapaTab) {
        mapaTab.addEventListener('shown.bs.tab', function() {
            carregarMapasMentais();
        });
    }
});

// Função para escapar HTML
function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Funções para ações das notas
function editarNota(id) {
    fetch(`buscar_nota.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.nota) {
                const nota = data.nota;
                document.getElementById('edit-nota-id').value = nota.id;
                document.getElementById('edit-nota-titulo').value = nota.titulo || '';
                document.getElementById('edit-nota-categoria').value = nota.categoria || '';
                document.getElementById('edit-nota-curso').value = nota.id_curso || '';
                document.getElementById('edit-nota-prioridade').value = nota.prioridade || 'baixa';
                document.getElementById('edit-nota-conteudo').value = nota.conteudo || '';
                
                const modal = new bootstrap.Modal(document.getElementById('modalEditarNota'));
                modal.show();
            } else {
                showToast('Erro!', data.message || 'Nota não encontrada', true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro de conexão', true);
        });
}

function duplicarNota(id) {
    if (!confirm('Deseja duplicar esta anotação?')) return;
    
    fetch('duplicar_nota_curso.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Anotação duplicada com sucesso!');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    });
}

function excluirNota(id) {
    if (!confirm('Tem certeza que deseja excluir esta anotação?')) return;
    
    fetch('excluir_nota_curso.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Anotação excluída com sucesso!');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    });
}

// Funções para mapa mental
function adicionarNoMapa() {
    if (!mindMapInstance) {
        showToast('Erro!', 'Mapa mental não inicializado', true);
        return;
    }
    
    const novoTexto = prompt('Digite o texto do novo nó:', 'Novo Nó');
    if (novoTexto !== null && novoTexto.trim() !== '') {
        const centralNode = mindMapInstance.nodes.find(n => n.isCentral);
        const x = centralNode ? centralNode.x + 150 : mindMapInstance.canvas.width / 2 + 150;
        const y = centralNode ? centralNode.y : mindMapInstance.canvas.height / 2;
        
        const newNode = mindMapInstance.addNode(novoTexto.trim(), x, y);
        if (centralNode) {
            mindMapInstance.addEdge(centralNode.id, newNode.id);
        }
    }
}

function limparMapa() {
    if (!confirm('Deseja limpar o mapa mental? Esta ação não pode ser desfeita.')) return;
    
    if (mindMapInstance) {
        mindMapInstance.clear();
    }
}

function salvarMapaMental() {
    const tituloInput = document.getElementById('mapa-titulo');
    if (!tituloInput) {
        showToast('Erro!', 'Campo de título não encontrado', true);
        return;
    }
    
    const titulo = tituloInput.value.trim();
    if (!titulo) {
        showToast('Erro!', 'Título é obrigatório', true);
        tituloInput.focus();
        return;
    }
    
    if (!mindMapInstance || mindMapInstance.nodes.length === 0) {
        showToast('Erro!', 'Adicione pelo menos um nó ao mapa', true);
        return;
    }
    
    const dados = mindMapInstance.getData();
    
    // Mostrar loading
    const btnSalvar = document.querySelector('#modalNovoMapa .btn-danger');
    if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    }
    
    fetch('salvar_mapa_mental.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ titulo: titulo, dados: JSON.stringify(dados) })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Mapa mental salvo com sucesso!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovoMapa'));
            if (modal) modal.hide();
            
            // Limpar e resetar
            tituloInput.value = '';
            if (mindMapInstance) {
                mindMapInstance.clear();
            }
            
            // Recarregar lista se estiver na aba de mapas
            const mapaTab = document.getElementById('mapa-tab');
            if (mapaTab && mapaTab.classList.contains('active')) {
                setTimeout(() => {
                    carregarMapasMentais();
                }, 500);
            }
        } else {
            showToast('Erro!', data.message || 'Erro ao salvar mapa mental', true);
            if (btnSalvar) {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Mapa Mental';
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão: ' + error.message, true);
        if (btnSalvar) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Mapa Mental';
        }
    });
}

function visualizarMapa(id, titulo, dadosJson) {
    try {
        const dados = typeof dadosJson === 'string' ? JSON.parse(dadosJson) : dadosJson;
        
        document.getElementById('mapa-visualizar-titulo').innerHTML = `<i class="bi bi-diagram-3 me-2"></i>${escapeHTML(titulo)}`;
        
        const container = document.getElementById('mindmap-visualizar-container');
        if (!container) {
            showToast('Erro!', 'Container de visualização não encontrado', true);
            return;
        }
        
        // Limpar container
        const oldCanvas = document.getElementById('mindmap-visualizar-canvas');
        if (oldCanvas) oldCanvas.remove();
        
        const canvas = document.createElement('canvas');
        canvas.id = 'mindmap-visualizar-canvas';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.display = 'block';
        container.appendChild(canvas);
        
        mindMapVisualizar = new MindMap('mindmap-visualizar-canvas');
        mindMapVisualizar.loadData(dados);
        
        const modal = new bootstrap.Modal(document.getElementById('modalVisualizarMapa'));
        modal.show();
        
        // Limpar ao fechar
        document.getElementById('modalVisualizarMapa').addEventListener('hidden.bs.modal', function() {
            if (mindMapVisualizar) {
                mindMapVisualizar = null;
            }
            if (canvas) canvas.remove();
        }, { once: true });
    } catch (error) {
        console.error('Erro ao visualizar mapa:', error);
        showToast('Erro!', 'Erro ao carregar mapa mental: ' + error.message, true);
    }
}

function excluirMapa(id) {
    if (!confirm('Tem certeza que deseja excluir este mapa mental?')) return;
    
    fetch('excluir_mapa_mental.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Mapa mental excluído com sucesso!');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    });
}

function criarMapaMental(notaId) {
    fetch(`buscar_nota.php?id=${notaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.nota) {
                const nota = data.nota;
                const tituloInput = document.getElementById('mapa-titulo');
                if (tituloInput) {
                    tituloInput.value = nota.titulo + ' - Mapa Mental';
                }
                
                const modal = new bootstrap.Modal(document.getElementById('modalNovoMapa'));
                modal.show();
                
                // Aguardar modal abrir e inicializar mapa
                setTimeout(() => {
                    if (!mindMapInstance) {
                        mindMapInstance = new MindMap('mindmap-canvas');
                    }
                    
                    // Criar nós baseados no conteúdo
                    const palavras = nota.conteudo.split(/\s+/).filter(p => p.length > 3).slice(0, 8);
                    const centralNode = mindMapInstance.nodes.find(n => n.isCentral);
                    if (centralNode) {
                        centralNode.text = nota.titulo.substring(0, 20);
                    }
                    
                    palavras.forEach((palavra, index) => {
                        const palavraLimpa = palavra.replace(/[^\w\s]/g, '').substring(0, 15);
                        if (palavraLimpa.length > 0 && centralNode) {
                            const angle = (index / palavras.length) * Math.PI * 2;
                            const radius = 150;
                            const x = centralNode.x + Math.cos(angle) * radius;
                            const y = centralNode.y + Math.sin(angle) * radius;
                            
                            const newNode = mindMapInstance.addNode(palavraLimpa, x, y);
                            mindMapInstance.addEdge(centralNode.id, newNode.id);
                        }
                    });
                }, 300);
            } else {
                showToast('Erro!', data.message || 'Nota não encontrada', true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro ao buscar nota: ' + error.message, true);
        });
}
</script>

<?php
require_once 'templates/footer.php';
?>
