<?php
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: index.php'); exit; }

$eventos_json = '[]';
try {
    $sql = "SELECT id, descricao, data_limite, prioridade, hora_inicio, hora_fim
            FROM tarefas WHERE id_usuario=? AND data_limite IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $eventos = [];
    foreach ($dados as $t) {
        $cor = '#6c757d';
        if ($t['prioridade']==='Alta') $cor='#e50914';
        if ($t['prioridade']==='Média') $cor='#ffc107';
        if ($t['prioridade']==='Baixa') $cor='#198754';

        $start = $t['data_limite'];
        if (!empty($t['hora_inicio'])) $start .= 'T'.$t['hora_inicio'];
        $end = !empty($t['hora_fim']) ? $t['data_limite'].'T'.$t['hora_fim'] : null;

        $eventos[] = [
            'id'=>(int)$t['id'],
            'title'=>htmlspecialchars($t['descricao']),
            'start'=>$start,
            'end'=>$end,
            'allDay'=>empty($t['hora_inicio']) && empty($t['hora_fim']),
            'color'=>$cor,
            'extendedProps'=>['prioridade'=>htmlspecialchars($t['prioridade'])]
        ];
    }
    $eventos_json = json_encode($eventos, JSON_UNESCAPED_UNICODE);
} catch(PDOException $e){ die("Erro: ".$e->getMessage()); }
?>

<style>
  #calendario { min-height:75vh; }
  /* Mobile tweaks */
  @media (max-width: 767px) {
    .fc .fc-toolbar { flex-wrap: wrap; gap:6px; }
    .fc .fc-toolbar-title { font-size:1.1rem; }
    .fc .fc-button { font-size:.8rem; padding:0.3rem 0.5rem; }
  }
  /* Dark theme */
  .fc { --fc-border-color:#303030; --fc-page-bg-color:transparent; --fc-event-text-color:#f5f5f5; }
  .fc-day-today { background-color:rgba(229,9,20,.15)!important; }
  .fc-event { font-weight:600; padding:2px 4px; border:2px solid transparent; border-radius:6px; }
  .fc .fc-button-primary { background:#e50914; border:#e50914; }
  .fc .fc-button-primary:hover { background:#f40612; border:#f40612; }
  /* FAB para mobile */
  .fab-add {
    position:fixed; bottom:20px; right:20px; width:56px; height:56px;
    border-radius:50%; background:#e50914; color:#fff; display:flex;
    align-items:center; justify-content:center; font-size:1.6rem; border:none;
    box-shadow:0 6px 15px rgba(0,0,0,.4); z-index:1050;
  }
  .fab-add:hover { background:#f40612; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h2">Calendário de Tarefas</h1>
  <button class="btn btn-danger d-none d-md-inline-flex" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
    <i class="bi bi-plus-lg me-2"></i>Nova Tarefa
  </button>
</div>

<div class="card card-custom">
  <div class="card-body p-2 p-md-4">
    <div id="calendario"></div>
  </div>
</div>

<!-- FAB só no mobile -->
<button class="fab-add d-md-none" data-bs-toggle="modal" data-bs-target="#modalNovaTarefa">
  <i class="bi bi-plus-lg"></i>
</button>

<!-- Modal Nova Tarefa -->
<div class="modal fade" id="modalNovaTarefa" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Adicionar Tarefa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="adicionar_tarefa.php" method="POST">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Descrição</label>
            <input type="text" name="descricao" id="novaTarefaDescricao" class="form-control" required>
          </div>
          <div class="row">
            <div class="col-6 mb-3"><label class="form-label">Data</label><input type="date" name="data_limite" id="novaTarefaData" class="form-control"></div>
            <div class="col-3 mb-3"><label class="form-label">Início</label><input type="time" name="hora_inicio" class="form-control"></div>
            <div class="col-3 mb-3"><label class="form-label">Fim</label><input type="time" name="hora_fim" class="form-control"></div>
          </div>
          <div class="mb-3"><label class="form-label">Prioridade</label>
            <select name="prioridade" class="form-select">
              <option value="Baixa">Baixa</option><option value="Média" selected>Média</option><option value="Alta">Alta</option>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Salvar</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Detalhes -->
<div class="modal fade" id="modalDetalhesTarefa" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="modalTarefaTitulo"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>Prioridade:</strong> <span id="modalTarefaPrioridade"></span></p>
        <p><strong>Data/Hora:</strong> <span id="modalTarefaData"></span></p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button><a href="#" id="modalTarefaEditarBtn" class="btn btn-danger">Editar</a></div>
    </div>
  </div>
</div>

<!-- FullCalendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/list@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const calendarEl = document.getElementById('calendario');
  const eventos = <?php echo $eventos_json; ?>;
  const isMobile = window.innerWidth < 768;

  const calendar = new FullCalendar.Calendar(calendarEl, {
    height: '100%',
    initialView: isMobile ? 'listWeek' : 'timeGridWeek',
    slotMinTime: "06:00:00",
    slotMaxTime: "23:00:00",
    nowIndicator: true,
    locale: 'pt-br',
    headerToolbar: isMobile 
      ? { left: 'prev,next', center: 'title', right: 'today' }
      : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
    buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', list: 'Lista' },
    events: eventos,
    dateClick: info => {
      document.getElementById('novaTarefaData').value = info.dateStr;
      new bootstrap.Modal('#modalNovaTarefa').show();
    },
    eventClick: info => {
      const e = info.event;
      const data = e.start ? e.start.toLocaleDateString('pt-BR') : '-';
      const horaIni = e.start ? e.start.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}) : null;
      const horaFim = e.end ? e.end.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}) : null;
      document.getElementById('modalTarefaTitulo').innerText = e.title;
      document.getElementById('modalTarefaPrioridade').innerText = e.extendedProps.prioridade;
      document.getElementById('modalTarefaData').innerText = data+(horaIni?`, ${horaIni}${horaFim?' - '+horaFim:''}`:'');
      document.getElementById('modalTarefaEditarBtn').href = 'editar_tarefa.php?id='+e.id;
      new bootstrap.Modal('#modalDetalhesTarefa').show();
    }
  });
  calendar.render();
});
</script>

<?php require_once 'templates/footer.php'; ?>
