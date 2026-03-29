<?php
/**
 * setup_telegram_webhook.php
 * Acesse UMA VEZ pelo navegador para registrar o webhook no Telegram.
 * Depois pode deletar ou proteger este arquivo.
 */
require_once __DIR__ . '/includes/db_connect.php';

$cfg      = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$token    = $cfg['TELEGRAM_BOT_TOKEN'] ?? '';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$dir      = dirname($_SERVER['SCRIPT_NAME']);
$webhookUrl = $protocol . '://' . $host . rtrim($dir, '/') . '/telegram_bot.php';

if (empty($token)) {
    die('<h2 style="color:red">Token não configurado em config.json</h2>');
}

// Registrar webhook
$url = "https://api.telegram.org/bot{$token}/setWebhook";
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['url' => $webhookUrl, 'max_connections' => 40, 'allowed_updates' => ['message', 'callback_query', 'edited_message']]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
curl_close($ch);

$result = json_decode($resp, true);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Setup Telegram Webhook</title>
<style>
  body { font-family: monospace; background:#0d0d0f; color:#f5f5f7; padding:2rem; }
  .ok  { color:#30d158; }
  .err { color:#ff453a; }
  pre  { background:#161618; padding:1rem; border-radius:8px; border:1px solid rgba(255,255,255,.1); }
  a    { color:#4da6ff; }
</style>
</head>
<body>
<h2>🤖 Telegram Webhook Setup</h2>

<?php if ($err): ?>
    <p class="err">❌ Erro cURL: <?= htmlspecialchars($err) ?></p>
<?php elseif (!empty($result['ok'])): ?>
    <p class="ok">✅ Webhook registrado com sucesso!</p>
    <p><b>URL:</b> <code><?= htmlspecialchars($webhookUrl) ?></code></p>
    <p>O bot já está pronto para receber mensagens.</p>
    <p>👉 <a href="vincular_telegram.php">Vincular minha conta Telegram</a></p>
<?php else: ?>
    <p class="err">❌ Erro ao registrar webhook:</p>
    <pre><?= htmlspecialchars($resp) ?></pre>
<?php endif; ?>

<h3>Detalhes</h3>
<pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<p style="color:#8e8e93; margin-top:2rem; font-size:.8rem;">
    ⚠️ Após confirmar que está funcionando, você pode deletar este arquivo ou protegê-lo com senha.
</p>
</body>
</html>
