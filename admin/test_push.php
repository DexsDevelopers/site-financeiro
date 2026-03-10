<?php
require_once dirname(__DIR__) . '/templates/header.php';
require_once dirname(__DIR__) . '/includes/push_helper.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0;
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $title = $_POST['title'] ?? 'Teste do Painel';
    $body = $_POST['body'] ?? 'Esta é uma notificação de teste nativa!';
    
    $result = sendWebPush($pdo, $userId, $title, $body, 'dashboard.php');
    
    if ($result && $result['success']) {
        $message = "<div class='alert alert-success'>Sucesso! Enviado para {$result['sent']} dispositivos. Falhas: {$result['failed']}.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Erro ao enviar. Verifique se você ativou as notificações no menu e se as chaves VAPID estão corretas.</div>";
    }
}
?>

<div class="container mt-5">
    <div class="card glass-panel featured">
        <div class="card-header border-0 bg-transparent">
            <h3 class="text-white"><i class="bi bi-send-fill me-2"></i>Testar Notificações Push</h3>
        </div>
        <div class="card-body">
            <?= $message ?>
            
            <p class="text-white-50">Use este formulário para enviar uma notificação para <strong>você mesmo</strong> neste navegador (após ter clicado em "Ativar Notificações" no menu lateral).</p>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-white">Título</label>
                    <input type="text" name="title" class="form-control bg-dark text-white border-secondary" value="Teste do Painel" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-white">Mensagem</label>
                    <textarea name="body" class="form-control bg-dark text-white border-secondary" rows="3" required>Esta é uma notificação de teste nativa!</textarea>
                </div>
                <button type="submit" name="send_test" class="btn btn-primary w-100 py-3 rounded-pill">
                    <i class="bi bi-bell-fill me-2"></i> Enviar Agora!
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
