<?php
/**
 * telegram_bot.php — Webhook do Orion Finance Bot
 * IA conversacional · Contexto persistente · Aprendizado · Inline keyboards
 */
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ── Debug: gravar raw input ───────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
file_put_contents(__DIR__ . '/tg_debug.log',
    date('[Y-m-d H:i:s] ') . $rawInput . "\n---\n",
    FILE_APPEND | LOCK_EX
);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/OrionTelegram.php';

// ─── Config ───────────────────────────────────────────────────────────────────

$cfg       = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$BOT_TOKEN = $cfg['TELEGRAM_BOT_TOKEN'] ?? '';

if (empty($BOT_TOKEN) || !$pdo) { http_response_code(200); exit; }

// ─── API Helpers ──────────────────────────────────────────────────────────────

function tgApi(string $token, string $method, array $data): void
{
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function tgSend(string $token, int $chatId, string $text, ?array $keyboard = null): void
{
    $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) {
        $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    }
    tgApi($token, 'sendMessage', $payload);
}

function tgAnswer(string $token, string $callbackId, string $text = ''): void
{
    tgApi($token, 'answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
}

function tgEdit(string $token, int $chatId, int $msgId, string $text, ?array $keyboard = null): void
{
    $payload = ['chat_id' => $chatId, 'message_id' => $msgId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    else           $payload['reply_markup'] = json_encode(['inline_keyboard' => []]);
    tgApi($token, 'editMessageText', $payload);
}

// ─── Garantir tabelas de vinculação ──────────────────────────────────────────

$pdo->exec("CREATE TABLE IF NOT EXISTS telegram_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    chat_id BIGINT NOT NULL UNIQUE, username VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uk_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS telegram_vincular_codes (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    code VARCHAR(32) NOT NULL UNIQUE, expires_at DATETIME NOT NULL,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── Parsear update ───────────────────────────────────────────────────────────

$update = json_decode($rawInput, true);
if (empty($update)) { http_response_code(200); exit; }

// Suporte: message normal + callback_query (botões inline)
$isCallback  = isset($update['callback_query']);
$message     = $isCallback ? $update['callback_query']['message'] : ($update['message'] ?? $update['edited_message'] ?? null);
$callbackId  = $isCallback ? $update['callback_query']['id']   : null;
$callbackData= $isCallback ? ($update['callback_query']['data'] ?? '') : null;
$fromData    = $isCallback ? $update['callback_query']['from'] : ($message['from'] ?? []);

if (!$message) { http_response_code(200); exit; }

$chatId   = (int)$message['chat']['id'];
$msgId    = (int)($message['message_id'] ?? 0);
$fromName = trim(($fromData['first_name'] ?? '') . ' ' . ($fromData['last_name'] ?? ''));
$username = $fromData['username'] ?? null;
$text     = $isCallback ? ($update['callback_query']['message']['text'] ?? '') : trim($message['text'] ?? '');

// ─── Verificar vinculação ─────────────────────────────────────────────────────

$stmt = $pdo->prepare("SELECT user_id FROM telegram_usuarios WHERE chat_id = ?");
$stmt->execute([$chatId]);
$linkedUserId = $stmt->fetchColumn();

// ─── Comandos de diagnóstico (sem OrionTelegram) ─────────────────────────────

$inputText = $isCallback ? '' : trim($message['text'] ?? '');

if (!$isCallback && strtolower($inputText) === '/teste') {
    tgSend($BOT_TOKEN, $chatId,
        "✅ <b>Bot funcionando!</b>\n\n" .
        "PHP " . PHP_VERSION . "\n" .
        "DB: " . ($pdo ? 'OK' : 'ERRO') . "\n" .
        "isCallback: " . ($isCallback ? 'sim' : 'não') . "\n" .
        "chatId: {$chatId}\n" .
        "linkedUserId: " . ($linkedUserId ?: 'não vinculado')
    );
    http_response_code(200); exit;
}

if (!$isCallback && strtolower($inputText) === '/log') {
    $logFile = __DIR__ . '/tg_debug.log';
    $tail = file_exists($logFile)
        ? implode('', array_slice(file($logFile), -30))
        : 'Sem log';
    tgSend($BOT_TOKEN, $chatId, '<pre>' . htmlspecialchars(substr($tail, -3000)) . '</pre>');
    http_response_code(200); exit;
}

// ─── /start — vinculação de conta ────────────────────────────────────────────

if (!$isCallback && str_starts_with($inputText, '/start')) {
    $code = trim(explode(' ', $inputText, 2)[1] ?? '');

    if ($linkedUserId) {
        $orion = new OrionTelegram($pdo, (int)$linkedUserId, $chatId, $fromName);
        $r = $orion->processar('/ajuda');
        tgSend($BOT_TOKEN, $chatId, "✅ Conta já vinculada!\n\n" . $r['texto'], $r['teclado'] ?? null);
        http_response_code(200); exit;
    }

    if (empty($code)) {
        tgSend($BOT_TOKEN, $chatId,
            "👋 <b>Olá, {$fromName}!</b>\n\n" .
            "Para usar o <b>Orion Finance Bot</b>, vincule sua conta:\n\n" .
            "1. Acesse o <b>Painel Financeiro</b>\n" .
            "2. Vá em <b>Sistema → Telegram Bot</b>\n" .
            "3. Gere o código e envie: <code>/start SEU_CODIGO</code>"
        );
        http_response_code(200); exit;
    }

    $stmt = $pdo->prepare("SELECT user_id FROM telegram_vincular_codes WHERE code = ? AND expires_at > NOW()");
    $stmt->execute([$code]);
    $newUserId = $stmt->fetchColumn();

    if (!$newUserId) {
        tgSend($BOT_TOKEN, $chatId, "❌ Código inválido ou expirado.\n\nGere um novo no painel.");
        http_response_code(200); exit;
    }

    $pdo->prepare("INSERT INTO telegram_usuarios (user_id, chat_id, username) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE chat_id=?, username=?")
        ->execute([$newUserId, $chatId, $username, $chatId, $username]);
    $pdo->prepare("DELETE FROM telegram_vincular_codes WHERE user_id = ?")->execute([$newUserId]);

    tgSend($BOT_TOKEN, $chatId,
        "🎉 <b>Conta vinculada com sucesso!</b>\n\n" .
        "Agora você controla tudo pelo Telegram:\n\n" .
        "💸 <i>gastei 120 no mercado</i>\n" .
        "💰 <i>recebi 3000 salário</i>\n" .
        "✅ <i>criar tarefa pagar boleto</i>\n" .
        "📊 <i>meu saldo</i> · <i>quanto gastei hoje</i>\n" .
        "🎯 <i>criar meta viagem 5000</i>\n\n" .
        "Pode digitar normalmente! /ajuda para ver tudo."
    );
    http_response_code(200); exit;
}

// ─── Usuário não vinculado ────────────────────────────────────────────────────

if (!$linkedUserId) {
    if (!$isCallback) {
        tgSend($BOT_TOKEN, $chatId,
            "🔒 <b>Conta não vinculada.</b>\n\n" .
            "Acesse o painel → <b>Sistema → Telegram Bot</b>\n" .
            "Gere um código e envie: <code>/start SEU_CODIGO</code>"
        );
    }
    http_response_code(200); exit;
}

$userId = (int)$linkedUserId;

// ─── /desconectar ────────────────────────────────────────────────────────────

if (!$isCallback && in_array(strtolower(trim($inputText)), ['/desconectar', '/disconnect', '/sair'])) {
    $pdo->prepare("DELETE FROM telegram_usuarios WHERE chat_id = ?")->execute([$chatId]);
    tgSend($BOT_TOKEN, $chatId, "👋 Conta desvinculada. Até logo!\n\nUse /start para reconectar.");
    http_response_code(200); exit;
}

// ─── Processar via OrionTelegram ──────────────────────────────────────────────

try {
    $orion = new OrionTelegram($pdo, $userId, $chatId, $fromName);

    if ($isCallback) {
        tgAnswer($BOT_TOKEN, $callbackId);
        $resultado = $orion->processar('', $callbackData);
        tgSend($BOT_TOKEN, $chatId, $resultado['texto'], $resultado['teclado'] ?? null);
    } else {
        $resultado = $orion->processar($inputText);
        tgSend($BOT_TOKEN, $chatId, $resultado['texto'], $resultado['teclado'] ?? null);
    }

} catch (Throwable $e) {
    error_log('[OrionTelegramBot] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
    if ($isCallback) tgAnswer($BOT_TOKEN, $callbackId ?? '', '❌ Erro ao processar');
    tgSend($BOT_TOKEN, $chatId, '❌ Erro interno: ' . $e->getMessage());
}

http_response_code(200);
exit;
