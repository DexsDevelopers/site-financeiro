<?php
// /login_process.php (Versão Moderna com AJAX)

session_start();
header('Content-Type: application/json');

// Resposta padrão
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';
require_once 'includes/remember_me_manager.php';

$usuario_form = $_POST['usuario'] ?? '';
$senha_form = $_POST['senha'] ?? '';
$lembrar_me = isset($_POST['lembrar_me']) && $_POST['lembrar_me'] === '1';

if (empty($usuario_form) || empty($senha_form)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Usuário e senha são obrigatórios.';
    echo json_encode($response);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario_form]);
    $user = $stmt->fetch();

    // Verifica se o usuário existe E se a senha criptografada corresponde
    if ($user && password_verify($senha_form, $user['senha_hash'])) {
        // SUCESSO! Armazena os dados do usuário na sessão.
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nome_completo'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user'] = [
            'id' => $user['id'],
            'nome' => $user['nome_completo'],
            'email' => $user['email']
        ];
        // Guarda o tipo de usuário na sessão, será útil no futuro
        $_SESSION['user_type'] = $user['tipo'];

        // Criar token de lembrança se solicitado
        if ($lembrar_me) {
            $rememberManager = new RememberMeManager($pdo);
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            
            $token = $rememberManager->createRememberToken($user['id'], $userAgent, $ipAddress);
            
            if ($token) {
                $response['rememberToken'] = true;
            }
        }

        $response['success'] = true;
        $response['redirectUrl'] = ($user['tipo'] === 'admin') ? 'admin/index.php' : 'dashboard.php';
        echo json_encode($response);
        exit();

    } else {
        // FALHA: Usuário ou senha incorretos.
        http_response_code(401); // Não Autorizado
        $response['message'] = 'Usuário ou senha incorretos.';
        echo json_encode($response);
        exit();
    }

} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados. Tente novamente mais tarde.';
    // Em produção, você deveria logar o erro: error_log($e->getMessage());
    echo json_encode($response);
    exit();
}
?>