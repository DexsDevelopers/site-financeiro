<?php
// editar_tarefa.php — Página completa para editar tarefa com AJAX (envia para atualizar_tarefa.php)
declare(strict_types=1);
session_start();

// ===== Guardas de sessão =====
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}

// ===== Carrega conexão PDO =====
require __DIR__ . '/includes/db_connect.php';

// ===== Sanitização do ID da tarefa =====
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Parâmetro "id" inválido.';
    exit;
}

// ===== Busca a tarefa =====
try {
    $stmt = $pdo->prepare("
        SELECT id, descricao, prioridade, data_limite, hora_inicio, hora_fim, tempo_estimado, status
        FROM tarefas
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarefa) {
        http_response_code(404);
        echo 'Tarefa não encontrada.';
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao carregar a tarefa.';
    exit;
}

// ===== CSRF Token =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Helpers para preencher o form com segurança
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function val_attr(?string $v): string {
    return $v !== null ? e($v) : '';
}
// Normaliza tempo HH:MM
function hhmm(?string $time): string {
    if (!$time) return '';
    // Aceita "HH:MM:SS" e retorna "HH:MM"
    $parts = explode(':', $time);
    if (count($parts) >= 2) return sprintf('%02d:%02d', (int)$parts[0], (int)$parts[1]);
    return '';
}

// Valores atuais
$descricao      = (string)($tarefa['descricao'] ?? '');
$prioridade     = (string)($tarefa['prioridade'] ?? 'media');
$data_limite    = (string)($tarefa['data_limite'] ?? '');      // yyyy-mm-dd
$hora_inicio    = hhmm($tarefa['hora_inicio'] ?? null);        // HH:MM
$hora_fim       = hhmm($tarefa['hora_fim'] ?? null);           // HH:MM
$tempo_estimado = (string)($tarefa['tempo_estimado'] ?? '');    // minutos
$status         = (string)($tarefa['status'] ?? 'pendente');

?><!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar Tarefa #<?php echo (int)$id; ?></title>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Alpine.js (opcional para microinterações) -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Ícones (Bootstrap Icons) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
/* Foco visível e contraste AA (ajustes sutis) */
:focus {
    outline: 2px solid #10b981; /* emerald */
    outline-offset: 2px;
}
/* Estado de carregamento no botão */
button[aria-busy="true"] {
    pointer-events: none;
    opacity: .7;
}
</style>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">

<header class="border-b border-slate-800 bg-slate-900/60 backdrop-blur">
  <div class="max-w-5xl mx-auto px-4 py-4 flex items-center gap-3">
    <a href="javascript:history.back()" class="text-slate-300 hover:text-white focus:text-white inline-flex items-center gap-2" aria-label="Voltar">
      <i class="bi bi-arrow-left"></i><span class="hidden sm:inline">Voltar</span>
    </a>
    <h1 class="text-lg sm:text-xl font-semibold">Editar Tarefa <span class="text-emerald-400">#<?php echo (int)$id; ?></span></h1>
  </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-6">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Formulário -->
    <section class="lg:col-span-2">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl p-5 sm:p-6">
        <form id="formEditarTarefa" class="space-y-5" novalidate>
          <!-- ID da tarefa -->
          <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
          <!-- CSRF -->
          <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

          <div>
            <label for="descricao" class="block text-sm font-medium text-slate-200">Descrição</label>
            <textarea id="descricao" name="descricao" rows="3" required
              class="mt-2 w-full resize-y rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-slate-100 placeholder-slate-400 focus:border-emerald-500"
              placeholder="Descreva a tarefa..."><?php echo e($descricao); ?></textarea>
            <p class="mt-1 text-xs text-slate-400">Seja específico. Ex.: “Revisar relatório mensal do financeiro”.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label for="prioridade" class="block text-sm font-medium text-slate-200">Prioridade</label>
              <select id="prioridade" name="prioridade" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-slate-100 focus:border-emerald-500" required>
                <option value="baixa"  <?php echo $prioridade==='baixa'?'selected':''; ?>>Baixa</option>
                <option value="media"  <?php echo $prioridade==='media'?'selected':''; ?>>Média</option>
                <option value="alta"   <?php echo $prioridade==='alta'?'selected':''; ?>>Alta</option>
              </select>
            </div>

            <div>
              <label for="status" class="block text-sm font-medium text-slate-200">Status</label>
              <select id="status" name="status" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-slate-100 focus:border-emerald-500" required>
                <option value="pendente"      <?php echo $status==='pendente'?'selected':''; ?>>Pendente</option>
                <option value="em_progresso"  <?php echo $status==='em_progresso'?'selected':''; ?>>Em progresso</option>
                <option value="concluida"     <?php echo $status==='concluida'?'selected':''; ?>>Concluída</option>
              </select>
            </div>

            <div>
              <label for="tempo_estimado" class="block text-sm font-medium text-slate-200">Tempo estimado (min)</label>
              <input type="number" id="tempo_estimado" name="tempo_estimado" min="0" step="1"
                     value="<?php echo val_attr($tempo_estimado); ?>"
                     class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-slate-100 placeholder-slate-400 focus:border-emerald-500"
                     placeholder="Ex.: 90">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label for="data_limite" class="block text-sm font-medium text-slate-200">Data</label>
              <input type="date" id="data_limite" name="data_limite"
                     value="<?php echo val_attr($data_limite); ?>"
                     class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-slate-100 focus:border-emerald-500">
            </div>
            <div>
              <label for="hora_inicio" class="block text-sm font-medium text-slate-200">Hora de início</label>
              <input type="time" id="hora_inicio" name="hora_inicio"
                     value="<?php echo val_attr($hora_inicio); ?>"
                     class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-slate-100 focus:border-emerald-500">
            </div>
            <div>
              <label for="hora_fim" class="block text-sm font-medium text-slate-200">Hora de fim</label>
              <input type="time" id="hora_fim" name="hora_fim"
                     value="<?php echo val_attr($hora_fim); ?>"
                     class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/60 px-4 py-3 text-slate-100 focus:border-emerald-500">
            </div>
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button id="btnSalvar" type="submit"
              class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 font-medium text-white hover:bg-emerald-500 focus:ring-2 focus:ring-emerald-400 transition"
              aria-busy="false">
              <i class="bi bi-check2-circle text-lg"></i>
              <span>Salvar alterações</span>
            </button>

            <a href="tarefas.php" class="inline-flex items-center gap-2 rounded-xl border border-slate-700 px-5 py-3 text-slate-200 hover:bg-slate-800 transition focus:ring-2 focus:ring-slate-600">
              <i class="bi bi-list-task text-lg"></i>
              Voltar para tarefas
            </a>
          </div>
        </form>
      </div>
    </section>

    <!-- Card de dicas -->
    <aside aria-label="Dicas de preenchimento">
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl p-5 sm:p-6">
        <h2 class="text-base font-semibold text-slate-100 flex items-center gap-2">
          <i class="bi bi-lightbulb"></i> Dicas
        </h2>
        <ul class="mt-3 space-y-2 text-sm text-slate-300 list-disc pl-5">
          <li>Use descrições claras e objetivas.</li>
          <li>Defina <span class="text-emerald-400">hora de início e fim</span> para facilitar o planejamento.</li>
          <li>“Tempo estimado” ajuda no cálculo de carga diária.</li>
          <li>Status “Concluída” some dos pendentes imediatamente.</li>
        </ul>
      </div>
    </aside>
  </div>
</main>

<!-- JS da página -->
<script>
// Acessibilidade: foca o primeiro erro
function focusFirstInvalid(form) {
  const invalid = form.querySelector(':invalid');
  if (invalid) invalid.focus();
}

// Serializa o form em objeto
function formToJSON(form) {
  const data = new FormData(form);
  return Object.fromEntries(data.entries());
}

// Validações locais simples
function validarCampos(payload) {
  // descricao obrigatória
  if (!payload.descricao || payload.descricao.trim().length < 3) {
    return 'Informe uma descrição com pelo menos 3 caracteres.';
  }
  // prioridade/status obrigatórios
  if (!payload.prioridade) return 'Selecione a prioridade.';
  if (!payload.status) return 'Selecione o status.';
  // horas coerentes (se ambas existirem)
  if (payload.hora_inicio && payload.hora_fim) {
    if (payload.hora_inicio >= payload.hora_fim) {
      return 'Hora de fim deve ser maior que a hora de início.';
    }
  }
  // tempo estimado numérico >= 0 (opcional)
  if (payload.tempo_estimado && Number(payload.tempo_estimado) < 0) {
    return 'Tempo estimado deve ser zero ou positivo.';
  }
  return null;
}

document.getElementById('formEditarTarefa').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.currentTarget;
  if (!form.checkValidity()) {
    focusFirstInvalid(form);
    return;
  }

  const btn = document.getElementById('btnSalvar');
  btn.setAttribute('aria-busy', 'true');
  btn.querySelector('span').textContent = 'Salvando...';

  const payload = formToJSON(form);
  const erro = validarCampos(payload);
  if (erro) {
    Swal.fire({icon: 'warning', title: 'Atenção', text: erro});
    btn.setAttribute('aria-busy', 'false');
    btn.querySelector('span').textContent = 'Salvar alterações';
    return;
  }

  // Envia para o endpoint existente no seu projeto
  // IMPORTANTE: ajustar atualizar_tarefa.php para validar CSRF se ainda não faz isso
  try {
    const res = await fetch('atualizar_tarefa.php', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      body: new FormData(form)
    });

    // Tenta ler JSON; se não for JSON, faz fallback
    let json = null;
    const text = await res.text();
    try { json = JSON.parse(text); } catch (_) {}

    if (!res.ok) {
      const msg = (json && json.error) ? json.error : 'Falha ao atualizar a tarefa.';
      throw new Error(msg);
    }

    const ok = json && (json.success === true || json.status === 'ok');
    if (ok) {
      await Swal.fire({icon: 'success', title: 'Tarefa atualizada!', text: 'As alterações foram salvas com sucesso.'});
      // Redireciona para a lista de tarefas (ajuste se quiser voltar para origem)
      window.location.href = 'tarefas.php';
    } else {
      const msg = (json && (json.error || json.message)) ? (json.error || json.message) : 'Não foi possível atualizar a tarefa.';
      throw new Error(msg);
    }
  } catch (err) {
    Swal.fire({icon: 'error', title: 'Erro', text: String(err.message || err)});
  } finally {
    btn.setAttribute('aria-busy', 'false');
    btn.querySelector('span').textContent = 'Salvar alterações';
  }
});
</script>

</body>
</html>
