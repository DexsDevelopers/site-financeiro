
<?php
/**
 * VERIFICAR E REPARAR SISTEMA "LEMBRAR-ME"
 * Este arquivo diagnostica e corrige automaticamente problemas do sistema
 */

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/remember_me_manager.php';

$html_output = '';
$status = 'success';

// ===== FUNÇÕES AUXILIARES =====
function logMessage($type, $message) {
    global $html_output;
    $icons = ['success' => '✅', 'error' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'];
    $colors = ['success' => '#28a745', 'error' => '#dc3545', 'warning' => '#ffc107', 'info' => '#17a2b8'];
    
    $icon = $icons[$type] ?? '❓';
    $color = $colors[$type] ?? '#000';
    
    $html_output .= "<p style='color: $color; font-weight: bold;'>";
    $html_output .= "$icon <strong>" . ucfirst($type) . ":</strong> " . htmlspecialchars($message);
    $html_output .= "</p>\n";
}

// ===== VERIFICAÇÕES =====
$html_output .= "<h2>🔍 VERIFICAÇÃO E REPARO DO SISTEMA 'LEMBRAR-ME'</h2>\n";
$html_output .= "<hr>\n";

// 1. Verificar tabela
$html_output .= "<h3>1️⃣ Verificando Tabela remember_tokens</h3>\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'remember_tokens'");
    if ($stmt->rowCount() == 0) {
        logMessage('warning', 'Tabela não existe, criando...');
        
        $sql = "CREATE TABLE remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(128) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL,
            user_agent TEXT,
            ip_address VARCHAR(45),
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_token (token),
            INDEX idx_expires_at (expires_at),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        logMessage('success', 'Tabela remember_tokens criada com sucesso!');
    } else {
        logMessage('success', 'Tabela remember_tokens existe');
        
        // Verificar colunas
        $stmt = $pdo->query("DESCRIBE remember_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $required_cols = ['id', 'user_id', 'token', 'expires_at', 'created_at', 'is_active'];
        $missing_cols = [];
        
        foreach ($required_cols as $col) {
            $found = false;
            foreach ($columns as $c) {
                if ($c['Field'] === $col) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing_cols[] = $col;
            }
        }
        
        if (!empty($missing_cols)) {
            logMessage('error', 'Colunas faltando: ' . implode(', ', $missing_cols));
            $status = 'error';
        } else {
            logMessage('success', 'Todas as colunas obrigatórias existem');
        }
    }
} catch (Exception $e) {
    logMessage('error', 'Erro ao verificar tabela: ' . $e->getMessage());
    $status = 'error';
}

// 2. Limpar tokens expirados
$html_output .= "<h3>2️⃣ Limpando Tokens Expirados</h3>\n";
try {
    $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW() OR is_active = 0");
    $stmt->execute();
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        logMessage('success', "$count token(s) expirado(s) removido(s)");
    } else {
        logMessage('info', 'Nenhum token expirado encontrado');
    }
} catch (Exception $e) {
    logMessage('error', 'Erro ao limpar tokens: ' . $e->getMessage());
    $status = 'error';
}

// 3. Verificar RememberMeManager
$html_output .= "<h3>3️⃣ Testando RememberMeManager</h3>\n";
try {
    $rememberManager = new RememberMeManager($pdo);
    logMessage('success', 'RememberMeManager carregado com sucesso');
} catch (Exception $e) {
    logMessage('error', 'Erro ao carregar RememberMeManager: ' . $e->getMessage());
    $status = 'error';
}

// 4. Verificar auto_login.php
$html_output .= "<h3>4️⃣ Verificando auto_login.php</h3>\n";
if (file_exists('includes/auto_login.php')) {
    logMessage('success', 'Arquivo auto_login.php existe');
} else {
    logMessage('error', 'Arquivo auto_login.php não encontrado!');
    $status = 'error';
}

