<?php
// rotina_academia.php (Versão Final com CRUD Completo de Exercícios)
require_once 'templates/header.php';

$dias_da_semana = [1 => 'Domingo', 2 => 'Segunda-feira', 3 => 'Terça-feira', 4 => 'Quarta-feira', 5 => 'Quinta-feira', 6 => 'Sexta-feira', 7 => 'Sábado'];
$rotina_completa = [];
$rotina_salva = [];
try {
    $sql_rotina_dias = "SELECT rd.id as id_dia, rd.dia_semana, rd.nome_treino FROM rotinas r JOIN rotina_dias rd ON r.id = rd.id_rotina WHERE r.id_usuario = ? AND r.ativo = 1";
    $stmt_rotina_dias = $pdo->prepare($sql_rotina_dias);
    $stmt_rotina_dias->execute([$userId]);
    $dias_com_treino = $stmt_rotina_dias->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dias_da_semana as $num => $nome) {
        $rotina_completa[$num] = ['id_dia' => null, 'nome_treino' => 'Não definido', 'exercicios' => []];
    }
    foreach ($dias_com_treino as $dia) {
        $rotina_completa[$dia['dia_semana']] = ['id_dia' => $dia['id_dia'], 'nome_treino' => $dia['nome_treino'] ?: 'Não definido', 'exercicios' => []];
        $rotina_salva[$dia['dia_semana']] = $dia['nome_treino'];
    }
    $sql_exercicios = "SELECT re.id, re.id_rotina_dia, e.nome_exercicio, re.series_sugeridas, re.repeticoes_sugeridas FROM rotina_exercicios re JOIN exercicios e ON re.id_exercicio = e.id JOIN rotina_dias rd ON re.id_rotina_dia = rd.id JOIN rotinas r ON rd.id_rotina = r.id WHERE r.id_usuario = ? AND r.ativo = 1 ORDER BY COALESCE(re.ordem, re.id)";
    $stmt_exercicios = $pdo->prepare($sql_exercicios);
    $stmt_exercicios->execute([$userId]);
    foreach ($stmt_exercicios->fetchAll(PDO::FETCH_ASSOC) as $ex) {
        foreach ($rotina_completa as &$dia_data) { if ($dia_data['id_dia'] == $ex['id_rotina_dia']) { $dia_data['exercicios'][] = $ex; break; } }
    }
} catch (PDOException $e) {
    die("Erro ao buscar rotina: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">🏋️ Rotina de Academia</h1>
        <p class="text-muted mb-0">Planejamento profissional de treinos para máxima performance</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarRotina">
            <i class="bi bi-calendar-week me-2"></i>Editar Rotina
        </button>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovoTreino">
            <i class="bi bi-plus-circle me-2"></i>Novo Treino
        </button>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="bi bi-calendar-check fs-2"></i>
                <h5 class="mt-2">Dias Ativos</h5>
                <h3 class="mb-0"><?php echo count(array_filter($rotina_completa, function($dia) { return !empty($dia['exercicios']); })); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="bi bi-activity fs-2"></i>
                <h5 class="mt-2">Exercícios</h5>
                <h3 class="mb-0"><?php echo array_sum(array_map(function($dia) { return count($dia['exercicios']); }, $rotina_completa)); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="bi bi-clock fs-2"></i>
                <h5 class="mt-2">Tempo Est.</h5>
                <h3 class="mb-0">45min</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="bi bi-trophy fs-2"></i>
                <h5 class="mt-2">Progresso</h5>
                <h3 class="mb-0">85%</h3>
            </div>
        </div>
    </div>
</div>
<!-- Rotina Semanal -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" data-aos="fade-up">
    <?php foreach ($dias_da_semana as $dia_num => $dia_nome): $treino_do_dia = $rotina_completa[$dia_num]; ?>
        <div class="col">
            <div class="card card-custom h-100 shadow-sm">
                <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-white">
                            <i class="bi bi-calendar-day me-2"></i><?php echo $dia_nome; ?>
                        </h5>
                        <span class="badge bg-light text-dark">
                            <?php echo count($treino_do_dia['exercicios']); ?> ex.
                        </span>
                    </div>
                    <div class="mt-2">
                        <span class="text-white-50 small">Treino:</span>
                        <span class="text-white fw-bold"><?php echo htmlspecialchars($treino_do_dia['nome_treino']); ?></span>
                    </div>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="flex-grow-1">
                        <?php if (empty($treino_do_dia['exercicios'])): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-plus-circle text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2 mb-0">Nenhum exercício planejado</p>
                                <small class="text-muted">Clique em "Gerenciar" para adicionar</small>
                            </div>
                        <?php else: ?>
                            <div class="exercise-list">
                                <?php foreach ($treino_do_dia['exercicios'] as $index => $ex): ?>
                                    <div class="exercise-item d-flex justify-content-between align-items-center py-2 <?php echo $index > 0 ? 'border-top' : ''; ?>">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold" style="color: var(--bs-body-color, #212529) !important;"><?php echo htmlspecialchars($ex['nome_exercicio'] ?? 'Exercício sem nome'); ?></div>
                                            <div class="small mt-1" style="color: var(--bs-secondary-color, #6c757d) !important;">
                                                <i class="bi bi-arrow-repeat me-1"></i>
                                                <?php echo htmlspecialchars($ex['series_sugeridas'] ?? 'N/A'); ?> séries × 
                                                <?php echo htmlspecialchars($ex['repeticoes_sugeridas'] ?? 'N/A'); ?> reps
                                            </div>
                                        </div>
                                        <div class="exercise-status">
                                            <i class="bi bi-check-circle text-success" title="Exercício configurado"></i>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-danger btn-sm btn-gerenciar-exercicios" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalGerenciarExercicios" 
                                    data-dia-id="<?php echo $treino_do_dia['id_dia']; ?>" 
                                    data-dia-nome="<?php echo $dia_nome; ?>" 
                                    data-nome-treino="<?php echo htmlspecialchars($treino_do_dia['nome_treino']); ?>">
                                <i class="bi bi-gear-fill me-2"></i>Gerenciar Exercícios
                            </button>
                            <?php if (!empty($treino_do_dia['exercicios'])): ?>
                                <button class="btn btn-danger btn-sm btn-iniciar-treino" 
                                        data-dia-id="<?php echo $treino_do_dia['id_dia']; ?>"
                                        data-dia-nome="<?php echo htmlspecialchars($dia_nome); ?>"
                                        data-nome-treino="<?php echo htmlspecialchars($treino_do_dia['nome_treino']); ?>">
                                    <i class="bi bi-play-fill me-2"></i>Iniciar Treino
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<!-- Modal Editar Rotina -->
<div class="modal fade" id="modalEditarRotina" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-week me-2"></i>Configurar Rotina Semanal
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarRotina">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Dica Profissional:</strong> Defina nomes descritivos para cada treino (ex: "Peito e Tríceps", "Costas e Bíceps", "Pernas").
                    </div>
                    <div class="row">
                        <?php foreach ($dias_da_semana as $dia_num => $dia_nome): ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-calendar-day me-2"></i><?php echo $dia_nome; ?>
                                </label>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           name="dia[<?php echo $dia_num; ?>]" 
                                           value="<?php echo htmlspecialchars($rotina_salva[$dia_num] ?? ''); ?>" 
                                           placeholder="Ex: Peito e Tríceps">
                                    <span class="input-group-text">
                                        <i class="bi bi-dumbbell"></i>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-check-circle me-2"></i>Salvar Rotina
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal Gerenciar Exercícios -->
<div class="modal fade" id="modalGerenciarExercicios" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalGerenciarTitle">
                    <i class="bi bi-dumbbell me-2"></i>Gerenciar Exercícios
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="corpoModalGerenciar">
                    <div class="text-center p-5">
                        <div class="spinner-border text-danger"></div>
                        <p class="mt-3 text-muted">Carregando exercícios...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div></div>

<style>
.exercise-list {
    max-height: 300px;
    overflow-y: auto;
}

.exercise-item {
    transition: all 0.3s ease;
    border-radius: 8px;
    margin-bottom: 8px;
    padding: 12px;
    background: var(--bs-body-bg, #f8f9fa);
    border: 1px solid var(--bs-border-color, #dee2e6) !important;
    color: var(--bs-body-color, #212529);
}

.exercise-item:hover {
    background: var(--bs-secondary-bg, #e9ecef);
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.exercise-item .fw-bold,
.exercise-item .fw-semibold {
    color: var(--bs-body-color, #212529) !important;
}

.exercise-item .small {
    color: var(--bs-secondary-color, #6c757d) !important;
}

/* Garantir que o texto seja visível mesmo em cards escuros */
.card-body .exercise-item .fw-semibold,
.card-body .exercise-item .fw-bold {
    color: var(--bs-body-color, #212529) !important;
    opacity: 1 !important;
}

.exercise-status i {
    font-size: 1.2rem;
}

.card-custom {
    border: none;
    border-radius: 15px;
    transition: all 0.3s ease;
}

.card-custom:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.bg-gradient {
    background: linear-gradient(135deg, #dc3545, #c82333) !important;
}

.exercise-item:last-child {
    border-bottom: none !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
    const modalEditarRotina = new bootstrap.Modal(document.getElementById('modalEditarRotina'));
    const formEditarRotina = document.getElementById('formEditarRotina');
    if (formEditarRotina) {
        formEditarRotina.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarRotina);
            const button = formEditarRotina.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Salvando...`;
            fetch('salvar_rotina_semanal.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) { showToast('Sucesso!', data.message); modalEditarRotina.hide(); setTimeout(() => window.location.reload(), 1000); } 
                else { showToast('Erro!', data.message, true); }
            }).catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true)).finally(() => { button.disabled = false; button.innerHTML = 'Salvar Rotina'; });
        });
    }

    const modalGerenciarEl = document.getElementById('modalGerenciarExercicios');
    const corpoModalGerenciar = document.getElementById('corpoModalGerenciar');
    if(modalGerenciarEl) {
        modalGerenciarEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const diaId = button.dataset.diaId;
            const diaNome = button.dataset.diaNome;
            const nomeTreino = button.dataset.nomeTreino;
            document.getElementById('modalGerenciarTitle').textContent = `Exercícios de ${diaNome} (${nomeTreino})`;
            corpoModalGerenciar.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-danger"></div></div>';
            if (!diaId) {
                corpoModalGerenciar.innerHTML = '<div class="alert alert-warning">Você precisa primeiro definir um nome para este dia de treino.</div>';
                return;
            }
            fetch(`buscar_exercicios_dia.php?id_dia=${diaId}`)
                .then(response => response.json()).then(data => {
                    if (data.success) {
                        let exerciciosHtml = '';
                        if (data.exercicios.length === 0) {
                            exerciciosHtml = `
                                <div class="text-center py-4">
                                    <i class="bi bi-dumbbell text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2 mb-0">Nenhum exercício adicionado</p>
                                    <small class="text-muted">Adicione exercícios usando o formulário acima</small>
                                </div>
                            `;
                        } else {
                            data.exercicios.forEach(ex => {
                                exerciciosHtml += `
                                    <div class="exercise-item d-flex justify-content-between align-items-center p-3 mb-2 border rounded" id="rot-ex-row-${ex.id}">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold" style="color: var(--bs-body-color, #212529);">${escapeHTML(ex.nome_exercicio || 'Exercício sem nome')}</div>
                                            <div class="small mt-1" style="color: var(--bs-secondary-color, #6c757d);">
                                                <i class="bi bi-arrow-repeat me-1"></i>
                                                <span class="series-text">${escapeHTML(ex.series_sugeridas || 'N/A')}</span> séries × 
                                                <span class="reps-text">${escapeHTML(ex.repeticoes_sugeridas || 'N/A')}</span> reps
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary btn-editar-exercicio-rotina" data-id="${ex.id}" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-excluir-exercicio-rotina" data-id="${ex.id}" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        corpoModalGerenciar.innerHTML = `
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="bi bi-plus-circle me-2"></i>Adicionar Novo Exercício
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form id="formAddExercicioRotina">
                                        <input type="hidden" name="id_rotina_dia" value="${diaId}">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Nome do Exercício</label>
                                                <input type="text" name="nome_exercicio" class="form-control" placeholder="Ex: Supino Reto" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label fw-semibold">Séries</label>
                                                <input type="number" name="series_sugeridas" class="form-control" placeholder="4" min="1" max="10">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label fw-semibold">Repetições</label>
                                                <input type="text" name="repeticoes_sugeridas" class="form-control" placeholder="8-12">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="submit" class="btn btn-danger w-100">
                                                    <i class="bi bi-plus-lg me-1"></i>Adicionar
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h6 class="fw-bold">
                                    <i class="bi bi-list-check me-2"></i>Exercícios Planejados
                                </h6>
                                <div class="exercise-list">
                                    ${exerciciosHtml}
                                </div>
                            </div>
                        `;
                    } else { corpoModalGerenciar.innerHTML = `<p class="text-danger">${data.message}</p>`; }
                }).catch(err => { corpoModalGerenciar.innerHTML = `<p class="text-danger">Erro de rede.</p>`; });
        });
    }

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
                    const errorMsg = data.debug ? `${data.message} (Debug: ${data.debug})` : data.message;
                    showToast('Erro!', errorMsg, true);
                    console.error('Erro ao adicionar exercício:', data);
                }
            })
            .catch(error => {
                showToast('Erro!', 'Erro de conexão. Verifique sua internet.', true);
                console.error('Erro de rede:', error);
            })
            .finally(() => { button.disabled = false; });
        }
    });

    corpoModalGerenciar.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.btn-excluir-exercicio-rotina');
        const editButton = event.target.closest('.btn-editar-exercicio-rotina');
        const saveButton = event.target.closest('.btn-salvar-exercicio-rotina');
        
        if (deleteButton) {
            if (!confirm('Remover este exercício da rotina?')) return;
            const id = deleteButton.dataset.id;
            fetch('excluir_exercicio_rotina.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: id})})
            .then(res => res.json()).then(data => {
                if (data.success) { showToast('Sucesso!', data.message); const row = document.getElementById(`rot-ex-row-${id}`); if(row) { gsap.to(row, {duration: 0.5, opacity: 0, onComplete: () => row.remove()}); } } 
                else { showToast('Erro!', data.message, true); }
            });
        }
        
        if (editButton) {
            const exerciseItem = editButton.closest('.exercise-item');
            if (!exerciseItem) {
                console.error('Elemento exercise-item não encontrado');
                return;
            }
            
            const seriesEl = exerciseItem.querySelector('.series-text');
            const repsEl = exerciseItem.querySelector('.reps-text');
            
            if (!seriesEl || !repsEl) {
                console.error('Elementos series-text ou reps-text não encontrados');
                return;
            }
            
            const seriesValue = seriesEl.textContent.trim().replace('N/A', '');
            const repsValue = repsEl.textContent.trim().replace('N/A', '');
            
            seriesEl.innerHTML = `<input type="text" class="form-control form-control-sm d-inline-block" value="${seriesValue}" style="width: 70px; color: var(--bs-body-color, #212529);">`;
            repsEl.innerHTML = `<input type="text" class="form-control form-control-sm d-inline-block" value="${repsValue}" style="width: 70px; color: var(--bs-body-color, #212529);">`;
            
            editButton.innerHTML = '<i class="bi bi-check-lg"></i>';
            editButton.classList.remove('btn-editar-exercicio-rotina', 'btn-outline-primary');
            editButton.classList.add('btn-salvar-exercicio-rotina', 'btn-outline-success');
        }

        if (saveButton) {
            const exerciseItem = saveButton.closest('.exercise-item');
            if (!exerciseItem) {
                console.error('Elemento exercise-item não encontrado');
                return;
            }
            
            const id = saveButton.dataset.id;
            const seriesInput = exerciseItem.querySelector('.series-text input');
            const repsInput = exerciseItem.querySelector('.reps-text input');
            
            if (!seriesInput || !repsInput) {
                console.error('Inputs de edição não encontrados');
                return;
            }
            
            const originalSeries = seriesInput.value;
            const originalReps = repsInput.value;
            
            saveButton.disabled = true;
            saveButton.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;
            
            fetch('editar_exercicio_rotina.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id, series_sugeridas: originalSeries, repeticoes_sugeridas: originalReps })
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Erro na resposta do servidor');
                }
                return res.json();
            })
            .then(data => {
                if(data.success) {
                    showToast('Sucesso!', data.message);
                    exerciseItem.querySelector('.series-text').textContent = originalSeries || 'N/A';
                    exerciseItem.querySelector('.reps-text').textContent = originalReps || 'N/A';
                    saveButton.innerHTML = '<i class="bi bi-pencil-fill"></i>';
                    saveButton.classList.remove('btn-salvar-exercicio-rotina', 'btn-outline-success');
                    saveButton.classList.add('btn-editar-exercicio-rotina', 'btn-outline-primary');
                    saveButton.disabled = false;
                } else { 
                    showToast('Erro!', data.message || 'Erro ao atualizar exercício', true);
                    exerciseItem.querySelector('.series-text').textContent = originalSeries || 'N/A';
                    exerciseItem.querySelector('.reps-text').textContent = originalReps || 'N/A';
                    saveButton.innerHTML = '<i class="bi bi-pencil-fill"></i>';
                    saveButton.classList.remove('btn-salvar-exercicio-rotina', 'btn-outline-success');
                    saveButton.classList.add('btn-editar-exercicio-rotina', 'btn-outline-primary');
                    saveButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erro ao editar exercício:', error);
                showToast('Erro!', 'Erro de conexão. Tente novamente.', true);
                exerciseItem.querySelector('.series-text').textContent = originalSeries || 'N/A';
                exerciseItem.querySelector('.reps-text').textContent = originalReps || 'N/A';
                saveButton.innerHTML = '<i class="bi bi-pencil-fill"></i>';
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
            
            // Redirecionar para a página de registro de treino
            // Passando o id_rotina_dia como parâmetro para pré-carregar os exercícios
            window.location.href = `treinos.php?rotina_dia=${diaId}&dia=${encodeURIComponent(diaNome)}&treino=${encodeURIComponent(nomeTreino)}`;
        });
    });
});

