<?php
// /includes/db_connect.php
date_default_timezone_set('America/Sao_Paulo');

define('ONESIGNAL_APP_ID', '8b948d38-c99d-402b-a456-e99e66fcc60f');
define('ONESIGNAL_REST_API_KEY', 'os_v2_app_roki2ogjtvacxjcw5gpgn7ggb6mdk2tfshne5g4h2i6iyji25kg3h7mljd6u7rl2kw23egygxcbkcxdvfjehi7u5x5df4e2z7zefrhi');
define('GEMINI_API_KEY', 'AIzaSyCv3V2FhpTzHEvHLiSNx0jAvsFJEdaQo78');

// Google OAuth (Integrações Google)
// ⚠️ SEGURANÇA: As credenciais devem ser configuradas em um arquivo separado não versionado
// Crie o arquivo 'includes/google_oauth_config.php' com:
// <?php
// define('GOOGLE_CLIENT_ID', 'seu-client-id');
// define('GOOGLE_CLIENT_SECRET', 'seu-client-secret');
// ?>
if (file_exists(__DIR__ . '/google_oauth_config.php')) {
    require_once __DIR__ . '/google_oauth_config.php';
}
// GOOGLE_REDIRECT_URI será gerado automaticamente baseado na URL atual

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

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     $pdo->exec("SET time_zone = '-03:00'");
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
     
}


?>


