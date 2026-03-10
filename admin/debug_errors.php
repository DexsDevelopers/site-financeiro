<?php
// admin/debug_errors.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Diagnóstico de Erros</h3>";

function test_file($path) {
    echo "Testando arquivo: <strong>$path</strong> ... ";
    if (file_exists($path)) {
        echo "<span style='color:green'>EXISTE</span><br>";
        return true;
    } else {
        echo "<span style='color:red'>NÃO ENCONTRADO</span><br>";
        return false;
    }
}

$root = dirname(__DIR__);
test_file($root . '/templates/header.php');
test_file($root . '/includes/db_connect.php');
test_file($root . '/includes/push_helper.php');
test_file($root . '/vendor/autoload.php');

echo "<hr><h4>Tentando carregar dependencies...</h4>";
try {
    require_once $root . '/vendor/autoload.php';
    echo "<span style='color:green'>Autoload carregado com sucesso!</span><br>";
    
    if (class_exists('Minishlink\WebPush\WebPush')) {
        echo "<span style='color:green'>Classe WebPush encontrada!</span><br>";
    } else {
        echo "<span style='color:red'>Classe WebPush NÃO encontrada nas dependências.</span><br>";
    }
} catch (Error $e) {
    echo "<span style='color:red'>ERRO FATAL: " . $e->getMessage() . "</span><br>";
}

echo "<hr><h4>Versão do PHP:</h4>";
echo phpversion();
?>