// Modal Novo Treino - Validação e Submissão
const btnSalvarTreino = document.getElementById('btnSalvarTreino');
if (btnSalvarTreino) {
    btnSalvarTreino.addEventListener('click', function() {
        const form = document.getElementById('formNovoTreino');
        if (!form) {
            showToast('Erro!', 'Formulário não encontrado', true);
            return;
        }
        
        const formData = new FormData(form);
        const nomeTreino = formData.get('nome_treino')?.trim();
        const diaSemana = formData.get('dia_semana');
        
        // Validação
        if (!nomeTreino) {
            showToast('Erro!', 'O nome do treino é obrigatório', true);
            const inputNome = form.querySelector('input[name="nome_treino"]');
            if (inputNome) {
                inputNome.focus();
                inputNome.classList.add('is-invalid');
                setTimeout(() => inputNome.classList.remove('is-invalid'), 3000);
            }
            return;
        }
        
        if (!diaSemana || diaSemana === '') {
            showToast('Erro!', 'Selecione um dia da semana', true);
            const selectDia = form.querySelector('select[name="dia_semana"]');
            if (selectDia) {
                selectDia.focus();
                selectDia.classList.add('is-invalid');
                setTimeout(() => selectDia.classList.remove('is-invalid'), 3000);
            }
            return;
        }
        
        const button = this;
        const btnOriginal = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
        
        fetch('salvar_rotina_semanal.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na resposta do servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', data.message || 'Treino criado com sucesso!');
                form.reset();
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovoTreino'));
                if (modal) modal.hide();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Erro!', data.message || 'Erro ao criar treino', true);
                button.disabled = false;
                button.innerHTML = btnOriginal;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro de conexão. Verifique sua internet e tente novamente.', true);
            button.disabled = false;
            button.innerHTML = btnOriginal;
        });
    });
}
</script>

