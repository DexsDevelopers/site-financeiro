<?php
// admin/debug_errors.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Diagnóstico Real de Notificações</h3>";

$root = dirname(__DIR__);

// 1. Testar inclusão do config
$configFile = $root . '/includes/config_push.php';
if (file_exists($configFile)) {
    require_once $configFile;
    echo "<span style='color:green'>Config VAPID carregado.</span><br>";
} else {
    echo "<span style='color:red'>Config VAPID NÃO ENCONTRADO em $configFile</span><br>";
}

// 2. Testar inclusão do Autoload
$autoloadFile = $root . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
    echo "<span style='color:green'>Autoload carregado.</span><br>";
} else {
    echo "<span style='color:red'>Autoload NÃO ENCONTRADO em $autoloadFile</span><br>";
}

// 3. Testar Instanciação com chaves REAIS
use Minishlink\WebPush\WebPush;
try {
    if (defined('VAPID_PUBLIC_KEY')) {
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ];
        $webPush = new WebPush($auth);
        echo "<span style='color:green'>WebPush instanciado com sucesso usando chaves REAIS!</span><br>";
    } else {
        echo "<span style='color:orange'>Chaves VAPID não definidas no config.</span><br>";
    }
} catch (Throwable $e) {
    echo "<span style='color:red'>ERRO FATAL NAS CHAVES: " . $e->getMessage() . "</span><br>";
}

// 4. Testar o Helper
echo "<hr><h4>Testando Helper...</h4>";
$helperFile = $root . '/includes/push_helper.php';
if (file_exists($helperFile)) {
    require_once $helperFile;
    echo "<span style='color:green'>Helper incluído.</span><br>";
    if (function_exists('sendWebPush')) {
        echo "<span style='color:green'>Função sendWebPush existe!</span><br>";
    } else {
        echo "<span style='color:red'>Função sendWebPush NÃO ENCONTRADA no helper.</span><br>";
    }
}

echo "<hr><h4>Info PHP:</h4>";
echo "GMP extension: " . (extension_loaded('gmp') ? 'Yes' : 'No') . "<br>";
echo "OpenSSL extension: " . (extension_loaded('openssl') ? 'Yes' : 'No') . "<br>";
?>
