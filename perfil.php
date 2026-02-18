<?php
// perfil.php (Versão Moderna com AJAX)

require_once 'templates/header.php';
require_once 'includes/db_connect.php';

// Busca o nome atual do usuário para preencher o formulário
try {
    $stmt = $pdo->prepare("SELECT nome_completo FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]); // $userId vem do header.php
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    // Em caso de erro na busca, define um valor padrão para não quebrar a página
    $usuario = ['nome_completo' => ''];
    // Poderíamos logar o erro aqui
}

?>

<h1 class="h2 mb-4">Meu Perfil</h1>

<div class="row g-4">
    <div class="col-lg-6" data-aos="fade-up">
        <div class="card card-custom h-100">
            <div class="card-body p-4">
                <h4 class="card-title mb-4"><i class="bi bi-person-badge-fill me-2"></i>Alterar Dados Pessoais</h4>
                <form id="formAtualizarPerfil" action="atualizar_perfil.php" method="POST">
                    <div class="mb-3">
                        <label for="nome_completo" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($usuario['nome_completo']); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-danger">Salvar Nome</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
        <div class="card card-custom h-100">
            <div class="card-body p-4">
                <h4 class="card-title mb-4"><i class="bi bi-key-fill me-2"></i>Alterar Senha</h4>
                <form id="formAtualizarSenha" action="atualizar_senha.php" method="POST">
                    <div class="mb-3">
                        <label for="senha_atual" class="form-label">Senha Atual</label>
                        <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                    </div>
                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova Senha (mínimo 8 caracteres)</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmar_nova_senha" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirmar_nova_senha" name="confirmar_nova_senha" required>
                    </div>
                    <button type="submit" class="btn btn-danger">Alterar Senha</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 800, once: true });

    const formPerfil = document.getElementById('formAtualizarPerfil');
    const formSenha = document.getElementById('formAtualizarSenha');

    // --- AJAX PARA ATUALIZAR O PERFIL (NOME) ---
    if (formPerfil) {
        formPerfil.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formPerfil);
            const button = formPerfil.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

            fetch('atualizar_perfil.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    // Atualiza o nome do usuário no menu lateral em tempo real
                    const nomeUsuarioMenu = document.querySelector('.user-info .text-white');
                    if (nomeUsuarioMenu) {
                        nomeUsuarioMenu.textContent = data.novo_nome;
                    }
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = 'Salvar Nome';
            });
        });
    }

    // --- AJAX PARA ATUALIZAR A SENHA ---
    if (formSenha) {
        formSenha.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formSenha);
            const button = formSenha.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Alterando...';

            fetch('atualizar_senha.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    formSenha.reset(); // Limpa os campos de senha
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = 'Alterar Senha';
            });
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>