<!-- Modal Novo Treino -->
<div class="modal fade" id="modalNovoTreino" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Novo Treino
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovoTreino">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nome do Treino</label>
                            <input type="text" name="nome_treino" class="form-control" placeholder="Ex: Treino de Peito e Tríceps" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Dia da Semana</label>
                            <select name="dia_semana" class="form-select" required>
                                <option value="">Selecione o dia</option>
                                <option value="1">Domingo</option>
                                <option value="2">Segunda-feira</option>
                                <option value="3">Terça-feira</option>
                                <option value="4">Quarta-feira</option>
                                <option value="5">Quinta-feira</option>
                                <option value="6">Sexta-feira</option>
                                <option value="7">Sábado</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Descrição do Treino</label>
                            <textarea name="descricao" class="form-control" rows="3" placeholder="Descreva o objetivo e características do treino..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Duração Estimada (min)</label>
                            <input type="number" name="duracao" class="form-control" placeholder="60" min="15" max="180">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nível de Dificuldade</label>
                            <select name="nivel" class="form-select">
                                <option value="iniciante">Iniciante</option>
                                <option value="intermediario">Intermediário</option>
                                <option value="avancado">Avançado</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnSalvarTreino">
                    <i class="bi bi-check-lg me-2"></i>Criar Treino
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>