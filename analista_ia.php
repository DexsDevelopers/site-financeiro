<?php
// analista_ia.php (Versão Final com Fetch Simples)
require_once 'templates/header.php';
?>
<style>
    .chat-wrapper { display: flex; flex-direction: column; height: calc(100vh - 200px); max-height: 700px; }
    .chat-history { flex-grow: 1; overflow-y: auto; padding: 1.5rem; }
    .chat-message { max-width: 80%; width: -moz-fit-content; width: fit-content; padding: 0.75rem 1rem; border-radius: 1.25rem; margin-bottom: 1rem; line-height: 1.5; animation: fadeIn 0.5s ease-out forwards; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .user-message { background-color: var(--accent-red); color: white; margin-left: auto; border-bottom-right-radius: 0.25rem; }
    .ia-message { background-color: #2a2a2a; color: #f5f5f1; margin-right: auto; border-bottom-left-radius: 0.25rem; }
    .ia-message ul { padding-left: 20px; margin-bottom: 0; }
    .chat-input-form { border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; }
    .sugestao-btn { transition: all 0.2s ease; border-color: var(--border-color) !important; color: var(--text-secondary); }
    .sugestao-btn:hover { background-color: #333 !important; color: #fff; transform: translateY(-2px); }
</style>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Analista Pessoal Orion</h1>
</div>
<div class="card card-custom">
    <div class="card-body p-0">
        <div class="chat-wrapper">
            <div id="chat-history" class="chat-history">
                <div class="ia-message">Olá, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?>! Sou Orion, seu assistente pessoal. Faça uma pergunta sobre suas finanças ou tarefas e eu farei uma análise para você.</div>
            </div>
            <div class="chat-input-form">
                <div class="d-flex gap-2 mb-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary sugestao-btn">Onde posso economizar este mês?</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary sugestao-btn">Quais são minhas tarefas mais urgentes?</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary sugestao-btn">Faça um resumo dos meus gastos.</button>
                </div>
                <form id="formAnaliseIa" class="d-flex gap-2">
                    <input type="text" id="perguntaUsuario" class="form-control" placeholder="Digite sua pergunta..." required autocomplete="off">
                    <button type="submit" id="btnAnalisar" class="btn btn-danger flex-shrink-0"><i class="bi bi-send-fill"></i><span class="d-none d-md-inline"> Enviar</span></button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formAnalise = document.getElementById('formAnaliseIa');
    const btnAnalisar = document.getElementById('btnAnalisar');
    const chatHistory = document.getElementById('chat-history');
    const perguntaInput = document.getElementById('perguntaUsuario');
    const sugestoes = document.querySelectorAll('.sugestao-btn');

    sugestoes.forEach(btn => {
        btn.addEventListener('click', () => {
            perguntaInput.value = btn.textContent;
            formAnalise.requestSubmit();
        });
    });

    formAnalise.addEventListener('submit', function(event) {
        event.preventDefault();
        const pergunta = perguntaInput.value.trim();
        if (!pergunta) return;

        addMessageToUI('user', pergunta);
        perguntaInput.value = '';
        btnAnalisar.disabled = true;
        btnAnalisar.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Analisando...`;
        
        // Adicione esta função ao seu <script> em analista_ia.php
function escapeHTML(str) {
    const p = document.createElement('p');
    p.appendChild(document.createTextNode(str));
    return p.innerHTML;
}

        const iaMessageDiv = addMessageToUI('ia', '<div class="spinner-border spinner-border-sm text-muted"></div>');

        fetch('processar_analise_ia.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pergunta: pergunta })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Usa a biblioteca marked.js para converter o markdown da IA em HTML
                iaMessageDiv.innerHTML = marked.parse(data.resposta);
            } else {
                iaMessageDiv.innerHTML = `<p class="text-danger m-0"><strong>Erro:</strong> ${data.message}</p>`;
            }
        })
        .catch(error => {
            console.error('Erro de rede:', error);
            iaMessageDiv.innerHTML = `<p class="text-danger m-0"><strong>Erro de Rede:</strong> Não foi possível se conectar.</p>`;
        })
        .finally(() => {
            btnAnalisar.disabled = false;
            btnAnalisar.innerHTML = '<i class="bi bi-send-fill"></i><span class="d-none d-md-inline"> Enviar</span>';
            chatHistory.scrollTop = chatHistory.scrollHeight;
        });
    });

    function addMessageToUI(role, text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${role}-message`;
        // Escapa o texto do usuário para segurança, mas renderiza o HTML da IA
        messageDiv.innerHTML = (role === 'user') ? escapeHTML(text) : text;
        chatHistory.appendChild(messageDiv);
        chatHistory.scrollTop = chatHistory.scrollHeight;
        return messageDiv;
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>