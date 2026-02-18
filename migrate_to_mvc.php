<?php
// migrate_to_mvc.php - Script de migração para MVC

require_once 'src/autoloader.php';

echo "🚀 Iniciando migração para estrutura MVC...\n\n";

// 1. Criar diretórios necessários
$directories = [
    'src/Controllers',
    'src/Models', 
    'src/Services',
    'src/Utils',
    'src/Middleware',
    'config',
    'cache',
    'logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ Diretório criado: {$dir}\n";
    } else {
        echo "⚠️ Diretório já existe: {$dir}\n";
    }
}

// 2. Criar arquivo .env se não existir
if (!file_exists('.env')) {
    if (file_exists('env.example')) {
        copy('env.example', '.env');
        echo "✅ Arquivo .env criado a partir do exemplo\n";
    } else {
        echo "⚠️ Arquivo env.example não encontrado\n";
    }
} else {
    echo "⚠️ Arquivo .env já existe\n";
}

// 3. Criar arquivo .gitignore
$gitignore = [
    '.env',
    'cache/',
    'logs/',
    '*.log',
    'vendor/',
    'node_modules/',
    '.DS_Store',
    'Thumbs.db'
];

if (!file_exists('.gitignore')) {
    file_put_contents('.gitignore', implode("\n", $gitignore));
    echo "✅ Arquivo .gitignore criado\n";
} else {
    echo "⚠️ Arquivo .gitignore já existe\n";
}

// 4. Criar arquivo de configuração
$config = [
    'app' => [
        'name' => 'Painel Financeiro',
        'version' => '2.0.0',
        'debug' => false,
        'timezone' => 'America/Sao_Paulo'
    ],
    'cache' => [
        'driver' => 'file',
        'ttl' => 3600,
        'directory' => 'cache/'
    ],
    'session' => [
        'lifetime' => 7200,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]
];

if (!file_exists('config/app.php')) {
    file_put_contents('config/app.php', '<?php return ' . var_export($config, true) . ';');
    echo "✅ Arquivo de configuração criado\n";
} else {
    echo "⚠️ Arquivo de configuração já existe\n";
}

// 5. Criar arquivo de rotas
$routes = [
    'GET' => [
        '/' => 'DashboardController@index',
        '/dashboard' => 'DashboardController@index',
        '/api/dashboard' => 'DashboardController@getDashboardData',
        '/api/charts' => 'DashboardController@getChartData',
        '/api/stats' => 'DashboardController@getStats'
    ],
    'POST' => [
        '/api/tasks/update' => 'DashboardController@updateTaskStatus'
    ]
];

if (!file_exists('config/routes.php')) {
    file_put_contents('config/routes.php', '<?php return ' . var_export($routes, true) . ';');
    echo "✅ Arquivo de rotas criado\n";
} else {
    echo "⚠️ Arquivo de rotas já existe\n";
}

// 6. Criar arquivo de middleware
$middleware = [
    'auth' => 'AuthMiddleware',
    'csrf' => 'CSRFMiddleware',
    'rate_limit' => 'RateLimitMiddleware'
];

if (!file_exists('config/middleware.php')) {
    file_put_contents('config/middleware.php', '<?php return ' . var_export($middleware, true) . ';');
    echo "✅ Arquivo de middleware criado\n";
} else {
    echo "⚠️ Arquivo de middleware já existe\n";
}

// 7. Criar arquivo de logs
if (!file_exists('logs/.gitkeep')) {
    file_put_contents('logs/.gitkeep', '');
    echo "✅ Diretório de logs criado\n";
} else {
    echo "⚠️ Diretório de logs já existe\n";
}

// 8. Criar arquivo de cache
if (!file_exists('cache/.gitkeep')) {
    file_put_contents('cache/.gitkeep', '');
    echo "✅ Diretório de cache criado\n";
} else {
    echo "⚠️ Diretório de cache já existe\n";
}

echo "\n🎉 Migração concluída!\n\n";
echo "📋 Próximos passos:\n";
echo "1. Configure o arquivo .env com suas credenciais\n";
echo "2. Teste a nova estrutura\n";
echo "3. Migre gradualmente os arquivos existentes\n";
echo "4. Implemente os middlewares de segurança\n";
echo "5. Configure o sistema de cache\n\n";

echo "⚠️ IMPORTANTE: Faça backup antes de continuar!\n";
echo "💡 Dica: Teste em ambiente de desenvolvimento primeiro\n";
?>
