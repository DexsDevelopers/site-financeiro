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
    $text = trim($_POST['text'] ?? '');

    // Normaliza número e força DDI
    $normalizedTo = wpp_normalize_number($toRaw);
    if ($normalizedTo && strlen(preg_replace('/\D+/', '', $normalizedTo)) === 11) {
        $normalizedTo = '55' . preg_replace('/\D+/', '', $normalizedTo); // BR fix
    }

    if (!$normalizedTo) {
        $resultado = ['ok' => false, 'error' => 'Número inválido. Use +55DDDNNNNNNN ou 55DDDNNNNNNN.'];
    } elseif ($action === 'check') {
        // Apenas verifica se número existe
        $resultado = wpp_test_number($normalizedTo);
    } else { 
        // Send
        if ($text === '') {
            $resultado = ['ok' => false, 'error' => 'Preencha a mensagem.'];
        } else {
            // Testa se número existe antes de enviar
            $chk = wpp_test_number($normalizedTo);
            if (empty($chk['ok'])) {
                $resultado = $chk; // retorna motivo da falha
            } else {
                $resultado = wpp_send_message($normalizedTo, $text);
                if (!empty($resultado['ok'])) $resultado['to_normalized'] = $normalizedTo;
            }
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
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="alert <?php echo (!empty($status['ready'])) ? 'alert-success' : 'alert-warning'; ?> p-2 mb-0">
                        Bot: <code><?php echo htmlspecialchars($cfg['base']); ?></code> — Status: <?php echo (!empty($status['ready'])) ? 'pronto ✅' : 'aguardando ⚠️'; ?>
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
                            <?php
                                if ($action === 'check') echo 'Número registrado no WhatsApp ✅';
                                else echo 'Mensagem enviada com sucesso ✅';
                            ?>
                        </div>
                        <?php if ($normalizedTo): ?>
                            <?php $wa = preg_replace('/\D+/', '', $normalizedTo); ?>
                            <div class="mb-2">
                                Link wa.me: <a class="link-light" href="https://wa.me/<?php echo $wa; ?>" target="_blank" rel="noopener">https://wa.me/<?php echo $wa; ?></a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            Falha: <?php echo htmlspecialchars($resultado['error'] ?? 'erro desconhecido'); ?>
                        </div>
                    <?php endif; ?>
                    <pre class="mt-2 p-2 bg-dark text-white rounded" style="white-space: pre-wrap;"><?php echo htmlspecialchars(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'templates/footer.php'; ?>
