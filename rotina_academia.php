<?php
// rotina_academia.php (Versão Redesenhada - Design Premium)
require_once 'templates/header.php';
require_once 'includes/exercicios_padrao.php';

$dias_da_semana = [1 => 'Domingo', 2 => 'Segunda', 3 => 'Terça', 4 => 'Quarta', 5 => 'Quinta', 6 => 'Sexta', 7 => 'Sábado'];
$dias_abrev = [1 => 'DOM', 2 => 'SEG', 3 => 'TER', 4 => 'QUA', 5 => 'QUI', 6 => 'SEX', 7 => 'SAB'];
$rotina_completa = [];
$rotina_salva = [];

try {
    $sql_rotina_dias = "SELECT rd.id as id_dia, rd.dia_semana, rd.nome_treino FROM rotinas r JOIN rotina_dias rd ON r.id = rd.id_rotina WHERE r.id_usuario = ? AND r.ativo = 1";
    $stmt_rotina_dias = $pdo->prepare($sql_rotina_dias);
    $stmt_rotina_dias->execute([$userId]);
    $dias_com_treino = $stmt_rotina_dias->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($dias_da_semana as $num => $nome) {
        $rotina_completa[$num] = ['id_dia' => null, 'nome_treino' => '', 'exercicios' => []];
    }
    
    foreach ($dias_com_treino as $dia) {
        $rotina_completa[$dia['dia_semana']] = ['id_dia' => $dia['id_dia'], 'nome_treino' => $dia['nome_treino'] ?: '', 'exercicios' => []];
        $rotina_salva[$dia['dia_semana']] = $dia['nome_treino'];
    }
    
    $sql_exercicios = "SELECT re.id, re.id_rotina_dia, e.nome_exercicio, re.series_sugeridas, re.repeticoes_sugeridas FROM rotina_exercicios re JOIN exercicios e ON re.id_exercicio = e.id JOIN rotina_dias rd ON re.id_rotina_dia = rd.id JOIN rotinas r ON rd.id_rotina = r.id WHERE r.id_usuario = ? AND r.ativo = 1 ORDER BY COALESCE(re.ordem, re.id)";
    $stmt_exercicios = $pdo->prepare($sql_exercicios);
    $stmt_exercicios->execute([$userId]);
    
    foreach ($stmt_exercicios->fetchAll(PDO::FETCH_ASSOC) as $ex) {
        foreach ($rotina_completa as &$dia_data) {
            if ($dia_data['id_dia'] == $ex['id_rotina_dia']) {
                $dia_data['exercicios'][] = $ex;
                break;
            }
        }
    }
} catch (PDOException $e) {
    die("Erro ao buscar rotina: " . $e->getMessage());
}

// Calcular estatísticas
$diasAtivos = count(array_filter($rotina_completa, function($dia) { return !empty($dia['exercicios']); }));
$totalExercicios = array_sum(array_map(function($dia) { return count($dia['exercicios']); }, $rotina_completa));
$totalSeries = 0;
foreach ($rotina_completa as $dia) {
    foreach ($dia['exercicios'] as $ex) {
        $totalSeries += intval($ex['series_sugeridas'] ?? 0);
    }
}
$tempoEstimado = $totalSeries * 2; // 2 minutos por série aproximadamente
?>

<style>
/* ================================================== */
/* ROTINA ACADEMIA - DESIGN REDESIGNED PREMIUM V2 */
/* ================================================== */

@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
    --gym-primary: #ff1e2d; /* Crimson Modern */
    --gym-primary-rgb: 255, 30, 45;
    --gym-accent: #ff4757;
    --gym-gradient-premium: linear-gradient(135deg, #ff1e2d 0%, #b30710 100%);
    --gym-glass-bg: rgba(26, 26, 26, 0.7);
    --gym-glass-border: rgba(255, 255, 255, 0.08);
    --gym-card-bg: #121212;
    --gym-text-main: #ffffff;
    --gym-text-sub: #b0b0b0;
    --gym-bg-dark: #0a0a0a;
    --gym-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
    --gym-glow: 0 0 20px rgba(255, 30, 45, 0.2);
}

.gym-page {
    font-family: 'Outfit', sans-serif;
    background: var(--gym-bg-dark);
    min-height: 100vh;
    padding-bottom: 4rem;
    color: var(--gym-text-main);
}

