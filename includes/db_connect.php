<?php
// /includes/db_connect.php
date_default_timezone_set('America/Sao_Paulo');

// Carregar variáveis de ambiente do arquivo .env
require_once __DIR__ . '/env_loader.php';

// Valores padrão (apenas se não foram carregados do .env)
if (!defined('ONESIGNAL_APP_ID')) {
    define('ONESIGNAL_APP_ID', '8b948d38-c99d-402b-a456-e99e66fcc60f');
}
if (!defined('ONESIGNAL_REST_API_KEY')) {
    define('ONESIGNAL_REST_API_KEY', 'os_v2_app_roki2ogjtvacxjcw5gpgn7ggb6mdk2tfshne5g4h2i6iyji25kg3h7mljd6u7rl2kw23egygxcbkcxdvfjehi7u5x5df4e2z7zefrhi');
}
// GEMINI_API_KEY deve ser definida no arquivo .env - não há valor padrão por segurança

// Google OAuth (Integrações Google)
// ⚠️ SEGURANÇA: As credenciais devem ser configuradas em um arquivo separado não versionado
if (file_exists(__DIR__ . '/google_oauth_config.php')) {
    require_once __DIR__ . '/google_oauth_config.php';
}

// Configurações de conexão
$host = 'localhost';
$dbname = 'u853242961_financeiro';
$user = 'u853242961_user7';
$pass = 'Lucastav8012@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Tentar conectar
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '-03:00'");
    // Limpar variáveis de erro em caso de sucesso
    $db_connect_error = null;
    $db_connect_error_code = null;
} catch (\PDOException $e) {
    // Não lançar exceção aqui - deixar que o código que inclui este arquivo trate o erro
    $pdo = null;
    $db_connect_error = $e->getMessage();
    $db_connect_error_code = $e->getCode();
}
