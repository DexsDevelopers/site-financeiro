<?php
/**
 * cron_notificacoes.php — Cron job de notificações de tarefas
 * Roda a cada minuto. Notifica via Telegram + Web Push.
 *
 * Configurar no Hostinger (cPanel → Cron Jobs):
 *   * * * * *   php /home/usuario/public_html/cron_notificacoes.php
 */
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Segurança: só roda via CLI ou token secreto
if (PHP_SAPI !== 'cli') {
    $cfg   = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    $token = $cfg['CRON_SECRET'] ?? '';
    if (empty($token) || ($_GET['token'] ?? '') !== $token) {
        http_response_code(403); exit('Forbidden');
    }
}

require_once __DIR__ . '/includes/db_connect.php';

if (!$pdo) { error_log('[Cron] PDO indisponível'); exit(1); }

// ─── Garantir colunas extras na tabela tarefas ───────────────────────────────

try {
    $pdo->exec("ALTER TABLE tarefas ADD COLUMN hora_lembrete TIME NULL DEFAULT NULL");
} catch (Throwable $e) { /* coluna já existe */ }

try {
    $pdo->exec("ALTER TABLE tarefas ADD COLUMN tg_notificado TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) { /* coluna já existe */ }

// ─── Carregar config ─────────────────────────────────────────────────────────

$cfg       = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$BOT_TOKEN = $cfg['TELEGRAM_BOT_TOKEN'] ?? '';

// ─── Helper Telegram ─────────────────────────────────────────────────────────

function tgCronSend(string $token, int $chatId, string $text, ?array $keyboard = null): void
{
    $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) {
        $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ─── Buscar tarefas que precisam ser notificadas AGORA ───────────────────────
// Janela: hora_lembrete entre (agora - 1 min) e (agora + 1 min) — tolerância do cron

$stmt = $pdo->prepare("
    SELECT
        t.id, t.id_usuario, t.descricao, t.prioridade,
        t.data_limite, t.hora_lembrete,
        tu.chat_id
    FROM tarefas t
    LEFT JOIN telegram_usuarios tu ON tu.user_id = t.id_usuario
    WHERE t.status = 'pendente'
      AND t.tg_notificado = 0
      AND t.hora_lembrete IS NOT NULL
      AND (t.data_limite IS NULL OR t.data_limite = CURDATE())
      AND t.hora_lembrete BETWEEN SUBTIME(CURTIME(), '00:01:30') AND ADDTIME(CURTIME(), '00:01:30')
");
$stmt->execute();
$tarefas = $stmt->fetchAll();

if (empty($tarefas)) {
    exit(0);
}

// Carregar web push se disponível
$pushDisponivel = file_exists(__DIR__ . '/includes/push_helper.php');
if ($pushDisponivel) {
    require_once __DIR__ . '/includes/push_helper.php';
}

$iconePrio = ['Alta' => '🔴', 'Média' => '🟡', 'Baixa' => '🟢'];

foreach ($tarefas as $t) {
    $userId   = (int)$t['id_usuario'];
    $chatId   = $t['chat_id'] ? (int)$t['chat_id'] : null;
    $descricao = $t['descricao'];
    $prioridade = $t['prioridade'] ?? 'Média';
    $hora     = $t['hora_lembrete'] ? substr($t['hora_lembrete'], 0, 5) : '';
    $icone    = $iconePrio[$prioridade] ?? '🔔';

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
        tgCronSend($BOT_TOKEN, $chatId, $msg, $keyboard);
    }

    // ── Web Push ──────────────────────────────────────────────────────────────
    if ($pushDisponivel) {
        try {
            sendWebPush(
                $pdo,
                $userId,
                "⏰ Lembrete: {$descricao}",
                "Horário: {$hora} · Prioridade: {$prioridade}",
                'tarefas.php',
                ['tipo' => 'lembrete', 'actions' => [['action' => 'open', 'title' => 'Ver Tarefa']]]
            );
        } catch (Throwable $e) {
            error_log('[Cron] Web push erro: ' . $e->getMessage());
        }
    }

    // ── Salvar no histórico de notificações ───────────────────────────────────
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notificacoes_historico (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
            titulo VARCHAR(255) NOT NULL, mensagem TEXT NOT NULL,
            url VARCHAR(500) DEFAULT 'tarefas.php', tipo VARCHAR(50) DEFAULT 'lembrete',
            lida TINYINT(1) DEFAULT 0, enviada_push TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->prepare("INSERT INTO notificacoes_historico (user_id, titulo, mensagem, url, tipo, enviada_push)
            VALUES (?, ?, ?, 'tarefas.php', 'lembrete', 1)")
            ->execute([$userId, "⏰ Lembrete: {$descricao}", "Horário: {$hora} · {$prioridade}"]);
    } catch (Throwable $e) { /* silencioso */ }

    // ── Marcar como notificado ────────────────────────────────────────────────
    $pdo->prepare("UPDATE tarefas SET tg_notificado = 1 WHERE id = ?")
        ->execute([$t['id']]);

    error_log("[Cron] Notificação enviada — userId:{$userId} tarefa:{$t['id']} '{$descricao}' às {$hora}");
}

exit(0);
