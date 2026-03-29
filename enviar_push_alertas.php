<?php
// enviar_push_alertas.php
// Cron script: envia notificações push para tarefas urgentes e alertas financeiros
// Sugestão de agendamento:
//   08:00 - Resumo diário com tarefas do dia
//   12:00 - Lembrete de tarefas atrasadas (se houver)
//   18:00 - Alerta de gastos acima do orçamento (se houver)
//
// Exemplo crontab:
//   0 8  * * * php /home/seu_projeto/public_html/enviar_push_alertas.php tipo=diario
//   0 12 * * * php /home/seu_projeto/public_html/enviar_push_alertas.php tipo=atrasadas
//   0 18 * * * php /home/seu_projeto/public_html/enviar_push_alertas.php tipo=financeiro

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/push_helper.php';

if (!$pdo) {
    error_log('[Push Cron] Conexão com banco de dados falhou.');
    exit(1);
}

// Tipo de alerta (pode ser passado via CLI: php script.php tipo=diario)
$tipo = 'diario';
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, 'tipo=') === 0) {
            $tipo = substr($arg, 5);
        }
    }
} else {
    $tipo = $_GET['tipo'] ?? 'diario';
}

// ─── Buscar todos os usuários com push subscriptions ──────────────────────────
try {
    $stmtUsers = $pdo->query("
        SELECT DISTINCT ps.user_id, u.nome_completo as nome
        FROM push_subscriptions ps
        INNER JOIN usuarios u ON ps.user_id = u.id
        WHERE u.id IS NOT NULL
    ");
    $usuarios = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Push Cron] Erro ao buscar usuários: ' . $e->getMessage());
    exit(1);
}

if (empty($usuarios)) {
    echo "Nenhum usuário com push subscription ativo.\n";
    exit(0);
}

$enviados = 0;
$erros = 0;

foreach ($usuarios as $usuario) {
    $userId = (int)$usuario['user_id'];
    $primeiroNome = explode(' ', $usuario['nome'])[0];

    switch ($tipo) {

        // ─── RESUMO DIÁRIO (08:00) ───────────────────────────────────────────
        case 'diario':
            $result = enviarResumoDiario($pdo, $userId, $primeiroNome);
            break;

        // ─── TAREFAS ATRASADAS (12:00) ───────────────────────────────────────
        case 'atrasadas':
            $result = enviarAlertaAtrasadas($pdo, $userId, $primeiroNome);
            break;

        // ─── ALERTA FINANCEIRO (18:00) ───────────────────────────────────────
        case 'financeiro':
            $result = enviarAlertaFinanceiro($pdo, $userId, $primeiroNome);
            break;

        default:
            $result = false;
    }

    if ($result && isset($result['sent']) && $result['sent'] > 0) {
        $enviados++;
        echo "✅ Push enviado para usuário #$userId ($primeiroNome)\n";
    } elseif ($result === false || (isset($result['sent']) && $result['sent'] === 0)) {
        // Sem notificação relevante para este usuário (normal)
        echo "⏭ Sem alertas relevantes para usuário #$userId\n";
    } else {
        $erros++;
        echo "❌ Erro ao enviar para usuário #$userId\n";
    }
}

echo "\n📊 Resumo: $enviados enviados, $erros erros de " . count($usuarios) . " usuários.\n";

// ─── FUNÇÕES ──────────────────────────────────────────────────────────────────

