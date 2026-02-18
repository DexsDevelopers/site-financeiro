<?php
// analista_ia.php - Orion Intelligence Hub (Redesign Completo)
session_start();

if (empty($_SESSION['user_id']) && empty($_SESSION['user']['id'])) {
    header("Location: /seu_projeto/index.php");
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';
$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'Usuário';
$primeiroNome = explode(' ', $userName)[0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Orion AI - Analista Pessoal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #e50914;
            --primary-dark: #b20710;
            --bg-dark: #0a0a0b;
            --bg-card: #1a1a1d;
            --bg-input: #252528;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a8;
            --border: rgba(255, 255, 255, 0.08);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            overflow: hidden;
            height: 100vh;
            width: 100vw;
        }

        /* ===== MENU FLUTUANTE ===== */
        .floating-menu-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 16px;
            color: white;
            font-size: 24px;
            cursor: pointer;
            z-index: 10000;
            box-shadow: 0 4px 20px rgba(229, 9, 20, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .floating-menu-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 28px rgba(229, 9, 20, 0.6);
        }

        .floating-menu-btn:active {
            transform: scale(0.95);
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            top: 0;
            left: -320px;
            width: 320px;
            height: 100vh;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            z-index: 9999;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.5);
        }

        .sidebar.open {
            left: 0;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .sidebar-close {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            transition: color 0.2s;
        }

        .sidebar-close:hover {
            color: var(--text-primary);
        }

        .sidebar-nav {
            padding: 16px 0;
        }

        .nav-item {
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            font-size: 15px;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        .nav-item.active {
            color: var(--primary);
            background: rgba(229, 9, 20, 0.1);
            border-left-color: var(--primary);
        }

        .nav-item i {
            font-size: 20px;
            width: 24px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-name {
            font-size: 14px;
            font-weight: 500;
        }

        .btn-logout {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
        }

        /* ===== OVERLAY ===== */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            backdrop-filter: blur(4px);
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* ===== CHAT CONTAINER ===== */
        .chat-wrapper {
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .chat-header {
            padding: 20px 24px;
            padding-left: 90px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .chat-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ai-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.2), rgba(178, 7, 16, 0.2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
        }

        .chat-title-text h1 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .chat-title-text p {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .btn-clear {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-clear:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
        }

        /* ===== MESSAGES AREA ===== */
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .message {
            display: flex;
            gap: 12px;
            max-width: 80%;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
        }

        .message.ai .message-avatar {
            background: rgba(229, 9, 20, 0.15);
            color: var(--primary);
        }

        .message.user .message-avatar {
            background: var(--bg-input);
            color: var(--text-primary);
        }

        .message-content {
            background: transparent;
            padding: 12px 0;
            border-radius: 12px;
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-primary);
        }

        .message.user .message-content {
            background: var(--bg-input);
            padding: 12px 16px;
            border-radius: 16px;
            border-bottom-right-radius: 4px;
        }

        .message-content p {
            margin: 0 0 12px 0;
        }

        .message-content p:last-child {
            margin-bottom: 0;
        }

        .message-content strong {
            font-weight: 600;
        }

        .message-content code {
            background: rgba(255, 255, 255, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 16px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--text-secondary);
            animation: typing 1.4s infinite;
        }

        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }

        /* ===== INPUT AREA ===== */
        .input-container {
            padding: 20px 24px;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .suggestions {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            overflow-x: auto;
            padding-bottom: 8px;
        }

        .suggestions::-webkit-scrollbar {
            height: 4px;
        }

        .suggestions::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 2px;
        }

        .suggestion-chip {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }

        .suggestion-chip:hover {
            background: rgba(229, 9, 20, 0.1);
            border-color: var(--primary);
            color: var(--primary);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: flex-end;
            gap: 12px;
        }

        .input-field {
            flex: 1;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            color: var(--text-primary);
            font-size: 15px;
            font-family: inherit;
            resize: none;
            max-height: 120px;
            transition: all 0.2s;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.1);
        }

        .input-field::placeholder {
            color: var(--text-secondary);
        }

        .btn-send {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .btn-send:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(229, 9, 20, 0.4);
        }

        .btn-send:active {
            transform: scale(0.95);
        }

        .btn-send:disabled {
            background: var(--bg-input);
            cursor: not-allowed;
            transform: none;
        }

        /* ===== MOBILE OPTIMIZATIONS ===== */
        @media (max-width: 768px) {
            .chat-header {
                padding: 16px 20px;
                padding-left: 80px;
            }

            .chat-title-text h1 {
                font-size: 16px;
            }

            .messages-container {
                padding: 16px;
            }

            .message {
                max-width: 90%;
            }

            .input-container {
                padding: 16px;
            }

            .sidebar {
                width: 280px;
                left: -280px;
            }
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body>
    <!-- Floating Menu Button -->
    <button class="floating-menu-btn" id="menuBtn">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="bi bi-shield-shaded"></i> Menu</h3>
            <button class="sidebar-close" id="sidebarClose">
                <i class="bi bi-x"></i>
            </button>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>
            <a href="analista_ia.php" class="nav-item active">
                <i class="bi bi-stars"></i>
                <span>Analista IA</span>
            </a>
            <a href="https://helmer.netlify.app/" target="_blank" class="nav-item">
                <i class="bi bi-people-fill"></i>
                <span>Minha Equipe</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($primeiroNome, 0, 1)); ?></div>
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
            </div>
            <button class="btn-logout" onclick="window.location.href='logout.php'">
                <i class="bi bi-box-arrow-right"></i> Sair
            </button>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Chat Container -->
    <div class="chat-wrapper">
        <!-- Header -->
        <div class="chat-header">
            <div class="chat-title">
                <div class="ai-icon">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="chat-title-text">
                    <h1>Orion Engine</h1>
                    <p>Seu analista pessoal local</p>
                </div>
            </div>
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>Online</span>
            </div>
        </div>

        <!-- Messages -->
        <div class="messages-container" id="messagesContainer">
            <div class="message ai">
                <div class="message-avatar">
                    <i class="bi bi-stars"></i>
                </div>
                <div class="message-content">
                    <p>Olá, <strong><?php echo htmlspecialchars($primeiroNome); ?></strong>! 👋</p>
                    <p>Sou o <strong>Orion</strong>, seu assistente financeiro inteligente. Posso te ajudar com:</p>
                    <p>
                        💰 <strong>Finanças:</strong> "Fiz uma venda de 90 reais" ou "Gastei 50 em lanche"<br>
                        📋 <strong>Tarefas:</strong> "Adicionar tarefa pagar internet" ou "Concluir tarefa pagar luz"<br>
                        🎯 <strong>Metas:</strong> "Criar meta de economizar 1000 reais"<br>
                        📊 <strong>Análises:</strong> "Quanto gastei este mês?" ou "Meu saldo"
                    </p>
                    <p><em>Digite "ajuda" para ver todos os comandos disponíveis!</em></p>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="input-container">
            <div class="suggestions">
                <div class="suggestion-chip" data-text="Resumo financeiro deste mês">💰 Resumo do mês</div>
                <div class="suggestion-chip" data-text="Tenho tarefas pendentes?">📅 Minhas Tarefas</div>
                <div class="suggestion-chip" data-text="Como economizar mais?">💡 Dicas de economia</div>
                <div class="suggestion-chip" data-text="Quanto gastei este mês?">📉 Maior despesa</div>
            </div>
            <div class="input-wrapper">
                <textarea 
                    class="input-field" 
                    id="userInput" 
                    placeholder="Digite sua pergunta..."
                    rows="1"
                ></textarea>
                <button class="btn-send" id="sendBtn">
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        // Menu Toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('active');
        }

        menuBtn.addEventListener('click', toggleSidebar);
        sidebarClose.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Chat Functionality
        const messagesContainer = document.getElementById('messagesContainer');
        const userInput = document.getElementById('userInput');
        const sendBtn = document.getElementById('sendBtn');

        // Auto-resize textarea
        userInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Scroll to bottom
        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Add message
        function addMessage(type, content, isTyping = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            
            const avatar = type === 'ai' 
                ? '<i class="bi bi-stars"></i>' 
                : '<i class="bi bi-person"></i>';
            
            if (isTyping) {
                messageDiv.innerHTML = `
                    <div class="message-avatar">${avatar}</div>
                    <div class="message-content">
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                `;
                messageDiv.id = 'typing-indicator';
            } else {
                messageDiv.innerHTML = `
                    <div class="message-avatar">${avatar}</div>
                    <div class="message-content">${content}</div>
                `;
            }
            
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
            return messageDiv;
        }

        // Send message
        async function sendMessage() {
            const text = userInput.value.trim();
            if (!text) return;

            // Add user message
            addMessage('user', `<p>${text.replace(/\n/g, '<br>')}</p>`);
            userInput.value = '';
            userInput.style.height = 'auto';

            // Show typing indicator
            sendBtn.disabled = true;
            const typingIndicator = addMessage('ai', '', true);

            try {
                const response = await fetch('processar_analise_ia.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pergunta: text })
                });

                const data = await response.json();
                typingIndicator.remove();

                if (data.success) {
                    const html = marked.parse(data.resposta);
                    addMessage('ai', html);
                } else {
                    addMessage('ai', `<p style="color: #ef4444;"><i class="bi bi-exclamation-triangle"></i> ${data.message || 'Erro ao processar sua solicitação.'}</p>`);
                }
            } catch (error) {
                typingIndicator.remove();
                addMessage('ai', '<p style="color: #ef4444;"><i class="bi bi-wifi-off"></i> Erro de conexão com o servidor.</p>');
                console.error(error);
            } finally {
                sendBtn.disabled = false;
                userInput.focus();
            }
        }

        // Event listeners
        sendBtn.addEventListener('click', sendMessage);
        userInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Suggestion chips
        document.querySelectorAll('.suggestion-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                userInput.value = chip.dataset.text;
                userInput.focus();
                sendMessage();
            });
        });
    </script>
</body>
</html>