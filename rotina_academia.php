<?php
// rotina_academia.php (Versão Redesenhada - Design Premium)
require_once 'templates/header.php';

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
/* ROTINA ACADEMIA - DESIGN PREMIUM */
/* ================================================== */

@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
    --gym-primary: #e50914;
    --gym-primary-dark: #b30710;
    --gym-gradient: linear-gradient(135deg, #e50914 0%, #ff4757 50%, #e50914 100%);
    --gym-gradient-dark: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    --gym-bg: #0d0d0d;
    --gym-card: #161616;
    --gym-card-hover: #1f1f1f;
    --gym-border: rgba(255, 255, 255, 0.08);
    --gym-text: #f5f5f5;
    --gym-text-muted: #888;
    --gym-success: #00d26a;
    --gym-warning: #ffc107;
}

.gym-page {
    font-family: 'Space Grotesk', sans-serif;
    background: var(--gym-bg);
    min-height: 100vh;
    padding-bottom: 2rem;
}

/* Header Section */
.gym-header {
    background: var(--gym-gradient-dark);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.gym-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(229, 9, 20, 0.15) 0%, transparent 70%);
    pointer-events: none;
}

.gym-header-content {
    position: relative;
    z-index: 1;
}

.gym-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.gym-title-icon {
    width: 60px;
    height: 60px;
    background: var(--gym-gradient);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.gym-subtitle {
    color: var(--gym-text-muted);
    margin: 0.5rem 0 0 0;
    font-size: 1rem;
}

.gym-header-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn-gym {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
}

.btn-gym-primary {
    background: var(--gym-gradient);
    color: #fff;
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
}

.btn-gym-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(229, 9, 20, 0.5);
    color: #fff;
}

.btn-gym-outline {
    background: transparent;
    color: var(--gym-text);
    border: 2px solid var(--gym-border);
}

.btn-gym-outline:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: var(--gym-primary);
    color: var(--gym-primary);
}

/* Stats Grid */
.gym-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.gym-stat-card {
    background: var(--gym-card);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    border: 1px solid var(--gym-border);
    transition: all 0.3s ease;
}

.gym-stat-card:hover {
    transform: translateY(-3px);
    border-color: var(--gym-primary);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.gym-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
}

