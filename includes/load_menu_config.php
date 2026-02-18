<?php
// includes/load_menu_config.php - Carregar configurações do menu personalizado

require_once 'cache_manager.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    return;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

// Carregar configurações do menu
$menu_config = $cache->getUserCache($userId, 'menu_personalizado');
if (!$menu_config) {
    // Buscar do banco se não estiver no cache
    try {
        $stmt = $pdo->prepare("SELECT configuracao FROM config_menu_personalizado WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $menu_config = json_decode($result['configuracao'], true);
            $cache->setUserCache($userId, 'menu_personalizado', $menu_config, 3600);
        }
    } catch (PDOException $e) {
        $menu_config = null;
    }
}

// Se não houver configuração personalizada, usar a padrão
if (!$menu_config) {
    $menu_config = [
        'secoes_visiveis' => [
            'academy' => true,
            'financeiro' => true,
            'produtividade' => true,
            'personalizacao' => true,
            'gestao_empresas' => true,
            'sistema' => true
        ],
        'paginas_visiveis' => [
            'academy' => ['cursos.php', 'treinos.php', 'rotina_academia.php', 'alimentacao.php', 'notas_cursos.php'],
            'financeiro' => ['compras_futuras.php', 'relatorios.php', 'extrato_completo.php', 'recorrentes.php', 'orcamento.php', 'categorias.php', 'regras_categorizacao.php', 'alertas_inteligentes.php'],
            'produtividade' => ['tarefas.php', 'calendario.php', 'pomodoro.php'],
            'personalizacao' => ['temas_customizaveis.php', 'layouts_flexiveis.php', 'preferencias_avancadas.php', 'personalizar_menu.php'],
            'gestao_empresas' => ['gestao_empresas.php'],
            'sistema' => ['perfil.php', 'contas.php', 'whatsapp_admin.php', 'whatsapp_qr.php', 'integracoes_google.php', 'debug_google_integration.php', 'verificar_apis_google.php', 'debug_ia.php', 'debug_ia_whatsapp.php']
        ],
        'ordem_secoes' => ['academy', 'financeiro', 'produtividade', 'gestao_empresas', 'personalizacao', 'sistema'],
        'ordem_paginas' => [
            'academy' => ['cursos.php', 'treinos.php', 'rotina_academia.php', 'alimentacao.php', 'notas_cursos.php'],
            'financeiro' => ['compras_futuras.php', 'relatorios.php', 'extrato_completo.php', 'recorrentes.php', 'orcamento.php', 'categorias.php', 'regras_categorizacao.php', 'alertas_inteligentes.php'],
            'produtividade' => ['tarefas.php', 'calendario.php', 'pomodoro.php'],
            'personalizacao' => ['temas_customizaveis.php', 'layouts_flexiveis.php', 'preferencias_avancadas.php', 'personalizar_menu.php'],
            'gestao_empresas' => ['gestao_empresas.php'],
            'sistema' => ['perfil.php', 'contas.php', 'whatsapp_admin.php', 'whatsapp_qr.php', 'integracoes_google.php', 'debug_google_integration.php', 'verificar_apis_google.php', 'debug_ia.php', 'debug_ia_whatsapp.php']
        ]
    ];
}

// Garante que 'contas.php' esteja sempre visível na seção Sistema (mesmo com config antiga)
if (!isset($menu_config['paginas_visiveis']['sistema'])) {
    $menu_config['paginas_visiveis']['sistema'] = [];
}
if (!in_array('contas.php', $menu_config['paginas_visiveis']['sistema'], true)) {
    $menu_config['paginas_visiveis']['sistema'][] = 'contas.php';
}
if (!isset($menu_config['ordem_paginas']['sistema'])) {
    $menu_config['ordem_paginas']['sistema'] = [];
}
if (!in_array('contas.php', $menu_config['ordem_paginas']['sistema'], true)) {
    $menu_config['ordem_paginas']['sistema'][] = 'contas.php';
}

// Garante que 'whatsapp_admin.php' apareça em Sistema
if (!in_array('whatsapp_admin.php', $menu_config['paginas_visiveis']['sistema'], true)) {
    $menu_config['paginas_visiveis']['sistema'][] = 'whatsapp_admin.php';
}
if (!in_array('whatsapp_admin.php', $menu_config['ordem_paginas']['sistema'], true)) {
    $menu_config['ordem_paginas']['sistema'][] = 'whatsapp_admin.php';
}

