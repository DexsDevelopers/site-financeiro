<?php
// login.php - Sistema de Login com Design Original Restaurado
// Painel Financeiro Helmer - Design original com funcionalidade segura

// Configurações de segurança
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurações de sessão seguras
session_set_cookie_params([
    'lifetime' => 0, // Sessão expira ao fechar o navegador
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']), // Apenas HTTPS se disponível
    'httponly' => true, // Inacessível via JavaScript
    'samesite' => 'Strict' // Previne CSRF
]);

// Iniciar sessão de forma segura
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se já está logado
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    error_log("LOGIN: Usuário já logado (ID: " . $_SESSION['user_id'] . "), redirecionando para dashboard");
    header('Location: dashboard.php');
    exit();
}

// Incluir conexão com banco
require_once 'includes/db_connect.php';

$erro = '';
$sucesso = '';

// Verificar mensagem de logout
if (isset($_GET['mensagem'])) {
    $sucesso = htmlspecialchars($_GET['mensagem']);
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    // Validação básica
    if (empty($usuario) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
        error_log("LOGIN: Tentativa de login com campos vazios - Usuário: " . $usuario);
    } else {
        try {
            // Buscar usuário no banco
            $stmt = $pdo->prepare("
                SELECT id, nome_completo as nome, email, senha_hash as senha, tipo as papel, role
                FROM usuarios 
                WHERE usuario = ?
            ");
            $stmt->execute([$usuario]);
            $usuarioData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuarioData && password_verify($senha, $usuarioData['senha'])) {
                // Login bem-sucedido
                
                // Regenerar ID da sessão para segurança
                session_regenerate_id(true);
                
                // Definir variáveis de sessão padronizadas
                $_SESSION['user_id'] = $usuarioData['id'];
                $_SESSION['nome'] = $usuarioData['nome'];
                $_SESSION['email'] = $usuarioData['email'];
                $_SESSION['papel'] = $usuarioData['papel'];
                $_SESSION['status'] = $usuarioData['status'];
                $_SESSION['last_activity'] = time();
                
                // Log de sucesso
                error_log("LOGIN: Login bem-sucedido - Usuário: " . $usuario . " (ID: " . $usuarioData['id'] . ", Papel: " . $usuarioData['papel'] . ")");
                
                // Redirecionar para dashboard
                header('Location: dashboard.php');
                exit();
                
            } else {
                // Credenciais incorretas
                $erro = 'Usuário ou senha incorretos.';
                error_log("LOGIN: Tentativa de login com credenciais incorretas - Usuário: " . $usuario);
            }
            
        } catch (PDOException $e) {
            $erro = 'Erro interno do servidor. Tente novamente.';
            error_log("LOGIN: Erro de banco de dados - " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Painel Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --accent-red: #e50914; 
            --dark-bg: #141414; 
            --accent-purple: #302b63; 
            --card-background: rgba(30, 30, 30, 0.5); 
            --border-color: rgba(255, 255, 255, 0.1); 
        }
        body, html { 
            height: 100%; 
            margin: 0; 
            overflow: hidden; 
            font-family: 'Poppins', sans-serif; 
        }
        .login-container { 
            height: 100%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 1rem; 
            background: linear-gradient(-45deg, var(--dark-bg), var(--accent-purple), var(--dark-bg), var(--accent-red)); 
            background-size: 400% 400%; 
            animation: gradientAnimation 18s ease infinite; 
        }
        @keyframes gradientAnimation { 
            0% { background-position: 0% 50%; } 
            50% { background-position: 100% 50%; } 
            100% { background-position: 0% 50%; } 
        }
        .login-card { 
            background: var(--card-background); 
            backdrop-filter: blur(15px); 
            -webkit-backdrop-filter: blur(15px); 
            border: 1px solid var(--border-color); 
            border-radius: 15px; 
            padding: 2.5rem; 
            color: #fff; 
            width: 100%; 
            max-width: 450px; 
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); 
            animation: fadeInZoom 0.7s ease-out forwards; 
        }
        @keyframes fadeInZoom { 
            from { opacity: 0; transform: scale(0.9); } 
            to { opacity: 1; transform: scale(1); } 
        }
        .form-element { 
            animation: fadeInUp 0.5s ease-out forwards; 
            opacity: 0; 
            animation-delay: var(--delay); 
        }
        @keyframes fadeInUp { 
            from { opacity: 0; transform: translateY(20px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        @keyframes shakeError { 
            0%, 100% { transform: translateX(0); } 
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); } 
            20%, 40%, 60%, 80% { transform: translateX(5px); } 
        }
        .login-error .login-card { 
            animation: shakeError 0.5s ease-in-out forwards; 
        }
        .login-card h2 { 
            font-weight: 700; 
            margin-bottom: 2rem; 
            text-align: center; 
        }
        .login-card .form-control { 
            background: rgba(255, 255, 255, 0.05); 
            border: 1px solid var(--border-color); 
            color: #fff; 
            border-radius: 8px; 
            padding-left: 2.75rem; 
        }
        .login-card .form-control::placeholder { 
            color: rgba(255, 255, 255, 0.4); 
        }
        .login-card .form-control:focus { 
            background: rgba(0, 0, 0, 0.2); 
            border-color: var(--accent-red); 
            box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25); 
            color: #fff; 
        }
        .input-group-text { 
            background: transparent; 
            border: none; 
            position: absolute; 
            left: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            z-index: 10; 
            color: rgba(255, 255, 255, 0.6); 
        }
        .input-wrapper { 
            position: relative; 
        }
        .btn-login { 
            background-color: var(--accent-red); 
            border: none; 
            font-weight: 600; 
            padding: 0.75rem; 
            border-radius: 8px; 
            width: 100%; 
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease; 
        }
        .btn-login:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 4px 20px rgba(229, 9, 20, 0.5); 
            background-color: #c4080f; 
        }
        @media (max-width: 576px) { 
            .login-card { 
                padding: 2rem 1.5rem; 
                border: none; 
                background: transparent; 
                backdrop-filter: none; 
                -webkit-backdrop-filter: none; 
                box-shadow: none; 
            } 
        }
    </style>
</head>
<body class="<?php if (!empty($erro)) echo 'login-error'; ?>">
    <div class="login-container">
        <div class="login-card">
            <h2 class="text-center form-element" style="--delay: 0.1s;">
                <i class="bi bi-shield-lock-fill me-2"></i>Acessar Painel
            </h2>
            
            <!-- Mensagens -->
            <?php if (!empty($erro)): ?>
                <div class="alert alert-danger p-2 text-center form-element" style="--delay: 0.15s;" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($sucesso)): ?>
                <div class="alert alert-success p-2 text-center form-element" style="--delay: 0.15s;" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" id="loginForm">
                <div class="mb-4 input-wrapper form-element" style="--delay: 0.2s;">
                    <i class="bi bi-person-fill input-group-text"></i>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="usuario" 
                        name="usuario" 
                        placeholder="Usuário" 
                        value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>"
                        required
                    >
                </div>
                
                <div class="mb-4 input-wrapper form-element" style="--delay: 0.3s;">
                    <i class="bi bi-key-fill input-group-text"></i>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="senha" 
                        name="senha" 
                        placeholder="Senha" 
                        required
                    >
                </div>
                
                <div class="d-grid mt-4 form-element" style="--delay: 0.4s;">
                    <button type="submit" class="btn btn-danger btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-3 form-element" style="--delay: 0.5s;">
                <p class="text-white-50 mb-0">
                    <i class="bi bi-shield-check me-1"></i>
                    Acesso seguro e criptografado
                </p>
            </div>
            
            <div class="text-center mt-3 form-element" style="--delay: 0.6s;">
                <a href="https://wa.me/SEUNUMERO" target="_blank" class="text-white-50">
                    <i class="bi bi-whatsapp"></i> Precisa de ajuda?
                </a>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Foco automático no campo usuário
        const usuarioField = document.getElementById('usuario');
        if (usuarioField && !usuarioField.value) {
            usuarioField.focus();
        }
        
        // Limpar mensagens após 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
        
        // Animação de loading no botão
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(event) {
                const button = this.querySelector('button[type="submit"]');
                const originalButtonText = button.innerHTML;
                const body = document.body;
                
                // Remover classe de erro
                body.classList.remove('login-error');
                
                // Mostrar loading
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';
                
                // Simular delay para mostrar a animação
                setTimeout(function() {
                    // O formulário será enviado normalmente
                }, 100);
            });
        }
    });
    </script>
</body>
</html>