<?php
// whatsapp_qr.php - Exibe QR Code do WhatsApp Bot
require_once 'templates/header.php';
require_once 'includes/whatsapp_client.php';

// Restringe a página a administradores
try {
    $uid = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : (int)($_SESSION['user_id'] ?? 0);
    $stmtAdm = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ? LIMIT 1");
    $stmtAdm->execute([$uid]);
    $tipoUser = $stmtAdm->fetchColumn();
    if ($tipoUser !== 'admin') {
        http_response_code(403);
        echo '<div class="alert alert-danger m-3">Acesso negado. Esta página é restrita a administradores.</div>';
        require_once 'templates/footer.php';
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger m-3">Erro ao verificar permissões.</div>';
    require_once 'templates/footer.php';
    exit;
}

$cfg = wpp_get_config();
$qrUrl = $cfg['base'] . '/qr';
$statusUrl = $cfg['base'] . '/status';
?>
<style>
    .qr-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    .qr-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        padding: 2.5rem;
        max-width: 500px;
        width: 100%;
        text-align: center;
    }
    .qr-card h2 {
        color: #25D366;
        margin-bottom: 1rem;
        font-weight: 700;
    }
    .qr-image {
        border: 4px solid #25D366;
        border-radius: 15px;
        padding: 1rem;
        background: white;
        margin: 1.5rem 0;
        max-width: 100%;
        height: auto;
    }
    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        margin: 1rem 0;
    }
    .status-online {
        background: #d4edda;
        color: #155724;
        border: 2px solid #28a745;
    }
    .status-offline {
        background: #f8d7da;
        color: #721c24;
        border: 2px solid #dc3545;
    }
    .status-loading {
        background: #fff3cd;
        color: #856404;
        border: 2px solid #ffc107;
    }
    .refresh-btn {
        margin-top: 1rem;
    }
    .info-box {
        background: #f8f9fa;
        border-left: 4px solid #25D366;
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 5px;
        text-align: left;
    }
    .info-box strong {
        color: #25D366;
    }
</style>

<div class="qr-container">
    <div class="qr-card">
        <h2><i class="bi bi-whatsapp me-2"></i>QR Code WhatsApp Bot</h2>
        <p class="text-muted">Escaneie este QR Code com seu WhatsApp para conectar o bot</p>
        
        <div id="statusContainer">
            <div class="status-badge status-loading">
                <span class="spinner-border spinner-border-sm me-2"></span>Verificando status...
            </div>
        </div>

        <div id="qrContainer" style="display: none;">
            <iframe id="qrFrame" src="<?php echo htmlspecialchars($qrUrl); ?>" 
                    style="width: 100%; min-height: 500px; border: none; border-radius: 10px;">
            </iframe>
        </div>

        <div id="errorContainer" style="display: none;">
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Erro:</strong> Não foi possível conectar ao bot WhatsApp.
                <br><small>Verifique se o bot está rodando na porta <?php echo parse_url($cfg['base'], PHP_URL_PORT) ?? '3001'; ?>.</small>
            </div>
        </div>

        <div class="info-box">
            <strong><i class="bi bi-info-circle me-2"></i>Instruções:</strong>
            <ol class="mb-0 mt-2">
                <li>Abra o WhatsApp no seu celular</li>
                <li>Vá em <strong>Configurações > Aparelhos Conectados</strong></li>
                <li>Toque em <strong>Conectar um Aparelho</strong></li>
                <li>Escaneie o QR Code acima</li>
            </ol>
        </div>

        <div class="info-box">
            <strong><i class="bi bi-gear me-2"></i>Configuração:</strong>
            <ul class="mb-0 mt-2">
                <li><strong>URL:</strong> <?php echo htmlspecialchars($cfg['base']); ?></li>
                <li><strong>Porta:</strong> <?php echo parse_url($cfg['base'], PHP_URL_PORT) ?? '3001'; ?></li>
                <li><strong>Status:</strong> <span id="botStatus">Verificando...</span></li>
            </ul>
        </div>

        <button onclick="verificarStatus()" class="btn btn-primary refresh-btn">
            <i class="bi bi-arrow-clockwise me-2"></i>Atualizar
        </button>

        <div class="mt-3">
            <a href="whatsapp_admin.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Voltar para Admin WhatsApp
            </a>
        </div>
    </div>
</div>

<script>
let statusCheckInterval;

function verificarStatus() {
    const statusContainer = document.getElementById('statusContainer');
    const qrContainer = document.getElementById('qrContainer');
    const errorContainer = document.getElementById('errorContainer');
    const botStatus = document.getElementById('botStatus');

    statusContainer.innerHTML = '<div class="status-badge status-loading"><span class="spinner-border spinner-border-sm me-2"></span>Verificando...</div>';
    statusContainer.style.display = 'block';
    qrContainer.style.display = 'none';
    errorContainer.style.display = 'none';

    fetch('<?php echo htmlspecialchars($statusUrl); ?>', {
        method: 'GET',
        headers: {
            'x-api-token': '<?php echo htmlspecialchars($cfg['token']); ?>'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.ok && data.ready) {
            statusContainer.innerHTML = '<div class="status-badge status-online"><i class="bi bi-check-circle me-2"></i>Bot Online e Conectado</div>';
            qrContainer.style.display = 'block';
            errorContainer.style.display = 'none';
            botStatus.textContent = 'Online';
            botStatus.className = 'text-success';
        } else {
            statusContainer.innerHTML = '<div class="status-badge status-offline"><i class="bi bi-x-circle me-2"></i>Bot Offline ou Não Conectado</div>';
            qrContainer.style.display = 'block'; // Mostra QR mesmo offline (pode estar aguardando scan)
            errorContainer.style.display = 'none';
            botStatus.textContent = 'Offline';
            botStatus.className = 'text-danger';
        }
    })
    .catch(error => {
        console.error('Erro ao verificar status:', error);
        statusContainer.innerHTML = '<div class="status-badge status-offline"><i class="bi bi-x-circle me-2"></i>Erro ao Conectar</div>';
        qrContainer.style.display = 'none';
        errorContainer.style.display = 'block';
        botStatus.textContent = 'Erro';
        botStatus.className = 'text-danger';
    });
}

// Verificar status ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    verificarStatus();
    // Verificar status a cada 10 segundos
    statusCheckInterval = setInterval(verificarStatus, 10000);
});

// Limpar intervalo ao sair da página
window.addEventListener('beforeunload', function() {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>

