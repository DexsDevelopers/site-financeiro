<?php
// templates/header.php (Versão Otimizada para Performance)

session_start();

if (empty($_SESSION['user_id']) && empty($_SESSION['user']['id'])) {
    header("Location: /seu_projeto/index.php");
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auto_login.php';
require_once __DIR__ . '/../includes/apply_user_settings.php';
require_once __DIR__ . '/../includes/load_menu_config.php';

// Definir ID do usuário logado
$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'Usuário';
$paginaAtual = basename($_SERVER['PHP_SELF']);

// 🔹 OTIMIZAÇÃO: Cache de notificações para evitar consultas desnecessárias
if (!isset($_SESSION['notificacao_cache_time']) || 
    (time() - $_SESSION['notificacao_cache_time']) > 60) { // Cache por 1 minuto
    
    try {
        $stmtNotif = $pdo->prepare("SELECT notificacao_vista FROM usuarios WHERE id = :id LIMIT 1");
        $stmtNotif->execute([':id' => $userId]);
        $rowNotif = $stmtNotif->fetch(PDO::FETCH_ASSOC);

        if ($rowNotif) {
            $_SESSION['notificacao_vista'] = (int) $rowNotif['notificacao_vista'];
            if (!isset($_SESSION['user'])) {
                $_SESSION['user'] = [];
            }
            $_SESSION['user']['notificacao_vista'] = (int) $rowNotif['notificacao_vista'];
        } else {
            $_SESSION['notificacao_vista'] = 0;
            $_SESSION['user']['notificacao_vista'] = 0;
        }
        
        $_SESSION['notificacao_cache_time'] = time();
        
    } catch (PDOException $e) {
        $_SESSION['notificacao_vista'] = 0;
    }
}

// Variável usada no footer.php
$notificacao_vista = (int) $_SESSION['notificacao_vista'];

// 🔹 OTIMIZAÇÃO: Cache de dias de uso
if (!isset($_SESSION['dias_uso_cache_time']) || 
    (time() - $_SESSION['dias_uso_cache_time']) > 3600) { // Cache por 1 hora
    
    $dias_de_uso = 0;
    try {
        $stmt_data_criacao = $pdo->prepare("SELECT data_criacao FROM usuarios WHERE id = ?");
        $stmt_data_criacao->execute([$userId]);
        $data_criacao_str = $stmt_data_criacao->fetchColumn();
        if ($data_criacao_str) {
            $data_criacao = new DateTime($data_criacao_str);
            $hoje = new DateTime();
            $dias_de_uso = $data_criacao->diff($hoje)->days + 1;
        }
        
        $_SESSION['dias_de_uso_cache'] = $dias_de_uso;
        $_SESSION['dias_uso_cache_time'] = time();
        
    } catch (PDOException $e) {
        $dias_de_uso = 0;
    }
} else {
    $dias_de_uso = $_SESSION['dias_de_uso_cache'] ?? 0;
}

// 🔹 Organização do menu (agora carregada dinamicamente)
// As variáveis $secoesPaginas, $secaoAtiva, $secoesInfo e $paginasInfo 
// são definidas em includes/load_menu_config.php
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel Financeiro</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Sistema completo de gestão financeira pessoal com funcionalidades avançadas">
    <meta name="theme-color" content="#e50914">
    <meta name="msapplication-TileColor" content="#e50914">
    <meta name="msapplication-config" content="/seu_projeto/browserconfig.xml">
    
    <!-- Apple Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Painel Financeiro">
    
    <!-- Manifest -->
    <link rel="manifest" href="/seu_projeto/manifest.json">
    
    <!-- Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/seu_projeto/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/seu_projeto/icons/icon-16x16.png">
    <link rel="apple-touch-icon" href="/seu_projeto/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/seu_projeto/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/seu_projeto/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/seu_projeto/icons/icon-167x167.png">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileImage" content="/seu_projeto/icons/icon-144x144.png">
    <meta name="msapplication-square70x70logo" content="/seu_projeto/icons/icon-70x70.png">
    <meta name="msapplication-square150x150logo" content="/seu_projeto/icons/icon-150x150.png">
    <meta name="msapplication-wide310x150logo" content="/seu_projeto/icons/icon-310x150.png">
    <meta name="msapplication-square310x310logo" content="/seu_projeto/icons/icon-310x310.png">
    <meta name="theme-color" content="#e50914">
    
    <!-- OTIMIZAÇÃO: Preload de recursos críticos -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Roboto+Mono:wght@400;700&display=swap" as="style">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style">
    
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="images/icon-192x192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Driver.js (Tour GUI) - CSS será injetado com fallback por assets/js/onboarding.js -->
    
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
    
    <style>
        :root { 
            --sidebar-width: 280px; 
            --background-color: #0d0d0f; 
            --card-background: rgba(30, 30, 30, 0.65); 
            --sidebar-background: rgba(20, 20, 20, 0.6); 
            --accent-red: #e50914; 
            --accent-red-glow: rgba(229, 9, 20, 0.5); 
            --border-color: rgba(255, 255, 255, 0.1); 
            --text-primary: #f5f5f1; 
            --text-secondary: #aaa; 
            --border-radius: 12px; 
        }
        
        html { height: 100%; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: var(--background-color); 
            color: var(--text-primary); 
            /* OTIMIZAÇÃO: Background estático mais leve */
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(229, 9, 20, 0.08) 0%, transparent 25%), 
                radial-gradient(circle at 80% 90%, rgba(48, 43, 99, 0.08) 0%, transparent 25%);
            background-attachment: fixed;
            min-height: 100%;
            /* PERFORMANCE: Remove animações pesadas */
            will-change: auto;
        }
        
        /* PERFORMANCE: Detecta preferência por movimento reduzido */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        .main-wrapper { 
            display: flex; 
            min-height: 100vh; 
        }
        
        .main-content { 
            width: 100%; 
            padding: 1.5rem; 
        }
        
        .card { 
            background: var(--card-background) !important; 
            /* OTIMIZAÇÃO: Backdrop-filter reduzido */
            backdrop-filter: blur(8px); 
            -webkit-backdrop-filter: blur(8px); 
            border: 1px solid var(--border-color); 
            border-radius: var(--border-radius); 
            /* PERFORMANCE: Transições mais suaves */
            transition: transform 0.2s ease, box-shadow 0.2s ease; 
            position: relative; 
            overflow: hidden; 
            color: var(--text-primary); 
            z-index: 1; 
            /* OTIMIZAÇÃO: Força aceleração por hardware */
            transform: translateZ(0);
            /* PERFORMANCE: Controla repaint */
            contain: layout style paint;
        }
        
        /* OTIMIZAÇÃO: Efeito de hover mais leve */
        .card::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: linear-gradient(135deg, 
                transparent 0%, 
                rgba(229, 9, 20, 0.05) 50%, 
                transparent 100%); 
            opacity: 0; 
            transition: opacity 0.3s ease; 
            z-index: 0; 
            pointer-events: none; 
        }
        
        .card:hover::before { 
            opacity: 1; 
        }
        
        /* NOVA: Animação sutil apenas para cards importantes */
        .card.featured:hover::before {
            animation: gentleGlow 3s ease-in-out infinite;
        }
        
        @keyframes gentleGlow {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }
        
        .card .card-body, .card .card-header { 
            position: relative; 
            z-index: 5; 
            background: transparent !important; 
            color: var(--text-primary); 
            margin: 1px; 
            border-radius: var(--border-radius); 
        }
        
        .card .card-subtitle, .card .text-muted { 
            color: var(--text-secondary) !important; 
        }
        
        .card .btn { 
            z-index: 6; 
        }
        
        .sidebar-nav { 
            background-color: var(--sidebar-background); 
            border-right: 1px solid var(--border-color); 
            /* OTIMIZAÇÃO: Backdrop-filter reduzido */
            backdrop-filter: blur(10px); 
            -webkit-backdrop-filter: blur(10px); 
        }
        
        .sidebar-nav .offcanvas-body { 
            overflow-y: auto; 
            /* PERFORMANCE: Scroll otimizado */
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
        
        .sidebar-nav .nav-link { 
            color: #adb5bd; 
            font-size: 1rem; 
            padding: 0.75rem 2rem; 
            /* OTIMIZAÇÃO: Transições mais rápidas */
            transition: background-color 0.15s ease, color 0.15s ease; 
            border-left: 3px solid transparent; 
            /* PERFORMANCE: Evita reflow */
            contain: layout style;
        }
        
        .sidebar-nav .nav-link:hover { 
            background-color: rgba(255,255,255,0.05); 
            color: white; 
        }
        
        .sidebar-nav .nav-link.active { 
            background-color: rgba(229, 9, 20, 0.1); 
            color: var(--accent-red); 
            font-weight: 600; 
            border-left-color: var(--accent-red); 
        }
        
        .sidebar-nav .user-info { 
            margin-top: auto; 
            padding-top: 1.5rem; 
            border-top: 1px solid var(--border-color); 
        }
        
        @media (min-width: 992px) { 
            .main-wrapper { 
                padding-left: var(--sidebar-width); 
            } 
            .sidebar-nav.offcanvas-lg { 
                transform: none; 
                visibility: visible !important; 
                width: var(--sidebar-width); 
                position: fixed; 
                top: 0; 
                left: 0; 
                height: 100vh; 
            } 
            .navbar-toggler-custom { 
                display: none; 
            } 
        }
        
        .sidebar-nav .accordion-button { 
            color: #adb5bd; 
            font-size: 1.1rem; 
            padding: 0.8rem 1.5rem; 
            font-weight: 600; 
            /* OTIMIZAÇÃO: Transição mais rápida */
            transition: all 0.15s ease;
        }
        
        .sidebar-nav .accordion-button:not(.collapsed) { 
            background-color: rgba(255,255,255,0.1); 
            color: white; 
            box-shadow: none; 
        }
        
        .sidebar-nav .accordion-button:focus { 
            box-shadow: none; 
        }
        
        .sidebar-nav .accordion-button::after { 
            filter: brightness(0) invert(1); 
        }
        
        .sidebar-nav .accordion-body { 
            padding: 0; 
        }
        
        .sidebar-nav .accordion-item { 
            border: none; 
        }
        
        .sidebar-nav .accordion { 
            --bs-accordion-bg: transparent; 
        }
        
        /* OTIMIZAÇÃO: Classe para ocultar saldo mais eficiente */
        body.saldo-oculto .valor-sensivel { 
            filter: blur(6px); 
            pointer-events: none; 
            transition: filter 0.2s ease; 
        }
        
        .valor-sensivel { 
            transition: filter 0.2s ease; 
        }
        
        #btn-toggle-saldo { 
            cursor: pointer; 
        }
        
        .chart-container { 
            position: relative; 
            height: 350px; 
            width: 100%; 
            /* PERFORMANCE: Otimização para gráficos */
            contain: layout style paint;
        }
        
        .card-header { 
            position: relative; 
            z-index: 2; 
        }
        
        .streak-bar { 
            background-color: rgba(0,0,0,0.2); 
            padding: 0.5rem 1.5rem; 
            color: var(--text-secondary); 
            text-align: center; 
            border-bottom: 1px solid var(--border-color); 
        }
        
        .streak-bar strong { 
            color: #ffc107; 
        }
        
        /* OTIMIZAÇÃO: Scrollbar mais leve e performática */
        ::-webkit-scrollbar { 
            width: 6px; 
            height: 6px; 
        } 
        
        ::-webkit-scrollbar-track { 
            background: rgba(0, 0, 0, 0.1); 
            border-radius: 10px; 
        } 
        
        ::-webkit-scrollbar-thumb { 
            background: #444; 
            border-radius: 10px; 
            transition: background 0.2s ease;
        } 
        
        ::-webkit-scrollbar-thumb:hover { 
            background: #555; 
        }
        
        * { 
            scrollbar-width: thin; 
            scrollbar-color: #444 rgba(0, 0, 0, 0.1); 
        }
        
        /* PERFORMANCE: Otimizações gerais */
        .accordion-collapse {
            contain: layout style;
        }
        
        .nav-link {
            contain: layout style;
        }
        
        /* OTIMIZAÇÃO: Remove animações em dispositivos touch */
        .touch-device .card:hover::before {
            animation: none !important;
        }
        
        .touch-device .card::before {
            transition: none !important;
        }
        
        /* Layouts Flexíveis */
        .layout-compacto .sidebar-nav {
            width: 200px !important;
        }
        .layout-compacto .main-content {
            margin-left: 200px !important;
        }
        .layout-minimalista .sidebar-nav {
            display: none;
        }
        .layout-minimalista .main-content {
            margin-left: 0 !important;
        }
        .layout-dashboard .main-content {
            padding: 1rem;
        }
        .layout-mobile .sidebar-nav {
            width: 100%;
            position: fixed;
            top: 0;
            left: -100%;
            z-index: 1050;
            transition: left 0.3s ease;
        }
        .layout-mobile .sidebar-nav.show {
            left: 0;
        }
        
        /* Densidades */
        .densidade-compacta .card {
            padding: 0.75rem;
        }
        .densidade-compacta .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        .densidade-confortavel .card {
            padding: 2rem;
        }
        .densidade-confortavel .btn {
            padding: 0.75rem 1.5rem;
            font-size: 1.125rem;
        }
        
        /* Temas */
        .theme-dark {
            --bs-body-bg: #111111;
            --bs-body-color: #f5f5f1;
        }
        .high-contrast {
            --accent-red: #ff0000;
            --bs-body-bg: #000000;
            --bs-body-color: #ffffff;
        }
        
        /* Tamanhos de fonte */
        .font-pequena {
            font-size: 0.875rem;
        }
        .font-normal {
            font-size: 1rem;
        }
        .font-grande {
            font-size: 1.125rem;
        }
        
        /* Animações */
        .animations-disabled * {
            animation: none !important;
            transition: none !important;
        }
        .reduce-motion * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
        
        /* Aplicar cores do tema personalizado */
        <?php if (isset($_SESSION['cores_tema'])): ?>
        :root {
            --accent-red: <?php echo $_SESSION['cores_tema']['primary'] ?? '#e50914'; ?>;
            --dark-bg: <?php echo $_SESSION['cores_tema']['background'] ?? '#111111'; ?>;
            --text-light: <?php echo $_SESSION['cores_tema']['text'] ?? '#f5f5f1'; ?>;
            --success-color: <?php echo $_SESSION['cores_tema']['success'] ?? '#00b894'; ?>;
            --info-color: <?php echo $_SESSION['cores_tema']['info'] ?? '#0984e3'; ?>;
            --warning-color: <?php echo $_SESSION['cores_tema']['warning'] ?? '#f9a826'; ?>;
        }
        <?php endif; ?>
        
        /* PWA Install Prompt */
        .install-prompt {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: var(--card-background);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            z-index: 1000;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .install-prompt.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .install-prompt-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .install-prompt-icon {
            font-size: 2rem;
            color: var(--accent-red);
        }
        
        .install-prompt-text {
            flex-grow: 1;
        }
        
        .install-prompt-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .install-prompt-desc {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .install-prompt-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .install-btn {
            background: var(--accent-red);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .install-btn:hover {
            background: #c4080f;
            transform: translateY(-1px);
        }
        
        .install-dismiss {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .install-dismiss:hover {
            background: var(--border-color);
            color: white;
        }
        
        @media (max-width: 576px) {
            .install-prompt {
                left: 10px;
                right: 10px;
                bottom: 10px;
            }
            
            .install-prompt-content {
                flex-direction: column;
                text-align: center;
            }
            
            .install-prompt-buttons {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="<?php echo $_SESSION['body_classes'] ?? ''; ?>">
<div class="main-wrapper">
    <aside class="offcanvas-lg offcanvas-start sidebar-nav d-flex flex-column p-3" tabindex="-1" id="sidebarMenu">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><i class="bi bi-shield-shaded me-2"></i>Painel</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <ul class="nav flex-column mb-3">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($paginaAtual == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="bi bi-grid-1x2-fill me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($paginaAtual == 'analista_ia.php') ? 'active' : ''; ?>" href="analista_ia.php">
                        <i class="bi bi-stars me-2"></i> Analista Pessoal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="https://helmer.netlify.app/" target="_blank">
                        <i class="bi bi-people-fill me-2"></i> Minha Equipe
                    </a>
                </li>
            </ul>
            
            <!-- acordeão menus -->
            <div class="accordion accordion-flush" id="menuAccordion">
                <?php foreach ($secoesPaginas as $secao => $paginas): ?>
                    <?php if (!empty($paginas)): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <?php if ($secao === 'academy'): ?>
                                    <button class="accordion-button <?php echo ($secaoAtiva !== $secao) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo ucfirst($secao); ?>">
                                        <i class="bi <?php echo $secoesInfo[$secao]['icone']; ?> me-2" style="color: <?php echo $secoesInfo[$secao]['cor']; ?>;"></i>
                                        <span class="me-2"><?php echo $secoesInfo[$secao]['nome']; ?></span>
                                        <a href="https://helmer-mbs.site/" target="_blank" rel="noopener" class="ms-auto btn btn-sm btn-outline-light" onclick="event.stopPropagation();">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </button>
                                <?php else: ?>
                                    <button class="accordion-button <?php echo ($secaoAtiva !== $secao) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo ucfirst($secao); ?>">
                                        <i class="bi <?php echo $secoesInfo[$secao]['icone']; ?> me-2" style="color: <?php echo $secoesInfo[$secao]['cor']; ?>;"></i>
                                        <?php echo $secoesInfo[$secao]['nome']; ?>
                                    </button>
                                <?php endif; ?>
                            </h2>
                            <div id="collapse<?php echo ucfirst($secao); ?>" class="accordion-collapse collapse <?php echo ($secaoAtiva === $secao) ? 'show' : ''; ?>" data-bs-parent="#menuAccordion">
                                <div class="accordion-body">
                                    <ul class="nav flex-column">
                                        <?php foreach ($paginas as $pagina): ?>
                                            <?php if (isset($paginasInfo[$pagina])): ?>
                                                <li class="nav-item">
                                                    <a class="nav-link <?php echo ($paginaAtual == $pagina) ? 'active' : ''; ?>" href="<?php echo $pagina; ?>">
                                                        <i class="bi <?php echo $paginasInfo[$pagina]['icone']; ?> me-2"></i> <?php echo $paginasInfo[$pagina]['nome']; ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="user-info text-white-50">
                <div>Logado como:</div>
                <strong class="text-white"><?php echo htmlspecialchars($userName); ?></strong>
                <div class="d-grid mt-3">
                    <a class="btn btn-sm btn-outline-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- PWA Install Prompt -->
    <div class="install-prompt" id="installPrompt">
        <div class="install-prompt-content">
            <div class="install-prompt-icon">
                <i class="bi bi-download"></i>
            </div>
            <div class="install-prompt-text">
                <div class="install-prompt-title">Instalar App</div>
                <div class="install-prompt-desc">Adicione o Painel Financeiro à sua tela inicial para acesso rápido</div>
            </div>
            <div class="install-prompt-buttons">
                <button class="install-btn" id="installBtn">Instalar</button>
                <button class="install-dismiss" id="dismissBtn">Agora não</button>
            </div>
        </div>
    </div>

    <div class="main-content">
        <nav class="navbar d-lg-none navbar-dark bg-dark mb-3">
            <div class="container-fluid">
                <button class="btn btn-outline-light navbar-toggler-custom" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                    <i class="bi bi-list"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">Painel Financeiro</a>
            </div>
        </nav>

        <?php if (isset($dias_de_uso) && $dias_de_uso > 0): ?>
        <div class="streak-bar mb-4">
            <i class="bi bi-award-fill text-warning"></i> 
            A <strong><?php echo $dias_de_uso; ?></strong> dia<?php echo ($dias_de_uso > 1) ? 's' : ''; ?> otimizando sua vida financeira.
        </div>
        <?php endif; ?>

<!-- PERFORMANCE: Script de otimização para dispositivos -->
<script>
(function() {
    // Remove animações em dispositivos com preferência por movimento reduzido
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.documentElement.style.setProperty('--animation-duration', '0ms');
    }

    // Detecta dispositivos touch e adiciona classe para otimização
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
        document.body.classList.add('touch-device');
    }

    // Otimização: Lazy loading para elementos que não estão na viewport
    const observerOptions = {
        root: null,
        rootMargin: '50px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, observerOptions);

    // Observa elementos que podem causar reflow
    document.addEventListener('DOMContentLoaded', () => {
        const heavyElements = document.querySelectorAll('.card, .accordion-item');
        heavyElements.forEach(el => observer.observe(el));
    });
})();
</script>

<!-- JavaScript Personalizado -->
<script src="assets/js/dashboard.js"></script>