/* Glassmorphism Generic Class */
.gym-glass {
    background: var(--gym-glass-bg);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--gym-glass-border);
    border-radius: 20px;
    box-shadow: var(--gym-shadow);
}

/* Header Section Refactored */
.gym-header {
    background: linear-gradient(180deg, rgba(255, 30, 45, 0.1) 0%, transparent 100%);
    border-radius: 30px;
    padding: 3rem 2rem;
    margin-bottom: 2.5rem;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 30, 45, 0.15);
}

.gym-header::after {
    content: '';
    position: absolute;
    top: -100px;
    right: -50px;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255, 30, 45, 0.1) 0%, transparent 70%);
    filter: blur(40px);
}

.gym-title {
    font-size: 2.8rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 1.2rem;
}

.gym-title i {
    color: var(--gym-primary);
    filter: drop-shadow(0 0 8px rgba(255, 30, 45, 0.5));
}

.gym-subtitle {
    color: var(--gym-text-sub);
    margin-top: 0.8rem;
    font-size: 1.1rem;
    font-weight: 300;
}

.gym-header-actions {
    display: flex;
    gap: 1.2rem;
    margin-top: 1rem;
}

.btn-gym-v2 {
    padding: 0.85rem 2rem;
    border-radius: 14px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: inline-flex;
    align-items: center;
    gap: 0.8rem;
    border: none;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-gym { padding: 12px 24px; border-radius: 50px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: none; font-size: 0.9rem; }
.btn-gym-primary { background: linear-gradient(135deg, var(--gym-primary), #ff2a35); color: white; box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4); }
.btn-gym-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(229, 9, 20, 0.6); color: white; }

/* Select2 Dark Theme Customization */
.select2-container--bootstrap-5 .select2-selection {
    background-color: transparent !important;
    border: 1px solid rgba(255,255,255,0.15) !important;
    color: #fff !important;
    min-height: 48px;
    padding-top: 5px;
}
.select2-container--bootstrap-5 .select2-selection__rendered {
    color: #fff !important;
}
.select2-container--bootstrap-5 .select2-dropdown {
    background-color: #1a1a1e !important;
    border: 1px solid var(--border-color, rgba(255,255,255,0.1)) !important;
    color: #fff !important;
}
.select2-container--bootstrap-5 .select2-results__option {
    color: #fff !important;
}
.select2-container--bootstrap-5 .select2-results__option--highlighted {
    background-color: var(--accent-red, #e50914) !important;
    color: #fff !important;
}
.select2-container--bootstrap-5 .select2-search__field {
    background-color: transparent !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
    color: #fff !important;
}
.btn-gym-v2-primary {
    background: var(--gym-primary);
    color: #fff;
    box-shadow: 0 10px 20px rgba(255, 30, 45, 0.25);
}

.btn-gym-v2-primary:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 15px 30px rgba(255, 30, 45, 0.4);
    background: #ff3341;
}

.btn-gym-v2-outline {
    background: rgba(255, 255, 255, 0.03);
    color: #fff;
    border: 1px solid var(--gym-glass-border);
}

.btn-gym-v2-outline:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--gym-primary);
    transform: translateY(-4px);
}

