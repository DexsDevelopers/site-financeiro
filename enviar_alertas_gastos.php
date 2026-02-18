<?php
// enviar_alertas_gastos.php - Envia alertas quando há gastos altos
// Este script pode ser executado após cada transação ou periodicamente

require_once 'includes/db_connect.php';
require_once 'includes/finance_helper.php';
require_once 'includes/whatsapp_client.php';

// Carregar configuração
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
if (!$config) {
    error_log("Erro ao carregar configuração");
    exit(1);
}

// Verificar se WhatsApp está habilitado
if (!($config['WHATSAPP_API_ENABLED'] ?? false)) {
    exit(0);
}

// Limite para considerar gasto alto (em reais)
$LIMITE_GASTO_ALTO = 500.00; // Ajuste conforme necessário

// Função para enviar mensagem via WhatsApp
function enviarMensagemWhatsApp(string $phone, string $message): bool {
    global $config;
    
    try {
        $response = wpp_send_message($phone, $message);
        return $response['success'] ?? false;
    } catch (Exception $e) {
        error_log("Erro ao enviar mensagem WhatsApp para $phone: " . $e->getMessage());
        return false;
    }
}

// Buscar transações de despesa do dia atual acima do limite
try {
    $sql = "SELECT t.*, u.nome_completo as nome, ws.phone_number
            FROM transactions t
            JOIN usuarios u ON t.id_usuario = u.id
            JOIN whatsapp_sessions ws ON ws.user_id = u.id
            WHERE t.type = 'despesa'
            AND t.value >= ?
            AND DATE(t.created_at) = CURDATE()
            AND ws.is_active = 1
            AND t.id NOT IN (
                SELECT transaction_id FROM whatsapp_notifications_sent 
                WHERE notification_type = 'gasto_alto' 
                AND DATE(created_at) = CURDATE()
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$LIMITE_GASTO_ALTO]);
    $gastosAltos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Criar tabela de notificações enviadas se não existir
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_notifications_sent (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            transaction_id INT,
            notification_type VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_type (notification_type),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        // Tabela já existe
    }
    
    foreach ($gastosAltos as $gasto) {
        $mensagem = "⚠️ *ALERTA: GASTO ALTO DETECTADO*\n\n";
        $mensagem .= "💰 Valor: R$ " . number_format($gasto['value'], 2, ',', '.') . "\n";
        $mensagem .= "📝 Descrição: " . $gasto['description'] . "\n";
        if ($gasto['category']) {
            $mensagem .= "🏷️ Categoria: " . $gasto['category'] . "\n";
        }
        $mensagem .= "📅 Data: " . date('d/m/Y H:i', strtotime($gasto['created_at'])) . "\n\n";
        
        // Verificar percentual do mês
        $balance = getBalance($pdo, null, null, $gasto['id_usuario']);
        if ($balance['success'] && $balance['receitas']['total'] > 0) {
            $percent = ($gasto['value'] / $balance['receitas']['total']) * 100;
            if ($percent > 10) {
                $mensagem .= "⚠️ Este gasto representa " . round($percent, 1) . "% da sua receita do mês!\n\n";
            }
        }
        
        $mensagem .= "💡 Use !saldo para ver seu saldo atual";
        
        if (enviarMensagemWhatsApp($gasto['phone_number'], $mensagem)) {
            // Registrar notificação enviada
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO whatsapp_notifications_sent (user_id, transaction_id, notification_type) VALUES (?, ?, 'gasto_alto')");
                $stmtInsert->execute([$gasto['id_usuario'], $gasto['id']]);
            } catch (PDOException $e) {
                error_log("Erro ao registrar notificação: " . $e->getMessage());
            }
            
            error_log("Alerta de gasto alto enviado para {$gasto['phone_number']} (transação ID: {$gasto['id']})");
        }
        
        sleep(1); // Evitar rate limit
    }
    
    echo "Alertas enviados: " . count($gastosAltos) . "\n";
    
} catch (PDOException $e) {
    error_log("Erro ao buscar gastos altos: " . $e->getMessage());
    exit(1);
}



