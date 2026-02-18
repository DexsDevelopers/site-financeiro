<?php
// enviar_notificacoes_diarias.php - Envia notificações diárias via WhatsApp
// Este script deve ser executado via cron job diariamente (ex: 08:00)

require_once 'includes/db_connect.php';
require_once 'includes/tasks_helper.php';
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
    error_log("WhatsApp API não está habilitada");
    exit(0);
}

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

// Buscar todos os usuários com sessão ativa no WhatsApp
try {
    $sql = "SELECT DISTINCT ws.phone_number, ws.user_id, u.nome_completo as nome
            FROM whatsapp_sessions ws
            JOIN usuarios u ON ws.user_id = u.id
            WHERE ws.is_active = 1
            AND ws.last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notificacoesEnviadas = 0;
    $erros = 0;
    
    foreach ($users as $user) {
        $userId = (int)$user['user_id'];
        $phone = $user['phone_number'];
        $nome = $user['nome'];
        
        $mensagem = "🌅 *Bom dia, " . explode(' ', $nome)[0] . "!*\n\n";
        $mensagem .= "📋 *RESUMO DO DIA*\n\n";
        
        // ============================================
        // 1. TAREFAS DO DIA
        // ============================================
        $tasksToday = getTodayTasks($pdo, $userId);
        if ($tasksToday['success'] && $tasksToday['count'] > 0) {
            $mensagem .= "📋 *TAREFAS DE HOJE* (" . $tasksToday['count'] . ")\n";
            $count = 1;
            foreach (array_slice($tasksToday['tasks'], 0, 5) as $task) {
                $mensagem .= "$count. " . formatPriority($task['prioridade']) . " " . $task['descricao'] . "\n";
                if ($task['data_limite']) {
                    $mensagem .= "   📅 " . formatTaskDate($task['data_limite']) . "\n";
                }
                $count++;
            }
            if ($tasksToday['count'] > 5) {
                $mensagem .= "... e mais " . ($tasksToday['count'] - 5) . " tarefa(s)\n";
            }
            $mensagem .= "\n";
        }
        
        // ============================================
        // 2. TAREFAS URGENTES
        // ============================================
        $urgentTasks = getUrgentTasks($pdo, $userId, 3);
        if ($urgentTasks['success'] && $urgentTasks['count'] > 0) {
            $mensagem .= "🚨 *TAREFAS URGENTES* (" . $urgentTasks['count'] . ")\n";
            foreach ($urgentTasks['tasks'] as $task) {
                $mensagem .= "⚠️ " . $task['descricao'] . "\n";
                if ($task['data_limite']) {
                    $mensagem .= "   📅 " . formatTaskDate($task['data_limite']) . "\n";
                }
            }
            $mensagem .= "\n";
        }
        
        // ============================================
        // 3. GASTOS ALTOS DO MÊS
        // ============================================
        $balance = getBalance($pdo, null, null, $userId);
        if ($balance['success']) {
            $mesAtual = date('m/Y');
            $mensagem .= "💰 *FINANÇAS - " . strtoupper($mesAtual) . "*\n";
            $mensagem .= "📈 Receitas: R$ " . number_format($balance['receitas']['total'], 2, ',', '.') . "\n";
            $mensagem .= "📉 Despesas: R$ " . number_format($balance['despesas']['total'], 2, ',', '.') . "\n";
            $mensagem .= "💵 Saldo: R$ " . number_format($balance['saldo'], 2, ',', '.') . "\n\n";
            
            // Alertar sobre gastos altos
            if ($balance['despesas']['total'] > 0) {
                $percentDespesas = ($balance['despesas']['total'] / max($balance['receitas']['total'], 1)) * 100;
                if ($percentDespesas > 80) {
                    $mensagem .= "⚠️ *ALERTA: Você já gastou " . round($percentDespesas) . "% da sua receita este mês!*\n\n";
                }
            }
        }
        
        // ============================================
        // 4. TOP 3 GASTOS DO MÊS
        // ============================================
        try {
            $sqlGastos = "SELECT description, value, category
                         FROM transactions
                         WHERE id_usuario = ?
                         AND type = 'despesa'
                         AND YEAR(created_at) = YEAR(CURDATE())
                         AND MONTH(created_at) = MONTH(CURDATE())
                         ORDER BY value DESC
                         LIMIT 3";
            $stmtGastos = $pdo->prepare($sqlGastos);
            $stmtGastos->execute([$userId]);
            $gastos = $stmtGastos->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($gastos)) {
                $mensagem .= "💸 *MAIORES GASTOS DO MÊS*\n";
                $count = 1;
                foreach ($gastos as $gasto) {
                    $mensagem .= "$count. R$ " . number_format($gasto['value'], 2, ',', '.') . " - " . $gasto['description'] . "\n";
                    if ($gasto['category']) {
                        $mensagem .= "   🏷️ " . $gasto['category'] . "\n";
                    }
                    $count++;
                }
                $mensagem .= "\n";
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar gastos: " . $e->getMessage());
        }
        
        // ============================================
        // 5. PENDÊNCIAS (se houver)
        // ============================================
        try {
            $pendencies = getClientPendencies($pdo, null, $userId);
            if ($pendencies['success'] && $pendencies['count'] > 0) {
                $mensagem .= "⚠️ *PENDÊNCIAS* (" . $pendencies['count'] . ")\n";
                $mensagem .= "Total pendente: R$ " . number_format($pendencies['total'], 2, ',', '.') . "\n\n";
            }
        } catch (Exception $e) {
            // Ignorar se a tabela não existir
        }
        
        $mensagem .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $mensagem .= "💡 Use !menu para ver todos os comandos";
        
        // Enviar mensagem
        if (enviarMensagemWhatsApp($phone, $mensagem)) {
            $notificacoesEnviadas++;
            error_log("Notificação enviada para $phone (usuário ID: $userId)");
        } else {
            $erros++;
            error_log("Erro ao enviar notificação para $phone (usuário ID: $userId)");
        }
        
        // Aguardar 2 segundos entre envios para evitar rate limit
        sleep(2);
    }
    
    echo "Notificações enviadas: $notificacoesEnviadas\n";
    echo "Erros: $erros\n";
    
} catch (PDOException $e) {
    error_log("Erro ao buscar usuários: " . $e->getMessage());
    exit(1);
}



