<?php
require_once 'templates/header.php';
require_once 'includes/whatsapp_client.php';

// Lê config e status do bot
$cfg = wpp_get_config();
$status = null;
try {
    $ch = curl_init($cfg['base'] . '/status');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $status = json_decode($resp, true);
} catch (Throwable $e) { $status = null; }

$resultado = null;
$normalizedTo = null;
$action = $_POST['action'] ?? 'send';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toRaw = trim($_POST['to'] ?? '');
    // Normaliza via cliente (mesma lógica do envio)
    $normalizedTo = wpp_normalize_number($toRaw);

    if ($action === 'check') {
        if ($normalizedTo) {
            $resultado = wpp_test_number($normalizedTo);
        } else {
            $resultado = ['ok' => false, 'error' => 'invalid_number'];
        }
    } else { // send
        $text = trim($_POST['text'] ?? '');
        if ($normalizedTo && $text) {
            $resultado = wpp_send_message($normalizedTo, $text);
        } else {
            $resultado = ['ok' => false, 'error' => 'Preencha telefone e mensagem.'];
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0">Teste WhatsApp</h1>
</div>

<main class="container-fluid p-0">
    <div class="card card-glass">
        <div class="card-body p-4">
            <div class="row g-3 mb-2">
                <div class="col-md-6">
                    <div class="alert <?php echo (!empty($status['ready'])) ? 'alert-success' : 'alert-warning'; ?> p-2 mb-0">
                        Bot: <code><?php echo htmlspecialchars($cfg['base']); ?></code> — Status: <?php echo (!empty($status['ready'])) ? 'pronto' : 'aguardando'; ?>
                    </div>
                </div>
            </div>

            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Telefone</label>
                    <input type="text" class="form-control" name="to" placeholder="55DDDNUMERO ou +55DDDNUMERO" value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>" required>
                    <div class="form-text">
                        Ex.: 55 + DDD + número (somente dígitos) ou E.164 (+55...).<br>
                        <?php if ($normalizedTo): ?>Normalizado: <code><?php echo htmlspecialchars($normalizedTo); ?></code><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Mensagem</label>
                    <input type="text" class="form-control" name="text" placeholder="Sua mensagem" value="<?php echo htmlspecialchars($_POST['text'] ?? ''); ?>">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" name="action" value="send" class="btn btn-custom-red">Enviar</button>
                    <button type="submit" name="action" value="check" class="btn btn-outline-light">Verificar número</button>
                    <a href="testar_whatsapp.php" class="btn btn-secondary">Limpar</a>
                </div>
            </form>

            <?php if ($resultado !== null): ?>
                <div class="mt-3">
                    <?php if (!empty($resultado['ok'])): ?>
                        <div class="alert alert-success">
                            <?php echo ($action === 'check') ? 'Número registrado no WhatsApp.' : 'Mensagem enviada com sucesso.'; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            Falha: <?php echo htmlspecialchars($resultado['error'] ?? 'erro'); ?>
                        </div>
                    <?php endif; ?>
                    <pre class="mt-2 p-2 bg-dark text-white rounded" style="white-space: pre-wrap;"><?php echo htmlspecialchars(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'templates/footer.php'; ?>