// 5. Verificar login_process.php
$html_output .= "<h3>5️⃣ Verificando login_process.php</h3>\n";
if (file_exists('login_process.php')) {
    $content = file_get_contents('login_process.php');
    if (strpos($content, 'remember_me_manager') !== false || strpos($content, 'RememberMeManager') !== false) {
        logMessage('success', 'login_process.php está configurado para Remember-Me');
    } else {
        logMessage('warning', 'login_process.php pode não estar configurado para Remember-Me');
    }
} else {
    logMessage('error', 'Arquivo login_process.php não encontrado!');
    $status = 'error';
}

// 6. Estatísticas
$html_output .= "<h3>6️⃣ Estatísticas dos Tokens</h3>\n";
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN is_active = 1 AND expires_at > NOW() THEN 1 END) as ativos,
            COUNT(CASE WHEN expires_at < NOW() THEN 1 END) as expirados
        FROM remember_tokens
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    logMessage('info', "Total de tokens: {$stats['total']}");
    logMessage('info', "Tokens ativos: {$stats['ativos']}");
    logMessage('info', "Tokens expirados: {$stats['expirados']}");
} catch (Exception $e) {
    logMessage('warning', 'Erro ao obter estatísticas: ' . $e->getMessage());
}

// 7. Verificar cookies
$html_output .= "<h3>7️⃣ Cookies do Navegador</h3>\n";
if (isset($_COOKIE['remember_token'])) {
    logMessage('success', 'Cookie remember_token encontrado');
    
    $token = $_COOKIE['remember_token'];
    $userData = $rememberManager->verifyRememberToken($token);
    if ($userData) {
        logMessage('success', "Token válido para usuário: {$userData['nome']}");
    } else {
        logMessage('warning', 'Token no cookie é inválido ou expirado');
    }
} else {
    logMessage('info', 'Nenhum cookie remember_token encontrado (normal se acabou de fazer logout)');
}

// 8. Recomendações finais
$html_output .= "<h3>📋 Próximos Passos</h3>\n";
if ($status === 'success') {
    $html_output .= "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>\n";
    $html_output .= "<h4>✅ Sistema está OK!</h4>\n";
    $html_output .= "<ol>\n";
    $html_output .= "<li>Faça logout e limpe todos os cookies do navegador</li>\n";
    $html_output .= "<li>Faça login novamente e <strong>marque 'Lembrar-me'</strong></li>\n";
    $html_output .= "<li>Feche o navegador completamente</li>\n";
    $html_output .= "<li>Abra novamente e verifique se fez login automático</li>\n";
    $html_output .= "</ol>\n";
    $html_output .= "</div>\n";
} else {
    $html_output .= "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 10px 0;'>\n";
    $html_output .= "<h4>⚠️ Problemas encontrados!</h4>\n";
    $html_output .= "<p>Verifique os erros acima e tente novamente.</p>\n";
    $html_output .= "<p>Se o problema persistir, verifique os logs: <code>/logs/error.log</code></p>\n";
    $html_output .= "</div>\n";
}

// Gerar HTML final
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar e Reparar Remember-Me</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 30px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 900px;
        }
        h2 {
            color: #667eea;
            margin-bottom: 30px;
            font-weight: bold;
        }
        h3 {
            color: #764ba2;
            margin-top: 30px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        hr {
            border-top: 2px solid #667eea;
            margin: 20px 0;
        }
        p {
            margin: 8px 0;
            font-size: 16px;
        }
        code {
            background: #f4f4f4;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .btn-container {
            margin-top: 40px;
            text-align: center;
        }
        .btn {
            background: #667eea;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php echo $html_output; ?>
        
        <div class="btn-container">
            <button onclick="location.reload()" class="btn btn-primary">🔄 Executar Verificação Novamente</button>
            <button onclick="window.history.back()" class="btn btn-secondary ms-2">← Voltar</button>
        </div>
        
        <hr style="margin-top: 40px;">
        
        <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-top: 20px;">
            <h4>📖 Documentação</h4>
            <p>Para mais informações, consulte: <a href="GUIA_REMEMBER_ME.md" target="_blank">GUIA_REMEMBER_ME.md</a></p>
            <p>Para teste completo: <a href="testar_remember_me_completo.php" target="_blank">testar_remember_me_completo.php</a></p>
        </div>
    </div>
</body>
</html>
