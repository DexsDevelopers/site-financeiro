<?php
/**
 * Carregador de variáveis de ambiente do arquivo .env
 * Lê o arquivo .env e define as constantes necessárias
 */

// Caminho do arquivo .env (na raiz do projeto)
$envFile = __DIR__ . '/../.env';

// Se o arquivo .env existe, carregar as variáveis
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorar comentários
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Separar chave e valor
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover aspas se existirem
            $value = trim($value, '"\'');
            
            // Definir constante se ainda não foi definida
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Definir valores padrão se não existirem no .env
if (!defined('GEMINI_API_KEY')) {
    // Tentar ler de variável de ambiente do servidor (Hostinger)
    if (isset($_ENV['GEMINI_API_KEY']) && !empty($_ENV['GEMINI_API_KEY'])) {
        define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY']);
    } elseif (getenv('GEMINI_API_KEY')) {
        define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
    } else {
        // Se não encontrar, definir como vazio (será tratado nos arquivos que usam)
        define('GEMINI_API_KEY', '');
    }
}

if (!defined('ONESIGNAL_APP_ID')) {
    if (isset($_ENV['ONESIGNAL_APP_ID']) && !empty($_ENV['ONESIGNAL_APP_ID'])) {
        define('ONESIGNAL_APP_ID', $_ENV['ONESIGNAL_APP_ID']);
    } elseif (getenv('ONESIGNAL_APP_ID')) {
        define('ONESIGNAL_APP_ID', getenv('ONESIGNAL_APP_ID'));
    }
}

if (!defined('ONESIGNAL_REST_API_KEY')) {
    if (isset($_ENV['ONESIGNAL_REST_API_KEY']) && !empty($_ENV['ONESIGNAL_REST_API_KEY'])) {
        define('ONESIGNAL_REST_API_KEY', $_ENV['ONESIGNAL_REST_API_KEY']);
    } elseif (getenv('ONESIGNAL_REST_API_KEY')) {
        define('ONESIGNAL_REST_API_KEY', getenv('ONESIGNAL_REST_API_KEY'));
    }
}