function enviarResumoDiario(PDO $pdo, int $userId, string $nome): mixed {
    $hoje = date('Y-m-d');

    // Tarefas para hoje
    $countHoje = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tarefas
            WHERE id_usuario = ? AND status != 'concluida'
            AND (data_limite = ? OR DATE(data_limite) = ?)
        ");
        $stmt->execute([$userId, $hoje, $hoje]);
        $countHoje = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* tabela pode ter estrutura diferente */ }

    // Tarefas urgentes
    $countUrgentes = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tarefas
            WHERE id_usuario = ? AND status != 'concluida'
            AND prioridade IN ('alta', 'urgente')
            AND (data_limite IS NULL OR data_limite >= ?)
        ");
        $stmt->execute([$userId, $hoje]);
        $countUrgentes = (int)$stmt->fetchColumn();
    } catch (PDOException $e) { /* silencioso */ }

    // Montar mensagem
    $parts = [];
    if ($countHoje > 0) $parts[] = "$countHoje tarefa(s) para hoje";
    if ($countUrgentes > 0) $parts[] = "$countUrgentes urgente(s)";

    if (empty($parts)) {
        // Nenhuma tarefa relevante — enviar mensagem motivacional apenas às segundas
        if (date('N') != 1) return false;
        $title = "☀️ Bom dia, $nome!";
        $body = "Você não tem tarefas pendentes. Aproveite o dia!";
        $url = 'dashboard.php';
    } else {
        $title = "☀️ Bom dia, $nome!";
        $body = "Hoje: " . implode(' • ', $parts) . ". Veja seu plano!";
        $url = 'tarefas.php';
    }

    return sendWebPush($pdo, $userId, $title, $body, $url, [
        'tipo' => 'tarefa',
        'actions' => [
            ['action' => 'open', 'title' => 'Ver Tarefas']
        ]
    ]);
}

function enviarAlertaAtrasadas(PDO $pdo, int $userId, string $nome): mixed {
    $hoje = date('Y-m-d');

    $atrasadas = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM tarefas
            WHERE id_usuario = ? AND status != 'concluida'
            AND data_limite < ? AND data_limite IS NOT NULL
        ");
        $stmt->execute([$userId, $hoje]);
        $atrasadas = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }

    if ($atrasadas === 0) return false;

    $title = "⚠️ Tarefas Atrasadas";
    $body = "Você tem $atrasadas tarefa(s) em atraso, $nome. Resolva agora!";

    return sendWebPush($pdo, $userId, $title, $body, 'tarefas.php', [
        'tipo' => 'warning',
        'badge' => $atrasadas,
        'actions' => [
            ['action' => 'open', 'title' => 'Ver Atrasadas']
        ]
    ]);
}

function enviarAlertaFinanceiro(PDO $pdo, int $userId, string $nome): mixed {
    $mes = date('Y-m');

    // Calcular saldo do mês atual
    $receitas = 0;
    $despesas = 0;

    try {
        $stmt = $pdo->prepare("
            SELECT tipo, SUM(valor) as total
            FROM transacoes
            WHERE id_usuario = ? AND DATE_FORMAT(data_transacao, '%Y-%m') = ?
            GROUP BY tipo
        ");
        $stmt->execute([$userId, $mes]);
        foreach ($stmt->fetchAll() as $row) {
            if ($row['tipo'] === 'receita') $receitas = (float)$row['total'];
            if ($row['tipo'] === 'despesa') $despesas = (float)$row['total'];
        }
    } catch (PDOException $e) {
        return false;
    }

    if ($receitas == 0 && $despesas == 0) return false;

    $saldo = $receitas - $despesas;
    $percentGasto = $receitas > 0 ? round(($despesas / $receitas) * 100) : 0;

    if ($percentGasto >= 90) {
        $title = "🚨 Alerta de Gastos!";
        $body = "Você gastou {$percentGasto}% da sua receita este mês! Saldo: R$ " . number_format($saldo, 2, ',', '.');
        $tipo = 'danger';
    } elseif ($percentGasto >= 70) {
        $title = "⚠️ Atenção aos Gastos";
        $body = "Já gastou {$percentGasto}% da receita do mês. Saldo: R$ " . number_format($saldo, 2, ',', '.');
        $tipo = 'warning';
    } elseif ($saldo < 0) {
        $title = "🔴 Saldo Negativo!";
        $body = "Suas despesas superaram as receitas este mês. Saldo: R$ " . number_format($saldo, 2, ',', '.');
        $tipo = 'danger';
    } else {
        // Situação saudável — sem push desnecessário
        return false;
    }

    return sendWebPush($pdo, $userId, $title, $body, 'relatorios.php', [
        'tipo' => $tipo,
        'actions' => [
            ['action' => 'open', 'title' => 'Ver Relatórios']
        ]
    ]);
}