/* Stats Revitalized */
.gym-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.gym-stat-card-v2 {
    padding: 1.8rem;
    text-align: left;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.gym-stat-card-v2:hover {
    transform: translateY(-8px);
    border-color: rgba(255, 30, 45, 0.3);
}

.gym-stat-icon-v2 {
    font-size: 1.8rem;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 12px;
}

.icon-days { background: rgba(102, 126, 234, 0.1); color: #667eea; }
.icon-ex { background: rgba(0, 210, 106, 0.1); color: #00d26a; }
.icon-time { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
.icon-series { background: rgba(255, 30, 45, 0.1); color: var(--gym-primary); }

.gym-stat-value-v2 {
    font-size: 2.2rem;
    font-weight: 800;
    color: #fff;
    font-family: 'JetBrains Mono', monospace;
}

.gym-stat-label-v2 {
    color: var(--gym-text-sub);
    font-size: 0.9rem;
    font-weight: 500;
    margin-top: 0.2rem;
}

/* Redesigned Week Grid */
.gym-week-v2 {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1rem;
    margin-bottom: 3rem;
}

.gym-day-card-v2 {
    background: #1a1a1a;
    border-radius: 20px;
    border: 1px solid var(--gym-glass-border);
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    display: flex;
    flex-direction: column;
    height: 100%;
}

.gym-day-card-v2.active {
    background: linear-gradient(180deg, #1a1a1a 0%, #121212 100%);
    border-bottom: 3px solid var(--gym-primary);
}

.gym-day-card-v2:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.6);
}

.gym-day-header-v2 {
    padding: 1.2rem;
    text-align: center;
    border-bottom: 1px solid var(--gym-glass-border);
}

.gym-day-name-v2 {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gym-text-sub);
    letter-spacing: 1.5px;
}

.gym-day-abbrev-v2 {
    font-size: 1.4rem;
    font-weight: 800;
    color: #fff;
    margin-top: 0.2rem;
}

.gym-day-body-v2 {
    padding: 1.2rem;
    flex-grow: 1;
}

.treino-info-v2 {
    margin-bottom: 1.2rem;
}

.treino-label-v2 {
    font-size: 0.8rem;
    color: var(--gym-primary);
    font-weight: 700;
    text-transform: uppercase;
}

.treino-name-v2 {
    font-size: 1.05rem;
    font-weight: 600;
    color: #fff;
    margin-top: 0.2rem;
}

.treino-name-v2.empty {
    color: #444;
    font-style: italic;
    font-weight: 400;
}

.ex-counter-v2 {
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--gym-text-sub);
}

.ex-counter-v2 i {
    color: var(--gym-primary);
}

.gym-day-footer-v2 {
    margin-top: auto;
    padding: 1.2rem;
    display: flex;
    gap: 0.8rem;
    border-top: 1px solid var(--gym-glass-border);
}

.btn-action-small {
    flex: 1;
    height: 42px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all 0.3s ease;
    border: none;
    text-transform: uppercase;
}

.btn-action-manage {
    background: rgba(255,255,255,0.05);
    color: #fff;
    border: 1px solid var(--gym-glass-border);
}

.btn-action-manage:hover {
    background: rgba(255,255,255,0.1);
    border-color: #fff;
}

.btn-action-start {
    background: var(--gym-primary);
    color: #fff;
}

.btn-action-start:hover {
    background: var(--gym-accent);
    transform: scale(1.05);
}

/* Modals Refined */
.modal-premium .modal-content {
    background: #141414;
    border: 1px solid var(--gym-glass-border);
    border-radius: 24px;
    overflow: hidden;
}

.modal-premium .modal-header {
    background: var(--gym-gradient-premium);
    padding: 1.8rem;
    border: none;
}

.modal-premium .modal-title {
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.5px;
}

.modal-premium .modal-body {
    padding: 2rem;
}

.modal-premium .form-control, 
.modal-premium .form-select {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--gym-glass-border);
    color: #fff;
    padding: 0.8rem 1rem;
    border-radius: 12px;
}

.modal-premium .form-control:focus {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--gym-primary);
    box-shadow: 0 0 0 4px rgba(255, 30, 45, 0.2);
}

/* Scrollbars */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: var(--gym-primary); }

