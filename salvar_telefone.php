<?php
// salvar_telefone.php (compatível com user_id e $_SESSION['user']['id'])
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=UTF-8');

// 1) Somente POST com JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido.']);
    exit;
}

// 2) Exigir sessão/autenticação (compatibilidade com user_id e $_SESSION['user']['id'])
$userId = 0;
if (!empty($_SESSION['user']['id'])) {
    $userId = (int) $_SESSION['user']['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
}

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

// 3) Normalizar e validar telefone (Brasil)
$telefoneOriginal = (string) ($payload['telefone'] ?? '');
$telefoneDigitos  = preg_replace('/\D+/', '', $telefoneOriginal);

$erroValidacao = null;
if ($telefoneDigitos === '') {
    $erroValidacao = 'Informe um telefone.';
} elseif (strlen($telefoneDigitos) !== 11) {
    $erroValidacao = 'Telefone deve ter 11 dígitos (DDD + celular).';
} elseif (!preg_match('/^\d{2}9\d{8}$/', $telefoneDigitos)) {
    $erroValidacao = 'Formato inválido. Use DDD + celular que começa com 9.';
}

if ($erroValidacao) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error'   => $erroValidacao,
        'hint'    => 'Exemplo: 11987654321'
    ]);
    exit;
}

// 4) Formato E.164 para integrações futuras
$telefoneE164 = '+55' . $telefoneDigitos;

// 5) Persistência com PDO
require __DIR__ . '/includes/db_connect.php'; // Deve disponibilizar $pdo (PDO)

try {
    // Verifica existência do usuário
    $stmtSel = $pdo->prepare('SELECT id, telefone FROM usuarios WHERE id = :id LIMIT 1');
    $stmtSel->execute([':id' => $userId]);
    $userRow = $stmtSel->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado.']);
        exit;
    }

    // Atualiza telefone e marca como visto
    $stmt = $pdo->prepare(
        'UPDATE usuarios
            SET telefone = :tel,
                telefone_e164 = :tel_e164,
                notificacao_vista = 1,
                telefone_atualizado_em = NOW()
          WHERE id = :id'
    );

    $ok = $stmt->execute([
        ':tel'       => $telefoneDigitos,
        ':tel_e164'  => $telefoneE164,
        ':id'        => $userId
    ]);

    if (!$ok) {
        throw new RuntimeException('Falha ao atualizar o telefone.');
    }

    // Sincroniza sessão nas duas formas
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = [];
    }
    $_SESSION['user']['id']             = $userId;
    $_SESSION['user']['telefone']       = $telefoneDigitos;
    $_SESSION['user']['telefone_e164']  = $telefoneE164;
    $_SESSION['user']['notificacao_vista'] = 1;
    $_SESSION['user_id']                = $userId;
    $_SESSION['notificacao_vista']      = 1;

    echo json_encode([
        'success'        => true,
        'message'        => 'Telefone salvo com sucesso.',
        'telefone'       => $telefoneDigitos,
        'telefone_e164'  => $telefoneE164
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno ao salvar o telefone.']);
    exit;
}
