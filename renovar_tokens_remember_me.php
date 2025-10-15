<?php
// renovar_tokens_remember_me.php - Renovar todos os tokens de "Lembre-se de mim" com nova configuração

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/remember_me_manager.php';

echo "<h2>🔄 RENOVAÇÃO DE TOKENS 'LEMBRE-SE DE MIM'</h2>";
echo "<hr>";

if (!isset($_SESSION['user_id'])) {
    echo "❌ <strong>Usuário não está logado. Faça login primeiro.</strong><br>";
    exit;
}

$userId = $_SESSION['user_id'];
$rememberManager = new RememberMeManager($pdo);

try {
    // 1. Verificar tokens existentes
    echo "<h3>1. Verificando tokens existentes</h3>";
    
    $stmt = $pdo->prepare("
        SELECT id, token, expires_at, created_at 
        FROM remember_tokens 
        WHERE user_id = ? AND is_active = 1 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $existingTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($existingTokens) {
        echo "✅ <strong>Encontrados " . count($existingTokens) . " tokens ativos</strong><br>";
        
        foreach ($existingTokens as $token) {
            $expiresIn = strtotime($token['expires_at']) - time();
            $daysLeft = floor($expiresIn / (24 * 60 * 60));
            echo "• Token ID " . $token['id'] . ": " . substr($token['token'], 0, 20) . "... ($daysLeft dias restantes)<br>";
        }
    } else {
        echo "❌ <strong>Nenhum token ativo encontrado</strong><br>";
    }
    
    // 2. Revogar todos os tokens existentes
    echo "<h3>2. Revogando tokens existentes</h3>";
    
    $stmt = $pdo->prepare("UPDATE remember_tokens SET is_active = 0 WHERE user_id = ?");
    $stmt->execute([$userId]);
    $revokedCount = $stmt->rowCount();
    
    echo "✅ <strong>$revokedCount tokens revogados</strong><br>";
    
    // 3. Criar novo token com configuração atualizada
    echo "<h3>3. Criando novo token</h3>";
    
    $newToken = $rememberManager->createRememberToken(
        $userId,
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    );
    
    if ($newToken) {
        echo "✅ <strong>Novo token criado com sucesso!</strong><br>";
        echo "• Token: " . substr($newToken, 0, 20) . "...<br>";
        echo "• Expira em: " . date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)) . "<br>";
        
        // Verificar se o cookie foi definido
        if (isset($_COOKIE['remember_token'])) {
            echo "✅ <strong>Cookie definido corretamente</strong><br>";
        } else {
            echo "⚠️ <strong>Cookie pode não ter sido definido (verifique logs)</strong><br>";
        }
    } else {
        echo "❌ <strong>Falha ao criar novo token</strong><br>";
    }
    
    // 4. Limpar tokens expirados
    echo "<h3>4. Limpeza de tokens expirados</h3>";
    
    $cleaned = $rememberManager->cleanExpiredTokens();
    if ($cleaned !== false) {
        echo "✅ <strong>Limpeza concluída:</strong> $cleaned tokens expirados removidos<br>";
    } else {
        echo "❌ <strong>Erro na limpeza de tokens</strong><br>";
    }
    
    // 5. Verificar configuração final
    echo "<h3>5. Verificação final</h3>";
    
    $stats = $rememberManager->getTokenStats($userId);
    if ($stats) {
        echo "• Total de tokens: " . $stats['total_tokens'] . "<br>";
        echo "• Tokens ativos: " . $stats['active_tokens'] . "<br>";
        echo "• Tokens revogados: " . $stats['revoked_tokens'] . "<br>";
        echo "• Tokens expirados: " . $stats['expired_tokens'] . "<br>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Renovação concluída com sucesso!</h3>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Faça logout e login novamente para testar</li>";
    echo "<li>✅ Verifique se o cookie está sendo definido no navegador</li>";
    echo "<li>✅ Teste em modo incógnito</li>";
    echo "<li>✅ O token agora deve durar 30 dias completos</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ <strong>Erro durante a renovação:</strong> " . $e->getMessage() . "<br>";
    error_log("Erro na renovação de tokens: " . $e->getMessage());
}
?>
