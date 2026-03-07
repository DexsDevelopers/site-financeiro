<?php
/**
 * Script para indexar todas as skills em um arquivo JSON
 * Utilizado para alimentar a galeria de skills do site
 */

$skillsDir = __DIR__ . '/skills';
$outputFile = __DIR__ . '/includes/skills_index.json';

if (!is_dir($skillsDir)) {
    die("Diretório de skills não encontrado.");
}

$index = [];
$folders = array_diff(scandir($skillsDir), ['.', '..']);

foreach ($folders as $folder) {
    if ($folder === '.git' || $folder === '.gitignore') continue;
    
    $skillPath = $skillsDir . '/' . $folder . '/SKILL.md';
    if (file_exists($skillPath)) {
        $content = file_get_contents($skillPath);
        
        // Extrair frontmatter
        preg_match('/---\s*(.*?)\s*---/s', $content, $matches);
        $data = [];
        if (!empty($matches[1])) {
            $lines = explode("\n", $matches[1]);
            foreach ($lines as $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) == 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1], " \t\n\r\0\x0B\"");
                    $data[$key] = $value;
                }
            }
        }
        
        $index[] = [
            'id' => $data['id'] ?? $folder,
            'name' => $data['name'] ?? $folder,
            'description' => $data['description'] ?? 'Sem descrição.',
            'category' => $data['category'] ?? 'Geral',
            'path' => 'skills/' . $folder . '/SKILL.md'
        ];
    }
}

file_put_contents($outputFile, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Índice de skills gerado com sucesso: " . count($index) . " skills encontradas.\n";