/* Responsive Adjustments */
@media (max-width: 1400px) {
    .gym-week-v2 { grid-template-columns: repeat(4, 1fr); }
}
@media (max-width: 1100px) {
    .gym-week-v2 { grid-template-columns: repeat(3, 1fr); }
    .gym-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .gym-week-v2 { grid-template-columns: repeat(2, 1fr); }
    .gym-title { font-size: 2rem; }
    .gym-stats { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .gym-week-v2 { grid-template-columns: 1fr; }
    .gym-header { padding: 2rem 1.2rem; }
}
</style>

<div class="gym-page">
    <!-- Header -->
    <div class="container-fluid px-4 pt-4">
        <div class="gym-header" data-aos="fade-down">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center position-relative" style="z-index: 2;">
                <div>
                    <h1 class="gym-title">
                        <i class="bi bi-fire"></i>
                        Rotina de Academia
                    </h1>
                    <p class="gym-subtitle">Gestão profissional de treinos com design de alta performance</p>
                </div>
                <div class="gym-header-actions">
                    <button class="btn-gym-v2 btn-gym-v2-outline" data-bs-toggle="modal" data-bs-target="#modalEditarRotina">
                        <i class="bi bi-sliders2"></i>
                        Ajustar Rotina
                    </button>
                    <button class="btn-gym-v2 btn-gym-v2-primary" data-bs-toggle="modal" data-bs-target="#modalNovoTreino">
                        <i class="bi bi-plus-lg"></i>
                        Novo Treino
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="gym-stats" data-aos="fade-up" data-aos-delay="100">
            <div class="gym-stat-card-v2 gym-glass">
                <div class="gym-stat-icon-v2 icon-days">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="gym-stat-value-v2"><?php echo $diasAtivos; ?></div>
                <div class="gym-stat-label-v2">Dias Ativos</div>
            </div>
            
            <div class="gym-stat-card-v2 gym-glass">
                <div class="gym-stat-icon-v2 icon-ex">
                    <i class="bi bi-lightning-charge"></i>
                </div>
                <div class="gym-stat-value-v2"><?php echo $totalExercicios; ?></div>
                <div class="gym-stat-label-v2">Exercícios</div>
            </div>

            <div class="gym-stat-card-v2 gym-glass">
                <div class="gym-stat-icon-v2 icon-time">
                    <i class="bi bi-stopwatch"></i>
                </div>
                <div class="gym-stat-value-v2"><?php echo $tempoEstimado; ?><small style="font-size: 1rem; margin-left: 5px;">min</small></div>
                <div class="gym-stat-label-v2">Tempo Estimado</div>
            </div>

            <div class="gym-stat-card-v2 gym-glass">
                <div class="gym-stat-icon-v2 icon-series">
                    <i class="bi bi-stack"></i>
                </div>
                <div class="gym-stat-value-v2"><?php echo $totalSeries; ?></div>
                <div class="gym-stat-label-v2">Total de Séries</div>
            </div>
        </div>

        <!-- Week Grid -->
        <div class="gym-week-v2" data-aos="fade-up" data-aos-delay="200">
            <?php foreach ($dias_da_semana as $dia_num => $dia_nome): 
                $treino_do_dia = $rotina_completa[$dia_num];
                $hasExercises = !empty($treino_do_dia['exercicios']);
            ?>
            <div class="gym-day-card-v2 <?php echo $hasExercises ? 'active' : ''; ?>">
                <div class="gym-day-header-v2">
                    <div class="gym-day-name-v2"><?php echo $dia_nome; ?></div>
                    <div class="gym-day-abbrev-v2"><?php echo $dias_abrev[$dia_num]; ?></div>
                </div>
                
                <div class="gym-day-body-v2">
                    <div class="treino-info-v2">
                        <div class="treino-label-v2">Treino do Dia</div>
                        <div class="treino-name-v2 <?php echo empty($treino_do_dia['nome_treino']) ? 'empty' : ''; ?>">
                            <?php echo !empty($treino_do_dia['nome_treino']) ? htmlspecialchars($treino_do_dia['nome_treino']) : 'Descanso'; ?>
                        </div>
                    </div>

                    <?php if ($hasExercises): ?>
                    <div class="ex-counter-v2">
                        <i class="bi bi-check2-circle"></i>
                        <span><?php echo count($treino_do_dia['exercicios']); ?> exercícios prontos</span>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3 opacity-25">
                        <i class="bi bi-moon-stars" style="font-size: 1.5rem;"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="gym-day-footer-v2">
                    <button class="btn-action-small btn-action-manage btn-gerenciar-exercicios" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalGerenciarExercicios" 
                            data-dia-id="<?php echo $treino_do_dia['id_dia']; ?>" 
                            data-dia-nome="<?php echo $dia_nome; ?>" 
                            data-nome-treino="<?php echo htmlspecialchars($treino_do_dia['nome_treino']); ?>">
                        <i class="bi bi-gear-fill"></i>
                        Ficha
                    </button>
                    
                    <?php if ($hasExercises): ?>
                    <button class="btn-action-small btn-action-start btn-iniciar-treino" 
                            data-dia-id="<?php echo $treino_do_dia['id_dia']; ?>"
                            data-dia-nome="<?php echo htmlspecialchars($dia_nome); ?>"
                            data-nome-treino="<?php echo htmlspecialchars($treino_do_dia['nome_treino']); ?>">
                        <i class="bi bi-play-fill"></i>
                        Treinar
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Editar Rotina -->
<div class="modal fade modal-premium" id="modalEditarRotina" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-range me-2"></i>Gestão da Rotina Semanal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditarRotina">
                <div class="modal-body">
                    <div class="alert alert-dark border-0 shadow-sm mb-4" style="background: rgba(255,255,255,0.03); border-left: 4px solid var(--gym-primary) !important;">
                        <div class="d-flex gap-3 align-items-center">
                            <i class="bi bi-info-circle-fill text-primary" style="font-size: 1.2rem;"></i>
                            <div class="small text-sub">Organize sua semana definindo os grupos musculares ou nomes dos treinos para cada dia.</div>
                        </div>
                    </div>
                    <div class="row g-4">
                        <?php foreach ($dias_da_semana as $dia_num => $dia_nome): ?>
                        <div class="col-md-6">
                            <label class="form-label d-flex justify-content-between">
                                <span><?php echo $dia_nome; ?></span>
                                <small class="opacity-50"><?php echo $dias_abrev[$dia_num]; ?></small>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   name="dia[<?php echo $dia_num; ?>]" 
                                   value="<?php echo htmlspecialchars($rotina_salva[$dia_num] ?? ''); ?>" 
                                   placeholder="Ex: Superiores A">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-link text-white text-decoration-none" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn-gym-v2 btn-gym-v2-primary">
                        <i class="bi bi-check-all"></i>
                        Confirmar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Gerenciar Exercícios -->
<div class="modal fade modal-premium" id="modalGerenciarExercicios" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalGerenciarTitle">
                    <i class="bi bi-list-task me-2"></i>Lista de Exercícios
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="corpoModalGerenciar">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-3 text-muted">Carregando ficha técnica...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Treino -->
<div class="modal fade modal-premium" id="modalNovoTreino" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle-dotted me-2"></i>Novo Planejamento de Treino
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formNovoTreino">
                    <div class="mb-4">
                        <label class="form-label text-sub">Nome da Sessão</label>
                        <input type="text" name="nome_treino" class="form-control" placeholder="Ex: Hipertrofia A - Peito" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-sub">Agendar para</label>
                        <select name="dia_semana" class="form-select" required>
                            <option value="">Escolher dia...</option>
                            <?php foreach ($dias_da_semana as $num => $nome): ?>
                            <option value="<?php echo $num; ?>"><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label text-sub">Observações Estratégicas</label>
                        <textarea name="descricao" class="form-control" rows="3" placeholder="Foco em carga, cadência, etc..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-link text-white text-decoration-none" data-bs-dismiss="modal">Descartar</button>
                <button type="button" class="btn-gym-v2 btn-gym-v2-primary" id="btnSalvarTreino">
                    <i class="bi bi-check-lg"></i>
                    Criar Sessão
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const exerciciosPadrao = <?php echo json_encode($exercicios_padrao); ?>;

function toggleCustomExerciseRotina(val) {
    const customInput = document.getElementById('exercicio_custom_rotina');
    if (val === 'outro') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}

function toggleCustomExerciseEditRotina(val) {
    const customInput = document.getElementById('edit_exercicio_custom_rotina');
    if (val === 'outro') {
        customInput.style.display = 'block';
        customInput.required = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 800, once: true, easing: 'ease-out-back' });
    
    // Form Editar Rotina
    const formEditarRotina = document.getElementById('formEditarRotina');
    if (formEditarRotina) {
        formEditarRotina.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarRotina);
            const button = formEditarRotina.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';
            
            fetch('salvar_rotina_semanal.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Sua rotina semanal foi atualizada estrategicamente.');
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarRotina')).hide();
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Falha de Rede!', 'Verifique sua conexão e tente novamente.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check-all"></i> Confirmar Alterações';
            });
        });
    }

    // Modal Gerenciar Exercícios
    const modalGerenciarEl = document.getElementById('modalGerenciarExercicios');
    const corpoModalGerenciar = document.getElementById('corpoModalGerenciar');
    
    if (modalGerenciarEl) {
        modalGerenciarEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const diaId = button.dataset.diaId;
            const diaNome = button.dataset.diaNome;
            const nomeTreino = button.dataset.nomeTreino;
            
            document.getElementById('modalGerenciarTitle').innerHTML = `<i class="bi bi-pencil-square me-2"></i>Ficha: ${diaNome} <span class="badge bg-primary ms-2" style="font-weight: 400; font-size: 0.75rem;">${nomeTreino || 'Padrão'}</span>`;
            corpoModalGerenciar.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>';
            
            if (!diaId) {
                corpoModalGerenciar.innerHTML = '<div class="p-5 text-center"><div class="alert alert-warning d-inline-block">Configure o nome do treino para este dia para gerenciar os exercícios.</div></div>';
                return;
            }
            
            fetch(`buscar_exercicios_dia.php?id_dia=${diaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let exerciciosHtml = '';
                    if (data.exercicios.length === 0) {
                        exerciciosHtml = `
                            <div class="text-center py-5 opacity-50">
                                <i class="bi bi-clipboard-x" style="font-size: 3.5rem;"></i>
                                <p class="mt-3 mb-0">Nenhum exercício na ficha.</p>
                            </div>
                        `;
                    } else {
                        data.exercicios.forEach(ex => {
                            exerciciosHtml += `
                                <div class="modal-exercise-item gym-glass mb-3 p-3 d-flex align-items-center justify-content-between" id="rot-ex-row-${ex.id}" style="border-radius: 14px;">
                                    <div style="flex-grow: 1;">
                                        <div style="font-weight: 600; color: #fff; font-size: 1rem;">${escapeHTML(ex.nome_exercicio || 'Exercício')}</div>
                                        <div style="font-size: 0.85rem; color: var(--gym-text-sub); font-family: 'JetBrains Mono', monospace; margin-top: 2px;">
                                            <span class="series-text badge bg-dark text-white">${escapeHTML(ex.series_sugeridas || '0')}</span> séries × 
                                            <span class="reps-text badge bg-dark text-white">${escapeHTML(ex.repeticoes_sugeridas || '0')}</span> reps
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-dark border-0 p-2 rounded-circle btn-editar-exercicio-rotina" data-id="${ex.id}" style="width: 36px; height: 36px;">
                                            <i class="bi bi-pencil-fill text-primary"></i>
                                        </button>
                                        <button class="btn btn-sm btn-dark border-0 p-2 rounded-circle btn-excluir-exercicio-rotina" data-id="${ex.id}" style="width: 36px; height: 36px;">
                                            <i class="bi bi-trash3-fill text-danger"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    
                    let optsHtml = '<option value="">Selecione um exercício...</option>';
                    for (const grupo in exerciciosPadrao) {
                        optsHtml += `<optgroup label="${grupo}">`;
                        exerciciosPadrao[grupo].forEach(ex => {
                            optsHtml += `<option value="${ex}">${ex}</option>`;
                        });
                        optsHtml += `</optgroup>`;
                    }
                    optsHtml += `<optgroup label="Outros"><option value="outro">Outro (Digitar manualmente)</option></optgroup>`;
                    
                    corpoModalGerenciar.innerHTML = `
                        <div class="p-4" style="background: rgba(255,255,255,0.02);">
                            <div class="mb-4 p-3 gym-glass" style="border-radius: 16px;">
                                <h6 style="color: #fff; margin-bottom: 1.2rem; font-weight: 700;">
                                    <i class="bi bi-plus-circle me-2 text-primary"></i>ADICIONAR NOVO EXERCÍCIO
                                </h6>
                                <form id="formAddExercicioRotina">
                                    <input type="hidden" name="id_rotina_dia" value="${diaId}">
                                    <input type="hidden" name="nome_exercicio" id="exercicio_final_rotina">
                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <select id="exercicio_select_rotina" class="form-select" required onchange="toggleCustomExerciseRotina(this.value)">
                                                ${optsHtml}
                                            </select>
                                            <input type="text" id="exercicio_custom_rotina" class="form-control mt-2" placeholder="Digite o nome do exercício" style="display: none;">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" name="series_sugeridas" class="form-control text-center" placeholder="Sets" min="1" max="10">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" name="repeticoes_sugeridas" class="form-control text-center" placeholder="Reps (Ex: 3x12)">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn-gym-v2 btn-gym-v2-primary w-100 justify-content-center" style="height: 48px;">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                                <h6 style="color: #fff; margin: 0; font-weight: 700; font-size: 0.9rem; letter-spacing: 1px;">FICHA ATUAL (${data.exercicios.length})</h6>
                            </div>
                            <div class="modal-exercise-list px-2" style="max-height: 450px; overflow-y: auto;">
                                ${exerciciosHtml}
                            </div>
                        </div>
                    `;

                    // Inicializando Select2 para inserção principal de registro e lidando com evento on change
                    $('#exercicio_select_rotina').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#modalGerenciarExercicios'),
                        width: '100%',
                        language: {
                            noResults: function () {
                                return "Nenhum exercício encontrado. Escolha 'Outro' para digitar manualmente.";
                            }
                        }
                    }).on('change', function(e) {
                         toggleCustomExerciseRotina(this.value);
                    });

                } else {
                    corpoModalGerenciar.innerHTML = `<div class="p-5 text-center text-danger">${data.message}</div>`;
                }
            })
            .catch(err => {
                corpoModalGerenciar.innerHTML = '<div class="p-5 text-center text-danger">Erro crítico de carregamento.</div>';
            });
        });
    }

    // Submit adicionar exercício
    document.body.addEventListener('submit', function(event) {
        if (event.target.id === 'formAddExercicioRotina') {
            event.preventDefault();
            const form = event.target;
            
            const selectValue = document.getElementById('exercicio_select_rotina').value;
            if(selectValue === 'outro') {
                document.getElementById('exercicio_final_rotina').value = document.getElementById('exercicio_custom_rotina').value;
            } else {
                document.getElementById('exercicio_final_rotina').value = selectValue;
            }

            const formData = new FormData(form);
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            
            fetch('adicionar_exercicio_rotina.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.exercicio) {
                    showToast('Ótimo!', 'Exercício adicionado à sua ficha.');
                    form.reset();
                    // Limpar e resetar o Select2
                    $('#exercicio_select_rotina').val('').trigger('change');
                    toggleCustomExerciseRotina('');

                    const ex = data.exercicio;
                    
                    // Criar o card do novo exercício
                    const novoExercicioHtml = `
                        <div class="modal-exercise-item gym-glass mb-3 p-3 d-flex align-items-center justify-content-between" id="rot-ex-row-${ex.id}" style="border-radius: 14px; animation: gentleGlow 1s ease-in;">
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 600; color: #fff; font-size: 1rem;">${escapeHTML(ex.nome_exercicio || 'Exercício')}</div>
                                <div style="font-size: 0.85rem; color: var(--gym-text-sub); font-family: 'JetBrains Mono', monospace; margin-top: 2px;">
                                    <span class="series-text badge bg-dark text-white">${escapeHTML(ex.series_sugeridas || '0')}</span> séries × 
                                    <span class="reps-text badge bg-dark text-white">${escapeHTML(ex.repeticoes_sugeridas || '0')}</span> reps
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-dark border-0 p-2 rounded-circle btn-editar-exercicio-rotina" data-id="${ex.id}" style="width: 36px; height: 36px;">
                                    <i class="bi bi-pencil-fill text-primary"></i>
                                </button>
                                <button class="btn btn-sm btn-dark border-0 p-2 rounded-circle btn-excluir-exercicio-rotina" data-id="${ex.id}" style="width: 36px; height: 36px;">
                                    <i class="bi bi-trash3-fill text-danger"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Adicionar na lista visual do modal
                    const listContainer = document.querySelector('.modal-exercise-list');
                    if (listContainer) {
                        // Remove o placeholder global "Nenhum exercício" se existir
                        const emptyPlaceholder = listContainer.querySelector('.bi-clipboard-x');
                        if (emptyPlaceholder) {
                            listContainer.innerHTML = '';
                        }
                        
                        listContainer.insertAdjacentHTML('beforeend', novoExercicioHtml);
                        
                        // Atualizamos a o contador no título
                        const titleEl = document.querySelector('h6:contains("FICHA ATUAL")');
                        if (titleEl) {
                           let text = titleEl.textContent;
                           let match = text.match(/\((\d+)\)/);
                           if(match) {
                               let currentCount = parseInt(match[1]) + 1;
                               titleEl.textContent = `FICHA ATUAL (${currentCount})`;
                           }
                        }
                    }

                } else {
                    showToast('Oops!', data.message || 'Erro desconhecido', true);
                }
            })
            .catch(error => showToast('Erro!', 'Falha ao salvar exercício.', true))
            .finally(() => { button.disabled = false; });
        }
    });

    // Click handlers no modal
    corpoModalGerenciar.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.btn-excluir-exercicio-rotina');
        const editButton = event.target.closest('.btn-editar-exercicio-rotina');
        
        if (deleteButton) {
            Swal.fire({
                title: 'Tem certeza?',
                text: "Deseja remover este exercício da sua ficha técnica?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--gym-primary)',
                cancelButtonColor: '#222',
                confirmButtonText: 'Sim, remover!',
                cancelButtonText: 'Cancelar',
                background: '#141414',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    const id = deleteButton.dataset.id;
                    fetch('excluir_exercicio_rotina.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id: id})
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Removido', 'Exercício excluído da ficha.');
                            const row = document.getElementById(`rot-ex-row-${id}`);
                            if (row) {
                                row.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 1, 1)';
                                row.style.opacity = '0';
                                row.style.transform = 'scale(0.9) translateX(30px)';
                                setTimeout(() => window.location.reload(), 450);
                            }
                        }
                    });
                }
            });
        }
        
        if (editButton) {
            const exerciseItem = editButton.closest('.modal-exercise-item');
            const seriesEl = exerciseItem.querySelector('.series-text');
            const repsEl = exerciseItem.querySelector('.reps-text');
            
            const seriesValue = seriesEl.textContent.trim();
            const repsValue = repsEl.textContent.trim();
            
            seriesEl.innerHTML = `<input type="text" class="form-control form-control-sm d-inline-block text-center" value="${seriesValue}" style="width: 50px; height: 24px; padding: 0; font-size: 0.8rem;">`;
            repsEl.innerHTML = `<input type="text" class="form-control form-control-sm d-inline-block text-center" value="${repsValue}" style="width: 70px; height: 24px; padding: 0; font-size: 0.8rem;">`;
            
            editButton.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            editButton.classList.remove('btn-editar-exercicio-rotina');
            editButton.classList.add('btn-salvar-exercicio-rotina');
        }

        if (saveButton) {
            const exerciseItem = saveButton.closest('.modal-exercise-item');
            const id = saveButton.dataset.id;
            const seriesInput = exerciseItem.querySelector('.series-text input');
            const repsInput = exerciseItem.querySelector('.reps-text input');
            
            const series = seriesInput.value;
            const reps = repsInput.value;
            
            saveButton.disabled = true;
            
            fetch('editar_exercicio_rotina.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id, series_sugeridas: series, repeticoes_sugeridas: reps })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Atualizado!', 'Configurações do exercício salvas.');
                    seriesEl = exerciseItem.querySelector('.series-text');
                    repsEl = exerciseItem.querySelector('.reps-text');
                    seriesEl.textContent = series || '0';
                    repsEl.textContent = reps || '0';
                } else {
                    showToast('Erro!', data.message, true);
                }
                saveButton.innerHTML = '<i class="bi bi-pencil-fill text-primary"></i>';
                saveButton.classList.remove('btn-salvar-exercicio-rotina');
                saveButton.classList.add('btn-editar-exercicio-rotina');
                saveButton.disabled = false;
            });
        }
    });
    
    // Botão Iniciar Treino
    document.querySelectorAll('.btn-iniciar-treino').forEach(btn => {
        btn.addEventListener('click', function() {
            const diaId = this.dataset.diaId;
            const diaNome = this.dataset.diaNome;
            const nomeTreino = this.dataset.nomeTreino;
            
            if (!diaId) {
                showToast('Erro!', 'ID da ficha não localizado.', true);
                return;
            }
            
            window.location.href = `treinos.php?rotina_dia=${diaId}&dia=${encodeURIComponent(diaNome)}&treino=${encodeURIComponent(nomeTreino)}`;
        });
    });
    
    // Botão Salvar Novo Treino
    const btnSalvarTreino = document.getElementById('btnSalvarTreino');
    if (btnSalvarTreino) {
        btnSalvarTreino.addEventListener('click', function() {
            const form = document.getElementById('formNovoTreino');
            const formData = new FormData(form);
            const nomeTreino = formData.get('nome_treino')?.trim();
            const diaSemana = formData.get('dia_semana');
            
            if (!nomeTreino || !diaSemana) {
                showToast('Atenção', 'Nome e dia são campos obrigatórios.', true);
                return;
            }
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            fetch('salvar_rotina_semanal.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Nova sessão criada com êxito.');
                    bootstrap.Modal.getInstance(document.getElementById('modalNovoTreino')).hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-check-lg"></i> Criar Sessão';
                }
            })
            .catch(error => {
                showToast('Erro!', 'Falha na comunicação com o servidor.', true);
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-check-lg"></i> Criar Sessão';
            });
        });
    }
});

// Suporte para Escaping de HTML no JS
function escapeHTML(str) {
    if (!str) return "";
    return str.replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[m];
    });
}
</script>

<?php require_once 'templates/footer.php'; ?>
