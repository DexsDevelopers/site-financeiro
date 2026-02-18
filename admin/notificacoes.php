<?php
// /admin/notificacoes.php (100% Completo)

require_once 'header_admin.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Enviar Notificação Push</h1>
</div>

<div class="card" style="background-color: var(--admin-card-bg); border-color: var(--admin-border-color);">
    <div class="card-body p-4">
        <p class="text-muted">Escreva uma mensagem abaixo para enviar uma notificação push para todos os usuários que permitiram o recebimento. Use para anunciar novidades, atualizações ou dar dicas.</p>
        <hr>
        <form id="formEnviarNotificacao">
            <div class="mb-3">
                <label for="titulo" class="form-label">Título da Notificação</label>
                <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Ex: Nova funcionalidade!" required>
            </div>
            <div class="mb-3">
                <label for="mensagem" class="form-label">Mensagem</label>
                <textarea class="form-control" id="mensagem" name="mensagem" rows="3" placeholder="Ex: Adicionamos a página de Relatórios. Venha conferir!" required></textarea>
            </div>
            <div class="mb-3">
                <label for="url" class="form-label">URL de Abertura (Opcional)</label>
                <input type="text" class="form-control" id="url" name="url" placeholder="https://gold-quail-250128.hostingersite.com/seu_projeto/relatorios.php">
                <div class="form-text">Link que será aberto quando o usuário clicar na notificação.</div>
            </div>
            <button type="submit" id="btnEnviar" class="btn btn-primary"><i class="bi bi-send-fill me-2"></i>Enviar para Todos</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formEnviarNotificacao');
    const button = document.getElementById('btnEnviar');

    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries()); // Converte para objeto

            const originalButtonText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Enviando...`;

            fetch('enviar_notificacao_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    window.showAdminToast('Sucesso!', result.message);
                    form.reset();
                } else {
                    window.showAdminToast('Erro!', result.message, true);
                }
            })
            .catch(error => {
                console.error('Erro de Rede:', error);
                window.showAdminToast('Erro de Rede!', 'Não foi possível se conectar ao servidor.', true);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalButtonText;
            });
        });
    }
});
</script>

<?php
require_once 'footer_admin.php';
?>