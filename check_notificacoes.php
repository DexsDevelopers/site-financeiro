<?php
/**
 * check_notificacoes.php — Endpoint de polling de notificações
 * Chamado pelo JS do painel a cada 60s.
 * Verifica tarefas com horário chegando e notifica via Telegram + Web Push.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth_check.php';

header('Content-Type: application/json');

if (!$pdo || !isset($userId)) {
    echo json_encode(['ok' => false]); exit;
}

// ─── Config ───────────────────────────────────────────────────────────────────
$cfg       = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$BOT_TOKEN = $cfg['TELEGRAM_BOT_TOKEN'] ?? '';

// ─── Garantir colunas ─────────────────────────────────────────────────────────
try { $pdo->exec("ALTER TABLE tarefas ADD COLUMN hora_lembrete TIME NULL DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE tarefas ADD COLUMN tg_notificado TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}

// ─── Buscar tarefas vencendo agora (±2 min) ───────────────────────────────────
$stmt = $pdo->prepare("
    SELECT t.id, t.descricao, t.prioridade, t.data_limite, t.hora_lembrete,
           tu.chat_id
    FROM tarefas t
    LEFT JOIN telegram_usuarios tu ON tu.user_id = t.id_usuario
    WHERE t.id_usuario = ?
      AND t.status     = 'pendente'
      AND t.tg_notificado = 0
      AND t.hora_lembrete IS NOT NULL
      AND (t.data_limite IS NULL OR t.data_limite = CURDATE())
      AND t.hora_lembrete BETWEEN SUBTIME(CURTIME(), '00:02:00')
                               AND ADDTIME(CURTIME(), '00:02:00')
");
$stmt->execute([$userId]);
$tarefas = $stmt->fetchAll();

$notificacoes = [];
$iconePrio = ['Alta' => '🔴', 'Média' => '🟡', 'Baixa' => '🟢'];

foreach ($tarefas as $t) {
    $descricao  = $t['descricao'];
    $prioridade = $t['prioridade'] ?? 'Média';
    $hora       = substr($t['hora_lembrete'] ?? '', 0, 5);
    $icone      = $iconePrio[$prioridade] ?? '🔔';
    $chatId     = $t['chat_id'] ? (int)$t['chat_id'] : null;

    // ── Telegram ──────────────────────────────────────────────────────────────
    if ($chatId && $BOT_TOKEN) {
        $msg  = "⏰ <b>Lembrete de Tarefa!</b>\n\n";
        $msg .= "{$icone} <b>{$descricao}</b>\n";
        $msg .= "🕐 Horário: <b>{$hora}</b>\n";
        $msg .= "🏷️ Prioridade: <b>{$prioridade}</b>";
        $keyboard = [[
            ['text' => '✅ Concluir tarefa', 'callback_data' => 'done_task:' . $t['id']],
            ['text' => '⏰ +1 hora',         'callback_data' => 'snooze_task:' . $t['id']],
        ]];
        $payload = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])];
        $ch = curl_init("https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        curl_exec($ch); curl_close($ch);
    }

    // ── Web Push ──────────────────────────────────────────────────────────────
    if (file_exists(__DIR__ . '/includes/push_helper.php')) {
        try {
            require_once __DIR__ . '/includes/push_helper.php';
            sendWebPush($pdo, $userId,
                "⏰ Lembrete: {$descricao}",
                "Horário: {$hora} · Prioridade: {$prioridade}",
                'tarefas.php',
                ['tipo' => 'lembrete']
            );
        } catch (Throwable $e) {}
    }

    // ── Histórico in-app ─────────────────────────────────────────────────────
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notificacoes_historico (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
            titulo VARCHAR(255) NOT NULL, mensagem TEXT NOT NULL,
            url VARCHAR(500) DEFAULT 'tarefas.php', tipo VARCHAR(50) DEFAULT 'lembrete',
            lida TINYINT(1) DEFAULT 0, enviada_push TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->prepare("INSERT INTO notificacoes_historico (user_id, titulo, mensagem, url, tipo, enviada_push) VALUES (?,?,?,'tarefas.php','lembrete',1)")
            ->execute([$userId, "⏰ {$descricao}", "Horário: {$hora} · {$prioridade}"]);
    } catch (Throwable $e) {}

    // ── Marcar notificado ─────────────────────────────────────────────────────
    $pdo->prepare("UPDATE tarefas SET tg_notificado = 1 WHERE id = ?")
        ->execute([$t['id']]);

    // ── Resposta para toast no site ───────────────────────────────────────────
    $notificacoes[] = [
        'titulo'    => '⏰ Lembrete de Tarefa',
        'mensagem'  => "{$icone} {$descricao} — {$hora}",
        'url'       => 'tarefas.php',
        'prioridade'=> $prioridade,
    ];
}

echo json_encode(['ok' => true, 'notificacoes' => $notificacoes]);
