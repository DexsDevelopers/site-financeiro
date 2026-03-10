<?php
// admin/debug_errors.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Diagnóstico Profundo de Erros</h3>";

function test_ext($ext) {
    echo "Extensão <strong>$ext</strong>: ";
    if (extension_loaded($ext)) {
        echo "<span style='color:green'>CARREGADA</span><br>";
    } else {
        echo "<span style='color:red'>NÃO DISPONÍVEL</span><br>";
    }
}

test_ext('openssl');
test_ext('curl');
test_ext('gmp');
test_ext('mbstring');
test_ext('pdo');

echo "<hr><h4>Testando Instanciação do WebPush...</h4>";
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;

try {
    $testVapid = [
        'VAPID' => [
            'subject' => 'mailto:admin@example.com',
            'publicKey' => 'BC0...',
            'privateKey' => 'A12...',
        ],
    ];
    $webPush = new WebPush($testVapid);
    echo "<span style='color:green'>Objeto WebPush criado com sucesso!</span><br>";
} catch (Throwable $e) {
    echo "<span style='color:red'>ERRO AO INSTANCIAR: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr><h4>Testando Conexão PDO...</h4>";
try {
    include_once $root . '/includes/db_connect.php';
    if (isset($pdo)) {
        echo "<span style='color:green'>Conexão \$pdo ativa!</span><br>";
    } else {
        echo "<span style='color:red'>\$pdo não encontrada após incluir db_connect.php</span><br>";
    }
} catch (Throwable $e) {
    echo "<span style='color:red'>ERRO NO BANCO: " . $e->getMessage() . "</span><br>";
}
?>
