<?php
// /registrar.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar - Painel Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent-red: #e50914; --dark-bg: #141414; --accent-purple: #302b63; --card-background: rgba(30, 30, 30, 0.5); --border-color: rgba(255, 255, 255, 0.1); }
        body, html { height: 100%; margin: 0; overflow-y: auto; font-family: 'Poppins', sans-serif; }
        .login-container { min-height: 100%; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; background: linear-gradient(-45deg, var(--dark-bg), var(--accent-purple), var(--dark-bg), var(--accent-red)); background-size: 400% 400%; animation: gradientAnimation 18s ease infinite; }
        @keyframes gradientAnimation { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .login-card { background: var(--card-background); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid var(--border-color); border-radius: 15px; padding: 2.5rem; color: #fff; width: 100%; max-width: 450px; box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .login-card h2 { font-weight: 700; margin-bottom: 2rem; text-align: center; }
        .login-card .form-control { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: #fff; border-radius: 8px; padding-left: 2.75rem; }
        .login-card .form-control::placeholder { color: rgba(255, 255, 255, 0.4); }
        .login-card .form-control:focus { background: rgba(0, 0, 0, 0.2); border-color: var(--accent-red); box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25); color: #fff; }
        .input-group-text { background: transparent; border: none; position: absolute; left: 15px; top: 50%; transform: translateY(-50%); z-index: 10; color: rgba(255, 255, 255, 0.6); }
        .input-wrapper { position: relative; }
        .btn-login { background-color: var(--accent-red); border: none; font-weight: 600; padding: 0.75rem; border-radius: 8px; width: 100%; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2 class="text-center"><i class="bi bi-person-plus-fill me-2"></i>Crie sua Conta</h2>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger p-2 text-center mb-3" role="alert">
                    <?php 
                        switch ($_GET['error']) {
                            case 'empty': echo 'Todos os campos são obrigatórios.'; break;
                            case 'password_short': echo 'A senha deve ter no mínimo 6 caracteres.'; break;
                            case 'password_mismatch': echo 'As senhas não correspondem.'; break;
                            case 'user_exists': echo 'Este nome de usuário já está em uso.'; break;
                            default: echo 'Ocorreu um erro. Tente novamente.'; break;
                        }
                    ?>
                </div>
            <?php endif; ?>
            <form action="registrar_process.php" method="POST">
                <div class="mb-3 input-wrapper"><i class="bi bi-person-badge-fill input-group-text"></i><input type="text" class="form-control" name="nome_completo" placeholder="Nome Completo" required></div>
                <div class="mb-3 input-wrapper"><i class="bi bi-person-fill input-group-text"></i><input type="text" class="form-control" name="usuario" placeholder="Nome de Usuário (para login)" required></div>
                <div class="mb-3 input-wrapper"><i class="bi bi-key-fill input-group-text"></i><input type="password" class="form-control" name="senha" placeholder="Senha (mínimo 6 caracteres)" required></div>
                <div class="mb-4 input-wrapper"><i class="bi bi-key-fill input-group-text"></i><input type="password" class="form-control" name="confirmar_senha" placeholder="Confirme sua Senha" required></div>
                <div class="d-grid mt-4"><button type="submit" class="btn btn-danger btn-login">Registrar</button></div>
            </form>
            <div class="text-center mt-3"><p class="text-white-50 mb-0">Já tem uma conta? <a href="index.php" class="fw-bold text-white">Faça o login</a></p></div>
        </div>
    </div>
</body>
</html>