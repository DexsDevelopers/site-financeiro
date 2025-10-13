<?php
// treinos.php (Versão Final com Histórico e Seletor de Data)

require_once 'templates/header.php';

// Pega a data da URL (GET), ou usa a data de hoje como padrão.
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

// Validação simples para garantir que a data está em um formato esperado
try {
    $dataObj = new DateTime($data_selecionada);
    $data_formatada = $dataObj->format('d/m/Y');
} catch (Exception $e) {
    $data_selecionada = date('Y-m-d');
    $data_formatada = date('d/m/Y');
}

$registros_dia = [];
try {
    $sql = "SELECT rt.id, e.nome_exercicio, rt.series, rt.repeticoes, rt.carga, rt.observacoes
            FROM registros_treino rt
            JOIN exercicios e ON rt.id_exercicio = e.id
            WHERE rt.id_usuario = ? AND rt.data_treino = ?
            ORDER BY rt.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $data_selecionada]);
    $registros_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar registros de treino: " . $e->getMessage());
}
?>

<style>
    .exercise-card { transition: all 0.3s ease; }
    .exercise-card:hover { transform: translateY(-3px); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h2 mb-0">Registro de Treino</h1>
    <div class="d-flex align-items-center gap-2">
        <form id="filtroDataTreino" class="d-flex align-items-center gap-2">
            <label for="data_treino" class="form-label mb-0">Ver treino do dia:</label>
            <input type="date" class="form-control" id="data_treino" name="data" value="<?php echo $data_selecionada; ?>" style="width: 160px;">
        </form>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovoExercicio"><i class="bi bi-plus-lg"></i></button>
    </div>
</div>

<div class="row g-3" id="lista-registros-treino">
    <?php if (empty($registros_dia)): ?>
        <div class="col-12" id="no-records-row">
            <div class="card card-custom"><div class="card-body text-center p-5"><i class="bi bi-barbell fs-1 text-muted"></i><h5 class="mt-3 text-muted">Nenhum exercício registrado para <?php echo ($data_selecionada == date('Y-m-d')) ? 'hoje' : 'o dia ' . $data_formatada; ?>.</h5><p class="text-muted mb-0">Use o botão "+" para começar seu treino.</p></div></div>
        </div>
    <?php else: ?>
        <?php foreach($registros_dia as $reg): ?>
            <div class="col-md-6 col-lg-4" id="registro-card-<?php echo $reg['id']; ?>" data-aos="fade-up">
                <div class="card card-custom h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($reg['nome_exercicio']); ?></h5>
                            <div>
                                <button class="btn btn-sm btn-outline-primary border-0 btn-editar-registro" data-registro='<?php echo json_encode($reg, JSON_HEX_QUOT | JSON_HEX_TAG); ?>'><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-outline-danger border-0 btn-excluir-registro" data-id="<?php echo $reg['id']; ?>" data-nome="<?php echo htmlspecialchars($reg['nome_exercicio']); ?>"><i class="bi bi-trash-fill"></i></button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-around text-center mt-3 mb-2">
                            <div><small class="text-muted d-block">Séries</small><strong><?php echo htmlspecialchars($reg['series'] ?: '-'); ?></strong></div>
                            <div><small class="text-muted d-block">Reps</small><strong><?php echo htmlspecialchars($reg['repeticoes'] ?: '-'); ?></strong></div>
                            <div><small class="text-muted d-block">Carga</small><strong><?php echo htmlspecialchars($reg['carga'] ? $reg['carga'] . ' Kg' : '-'); ?></strong></div>
                        </div>
                        <?php if(!empty($reg['observacoes'])): ?><p class="card-text text-muted small fst-italic mt-2"><i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($reg['observacoes']); ?></p><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalNovoExercicio" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Adicionar Exercício ao Treino</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formNovoRegistro"><input type="hidden" name="data_treino" value="<?php echo $data_selecionada; ?>"><div class="modal-body"><div class="mb-3"><label class="form-label">Exercício</label><input type="text" name="exercicio" class="form-control" required><div class="form-text">Se o exercício não existir, será criado.</div></div><div class="row"><div class="col-md-4 mb-3"><label class="form-label">Séries</label><input type="text" name="series" class="form-control"></div><div class="col-md-4 mb-3"><label class="form-label">Repetições</label><input type="text" name="repeticoes" class="form-control"></div><div class="col-md-4 mb-3"><label class="form-label">Carga (Kg)</label><input type="number" step="0.5" name="carga" class="form-control"></div></div><div class="mb-3"><label class="form-label">Observações</label><textarea name="observacoes" class="form-control" rows="2"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Adicionar</button></div></form></div></div></div>

<div class="modal fade" id="modalEditarExercicio" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar Registro</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formEditarRegistro"><input type="hidden" name="id" id="edit-registro-id"><div class="modal-body"><h6 id="edit-exercicio-nome" class="mb-3"></h6><div class="row"><div class="col-md-4 mb-3"><label class="form-label">Séries</label><input type="text" name="series" id="edit-series" class="form-control"></div><div class="col-md-4 mb-3"><label class="form-label">Repetições</label><input type="text" name="repeticoes" id="edit-repeticoes" class="form-control"></div><div class="col-md-4 mb-3"><label class="form-label">Carga (Kg)</label><input type="number" step="0.5" name="carga" id="edit-carga" class="form-control"></div></div><div class="mb-3"><label class="form-label">Observações</label><textarea name="observacoes" id="edit-observacoes" class="form-control" rows="2"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
    // --- LÓGICA DO FILTRO DE DATA ---
    const inputData = document.getElementById('data_treino');
    if (inputData) {
        inputData.addEventListener('change', function() {
            const dataSelecionada = this.value;
            window.location.href = `treinos.php?data=${dataSelecionada}`;
        });
    }

    const modalNovoExercicio = new bootstrap.Modal(document.getElementById('modalNovoExercicio'));
    const formNovoRegistro = document.getElementById('formNovoRegistro');
    const modalEditarExercicio = new bootstrap.Modal(document.getElementById('modalEditarExercicio'));
    const formEditarRegistro = document.getElementById('formEditarRegistro');

    // --- LÓGICA PARA ADICIONAR REGISTRO ---
    formNovoRegistro.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(formNovoRegistro);
        const button = formNovoRegistro.querySelector('button[type="submit"]');
        button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>...';
        fetch('salvar_registro_treino.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            if(data.success) { showToast('Sucesso!', data.message); setTimeout(() => window.location.reload(), 1000); }
            else { showToast('Erro!', data.message, true); }
        }).finally(() => { button.disabled = false; button.innerHTML = 'Adicionar'; modalNovoExercicio.hide(); });
    });

    // --- LÓGICA PARA EDITAR REGISTRO ---
    formEditarRegistro.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(formEditarRegistro);
        const button = formEditarRegistro.querySelector('button[type="submit"]');
        button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>...';
        fetch('editar_registro_treino.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
            if(data.success) { showToast('Sucesso!', data.message); setTimeout(() => window.location.reload(), 1000); }
            else { showToast('Erro!', data.message, true); }
        }).finally(() => { button.disabled = false; button.innerHTML = 'Salvar'; modalEditarExercicio.hide(); });
    });

    // --- LÓGICA PARA ABRIR MODAL DE EDIÇÃO E EXCLUIR ---
    document.getElementById('lista-registros-treino').addEventListener('click', function(event) {
        const editButton = event.target.closest('.btn-editar-registro');
        const deleteButton = event.target.closest('.btn-excluir-registro');

        if(editButton) {
            const registroData = JSON.parse(editButton.dataset.registro);
            document.getElementById('edit-registro-id').value = registroData.id;
            document.getElementById('edit-exercicio-nome').textContent = registroData.nome_exercicio;
            document.getElementById('edit-series').value = registroData.series;
            document.getElementById('edit-repeticoes').value = registroData.repeticoes;
            document.getElementById('edit-carga').value = registroData.carga;
            document.getElementById('edit-observacoes').value = registroData.observacoes;
            modalEditarExercicio.show();
        }

        if(deleteButton) {
            const registroId = deleteButton.dataset.id;
            const registroNome = deleteButton.dataset.nome;
            Swal.fire({ title: 'Tem certeza?', text: `Excluir o registro de "${registroNome}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sim, excluir!', cancelButtonText: 'Cancelar' })
            .then((result) => {
                if(result.isConfirmed) {
                    fetch('excluir_registro_treino.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: registroId})})
                    .then(res => res.json()).then(data => {
                        if(data.success) {
                            showToast('Sucesso!', data.message);
                            const card = document.getElementById(`registro-card-${registroId}`);
                            if(card) gsap.to(card, {duration: 0.5, opacity: 0, onComplete: () => card.remove()});
                        } else { showToast('Erro!', data.message, true); }
                    });
                }
            });
        }
    });
});
</script>

<?php
require_once 'templates/footer.php';
?>