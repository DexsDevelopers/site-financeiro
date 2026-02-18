<?php
// src/Services/CacheService.php - Serviço de Cache Otimizado

class CacheService {
    private $pdo;
    private $cache_dir;
    private $default_ttl = 3600; // 1 hora
    
    public function __construct($pdo, $cache_dir = 'cache/') {
        $this->pdo = $pdo;
        $this->cache_dir = $cache_dir;
        
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Cache de usuário
     */
    public function getUserCache($userId, $key, $callback = null, $ttl = null) {
        $cacheKey = "user_{$userId}_{$key}";
        $ttl = $ttl ?? $this->default_ttl;
        
        // Tentar buscar do cache
        $cached = $this->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }
        
        // Se não encontrou e tem callback, executar e cachear
        if ($callback && is_callable($callback)) {
            $data = $callback();
            $this->set($cacheKey, $data, $ttl);
            return $data;
        }
        
        return null;
    }
    
    /**
     * Cache de sistema
     */
    public function getSystemCache($key, $callback = null, $ttl = null) {
        $cacheKey = "system_{$key}";
        $ttl = $ttl ?? $this->default_ttl;
        
        $cached = $this->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }
        
        if ($callback && is_callable($callback)) {
            $data = $callback();
            $this->set($cacheKey, $data, $ttl);
            return $data;
        }
        
        return null;
    }
    
    /**
     * Cache de consultas SQL
     */
    public function getQueryCache($sql, $params = [], $ttl = 1800) {
        $cacheKey = 'query_' . md5($sql . serialize($params));
        
        $cached = $this->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }
        
        // Executar query
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->set($cacheKey, $result, $ttl);
        return $result;
    }
    
    /**
     * Cache de estatísticas
     */
    public function getStatsCache($userId, $type, $callback, $ttl = 300) {
        $cacheKey = "stats_{$userId}_{$type}";
        
        $cached = $this->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }
        
        $data = $callback();
        $this->set($cacheKey, $data, $ttl);
        return $data;
    }
    
    /**
     * Limpar cache do usuário
     */
    public function clearUserCache($userId) {
        $pattern = $this->cache_dir . "user_{$userId}_*";
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Limpar todo o cache
     */
    public function clearAllCache() {
        $files = glob($this->cache_dir . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Cache inteligente com invalidação
     */
    public function smartCache($key, $callback, $dependencies = [], $ttl = 3600) {
        $cacheKey = "smart_{$key}";
        
        // Verificar se as dependências mudaram
        $depKey = $cacheKey . '_deps';
        $cachedDeps = $this->get($depKey);
        
        if ($cachedDeps !== false) {
            $depsChanged = false;
            foreach ($dependencies as $dep => $value) {
                if (!isset($cachedDeps[$dep]) || $cachedDeps[$dep] !== $value) {
                    $depsChanged = true;
                    break;
                }
            }
            
            if (!$depsChanged) {
                $cached = $this->get($cacheKey);
                if ($cached !== false) {
                    return $cached;
                }
            }
        }
        
        // Executar callback e cachear
        $data = $callback();
        $this->set($cacheKey, $data, $ttl);
        $this->set($depKey, $dependencies, $ttl);
        
        return $data;
    }
    
    /**
     * Métodos privados
     */
    private function get($key) {
        $file = $this->cache_dir . $key . '.cache';
        
        if (!file_exists($file)) {
            return false;
        }
        
        $content = file_get_contents($file);
        $data = unserialize($content);
        
        if ($data['expires'] < time()) {
            unlink($file);
            return false;
        }
        
        return $data['value'];
    }
    
    private function set($key, $value, $ttl) {
        $file = $this->cache_dir . $key . '.cache';
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($file, serialize($data), LOCK_EX);
    }
}
