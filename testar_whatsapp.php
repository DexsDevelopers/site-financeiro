<?php
require_once 'templates/header.php';
require_once 'includes/whatsapp_client.php';

$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toRaw = trim($_POST['to'] ?? '');
    $toDigits = preg_replace('/\D+/', '', ltrim($toRaw, '+'));
    // Se informou só 11 dígitos, assume BR
    if (strlen($toDigits) === 11) $toDigits = '55' . $toDigits;
    $text = trim($_POST['text'] ?? '');
    if ($toDigits && $text) {
        $resultado = wpp_send_message($toDigits, $text);
    } else {
        $resultado = ['ok' => false, 'error' => 'Preencha telefone e mensagem.'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0">Teste WhatsApp</h1>
</div>

<main class="container-fluid p-0">
    <div class="card card-glass">
        <div class="card-body p-4">
            <form method="POST" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Telefone (E.164)</label>
                    <input type="text" class="form-control" name="to" placeholder="55DDDNUMERO" required>
                    <div class="form-text">Ex.: 55 + DDD + número (somente dígitos)</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Mensagem</label>
                    <input type="text" class="form-control" name="text" placeholder="Sua mensagem" required>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-custom-red">Enviar</button>
                    <a href="testar_whatsapp.php" class="btn btn-secondary">Limpar</a>
                </div>
            </form>
            <?php if ($resultado !== null): ?>
                <div class="mt-3">
                    <?php if (!empty($resultado['ok'])): ?>
                        <div class="alert alert-success">Mensagem enviada com sucesso.</div>
                    <?php else: ?>
                        <div class="alert alert-danger">Falha ao enviar: <?php echo htmlspecialchars($resultado['error'] ?? 'erro'); ?></div>
                    <?php endif; ?>
                    <pre class="mt-2 p-2 bg-dark text-white rounded" style="white-space: pre-wrap;"><?php echo htmlspecialchars(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'templates/footer.php'; ?>


