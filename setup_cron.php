<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth_check.php';

$cfg    = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$secret = $cfg['CRON_SECRET'] ?? 'sem-token';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'seu-site.com';
$dir      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$cronUrl  = "{$protocol}://{$host}{$dir}/cron_notificacoes.php?token={$secret}";

// Testar manualmente
$testResult = null;
if (isset($_GET['testar'])) {
    $ch = curl_init($cronUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    $testResult = $err ?: $resp;
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="container py-4" style="max-width:780px">

  <h2 class="mb-1">⏰ Notificações Automáticas</h2>
  <p class="text-muted mb-4">Configure para receber lembretes no <b>Telegram</b> e no <b>site</b> mesmo com o navegador fechado.</p>

  <!-- URL do cron -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <h5 class="card-title mb-3">🔗 URL do seu cron</h5>
      <div class="input-group mb-2">
        <input id="cronUrl" type="text" class="form-control font-monospace" readonly
               value="<?= htmlspecialchars($cronUrl) ?>">
        <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('cronUrl').value);this.textContent='✅ Copiado!'">
          📋 Copiar
        </button>
      </div>
      <small class="text-muted">Guarde esta URL — é usada para acionar as notificações automaticamente.</small>
    </div>
  </div>

  <!-- Passo a passo cron-job.org -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <h5 class="card-title mb-3">🚀 Configurar no cron-job.org <span class="badge bg-success">Grátis</span></h5>
      <p class="text-muted small">Serviço externo gratuito que chama sua URL a cada minuto. <b>Sem precisar do hPanel.</b></p>

      <ol class="mb-0" style="line-height:2.2">
        <li>
          Acesse
          <a href="https://cron-job.org" target="_blank" class="fw-bold">cron-job.org</a>
          e crie uma conta gratuita (só email + senha)
        </li>
        <li>Clique em <b>"CREATE CRONJOB"</b></li>
        <li>
          Em <b>URL</b> cole:<br>
          <code class="bg-dark text-success px-2 py-1 rounded d-inline-block my-1"><?= htmlspecialchars($cronUrl) ?></code>
        </li>
        <li>
          Em <b>Schedule</b> selecione <b>"Every minute"</b>
        </li>
        <li>Clique em <b>CREATE</b> — pronto! ✅</li>
      </ol>
    </div>
  </div>

  <!-- Testar agora -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <h5 class="card-title mb-3">🧪 Testar agora</h5>
      <p class="text-muted small">Clique para disparar o cron manualmente e verificar se está funcionando.</p>
      <a href="?testar=1" class="btn btn-outline-primary">▶️ Executar cron agora</a>

      <?php if ($testResult !== null): ?>
        <div class="mt-3 p-3 rounded <?= str_contains($testResult, '"ok":true') ? 'bg-success bg-opacity-10 border border-success' : 'bg-danger bg-opacity-10 border border-danger' ?>">
          <b><?= str_contains($testResult, '"ok":true') ? '✅ Sucesso!' : '❌ Erro:' ?></b>
          <pre class="mb-0 mt-1 small"><?= htmlspecialchars(substr($testResult, 0, 500)) ?></pre>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Como funciona -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h5 class="card-title mb-3">ℹ️ Como funciona</h5>
      <ul class="mb-0 text-muted small" style="line-height:2">
        <li>Ao criar uma tarefa com horário (<i>"lembrar às 22h"</i>), o sistema salva o lembrete</li>
        <li>O cron roda a cada minuto e verifica se tem tarefa vencendo</li>
        <li>Quando o horário chegar:<br>
          📱 Envia mensagem no <b>Telegram</b><br>
          🔔 Dispara <b>Web Push</b> no celular/PC (mesmo com o site fechado)<br>
          📋 Salva no histórico de notificações
        </li>
        <li>Cada lembrete é enviado <b>uma única vez</b></li>
      </ul>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
