<?php
// /admin/adicionar_usuario.php (Versão Moderna e Segura com AJAX)

session_start();
header('Content-Type: application/json'); // A resposta será sempre em formato JSON

// Prepara uma resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. VERIFICAÇÃO DE SEGURANÇA: O usuário logado é um admin? ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Código de erro "Proibido"
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

require_once '../includes/db_connect.php';

try {
    $stmt_check = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    $user_tipo = $stmt_check->fetchColumn();

    if ($user_tipo !== 'admin') {
        http_response_code(403); // Proibido
        $response['message'] = 'Acesso negado. Você não tem permissão de administrador.';
        echo json_encode($response);
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500); // Erro de Servidor
    $response['message'] = 'Erro ao verificar permissões do usuário.';
    echo json_encode($response);
    exit();
}


// --- 2. VALIDAÇÃO DOS DADOS DE ENTRADA (DO FORMULÁRIO) ---
$nome_completo = $_POST['nome_completo'] ?? '';
$usuario = $_POST['usuario'] ?? '';
$senha = $_POST['senha'] ?? '';

if (empty($nome_completo) || empty($usuario) || empty($senha)) {
    http_response_code(400); // Código de erro "Requisição Inválida"
    $response['message'] = 'Todos os campos são obrigatórios.';
    echo json_encode($response);
    exit();
}

if (strlen($senha) < 6) { // Regra de negócio: senha com no mínimo 6 caracteres
    http_response_code(400);
    $response['message'] = 'A senha deve ter no mínimo 6 caracteres.';
    echo json_encode($response);
    exit();
}

try {
    // --- 3. VERIFICAÇÃO DE USUÁRIO DUPLICADO ---
    $stmt_exists = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
    $stmt_exists->execute([$usuario]);
    if ($stmt_exists->fetch()) {
        http_response_code(409); // Código de erro "Conflito"
        $response['message'] = 'Este nome de usuário já está em uso. Por favor, escolha outro.';
        echo json_encode($response);
        exit();
    }
    
    // --- 4. INSERÇÃO SEGURA NO BANCO DE DADOS ---
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    
    $stmt_insert = $pdo->prepare("INSERT INTO usuarios (nome_completo, usuario, senha_hash, tipo) VALUES (?, ?, ?, 'usuario')");
    $stmt_insert->execute([$nome_completo, $usuario, $senha_hash]);

    // Se a execução chegou até aqui, a operação foi um sucesso
    $response['success'] = true;
    $response['message'] = 'Usuário "' . htmlspecialchars($nome_completo) . '" criado com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao criar o usuário.';
    // Em um ambiente de produção real, você logaria o erro em um arquivo em vez de exibi-lo.
    // error_log($e->getMessage());
    echo json_encode($response);
}
?>