.gym-stat-icon.days { background: linear-gradient(135deg, #667eea, #764ba2); }
.gym-stat-icon.exercises { background: linear-gradient(135deg, #00d26a, #00a854); }
.gym-stat-icon.time { background: linear-gradient(135deg, #ffc107, #ff9800); }
.gym-stat-icon.series { background: linear-gradient(135deg, #e50914, #ff4757); }

.gym-stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
}

.gym-stat-label {
    color: var(--gym-text-muted);
    font-size: 0.85rem;
    margin-top: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Week Grid */
.gym-week {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.gym-day-card {
    background: var(--gym-card);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--gym-border);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.gym-day-card:hover {
    transform: translateY(-5px);
    border-color: rgba(229, 9, 20, 0.5);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.gym-day-card.active {
    border-color: var(--gym-primary);
}

.gym-day-header {
    background: var(--gym-gradient);
    padding: 1rem;
    text-align: center;
}

.gym-day-name {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 0.25rem;
}

.gym-day-abbrev {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
}

.gym-day-body {
    padding: 1rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.gym-workout-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gym-text);
    margin-bottom: 1rem;
    min-height: 40px;
}

.gym-workout-name.empty {
    color: var(--gym-text-muted);
    font-style: italic;
}

.gym-exercises-list {
    flex-grow: 1;
    max-height: 200px;
    overflow-y: auto;
    margin-bottom: 1rem;
}

.gym-exercise-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
    margin-bottom: 0.5rem;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

.gym-exercise-item:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: var(--gym-border);
}

.gym-exercise-info {
    flex-grow: 1;
    min-width: 0;
}

.gym-exercise-name {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--gym-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gym-exercise-sets {
    font-size: 0.75rem;
    color: var(--gym-text-muted);
    font-family: 'JetBrains Mono', monospace;
}

.gym-exercise-actions {
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.gym-exercise-item:hover .gym-exercise-actions {
    opacity: 1;
}

.btn-exercise-action {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.8rem;
}

.btn-exercise-edit {
    background: rgba(102, 126, 234, 0.2);
    color: #667eea;
}

.btn-exercise-edit:hover {
    background: #667eea;
    color: #fff;
}

.btn-exercise-delete {
    background: rgba(229, 9, 20, 0.2);
    color: var(--gym-primary);
}

.btn-exercise-delete:hover {
    background: var(--gym-primary);
    color: #fff;
}

.gym-empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--gym-text-muted);
}

.gym-empty-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    opacity: 0.3;
}

.gym-day-footer {
    padding: 0 1rem 1rem;
    display: flex;
    gap: 0.5rem;
}

.btn-day-action {
    flex: 1;
    padding: 0.6rem;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
}

.btn-manage {
    background: rgba(255, 255, 255, 0.05);
    color: var(--gym-text);
    border: 1px solid var(--gym-border);
}

.btn-manage:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--gym-text-muted);
}

.btn-start {
    background: var(--gym-gradient);
    color: #fff;
}

.btn-start:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
}

/* Responsive */
@media (max-width: 1200px) {
    .gym-week {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 992px) {
    .gym-week {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .gym-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .gym-week {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .gym-title {
        font-size: 1.8rem;
    }
    
    .gym-title-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .gym-header {
        padding: 1.5rem;
    }
    
    .gym-header-actions {
        flex-direction: column;
    }
    
    .btn-gym {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .gym-week {
        grid-template-columns: 1fr;
    }
    
    .gym-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .gym-stat-card {
        padding: 1rem;
    }
    
    .gym-stat-value {
        font-size: 1.5rem;
    }
    
    .gym-day-card {
        border-radius: 16px;
    }
    
    .gym-exercise-actions {
        opacity: 1;
    }
}

/* Scrollbar */
.gym-exercises-list::-webkit-scrollbar {
    width: 4px;
}

.gym-exercises-list::-webkit-scrollbar-track {
    background: transparent;
}

.gym-exercises-list::-webkit-scrollbar-thumb {
    background: var(--gym-border);
    border-radius: 2px;
}

/* Modal Customization */
.modal-gym .modal-content {
    background: var(--gym-card);
    border: 1px solid var(--gym-border);
    border-radius: 20px;
}

.modal-gym .modal-header {
    background: var(--gym-gradient);
    border-radius: 20px 20px 0 0;
    border: none;
    padding: 1.5rem;
}

.modal-gym .modal-title {
    font-weight: 700;
    color: #fff;
}

.modal-gym .modal-body {
    padding: 1.5rem;
}

.modal-gym .form-control,
.modal-gym .form-select {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--gym-border);
    color: var(--gym-text);
    border-radius: 10px;
    padding: 0.75rem 1rem;
}

.modal-gym .form-control:focus,
.modal-gym .form-select:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--gym-primary);
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.2);
    color: var(--gym-text);
}

.modal-gym .form-control::placeholder {
    color: var(--gym-text-muted);
}

.modal-gym .form-label {
    color: var(--gym-text);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.modal-gym .modal-footer {
    border-top: 1px solid var(--gym-border);
    padding: 1rem 1.5rem;
}

.modal-gym .btn-close {
    filter: invert(1);
}

/* Exercise List in Modal */
.modal-exercise-list {
    max-height: 300px;
    overflow-y: auto;
}

.modal-exercise-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    margin-bottom: 0.75rem;
    border: 1px solid var(--gym-border);
}

.modal-exercise-item:hover {
    background: rgba(255, 255, 255, 0.06);
}
</style>

<div class="gym-page">
    <!-- Header -->
    <div class="gym-header" data-aos="fade-down">
        <div class="gym-header-content">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                <div>
                    <h1 class="gym-title">
                        <span class="gym-title-icon">🏋️</span>
                        Rotina de Academia
                    </h1>
                    <p class="gym-subtitle">Planejamento profissional de treinos para máxima performance</p>
                </div>
                <div class="gym-header-actions">
                    <button class="btn-gym btn-gym-outline" data-bs-toggle="modal" data-bs-target="#modalEditarRotina">
                        <i class="bi bi-calendar-week"></i>
                        Editar Rotina
                    </button>
                    <button class="btn-gym btn-gym-primary" data-bs-toggle="modal" data-bs-target="#modalNovoTreino">
                        <i class="bi bi-plus-lg"></i>
                        Novo Treino
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="gym-stats" data-aos="fade-up" data-aos-delay="100">
        <div class="gym-stat-card">
            <div class="gym-stat-icon days">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="gym-stat-value"><?php echo $diasAtivos; ?></div>
            <div class="gym-stat-label">Dias Ativos</div>
        </div>
        <div class="gym-stat-card">
            <div class="gym-stat-icon exercises">
                <i class="bi bi-activity"></i>
            </div>
            <div class="gym-stat-value"><?php echo $totalExercicios; ?></div>
            <div class="gym-stat-label">Exercícios</div>
        </div>
        <div class="gym-stat-card">
            <div class="gym-stat-icon time">
                <i class="bi bi-clock"></i>
            </div>
            <div class="gym-stat-value"><?php echo $tempoEstimado; ?><small>min</small></div>
            <div class="gym-stat-label">Tempo/Semana</div>
        </div>
        <div class="gym-stat-card">
            <div class="gym-stat-icon series">
                <i class="bi bi-arrow-repeat"></i>
            </div>
            <div class="gym-stat-value"><?php echo $totalSeries; ?></div>
            <div class="gym-stat-label">Total Séries</div>
        </div>
    </div>

    <!-- Week Grid -->
    <div class="gym-week" data-aos="fade-up" data-aos-delay="200">
        <?php foreach ($dias_da_semana as $dia_num => $dia_nome): 
            $treino_do_dia = $rotina_completa[$dia_num];
            $hasExercises = !empty($treino_do_dia['exercicios']);
        ?>
        <div class="gym-day-card <?php echo $hasExercises ? 'active' : ''; ?>">
            <div class="gym-day-header">
                <div class="gym-day-name"><?php echo $dia_nome; ?></div>
                <div class="gym-day-abbrev"><?php echo $dias_abrev[$dia_num]; ?></div>
            </div>
            <div class="gym-day-body">
                <div class="gym-workout-name <?php echo empty($treino_do_dia['nome_treino']) ? 'empty' : ''; ?>">
                    <?php echo !empty($treino_do_dia['nome_treino']) ? htmlspecialchars($treino_do_dia['nome_treino']) : 'Sem treino definido'; ?>
                </div>
                
                <?php if ($hasExercises): ?>
                <div class="gym-exercises-list">
                    <?php foreach ($treino_do_dia['exercicios'] as $ex): ?>
                    <div class="gym-exercise-item" data-exercise-id="<?php echo $ex['id']; ?>">
                        <div class="gym-exercise-info">
                            <div class="gym-exercise-name"><?php echo htmlspecialchars($ex['nome_exercicio'] ?? 'Exercício'); ?></div>
                            <div class="gym-exercise-sets">
                                <?php echo htmlspecialchars($ex['series_sugeridas'] ?? '0'); ?>×<?php echo htmlspecialchars($ex['repeticoes_sugeridas'] ?? '0'); ?>
                            </div>
                        </div>
                        <div class="gym-exercise-actions">
                            <button class="btn-exercise-action btn-exercise-delete" 
                                    onclick="excluirExercicio(<?php echo $ex['id']; ?>, '<?php echo htmlspecialchars($ex['nome_exercicio']); ?>')"
                                    title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="gym-empty-state">
                    <div class="gym-empty-icon">💪</div>
                    <div>Nenhum exercício</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="gym-day-footer">
                <button class="btn-day-action btn-manage btn-gerenciar-exercicios" 
                        data-bs-toggle="modal" 
                        data-bs-target="#modalGerenciarExercicios" 
                        data-dia-id="<?php echo $treino_do_dia['id_dia']; ?>" 
                        data-dia-nome="<?php echo $dia_nome; ?>" 
                        data-nome-treino="<?php echo htmlspecialchars($treino_do_dia['nome_treino']); ?>">
                    <i class="bi bi-gear"></i>
                    Gerenciar
                </button>
                <?php if ($hasExercises): ?>
                <button class="btn-day-action btn-start btn-iniciar-treino" 
                        data-dia-id="<?php echo $treino_do_dia['id_dia']; ?>"
                        data-dia-nome="<?php echo htmlspecialchars($dia_nome); ?>"
                        data-nome-treino="<?php echo htmlspecialchars($treino_do_dia['nome_treino']); ?>">
                    <i class="bi bi-play-fill"></i>
                    Iniciar
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Editar Rotina -->
<div class="modal fade modal-gym" id="modalEditarRotina" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-week me-2"></i>Configurar Rotina Semanal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarRotina">
                <div class="modal-body">
                    <div class="alert alert-info" style="background: rgba(102, 126, 234, 0.1); border-color: #667eea; color: #667eea;">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Dica:</strong> Defina nomes descritivos para cada treino (ex: "Peito e Tríceps", "Costas e Bíceps").
                    </div>
                    <div class="row g-3">
                        <?php foreach ($dias_da_semana as $dia_num => $dia_nome): ?>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="bi bi-calendar-day me-2"></i><?php echo $dia_nome; ?>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   name="dia[<?php echo $dia_num; ?>]" 
                                   value="<?php echo htmlspecialchars($rotina_salva[$dia_num] ?? ''); ?>" 
                                   placeholder="Ex: Peito e Tríceps">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-gym btn-gym-primary">
                        <i class="bi bi-check-lg"></i>
                        Salvar Rotina
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Gerenciar Exercícios -->
<div class="modal fade modal-gym" id="modalGerenciarExercicios" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalGerenciarTitle">
                    <i class="bi bi-dumbbell me-2"></i>Gerenciar Exercícios
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="corpoModalGerenciar">
                    <div class="text-center p-5">
                        <div class="spinner-border" style="color: var(--gym-primary);"></div>
                        <p class="mt-3" style="color: var(--gym-text-muted);">Carregando exercícios...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Treino -->
<div class="modal fade modal-gym" id="modalNovoTreino" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Novo Treino
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovoTreino">
                    <div class="mb-3">
                        <label class="form-label">Nome do Treino</label>
                        <input type="text" name="nome_treino" class="form-control" placeholder="Ex: Treino de Peito e Tríceps" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dia da Semana</label>
                        <select name="dia_semana" class="form-select" required>
                            <option value="">Selecione o dia</option>
                            <?php foreach ($dias_da_semana as $num => $nome): ?>
                            <option value="<?php echo $num; ?>"><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição (opcional)</label>
                        <textarea name="descricao" class="form-control" rows="3" placeholder="Descreva o objetivo do treino..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-gym btn-gym-primary" id="btnSalvarTreino">
                    <i class="bi bi-check-lg"></i>
                    Criar Treino
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
    // Form Editar Rotina
    const formEditarRotina = document.getElementById('formEditarRotina');
    if (formEditarRotina) {
        formEditarRotina.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarRotina);
            const button = formEditarRotina.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
            
            fetch('salvar_rotina_semanal.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarRotina')).hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro de Rede!', 'Não foi possível conectar.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check-lg"></i> Salvar Rotina';
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
            
            document.getElementById('modalGerenciarTitle').innerHTML = `<i class="bi bi-dumbbell me-2"></i>Exercícios - ${diaNome} (${nomeTreino || 'Sem nome'})`;
            corpoModalGerenciar.innerHTML = '<div class="text-center p-5"><div class="spinner-border" style="color: var(--gym-primary);"></div></div>';
            
            if (!diaId) {
                corpoModalGerenciar.innerHTML = '<div class="alert alert-warning">Defina primeiro um nome para este dia de treino.</div>';
                return;
            }
            
            fetch(`buscar_exercicios_dia.php?id_dia=${diaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let exerciciosHtml = '';
                    if (data.exercicios.length === 0) {
                        exerciciosHtml = `
                            <div class="text-center py-4" style="color: var(--gym-text-muted);">
                                <i class="bi bi-dumbbell" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="mt-2 mb-0">Nenhum exercício adicionado</p>
                            </div>
                        `;
                    } else {
                        data.exercicios.forEach(ex => {
                            exerciciosHtml += `
                                <div class="modal-exercise-item" id="rot-ex-row-${ex.id}">
                                    <div style="flex-grow: 1;">
                                        <div style="font-weight: 600; color: var(--gym-text);">${escapeHTML(ex.nome_exercicio || 'Exercício')}</div>
                                        <div style="font-size: 0.85rem; color: var(--gym-text-muted); font-family: 'JetBrains Mono', monospace;">
                                            <span class="series-text">${escapeHTML(ex.series_sugeridas || '0')}</span> séries × 
                                            <span class="reps-text">${escapeHTML(ex.repeticoes_sugeridas || '0')}</span> reps
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn btn-sm btn-outline-primary btn-editar-exercicio-rotina" data-id="${ex.id}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-excluir-exercicio-rotina" data-id="${ex.id}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    
                    corpoModalGerenciar.innerHTML = `
                        <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                            <h6 style="color: var(--gym-text); margin-bottom: 1rem;">
                                <i class="bi bi-plus-circle me-2"></i>Adicionar Exercício
                            </h6>
                            <form id="formAddExercicioRotina">
                                <input type="hidden" name="id_rotina_dia" value="${diaId}">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <input type="text" name="nome_exercicio" class="form-control" placeholder="Nome do exercício" required>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="series_sugeridas" class="form-control" placeholder="Séries" min="1" max="10">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" name="repeticoes_sugeridas" class="form-control" placeholder="Reps (ex: 8-12)">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn-gym btn-gym-primary w-100" style="padding: 0.6rem;">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <h6 style="color: var(--gym-text); margin-bottom: 1rem;">
                            <i class="bi bi-list-check me-2"></i>Exercícios (${data.exercicios.length})
                        </h6>
                        <div class="modal-exercise-list">
                            ${exerciciosHtml}
                        </div>
                    `;
                } else {
                    corpoModalGerenciar.innerHTML = `<p style="color: var(--gym-primary);">${data.message}</p>`;
                }
            })
            .catch(err => {
                corpoModalGerenciar.innerHTML = '<p style="color: var(--gym-primary);">Erro de conexão.</p>';
            });
        });
    }

    // Submit adicionar exercício
    document.body.addEventListener('submit', function(event) {
        if (event.target.id === 'formAddExercicioRotina') {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            
            fetch('adicionar_exercicio_rotina.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    form.reset();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro!', 'Erro de conexão.', true))
            .finally(() => { button.disabled = false; });
        }
    });

    // Click handlers no modal
    corpoModalGerenciar.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.btn-excluir-exercicio-rotina');
        const editButton = event.target.closest('.btn-editar-exercicio-rotina');
        const saveButton = event.target.closest('.btn-salvar-exercicio-rotina');
        
        if (deleteButton) {
            if (!confirm('Remover este exercício da rotina?')) return;
            const id = deleteButton.dataset.id;
            
            fetch('excluir_exercicio_rotina.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    const row = document.getElementById(`rot-ex-row-${id}`);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';
                        setTimeout(() => {
                            row.remove();
                            
                            // Atualizar contador no título do modal
                            const lista = document.querySelector('.modal-exercise-list');
                            const count = lista ? lista.querySelectorAll('.modal-exercise-item').length : 0;
                            const titulo = document.getElementById('modalGerenciarTitle');
                            if (titulo) {
                                const textoAtual = titulo.innerHTML;
                                titulo.innerHTML = textoAtual.replace(/\(\d+\)/, `(${count})`);
                            }
                            
                            // Se lista vazia, mostrar mensagem
                            if (count === 0 && lista) {
                                lista.innerHTML = `
                                    <div class="text-center py-4" style="color: var(--gym-text-muted);">
                                        <i class="bi bi-dumbbell" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <p class="mt-2 mb-0">Nenhum exercício adicionado</p>
                                    </div>
                                `;
                            }
                            
                            // Remover também do card principal na página
                            const itemPagina = document.querySelector(`[data-exercise-id="${id}"]`);
                            if (itemPagina) {
                                const parentList = itemPagina.closest('.gym-exercises-list');
                                const parentCard = itemPagina.closest('.gym-day-card');
                                itemPagina.remove();
                                
                                if (parentList && parentList.querySelectorAll('.gym-exercise-item').length === 0) {
                                    parentList.innerHTML = `
                                        <div class="gym-empty-state">
                                            <div class="gym-empty-icon">💪</div>
                                            <div>Nenhum exercício</div>
                                        </div>
                                    `;
                                    const btnIniciar = parentCard.querySelector('.btn-iniciar-treino');
                                    if (btnIniciar) btnIniciar.remove();
                                    parentCard.classList.remove('active');
                                }
                                
                                atualizarEstatisticas();
                            }
                        }, 300);
                    }
                } else {
                    showToast('Erro!', data.message, true);
                }
            });
        }
        
        if (editButton) {
            const exerciseItem = editButton.closest('.modal-exercise-item');
            const seriesEl = exerciseItem.querySelector('.series-text');
            const repsEl = exerciseItem.querySelector('.reps-text');
            
            const seriesValue = seriesEl.textContent.trim();
            const repsValue = repsEl.textContent.trim();
            
            seriesEl.innerHTML = `<input type="text" class="form-control form-control-sm d-inline-block" value="${seriesValue}" style="width: 60px;">`;
            repsEl.innerHTML = `<input type="text" class="form-control form-control-sm d-inline-block" value="${repsValue}" style="width: 60px;">`;
            
            editButton.innerHTML = '<i class="bi bi-check-lg"></i>';
            editButton.classList.remove('btn-editar-exercicio-rotina', 'btn-outline-primary');
            editButton.classList.add('btn-salvar-exercicio-rotina', 'btn-outline-success');
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
                    showToast('Sucesso!', data.message);
                    exerciseItem.querySelector('.series-text').textContent = series || '0';
                    exerciseItem.querySelector('.reps-text').textContent = reps || '0';
                } else {
                    showToast('Erro!', data.message, true);
                }
                saveButton.innerHTML = '<i class="bi bi-pencil"></i>';
                saveButton.classList.remove('btn-salvar-exercicio-rotina', 'btn-outline-success');
                saveButton.classList.add('btn-editar-exercicio-rotina', 'btn-outline-primary');
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
                showToast('Erro!', 'ID do dia não encontrado', true);
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
            
            if (!nomeTreino) {
                showToast('Erro!', 'Nome do treino é obrigatório', true);
                return;
            }
            
            if (!diaSemana) {
                showToast('Erro!', 'Selecione um dia da semana', true);
                return;
            }
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
            
            fetch('salvar_rotina_semanal.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message || 'Treino criado!');
                    bootstrap.Modal.getInstance(document.getElementById('modalNovoTreino')).hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-check-lg"></i> Criar Treino';
                }
            })
            .catch(error => {
                showToast('Erro!', 'Erro de conexão.', true);
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-check-lg"></i> Criar Treino';
            });
        });
    }
});

