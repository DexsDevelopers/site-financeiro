<?php
/**
 * asset_helper.php - Utilitário para gerenciar cache de assets (CSS/JS)
 */

if (!function_exists('asset')) {
    /**
     * Retorna a URL do asset com um parâmetro de versão baseado na última modificação do arquivo.
     * @param string $path Caminho relativo ao diretório raiz do projeto.
     * @return string URL formatada com ?v=timestamp
     */
    function asset($path) {
        $fullPath = __DIR__ . '/../' . ltrim($path, '/');
        
        if (file_exists($fullPath)) {
            $version = filemtime($fullPath);
            // Detecta se o path já tem query string
            $separator = (strpos($path, '?') !== false) ? '&' : '?';
            return $path . $separator . 'v=' . $version;
        }
        
        return $path;
    }
}