// Garante que 'integracoes_google.php' apareça em Sistema
if (!in_array('integracoes_google.php', $menu_config['paginas_visiveis']['sistema'], true)) {
    $menu_config['paginas_visiveis']['sistema'][] = 'integracoes_google.php';
}
if (!in_array('integracoes_google.php', $menu_config['ordem_paginas']['sistema'], true)) {
    $menu_config['ordem_paginas']['sistema'][] = 'integracoes_google.php';
}

// Garante que 'debug_google_integration.php' apareça em Sistema
if (!in_array('debug_google_integration.php', $menu_config['paginas_visiveis']['sistema'], true)) {
    $menu_config['paginas_visiveis']['sistema'][] = 'debug_google_integration.php';
}
if (!in_array('debug_google_integration.php', $menu_config['ordem_paginas']['sistema'], true)) {
    $menu_config['ordem_paginas']['sistema'][] = 'debug_google_integration.php';
}

// Garante que 'debug_ia.php' apareça em Sistema
if (!in_array('debug_ia.php', $menu_config['paginas_visiveis']['sistema'], true)) {
    $menu_config['paginas_visiveis']['sistema'][] = 'debug_ia.php';
}
if (!in_array('debug_ia.php', $menu_config['ordem_paginas']['sistema'], true)) {
    $menu_config['ordem_paginas']['sistema'][] = 'debug_ia.php';
}

// Garante que 'debug_ia_whatsapp.php' apareça em Sistema
if (!in_array('debug_ia_whatsapp.php', $menu_config['paginas_visiveis']['sistema'], true)) {
    $menu_config['paginas_visiveis']['sistema'][] = 'debug_ia_whatsapp.php';
}
if (!in_array('debug_ia_whatsapp.php', $menu_config['ordem_paginas']['sistema'], true)) {
    $menu_config['ordem_paginas']['sistema'][] = 'debug_ia_whatsapp.php';
}

// Exibir páginas administrativas apenas para administradores
try {
    $stmtTipo = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ? LIMIT 1");
    $stmtTipo->execute([$userId]);
    $tipo = $stmtTipo->fetchColumn();

    $paginas_restritas = [
        'whatsapp_admin.php', 
        'whatsapp_qr.php',
        'integracoes_google.php', 
        'debug_google_integration.php', 
        'verificar_apis_google.php', 
        'debug_ia.php', 
        'debug_ia_whatsapp.php'
    ];

    if ($tipo !== 'admin') {
        // Remove páginas restritas da lista visível e da ordem
        $menu_config['paginas_visiveis']['sistema'] = array_values(array_filter(
            $menu_config['paginas_visiveis']['sistema'],
            fn($p) => !in_array($p, $paginas_restritas)
        ));
        $menu_config['ordem_paginas']['sistema'] = array_values(array_filter(
            $menu_config['ordem_paginas']['sistema'],
            fn($p) => !in_array($p, $paginas_restritas)
        ));
    }
} catch (Throwable $e) {
    // Em erro, por segurança, oculta as principais páginas admin
    $paginas_restritas_fallback = ['whatsapp_admin.php', 'whatsapp_qr.php'];
    
    $menu_config['paginas_visiveis']['sistema'] = array_values(array_filter(
        $menu_config['paginas_visiveis']['sistema'],
        fn($p) => !in_array($p, $paginas_restritas_fallback)
    ));
    $menu_config['ordem_paginas']['sistema'] = array_values(array_filter(
        $menu_config['ordem_paginas']['sistema'],
        fn($p) => !in_array($p, $paginas_restritas_fallback)
    ));
}

// Salvar na sessão para uso no header
$_SESSION['menu_personalizado'] = $menu_config;

// Definir seções e páginas baseadas na configuração personalizada
$secoesPaginas = [];
foreach ($menu_config['ordem_secoes'] as $secao) {
    if (isset($menu_config['secoes_visiveis'][$secao]) && $menu_config['secoes_visiveis'][$secao]) {
        $secoesPaginas[$secao] = $menu_config['paginas_visiveis'][$secao] ?? [];
    }
}

// Definir seção ativa baseada na página atual
$paginaAtual = basename($_SERVER['PHP_SELF']);
$secaoAtiva = '';
foreach ($secoesPaginas as $secao => $paginas) {
    if (in_array($paginaAtual, $paginas)) {
        $secaoAtiva = $secao;
        break;
    }
}

