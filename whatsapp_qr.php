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

// Verificar status do bot via PHP (server-side para evitar CORS)
$botStatus = ['online' => false, 'message' => 'Verificando...', 'ready' => false];
try {
    $ch = curl_init($statusUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-api-token: ' . $cfg['token']],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $statusData = json_decode($response, true);
        $botStatus['online'] = !empty($statusData['ready']);
        $botStatus['ready'] = !empty($statusData['ready']);
        $botStatus['message'] = $botStatus['online'] ? 'Online e Conectado' : 'Offline ou Não Conectado';
    } else {
        $botStatus['message'] = $curlError ? 'Erro de Conexão: ' . $curlError : 'Erro HTTP ' . $httpCode;
    }
} catch (Exception $e) {
    $botStatus['message'] = 'Erro: ' . $e->getMessage();
}

$porta = parse_url($cfg['base'], PHP_URL_PORT) ?? '3001';

// Endpoint para verificação de status via AJAX (evita CORS)
if (isset($_GET['check_status'])) {
    header('Content-Type: application/json');
    try {
        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['x-api-token: ' . $cfg['token']],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $statusData = json_decode($response, true);
            echo json_encode([
                'ok' => true,
                'ready' => !empty($statusData['ready']),
                'message' => !empty($statusData['ready']) ? 'Online e Conectado' : 'Offline ou Não Conectado'
            ]);
        } else {
            echo json_encode(['ok' => false, 'ready' => false, 'message' => 'Erro HTTP ' . $httpCode]);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'ready' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}
?>
<style>
    :root {
        --whatsapp-green: #25D366;
        --whatsapp-green-dark: #128C7E;
        --bg-dark: #0d0d0f;
        --bg-800: #141417;
        --bg-700: #1c1c20;
        --bg-600: #242428;
        --text-primary: #f5f5f1;
        --text-secondary: #b3b3b7;
        --accent: #e50914;
        --border: rgba(255,255,255,0.08);
    }

    .qr-container {
        background: linear-gradient(135deg, var(--bg-900, #0d0d0f) 0%, var(--bg-800, #141417) 100%);
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .qr-card {
        background: rgba(28, 28, 32, 0.95);
        border: 1px solid var(--border);
        border-radius: 12px;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        padding: 2.5rem;
        max-width: 600px;
        width: 100%;
        text-align: center;
        color: var(--text-primary);
    }

    .qr-card h2 {
        color: var(--whatsapp-green);
        margin-bottom: 1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .qr-card h2 i {
        filter: drop-shadow(0 0 10px rgba(37, 211, 102, 0.5));
    }

    .qr-image {
        border: 4px solid var(--whatsapp-green);
        border-radius: 12px;
        padding: 1rem;
        background: rgba(36, 36, 40, 0.8);
        margin: 1.5rem 0;
        max-width: 100%;
        height: auto;
        box-shadow: 0 0 20px rgba(37, 211, 102, 0.3);
    }

    #qrFrame {
        background: white;
        border-radius: 8px;
        min-height: 500px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        margin: 1rem 0;
        transition: all 0.3s ease;
    }

    .status-online {
        background: rgba(37, 211, 102, 0.1);
        color: var(--whatsapp-green);
        border: 2px solid var(--whatsapp-green);
        box-shadow: 0 0 15px rgba(37, 211, 102, 0.3);
    }

    .status-offline {
        background: rgba(229, 9, 20, 0.1);
        color: var(--accent);
        border: 2px solid var(--accent);
    }

    .status-loading {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
        border: 2px solid #ffc107;
    }

    .refresh-btn {
        margin-top: 1rem;
        background: var(--whatsapp-green);
        border: none;
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
    }

    .refresh-btn:hover {
        background: var(--whatsapp-green-dark);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
    }

    .info-box {
        background: rgba(36, 36, 40, 0.6);
        border-left: 4px solid var(--whatsapp-green);
        padding: 1.25rem;
        margin: 1rem 0;
        border-radius: 8px;
        text-align: left;
        color: var(--text-primary);
    }

    .info-box strong {
        color: var(--whatsapp-green);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-box ul, .info-box ol {
        margin: 0.75rem 0 0 1.5rem;
        color: var(--text-secondary);
    }

    .info-box li {
        margin: 0.5rem 0;
    }

    .alert {
        border-radius: 8px;
        border: 1px solid;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .alert-danger {
        background: rgba(229, 9, 20, 0.1);
        border-color: var(--accent);
        color: var(--accent);
    }

    .btn-outline-secondary {
        border-color: var(--border);
        color: var(--text-secondary);
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
        background: rgba(255,255,255,0.1);
        border-color: var(--text-secondary);
        color: var(--text-primary);
    }

    .text-muted {
        color: var(--text-secondary) !important;
    }

    .text-success {
        color: var(--whatsapp-green) !important;
    }

    .text-danger {
        color: var(--accent) !important;
    }

    @media (max-width: 768px) {
        .qr-container {
            padding: 1rem;
        }

        .qr-card {
            padding: 1.5rem;
        }

        #qrFrame {
            min-height: 400px;
        }
    }
</style>

<div class="qr-container">
    <div class="qr-card">
        <h2><i class="bi bi-whatsapp me-2"></i>QR Code WhatsApp Bot</h2>
        <p class="text-muted">Escaneie este QR Code com seu WhatsApp para conectar o bot</p>
        
        <div id="statusContainer">
            <?php if ($botStatus['online']): ?>
                <div class="status-badge status-online">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?php echo htmlspecialchars($botStatus['message']); ?></span>
                </div>
            <?php else: ?>
                <div class="status-badge status-offline">
                    <i class="bi bi-x-circle-fill"></i>
                    <span><?php echo htmlspecialchars($botStatus['message']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div id="qrContainer" style="display: <?php echo $botStatus['online'] ? 'block' : 'none'; ?>;">
            <iframe id="qrFrame" src="<?php echo htmlspecialchars($qrUrl); ?>" 
                    style="width: 100%; min-height: 500px; border: none; border-radius: 8px;"
                    onerror="document.getElementById('errorContainer').style.display='block';">
            </iframe>
        </div>

        <div id="errorContainer" style="display: <?php echo !$botStatus['online'] ? 'block' : 'none'; ?>;">
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Erro:</strong> Não foi possível conectar ao bot WhatsApp.
                <br><small>Verifique se o bot está rodando na porta <?php echo htmlspecialchars($porta); ?>.</small>
                <br><small>URL: <?php echo htmlspecialchars($cfg['base']); ?></small>
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
                <li><strong>Status:</strong> 
                    <span id="botStatus" class="<?php echo $botStatus['online'] ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $botStatus['online'] ? 'Online' : 'Offline'; ?>
                    </span>
                </li>
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
    
    // Fazer requisição via PHP para evitar CORS
    fetch('whatsapp_qr.php?check_status=1', {
        method: 'GET',
        cache: 'no-cache'
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok && data.ready) {
            statusContainer.innerHTML = '<div class="status-badge status-online"><i class="bi bi-check-circle-fill"></i><span>Bot Online e Conectado</span></div>';
            qrContainer.style.display = 'block';
            errorContainer.style.display = 'none';
            botStatus.textContent = 'Online';
            botStatus.className = 'text-success';
        } else {
            statusContainer.innerHTML = '<div class="status-badge status-offline"><i class="bi bi-x-circle-fill"></i><span>Bot Offline ou Não Conectado</span></div>';
            qrContainer.style.display = 'block'; // Mostra QR mesmo offline (pode estar aguardando scan)
            errorContainer.style.display = 'none';
            botStatus.textContent = 'Offline';
            botStatus.className = 'text-danger';
        }
    })
    .catch(error => {
        console.error('Erro ao verificar status:', error);
        statusContainer.innerHTML = '<div class="status-badge status-offline"><i class="bi bi-x-circle-fill"></i><span>Erro ao Conectar</span></div>';
        qrContainer.style.display = 'none';
        errorContainer.style.display = 'block';
        botStatus.textContent = 'Erro';
        botStatus.className = 'text-danger';
    });
}

// Verificar status ao carregar a página (apenas se não estiver online)
<?php if (!$botStatus['online']): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Verificar status a cada 10 segundos
    statusCheckInterval = setInterval(verificarStatus, 10000);
});
<?php endif; ?>

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

