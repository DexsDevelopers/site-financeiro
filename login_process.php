<?php
// /login_process.php (Versão Melhorada com Remember Me)

// Garantir que setcookie funcione
if (headers_sent()) {
    error_log("LOGIN_PROCESS: Headers já enviados! Setcookie não funcionará.");
}

session_start();
header('Content-Type: application/json; charset=utf-8');

// Resposta padrão
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

require_once 'includes/db_connect.php';
require_once 'includes/remember_me_manager.php';
require_once 'includes/atividade_manager.php';

$usuario_form = trim($_POST['usuario'] ?? '');
$senha_form = $_POST['senha'] ?? '';
$lembrar_me = isset($_POST['lembrar_me']) && $_POST['lembrar_me'] === '1';

error_log("LOGIN_PROCESS: Tentativa de login - Usuário: $usuario_form, Lembrar-me: " . ($lembrar_me ? 'Sim' : 'Não'));

if (empty($usuario_form) || empty($senha_form)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Usuário e senha são obrigatórios.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log("LOGIN_PROCESS: Falha - Campos vazios");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario_form]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

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
        // Guarda o tipo de usuário na sessão
        $_SESSION['user_type'] = $user['tipo'];

        error_log("LOGIN_PROCESS: Login bem-sucedido para usuário ID: " . $user['id']);

        // ===== REGISTRAR ATIVIDADE DE LOGIN =====
        try {
            $atividadeManager = new AtividadeManager($pdo);
            $atividadeManager->registrarAtividade(
                $user['id'],
                'login',
                'login.php',
                ['lembrar_me' => $lembrar_me]
            );
        } catch (Exception $e) {
            error_log("LOGIN_PROCESS: Erro ao registrar atividade: " . $e->getMessage());
            // Não impede o login se falhar
        }

        // ===== CRIAR TOKEN DE LEMBRANÇA SE SOLICITADO =====
        // IMPORTANTE: Fazer isso ANTES de enviar a resposta JSON
        if ($lembrar_me) {
            error_log("LOGIN_PROCESS: Criando token de lembrança...");
            try {
                $rememberManager = new RememberMeManager($pdo);
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
                
                $token = $rememberManager->createRememberToken($user['id'], $userAgent, $ipAddress);
                
                if ($token) {
                    error_log("LOGIN_PROCESS: Token de lembrança criado com sucesso: " . substr($token, 0, 20) . "...");
                    $response['rememberToken'] = true;
                    $response['remember_message'] = 'Cookie de lembrança definido com sucesso.';
                } else {
                    error_log("LOGIN_PROCESS: Falha ao criar token de lembrança");
                    $response['rememberToken'] = false;
                    $response['remember_message'] = 'Não foi possível ativar "Lembrar-me".';
                }
            } catch (Exception $e) {
                error_log("LOGIN_PROCESS: Erro ao criar token: " . $e->getMessage());
                $response['rememberToken'] = false;
            }
        }

        // Retornar resposta bem-sucedida
        $response['success'] = true;
        $response['message'] = 'Login realizado com sucesso!';
        $response['redirectUrl'] = ($user['tipo'] === 'admin') ? 'admin/index.php' : 'dashboard.php';
        
        http_response_code(200);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        error_log("LOGIN_PROCESS: Resposta enviada ao cliente");
        exit();

    } else {
        // FALHA: Usuário ou senha incorretos.
        http_response_code(401); // Não Autorizado
        $response['message'] = 'Usuário ou senha incorretos.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        error_log("LOGIN_PROCESS: Falha - Credenciais incorretas para usuário: $usuario_form");
        exit();
    }

} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados. Tente novamente mais tarde.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log("LOGIN_PROCESS: Erro PDO - " . $e->getMessage());
    exit();
} catch (Exception $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro ao processar login. Tente novamente.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log("LOGIN_PROCESS: Erro geral - " . $e->getMessage());
    exit();
}
?>