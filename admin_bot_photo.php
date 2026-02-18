<?php
// admin_bot_photo.php - Endpoint para upload de comprovantes
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'includes/db_connect.php';

// Carregar configuração
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
if (!$config) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar configuração']);
    exit;
}

// Validar token
$headers = getallheaders();
$token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

if ($token !== $config['WHATSAPP_API_TOKEN']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
    exit;
}

// Verificar se há upload
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado']);
    exit;
}

$file = $_FILES['photo'];
$transactionId = $_POST['transaction_id'] ?? null;
$phoneNumber = $_POST['phone'] ?? '';

if (!$transactionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID da transação não informado']);
    exit;
}

// Validar tipo MIME
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPEG, PNG ou PDF']);
    exit;
}

// Validar tamanho
$maxSize = ($config['LIMITE_UPLOAD_MB'] ?? 10) * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Máximo: ' . ($config['LIMITE_UPLOAD_MB'] ?? 10) . 'MB']);
    exit;
}

// Verificar se transação existe
try {
    $stmt = $pdo->prepare("SELECT id FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Transação não encontrada']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao verificar transação']);
    exit;
}

// Determinar extensão
$extension = 'jpg';
if ($mimeType === 'image/png') $extension = 'png';
if ($mimeType === 'application/pdf') $extension = 'pdf';

// Criar nome do arquivo
$uploadDir = __DIR__ . '/' . $config['COMPROVANTES_DIR'];
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'comprovante_' . $transactionId . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Mover arquivo
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo']);
    exit;
}

// Atualizar banco de dados
try {
    $stmt = $pdo->prepare("UPDATE transactions SET receipt_path = ? WHERE id = ?");
    $stmt->execute([$config['COMPROVANTES_DIR'] . $filename, $transactionId]);
    
    // Log
    $logSql = "INSERT INTO whatsapp_bot_logs (phone_number, command, message, response, success) 
               VALUES (?, 'comprovante', ?, ?, 1)";
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute([
        $phoneNumber,
        "Upload comprovante ID #$transactionId",
        "Comprovante salvo: $filename"
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "✅ Comprovante anexado ao ID #$transactionId",
        'filename' => $filename,
        'path' => $config['COMPROVANTES_DIR'] . $filename
    ]);
} catch (PDOException $e) {
    // Remover arquivo se falhar ao salvar no banco
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar banco de dados: ' . $e->getMessage()]);
}