// Definir informações das seções
$secoesInfo = [
    'academy' => [
        'nome' => 'Academy',
        'icone' => 'bi-mortarboard',
        'cor' => '#e50914'
    ],
    'financeiro' => [
        'nome' => 'Financeiro',
        'icone' => 'bi-wallet2',
        'cor' => '#00b894'
    ],
    'produtividade' => [
        'nome' => 'Produtividade',
        'icone' => 'bi-speedometer2',
        'cor' => '#0984e3'
    ],
    'personalizacao' => [
        'nome' => 'Personalização',
        'icone' => 'bi-gear',
        'cor' => '#f9a826'
    ],
    'gestao_empresas' => [
        'nome' => 'Gestão de Empresas',
        'icone' => 'bi-building',
        'cor' => '#6c5ce7'
    ],
    'sistema' => [
        'nome' => 'Sistema',
        'icone' => 'bi-shield-shaded',
        'cor' => '#6c757d'
    ]
];

// Definir informações das páginas
$paginasInfo = [
    'cursos.php' => ['nome' => 'Meus Cursos', 'icone' => 'bi-book'],
    'treinos.php' => ['nome' => 'Registro de Treinos', 'icone' => 'bi-dumbbell'],
    'rotina_academia.php' => ['nome' => 'Rotina', 'icone' => 'bi-calendar-check'],
    'alimentacao.php' => ['nome' => 'Alimentação', 'icone' => 'bi-apple'],
    'notas_cursos.php' => ['nome' => 'Notas e Anotações', 'icone' => 'bi-journal-text'],
    'compras_futuras.php' => ['nome' => 'Metas de Compras', 'icone' => 'bi-bag-check'],
    'relatorios.php' => ['nome' => 'Relatórios', 'icone' => 'bi-graph-up'],
    'extrato_completo.php' => ['nome' => 'Extrato', 'icone' => 'bi-receipt'],
    'recorrentes.php' => ['nome' => 'Recorrentes', 'icone' => 'bi-arrow-repeat'],
    'orcamento.php' => ['nome' => 'Orçamentos', 'icone' => 'bi-calculator'],
    'categorias.php' => ['nome' => 'Categorias', 'icone' => 'bi-tags'],
    'regras_categorizacao.php' => ['nome' => 'Regras de Categorização', 'icone' => 'bi-robot'],
    'alertas_inteligentes.php' => ['nome' => 'Alertas Inteligentes', 'icone' => 'bi-bell-fill'],
    'tarefas.php' => ['nome' => 'Rotina de Tarefas', 'icone' => 'bi-check2-square'],
    'calendario.php' => ['nome' => 'Calendário', 'icone' => 'bi-calendar3'],
    'pomodoro.php' => ['nome' => 'Pomodoro Timer', 'icone' => 'bi-stopwatch'],
    'temas_customizaveis.php' => ['nome' => 'Temas Customizáveis', 'icone' => 'bi-palette'],
    'layouts_flexiveis.php' => ['nome' => 'Layouts Flexíveis', 'icone' => 'bi-layout-window'],
    'preferencias_avancadas.php' => ['nome' => 'Preferências Avançadas', 'icone' => 'bi-gear-fill'],
    'personalizar_menu.php' => ['nome' => 'Personalizar Menu', 'icone' => 'bi-list-ul'],
    'perfil.php' => ['nome' => 'Meu Perfil', 'icone' => 'bi-person-circle'],
    'contas.php' => ['nome' => 'Contas', 'icone' => 'bi-wallet2'],
    'whatsapp_admin.php' => ['nome' => 'Avisos WhatsApp', 'icone' => 'bi-whatsapp'],
    'whatsapp_qr.php' => ['nome' => 'QR Code WhatsApp', 'icone' => 'bi-qr-code'],
    'integracoes_google.php' => ['nome' => 'Integrações Google', 'icone' => 'bi-google'],
    'debug_google_integration.php' => ['nome' => 'Debug Integração Google', 'icone' => 'bi-bug'],
    'verificar_apis_google.php' => ['nome' => 'Verificar APIs Google', 'icone' => 'bi-shield-check'],
    'debug_ia.php' => ['nome' => 'Debug Assistente Orion', 'icone' => 'bi-robot'],
    'debug_ia_whatsapp.php' => ['nome' => 'Diagnóstico IA WhatsApp', 'icone' => 'bi-whatsapp'],
    'gestao_empresas.php' => ['nome' => 'Dashboard Business', 'icone' => 'bi-speedometer2'],
];
?>
