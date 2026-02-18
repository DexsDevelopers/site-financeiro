<?php
// upload_pdf_corrigido.php - Endpoint corrigido para upload de PDF

// Configurações de segurança
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder a OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit();
}

// Iniciar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se usuário está logado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não logado. Faça login primeiro.'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    // Verificar se arquivo foi enviado
    if (!isset($_FILES['pdf_extrato']) || $_FILES['pdf_extrato']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo muito grande (limite do PHP)',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande (limite do formulário)',
            UPLOAD_ERR_PARTIAL => 'Upload foi interrompido',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
            UPLOAD_ERR_CANT_WRITE => 'Erro ao escrever arquivo',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        
        $errorCode = $_FILES['pdf_extrato']['error'] ?? UPLOAD_ERR_NO_FILE;
        $response['message'] = $errorMessages[$errorCode] ?? 'Erro desconhecido no upload';
        echo json_encode($response);
        exit();
    }
    
    $arquivo = $_FILES['pdf_extrato'];
    
    // Log do upload
    error_log("Upload iniciado - Usuário: $userId, Arquivo: " . $arquivo['name'] . ", Tamanho: " . $arquivo['size']);
    
    // Validar tamanho (máximo 10MB)
    $tamanhoMaximo = 10 * 1024 * 1024; // 10MB
    if ($arquivo['size'] > $tamanhoMaximo) {
        $response['message'] = 'Arquivo muito grande. Máximo permitido: 10MB';
        echo json_encode($response);
        exit();
    }
    
    // Validar tipo MIME
    $tipoPermitido = mime_content_type($arquivo['tmp_name']);
    $tiposValidos = ['application/pdf', 'application/x-pdf'];
    
    if (!in_array($tipoPermitido, $tiposValidos)) {
        $response['message'] = 'Tipo de arquivo inválido. Apenas PDFs são aceitos. Tipo detectado: ' . $tipoPermitido;
        echo json_encode($response);
        exit();
    }
    
    // Validar extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if ($extensao !== 'pdf') {
        $response['message'] = 'Extensão inválida. Apenas arquivos .pdf são aceitos.';
        echo json_encode($response);
        exit();
    }
    
    // Criar diretório de uploads se não existir
    $diretorioUploads = 'uploads/extratos/';
    if (!is_dir($diretorioUploads)) {
        if (!mkdir($diretorioUploads, 0755, true)) {
            $response['message'] = 'Erro ao criar diretório de uploads';
            echo json_encode($response);
            exit();
        }
    }
    
    // Verificar permissões do diretório
    if (!is_writable($diretorioUploads)) {
        chmod($diretorioUploads, 0755);
        if (!is_writable($diretorioUploads)) {
            $response['message'] = 'Diretório de uploads não tem permissão de escrita';
            echo json_encode($response);
            exit();
        }
    }
    
    // Gerar nome único para o arquivo
    $nomeArquivo = 'extrato_' . $userId . '_' . time() . '_' . uniqid() . '.pdf';
    $caminhoCompleto = $diretorioUploads . $nomeArquivo;
    
    // Mover arquivo para diretório final
    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        $response['message'] = 'Erro ao salvar arquivo no servidor';
        echo json_encode($response);
        exit();
    }
    
    // Verificar se arquivo foi salvo corretamente
    if (!file_exists($caminhoCompleto) || filesize($caminhoCompleto) === 0) {
        $response['message'] = 'Arquivo não foi salvo corretamente';
        echo json_encode($response);
        exit();
    }
    
    // Definir permissões do arquivo
    chmod($caminhoCompleto, 0644);
    
    // Verificar se é um PDF válido
    $handle = fopen($caminhoCompleto, 'rb');
    if (!$handle) {
        $response['message'] = 'Erro ao abrir arquivo para verificação';
        echo json_encode($response);
        exit();
    }
    
    $header = fread($handle, 4);
    fclose($handle);
    
    if ($header !== '%PDF') {
        // Remover arquivo inválido
        unlink($caminhoCompleto);
        $response['message'] = 'Arquivo não é um PDF válido';
        echo json_encode($response);
        exit();
    }
    
    // Log de sucesso
    error_log("Upload bem-sucedido - Arquivo: $caminhoCompleto, Tamanho: " . filesize($caminhoCompleto));
    
    // Resposta de sucesso
    $response['success'] = true;
    $response['message'] = 'Arquivo PDF carregado com sucesso!';
    $response['arquivo'] = $caminhoCompleto;
    $response['tamanho'] = filesize($caminhoCompleto);
    $response['nome'] = $nomeArquivo;
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Erro no upload: " . $e->getMessage());
    
    $response['message'] = 'Erro interno do servidor: ' . $e->getMessage();
    echo json_encode($response);
}
?>
