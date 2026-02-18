<?php
// /index.php (Versão Robusta)
session_start();

// Incluir sistema de auto-login (Lembre-se de mim)
require_once 'includes/db_connect.php';
require_once 'includes/auto_login.php';

// Se o usuário já estiver logado, redireciona para o dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
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
        :root { --accent-red: #e50914; --dark-bg: #141414; --accent-purple: #302b63; --card-background: rgba(30, 30, 30, 0.5); --border-color: rgba(255, 255, 255, 0.1); }
        body, html { height: 100%; margin: 0; overflow: hidden; font-family: 'Poppins', sans-serif; }
        .login-container { height: 100%; display: flex; align-items: center; justify-content: center; padding: 1rem; background: linear-gradient(-45deg, var(--dark-bg), var(--accent-purple), var(--dark-bg), var(--accent-red)); background-size: 400% 400%; animation: gradientAnimation 18s ease infinite; }
        @keyframes gradientAnimation { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .login-card { background: var(--card-background); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid var(--border-color); border-radius: 15px; padding: 2.5rem; color: #fff; width: 100%; max-width: 450px; box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); animation: fadeInZoom 0.7s ease-out forwards; }
        @keyframes fadeInZoom { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .form-element { animation: fadeInUp 0.5s ease-out forwards; opacity: 0; animation-delay: var(--delay); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes shakeError { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); } 20%, 40%, 60%, 80% { transform: translateX(5px); } }
        .login-error .login-card { animation: shakeError 0.5s ease-in-out forwards; }
        .login-card h2 { font-weight: 700; margin-bottom: 2rem; text-align: center; }
        .login-card .form-control { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: #fff; border-radius: 8px; padding-left: 2.75rem; }
        .login-card .form-control::placeholder { color: rgba(255, 255, 255, 0.4); }
        .login-card .form-control:focus { background: rgba(0, 0, 0, 0.2); border-color: var(--accent-red); box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25); color: #fff; }
        .input-group-text { background: transparent; border: none; position: absolute; left: 15px; top: 50%; transform: translateY(-50%); z-index: 10; color: rgba(255, 255, 255, 0.6); }
        .input-wrapper { position: relative; }
        .btn-login { background-color: var(--accent-red); border: none; font-weight: 600; padding: 0.75rem; border-radius: 8px; width: 100%; transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease; }
        .btn-login:hover { transform: translateY(-3px); box-shadow: 0 4px 20px rgba(229, 9, 20, 0.5); background-color: var(--accent-red-hover); }
        @media (max-width: 576px) { .login-card { padding: 2rem 1.5rem; border: none; background: transparent; backdrop-filter: none; -webkit-backdrop-filter: none; box-shadow: none; } }
    </style>
</head>
<body class="<?php if (isset($_GET['error'])) echo 'login-error'; ?>">
    <div class="login-container">
        <div class="login-card">
            <h2 class="text-center form-element" style="--delay: 0.1s;"><i class="bi bi-shield-lock-fill me-2"></i>Acessar Painel</h2>
            <form action="login_process.php" method="POST" id="loginForm">
                <div class="mb-4 input-wrapper form-element" style="--delay: 0.2s;"><i class="bi bi-person-fill input-group-text"></i><input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuário" required></div>
                <div class="mb-4 input-wrapper form-element" style="--delay: 0.3s;"><i class="bi bi-key-fill input-group-text"></i><input type="password" class="form-control" id="senha" name="senha" placeholder="Senha" required></div>
                
                <!-- Checkbox Lembrar-me -->
                <div class="mb-3 form-element" style="--delay: 0.35s;">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="lembrar_me" name="lembrar_me" value="1">
                        <label class="form-check-label text-white-50" for="lembrar_me">
                            <i class="bi bi-clock-history me-1"></i>Lembrar-me por 30 dias
                        </label>
                    </div>
                </div>
                
                <div id="error-container">
                    <?php if (isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                        <div class="alert alert-success p-2 text-center" role="alert">Registro concluído! Faça o login.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['mensagem'])): ?>
                        <div class="alert alert-info p-2 text-center" role="alert"><?php echo htmlspecialchars($_GET['mensagem']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="d-grid mt-4 form-element" style="--delay: 0.4s;"><button type="submit" class="btn btn-danger btn-login">Entrar</button></div>
            </form>
            <div class="text-center mt-3 form-element" style="--delay: 0.5s;"><p class="text-white-50 mb-0">Não tem uma conta? <a href="registrar.php" class="fw-bold text-white">Registre-se</a></p></div>
            <div class="text-center mt-3 form-element" style="--delay: 0.6s;"><a href="https://wa.me/SEUNUMERO" target="_blank" class="text-white-50"><i class="bi bi-whatsapp"></i> Precisa de ajuda?</a></div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(loginForm);
                const button = loginForm.querySelector('button[type="submit"]');
                const originalButtonText = button.innerHTML;
                const body = document.body;
                const errorContainer = document.getElementById('error-container');
                errorContainer.innerHTML = '';
                body.classList.remove('login-error');
                button.disabled = true; button.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Entrando...`;
                fetch('login_process.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                    if (data.success) {
                        document.querySelector('.login-card').style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        document.querySelector('.login-card').style.opacity = '0';
                        document.querySelector('.login-card').style.transform = 'scale(0.95)';
                        setTimeout(() => { window.location.href = data.redirectUrl; }, 500);
                    } else {
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger p-2 text-center';
                        alertDiv.setAttribute('role', 'alert');
                        alertDiv.textContent = data.message || 'Usuário ou senha incorretos.';
                        errorContainer.appendChild(alertDiv);
                        body.classList.add('login-error');
                        button.disabled = false; button.innerHTML = originalButtonText;
                    }
                }).catch(error => {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger p-2 text-center';
                    alertDiv.setAttribute('role', 'alert');
                    alertDiv.textContent = 'Erro de conexão. Tente novamente.';
                    errorContainer.appendChild(alertDiv);
                    button.disabled = false; button.innerHTML = originalButtonText;
                });
            });
        }
    });
    </script>
</body>
</html>