<?php
// cursos.php (Versão Final com Botões Diretos de Ação)
require_once 'templates/header.php';

$cursos_pendentes = [];
$cursos_assistindo = [];
$cursos_concluidos = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM cursos WHERE id_usuario = ? ORDER BY status, ordem ASC");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $curso) {
        if ($curso['status'] == 'pendente') $cursos_pendentes[] = $curso;
        elseif ($curso['status'] == 'assistindo') $cursos_assistindo[] = $curso;
        else $cursos_concluidos[] = $curso;
    }
} catch (PDOException $e) {
    die("Erro ao buscar cursos: " . $e->getMessage());
}
?>
<style>
    .kanban-board { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start; }
    .kanban-column { background-color: rgba(0,0,0,0.2); border-radius: var(--border-radius); padding: 1rem; }
    .kanban-list { min-height: 200px; }
    .course-card { background-color: var(--card-background); border: 1px solid var(--border-color); cursor: grab; }
    .course-card .card-body { padding: 1rem; }
    .sortable-ghost { background: var(--accent-red); opacity: 0.5; border-radius: var(--border-radius); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Meus Cursos</h1>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovoCurso"><i class="bi bi-plus-lg me-2"></i>Adicionar Curso</button>
</div>

<div class="kanban-board" id="kanban-board">
    <div class="kanban-column">
        <h4 class="mb-3"><i class="bi bi-collection text-secondary"></i> Pendentes</h4>
        <div class="kanban-list d-flex flex-column gap-3" id="pendente" data-status="pendente">
            <?php foreach($cursos_pendentes as $curso): ?>
                <div class="card course-card" data-id="<?php echo $curso['id']; ?>">
                    <div class="card-body">
                        <h6 class="mb-2"><?php echo htmlspecialchars($curso['nome_curso']); ?></h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <div><?php if($curso['link_curso']): ?><a href="<?php echo htmlspecialchars($curso['link_curso']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Acessar Link <i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary btn-editar-curso" data-id="<?php echo $curso['id']; ?>" data-nome="<?php echo htmlspecialchars($curso['nome_curso']); ?>" data-link="<?php echo htmlspecialchars($curso['link_curso']); ?>"><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-excluir-curso" data-id="<?php echo $curso['id']; ?>" data-nome="<?php echo htmlspecialchars($curso['nome_curso']); ?>"><i class="bi bi-trash-fill"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="kanban-column">
        <h4 class="mb-3"><i class="bi bi-play-circle-fill text-primary"></i> Assistindo</h4>
        <div class="kanban-list d-flex flex-column gap-3" id="assistindo" data-status="assistindo">
            <?php foreach($cursos_assistindo as $curso): ?>
                <div class="card course-card" data-id="<?php echo $curso['id']; ?>">
                    <div class="card-body">
                         <h6 class="mb-2"><?php echo htmlspecialchars($curso['nome_curso']); ?></h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <div><?php if($curso['link_curso']): ?><a href="<?php echo htmlspecialchars($curso['link_curso']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Acessar Link <i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary btn-editar-curso" data-id="<?php echo $curso['id']; ?>" data-nome="<?php echo htmlspecialchars($curso['nome_curso']); ?>" data-link="<?php echo htmlspecialchars($curso['link_curso']); ?>"><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-excluir-curso" data-id="<?php echo $curso['id']; ?>" data-nome="<?php echo htmlspecialchars($curso['nome_curso']); ?>"><i class="bi bi-trash-fill"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="kanban-column">
        <h4 class="mb-3"><i class="bi bi-check-circle-fill text-success"></i> Concluídos</h4>
        <div class="kanban-list d-flex flex-column gap-3" id="concluido" data-status="concluido">
             <?php foreach($cursos_concluidos as $curso): ?>
                <div class="card course-card" data-id="<?php echo $curso['id']; ?>">
                    <div class="card-body">
                         <h6 class="mb-2"><?php echo htmlspecialchars($curso['nome_curso']); ?></h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <div><?php if($curso['link_curso']): ?><a href="<?php echo htmlspecialchars($curso['link_curso']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Acessar Link <i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary btn-editar-curso" data-id="<?php echo $curso['id']; ?>" data-nome="<?php echo htmlspecialchars($curso['nome_curso']); ?>" data-link="<?php echo htmlspecialchars($curso['link_curso']); ?>"><i class="bi bi-pencil-fill"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-excluir-curso" data-id="<?php echo $curso['id']; ?>" data-nome="<?php echo htmlspecialchars($curso['nome_curso']); ?>"><i class="bi bi-trash-fill"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovoCurso" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Adicionar Novo Curso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formNovoCurso" action="salvar_curso.php" method="POST"><div class="modal-body"><div class="mb-3"><label for="nome_curso" class="form-label">Nome do Curso</label><input type="text" name="nome_curso" class="form-control" required></div><div class="mb-3"><label for="link_curso" class="form-label">Link do Curso (Opcional)</label><input type="url" name="link_curso" class="form-control"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar</button></div></form></div></div></div>
<div class="modal fade" id="modalEditarCurso" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar Curso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="formEditarCurso" action="editar_curso.php" method="POST"><input type="hidden" name="id" id="edit_curso_id"><div class="modal-body"><div class="mb-3"><label for="edit_nome_curso" class="form-label">Nome do Curso</label><input type="text" id="edit_nome_curso" name="nome_curso" class="form-control" required></div><div class="mb-3"><label for="edit_link_curso" class="form-label">Link do Curso (Opcional)</label><input type="url" id="edit_link_curso" name="link_curso" class="form-control"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar Alterações</button></div></form></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });

    const modalNovoCurso = new bootstrap.Modal(document.getElementById('modalNovoCurso'));
    const formNovoCurso = document.getElementById('formNovoCurso');
    const modalEditarCursoEl = document.getElementById('modalEditarCurso');
    const modalEditarCurso = new bootstrap.Modal(modalEditarCursoEl);
    const formEditarCurso = document.getElementById('formEditarCurso');
    const kanbanBoard = document.getElementById('kanban-board');

    if (formNovoCurso) {
        formNovoCurso.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formNovoCurso);
            const button = formNovoCurso.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            fetch('salvar_curso.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            }).catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true)).finally(() => { button.disabled = false; button.innerHTML = 'Salvar'; modalNovoCurso.hide(); });
        });
    }
    
    if (kanbanBoard) {
        kanbanBoard.addEventListener('click', function(event) {
            const editButton = event.target.closest('.btn-editar-curso');
            const deleteButton = event.target.closest('.btn-excluir-curso');

            if (editButton) {
                document.getElementById('edit_curso_id').value = editButton.dataset.id;
                document.getElementById('edit_nome_curso').value = editButton.dataset.nome;
                document.getElementById('edit_link_curso').value = editButton.dataset.link;
                modalEditarCurso.show();
            }

            if (deleteButton) {
                const cursoId = deleteButton.dataset.id;
                const cursoNome = deleteButton.dataset.nome;
                Swal.fire({ title: 'Tem certeza?', text: `Excluir o curso "${cursoNome}"?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d', confirmButtonText: 'Sim, excluir!', cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('excluir_curso.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: cursoId})})
                        .then(res => res.json()).then(data => {
                            if(data.success) {
                                showToast('Sucesso!', data.message);
                                const card = document.querySelector(`.course-card[data-id='${cursoId}']`);
                                if(card) gsap.to(card, {duration: 0.5, opacity: 0, scale: 0.9, onComplete: () => card.remove()});
                            } else { showToast('Erro!', data.message, true); }
                        }).catch(err => showToast('Erro de Rede!', 'Não foi possível conectar.', true));
                    }
                });
            }
        });
    }

    if(formEditarCurso) {
        formEditarCurso.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarCurso);
            const button = formEditarCurso.querySelector('button[type="submit"]');
            button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
            fetch('editar_curso.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                if(data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else { showToast('Erro!', data.message, true); }
            }).finally(() => { button.disabled = false; button.innerHTML = 'Salvar Alterações'; modalEditarCurso.hide(); });
        });
    }

    const kanbanLists = document.querySelectorAll('.kanban-list');
    kanbanLists.forEach(list => {
        new Sortable(list, {
            group: 'cursos', animation: 150, ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                const cursoId = evt.item.dataset.id;
                const novaColuna = evt.to;
                const novoStatus = novaColuna.dataset.status;
                const ordemIds = Array.from(novaColuna.children).map(card => card.dataset.id);
                fetch('atualizar_status_curso.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: cursoId, status: novoStatus, ordem: ordemIds }) })
                .then(response => response.json()).then(data => {
                    if (!data.success) { showToast('Erro!', data.message, true); evt.from.appendChild(evt.item); }
                }).catch(error => { showToast('Erro de Rede!', 'Não foi possível salvar a alteração.', true); evt.from.appendChild(evt.item); });
            }
        });
    });
});
</script>

<?php
require_once 'templates/footer.php';
?>