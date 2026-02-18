<?php
// includes/cache_manager.php - Sistema de Cache Inteligente

class CacheManager {
    private $cache_dir;
    private $default_ttl = 3600; // 1 hora
    
    public function __construct($cache_dir = 'cache/') {
        $this->cache_dir = $cache_dir;
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Gera chave de cache baseada em parâmetros
     */
    private function generateKey($prefix, $params = []) {
        $key = $prefix . '_' . md5(serialize($params));
        return $key;
    }
    
    /**
     * Verifica se o cache existe e é válido
     */
    public function exists($key) {
        $file = $this->cache_dir . $key . '.cache';
        if (!file_exists($file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!$data || $data['expires'] < time()) {
            unlink($file);
            return false;
        }
        
        return true;
    }
    
    /**
     * Recupera dados do cache
     */
    public function get($key) {
        if (!$this->exists($key)) {
            return null;
        }
        
        $file = $this->cache_dir . $key . '.cache';
        $data = json_decode(file_get_contents($file), true);
        return $data['data'];
    }
    
    /**
     * Salva dados no cache
     */
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->default_ttl;
        $cache_data = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        $file = $this->cache_dir . $key . '.cache';
        file_put_contents($file, json_encode($cache_data), LOCK_EX);
    }
    
    /**
     * Remove item do cache
     */
    public function delete($key) {
        $file = $this->cache_dir . $key . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Limpa cache expirado
     */
    public function cleanExpired() {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['expires'] < time()) {
                unlink($file);
            }
        }
    }
    
    /**
     * Limpa todo o cache
     */
    public function clear() {
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Cache para consultas de banco de dados
     */
    public function getCachedQuery($query, $params = [], $ttl = 3600) {
        $key = $this->generateKey('query', [$query, $params]);
        
        if ($this->exists($key)) {
            return $this->get($key);
        }
        
        return null;
    }
    
    /**
     * Salva resultado de consulta no cache
     */
    public function setCachedQuery($query, $params = [], $data, $ttl = 3600) {
        $key = $this->generateKey('query', [$query, $params]);
        $this->set($key, $data, $ttl);
    }
    
    /**
     * Cache para dados do usuário
     */
    public function getUserCache($userId, $type, $params = []) {
        $key = $this->generateKey("user_{$userId}_{$type}", $params);
        return $this->get($key);
    }
    
    /**
     * Salva dados do usuário no cache
     */
    public function setUserCache($userId, $type, $data, $params = [], $ttl = 1800) {
        $key = $this->generateKey("user_{$userId}_{$type}", $params);
        $this->set($key, $data, $ttl);
    }
    
    /**
     * Invalida cache do usuário
     */
    public function invalidateUserCache($userId, $type = null) {
        $pattern = $type ? "user_{$userId}_{$type}_*" : "user_{$userId}_*";
        $files = glob($this->cache_dir . $pattern . '.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Estatísticas do cache
     */
    public function getStats() {
        $files = glob($this->cache_dir . '*.cache');
        $total_files = count($files);
        $total_size = 0;
        $expired_files = 0;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['expires'] < time()) {
                $expired_files++;
            }
        }
        
        return [
            'total_files' => $total_files,
            'total_size' => $total_size,
            'expired_files' => $expired_files,
            'cache_dir' => $this->cache_dir
        ];
    }
}

// Instância global do cache
$cache = new CacheManager();
?>
