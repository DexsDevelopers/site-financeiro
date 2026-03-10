<?php
// admin/test_push.php
require_once dirname(__DIR__) . '/templates/header.php';
require_once dirname(__DIR__) . '/includes/push_helper.php';

// Garantir que temos acesso ao PDO e ao Usuário
$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0;
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $title = $_POST['title'] ?? 'Teste do Painel';
    $body = $_POST['body'] ?? 'Esta é uma notificação de teste nativa!';
    
    // sendWebPush espera ($pdo, $user_id, $title, $body, $url, $icon)
    $result = sendWebPush($pdo, $userId, $title, $body, 'dashboard.php');
    
    if ($result && isset($result['success']) && $result['success']) {
        $message = "<div class='alert alert-success'>✈️ Enviado! Sucesso em {$result['sent']} dispositivos. Falhas: {$result['failed']}.</div>";
    } else {
        $msgErr = ($result === false) ? "Dispositivo não encontrado ou erro de VAPID." : "Erro ao enviar.";
        $message = "<div class='alert alert-warning'>⚠️ {$msgErr}<br><small>Verifique se clicou em 'Ativar Notificações' no menu lateral ANTES.</small></div>";
    }
}
?>

<div class="main-content-inner p-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card glass-panel shadow-lg border-0" style="background: rgba(30,30,30,0.4); backdrop-filter: blur(15px); border-radius: 20px;">
                <div class="card-header bg-transparent border-0 pt-4 text-center">
                    <div class="icon-box mb-3 mx-auto" style="width: 60px; height: 60px; background: linear-gradient(135deg, #00b8d4, #00e5ff); border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-send-check-fill text-white fs-2"></i>
                    </div>
                    <h3 class="text-white fw-bold mb-0">Testar Push Nativo</h3>
                    <p class="text-white-50 mt-2">Valide seu sistema de notificações em tempo real</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <?= $message ?>
                    
                    <form method="POST" class="mt-3">
                        <div class="mb-4">
                            <label class="form-label text-white-50 small text-uppercase">Título da Notificação</label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white py-2" value="Ghost Pix: Notificação" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-white-50 small text-uppercase">Mensagem</label>
                            <textarea name="body" class="form-control bg-dark border-secondary text-white" rows="3" required>Sua nova funcionalidade de notificações nativas está operando 100%!</textarea>
                        </div>
                        <button type="submit" name="send_test" class="btn btn-info w-100 py-3 rounded-pill fw-bold shadow-sm">
                            <i class="bi bi-rocket-takeoff-fill me-2"></i> Disparar Notificação
                        </button>
                    </form>
                    
                    <div class="alert bg-black border-secondary mt-4 py-2 px-3 small">
                        <i class="bi bi-info-circle text-info me-2"></i>
                        <span class="text-white-50">Isso só funciona se o arquivo <strong>includes/config_push.php</strong> estiver com chaves VAPID válidas.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>