// Função para excluir exercício diretamente do card
function excluirExercicio(id, nome) {
    if (!confirm(`Remover "${nome}" da rotina?`)) return;
    
    fetch('excluir_exercicio_rotina.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', data.message);
            const item = document.querySelector(`[data-exercise-id="${id}"]`);
            if (item) {
                // Animar e remover o item
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    const parentList = item.closest('.gym-exercises-list');
                    const parentCard = item.closest('.gym-day-card');
                    item.remove();
                    
                    // Verificar se a lista ficou vazia
                    if (parentList && parentList.children.length === 0) {
                        parentList.innerHTML = `
                            <div class="gym-empty-state">
                                <div class="gym-empty-icon">💪</div>
                                <div>Nenhum exercício</div>
                            </div>
                        `;
                        // Remover botão "Iniciar" se existir
                        const btnIniciar = parentCard.querySelector('.btn-iniciar-treino');
                        if (btnIniciar) btnIniciar.remove();
                        // Remover classe active do card
                        parentCard.classList.remove('active');
                    }
                    
                    // Atualizar estatísticas na página
                    atualizarEstatisticas();
                }, 300);
            }
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(err => showToast('Erro!', 'Erro de conexão.', true));
}

// Função para atualizar estatísticas sem recarregar
function atualizarEstatisticas() {
    // Contar dias ativos (cards com exercícios)
    const diasAtivos = document.querySelectorAll('.gym-day-card.active').length;
    
    // Contar total de exercícios
    const exercicios = document.querySelectorAll('.gym-exercise-item').length;
    
    // Atualizar valores na página
    const statCards = document.querySelectorAll('.gym-stat-value');
    if (statCards[0]) statCards[0].textContent = diasAtivos;
    if (statCards[1]) statCards[1].textContent = exercicios;
}
</script>

<?php require_once 'templates/footer.php'; ?>
