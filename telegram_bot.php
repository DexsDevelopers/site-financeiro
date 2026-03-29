<?php
/**
 * telegram_bot.php — Webhook do Telegram Bot
 * Recebe mensagens e processa via OrionEngine
 */
declare(strict_types=1);

// Sem output HTML, sem exibição de erros
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/OrionEngine.php';

// ─── Helpers ──────────────────────────────────────────────────────────────────

$cfg = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$BOT_TOKEN = $cfg['TELEGRAM_BOT_TOKEN'] ?? '';

if (empty($BOT_TOKEN)) {
    http_response_code(500);
    exit('Token não configurado');
}

function tg_send(string $token, int|string $chatId, string $text): void
{
    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function mdToHtml(string $text): string
{
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $text);
    $text = preg_replace('/\*(.+?)\*/s',     '<b>$1</b>', $text);
    return $text;
}

// ─── Criar tabelas se não existirem ───────────────────────────────────────────

if ($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS telegram_usuarios (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            chat_id    BIGINT NOT NULL UNIQUE,
            username   VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS telegram_vincular_codes (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            code       VARCHAR(32) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            INDEX idx_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ─── Receber update do Telegram ───────────────────────────────────────────────

$input  = file_get_contents('php://input');
$update = json_decode($input, true);

if (empty($update)) {
    http_response_code(200);
    exit;
}

// Suporte a mensagens de texto e callback_query básico
$message = $update['message'] ?? $update['edited_message'] ?? null;
if (!$message) {
    http_response_code(200);
    exit;
}

$chatId   = (int) $message['chat']['id'];
$fromName = trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? ''));
$username = $message['from']['username'] ?? null;
$text     = trim($message['text'] ?? '');

if (empty($text) || !$pdo) {
    http_response_code(200);
    exit;
}

// ─── Verificar se chat_id já está vinculado ───────────────────────────────────

$stmt   = $pdo->prepare("SELECT user_id FROM telegram_usuarios WHERE chat_id = ?");
$stmt->execute([$chatId]);
$linked = $stmt->fetchColumn();

// ─── Comando /start ───────────────────────────────────────────────────────────

if (str_starts_with($text, '/start')) {
    $parts = explode(' ', $text, 2);
    $code  = trim($parts[1] ?? '');

    if ($linked) {
        tg_send($BOT_TOKEN, $chatId,
            "✅ Sua conta já está vinculada!\n\n" .
            "Pode usar normalmente. Exemplos:\n" .
            "• <i>gastei 50 no almoço</i>\n" .
            "• <i>criar tarefa pagar boleto amanhã</i>\n" .
            "• <i>meu saldo</i>\n" .
            "• <i>quanto gastei esse mês</i>\n\n" .
            "/ajuda para ver todos os comandos."
        );
        http_response_code(200);
        exit;
    }

    if (empty($code)) {
        tg_send($BOT_TOKEN, $chatId,
            "👋 Olá, {$fromName}!\n\n" .
            "Para usar o bot, vincule sua conta:\n\n" .
            "1. Acesse o <b>Painel Financeiro</b>\n" .
            "2. Vá em <b>Configurações → Telegram</b>\n" .
            "3. Copie o código e envie aqui como:\n" .
            "   <code>/start SEU_CODIGO</code>"
        );
        http_response_code(200);
        exit;
    }

    // Validar código
    $stmt = $pdo->prepare("
        SELECT user_id FROM telegram_vincular_codes
        WHERE code = ? AND expires_at > NOW()
    ");
    $stmt->execute([$code]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        tg_send($BOT_TOKEN, $chatId,
            "❌ Código inválido ou expirado.\n\n" .
            "Gere um novo código no painel e tente novamente."
        );
        http_response_code(200);
        exit;
    }

    // Vincular
    $pdo->prepare("
        INSERT INTO telegram_usuarios (user_id, chat_id, username)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE chat_id = ?, username = ?
    ")->execute([$userId, $chatId, $username, $chatId, $username]);

    // Apagar código usado
    $pdo->prepare("DELETE FROM telegram_vincular_codes WHERE user_id = ?")->execute([$userId]);

    tg_send($BOT_TOKEN, $chatId,
        "🎉 Conta vinculada com sucesso!\n\n" .
        "Agora você pode controlar seu sistema pelo Telegram. Exemplos:\n\n" .
        "💸 <i>gastei 120 no mercado</i>\n" .
        "💰 <i>recebi 3000 de salário</i>\n" .
        "✅ <i>criar tarefa reunião amanhã</i>\n" .
        "📊 <i>meu saldo</i> · <i>quanto gastei esse mês</i>\n\n" .
        "/ajuda para ver todos os comandos."
    );
    http_response_code(200);
    exit;
}

// ─── Usuário não vinculado → pedir vinculação ─────────────────────────────────

if (!$linked) {
    tg_send($BOT_TOKEN, $chatId,
        "🔒 Conta não vinculada.\n\n" .
        "Acesse o painel e gere um código em <b>Configurações → Telegram</b>.\n" .
        "Depois envie: <code>/start SEU_CODIGO</code>"
    );
    http_response_code(200);
    exit;
}

$userId = (int) $linked;

// ─── Comandos especiais ───────────────────────────────────────────────────────

if ($text === '/ajuda' || $text === '/help') {
    tg_send($BOT_TOKEN, $chatId,
        "🤖 <b>Comandos disponíveis:</b>\n\n" .
        "<b>💸 Finanças</b>\n" .
        "• gastei 50 no almoço\n" .
        "• comprei pizza 30 reais\n" .
        "• recebi 3000 de salário\n" .
        "• paguei conta de luz 180\n\n" .
        "<b>✅ Tarefas</b>\n" .
        "• criar tarefa pagar boleto\n" .
        "• criar tarefa reunião amanhã alta prioridade\n\n" .
        "<b>📊 Consultas</b>\n" .
        "• meu saldo\n" .
        "• quanto gastei esse mês\n" .
        "• minhas despesas de hoje\n" .
        "• resumo financeiro\n\n" .
        "<b>🎯 Metas</b>\n" .
        "• criar meta viagem 5000\n\n" .
        "/desconectar — desvincular conta"
    );
    http_response_code(200);
    exit;
}

if ($text === '/desconectar' || $text === '/disconnect') {
    $pdo->prepare("DELETE FROM telegram_usuarios WHERE chat_id = ?")->execute([$chatId]);
    tg_send($BOT_TOKEN, $chatId,
        "👋 Conta desvinculada. Até logo!\n\n" .
        "Para reconectar, gere um novo código no painel."
    );
    http_response_code(200);
    exit;
}

if ($text === '/saldo' || $text === '/balance') {
    $text = 'meu saldo';
}

// ─── Processar via OrionEngine ────────────────────────────────────────────────

try {
    $orion   = new OrionEngine($pdo, $userId);
    $resposta = $orion->processQuery($text);

    // Converter markdown simples para HTML do Telegram
    $resposta = mdToHtml($resposta);

    tg_send($BOT_TOKEN, $chatId, $resposta);

} catch (Throwable $e) {
    error_log('[TelegramBot] Erro: ' . $e->getMessage());
    tg_send($BOT_TOKEN, $chatId, '❌ Erro interno. Tente novamente.');
}

http_response_code(200);
exit;
