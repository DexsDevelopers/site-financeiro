<?php
// src/autoloader.php - Autoloader PSR-4

spl_autoload_register(function ($class) {
    // Mapear namespaces para diretórios
    $prefixes = [
        'App\\Controllers\\' => __DIR__ . '/Controllers/',
        'App\\Models\\' => __DIR__ . '/Models/',
        'App\\Services\\' => __DIR__ . '/Services/',
        'App\\Utils\\' => __DIR__ . '/Utils/',
        'App\\Middleware\\' => __DIR__ . '/Middleware/',
    ];
    
    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Incluir arquivos de configuração
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
