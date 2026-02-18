<?php
/**
 * Gerador de Ícones PWA
 * Cria todos os ícones necessários para o Progressive Web App
 */

// Configurações
$iconSizes = [
    72, 96, 128, 144, 152, 192, 384, 512
];

$maskableSizes = [192, 512];

// Cores do tema
$primaryColor = '#667eea';
$secondaryColor = '#764ba2';
$accentColor = '#e50914';

/**
 * Criar ícone PNG
 */
function createIcon($size, $isMaskable = false) {
    global $primaryColor, $secondaryColor, $accentColor;
    
    // Criar imagem
    $image = imagecreatetruecolor($size, $size);
    
    // Cores
    $bgColor = imagecolorallocate($image, 102, 126, 234); // #667eea
    $white = imagecolorallocate($image, 255, 255, 255);
    $darkBlue = imagecolorallocate($image, 118, 75, 162); // #764ba2
    $red = imagecolorallocate($image, 229, 9, 20); // #e50914
    
    // Preenchimento de fundo
    imagefill($image, 0, 0, $bgColor);
    
    // Para ícones maskable, adicionar padding
    $padding = $isMaskable ? $size * 0.1 : 0;
    $iconSize = $size - ($padding * 2);
    $centerX = $size / 2;
    $centerY = $size / 2;
    $radius = $iconSize * 0.3;
    
    // Círculo de fundo branco
    imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $white);
    
    // Símbolo de dinheiro ($)
    $fontSize = $radius * 0.6;
    $text = '$';
    
    // Calcular posição do texto
    $bbox = imagettfbbox($fontSize, 0, __DIR__ . '/assets/fonts/arial.ttf', $text);
    $textWidth = $bbox[4] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[5];
    
    $textX = $centerX - ($textWidth / 2);
    $textY = $centerY + ($textHeight / 2);
    
    // Usar fonte padrão se não encontrar arial
    $font = 5; // Fonte padrão do GD
    if (file_exists(__DIR__ . '/assets/fonts/arial.ttf')) {
        $font = __DIR__ . '/assets/fonts/arial.ttf';
    }
    
    // Desenhar texto
    if (is_string($font)) {
        imagettftext($image, $fontSize, 0, $textX, $textY, $darkBlue, $font, $text);
    } else {
        imagestring($image, $font, $textX, $textY - 10, $text, $darkBlue);
    }
    
    // Borda
    imageellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $darkBlue);
    
    return $image;
}

/**
 * Salvar ícone
 */
function saveIcon($image, $filename) {
    $path = __DIR__ . '/icons/' . $filename;
    
    // Criar diretório se não existir
    if (!is_dir(__DIR__ . '/icons')) {
        mkdir(__DIR__ . '/icons', 0755, true);
    }
    
    // Salvar como PNG
    if (imagepng($image, $path)) {
        imagedestroy($image);
        return true;
    }
    
    imagedestroy($image);
    return false;
}

// Processar requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'generate') {
        $results = [];
        $success = true;
        
        // Gerar ícones normais
        foreach ($iconSizes as $size) {
            $filename = "icon-{$size}x{$size}.png";
            $image = createIcon($size);
            
            if (saveIcon($image, $filename)) {
                $results[] = [
                    'filename' => $filename,
                    'size' => $size,
                    'status' => 'success'
                ];
            } else {
                $results[] = [
                    'filename' => $filename,
                    'size' => $size,
                    'status' => 'error'
                ];
                $success = false;
            }
        }
        
        // Gerar ícones maskable
        foreach ($maskableSizes as $size) {
            $filename = "icon-maskable-{$size}x{$size}.png";
            $image = createIcon($size, true);
            
            if (saveIcon($image, $filename)) {
                $results[] = [
                    'filename' => $filename,
                    'size' => $size,
                    'status' => 'success',
                    'type' => 'maskable'
                ];
            } else {
                $results[] = [
                    'filename' => $filename,
                    'size' => $size,
                    'status' => 'error',
                    'type' => 'maskable'
                ];
                $success = false;
            }
        }
        
        echo json_encode([
            'success' => $success,
            'results' => $results,
            'message' => $success ? 'Ícones gerados com sucesso!' : 'Erro ao gerar alguns ícones'
        ]);
        exit;
    }
}

// Página HTML
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerador de Ícones PWA - PHP</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            margin: 5px;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status.loading {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .results {
            margin-top: 20px;
        }
        
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin: 5px 0;
        }
        
        .result-item.success {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .result-item.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .icon-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .icon-item {
            text-align: center;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .icon-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .icon-item img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 Gerador de Ícones PWA - PHP</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Gere todos os ícones necessários para seu Progressive Web App usando PHP
        </p>
        
        <div id="status"></div>
        
        <form id="generateForm">
            <button type="submit" class="btn">🔄 Gerar Todos os Ícones</button>
        </form>
        
        <div id="results" class="results"></div>
        <div id="preview" class="icon-preview"></div>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
            <h3>📋 Tamanhos que serão gerados:</h3>
            <ul>
                <li><strong>72x72</strong> - Ícone pequeno</li>
                <li><strong>96x96</strong> - Ícone médio</li>
                <li><strong>128x128</strong> - Ícone padrão</li>
                <li><strong>144x144</strong> - Windows tiles</li>
                <li><strong>152x152</strong> - iOS Safari</li>
                <li><strong>192x192</strong> - Android Chrome</li>
                <li><strong>384x384</strong> - Android Chrome grande</li>
                <li><strong>512x512</strong> - Splash screen</li>
                <li><strong>Maskable 192x192</strong> - Ícone adaptativo</li>
                <li><strong>Maskable 512x512</strong> - Ícone adaptativo grande</li>
            </ul>
        </div>
    </div>

    <script>
        document.getElementById('generateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            generateIcons();
        });
        
        function showStatus(message, type = 'success') {
            const statusDiv = document.getElementById('status');
            statusDiv.innerHTML = `<div class="status ${type}">${message}</div>`;
        }
        
        function generateIcons() {
            showStatus('Gerando ícones...', 'loading');
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=generate'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus(data.message, 'success');
                    displayResults(data.results);
                    loadPreview();
                } else {
                    showStatus(data.message, 'error');
                    displayResults(data.results);
                }
            })
            .catch(error => {
                showStatus('Erro ao gerar ícones: ' + error.message, 'error');
            });
        }
        
        function displayResults(results) {
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<h3>Resultados:</h3>';
            
            results.forEach(result => {
                const item = document.createElement('div');
                item.className = `result-item ${result.status}`;
                
                const statusIcon = result.status === 'success' ? '✅' : '❌';
                const typeLabel = result.type === 'maskable' ? ' (Maskable)' : '';
                
                item.innerHTML = `
                    <span>${statusIcon} ${result.filename}${typeLabel}</span>
                    <span>${result.size}x${result.size}px</span>
                `;
                
                resultsDiv.appendChild(item);
            });
        }
        
        function loadPreview() {
            const previewDiv = document.getElementById('preview');
            previewDiv.innerHTML = '<h3>Preview dos Ícones:</h3>';
            
            // Lista de ícones para preview
            const icons = [
                'icon-72x72.png', 'icon-96x96.png', 'icon-128x128.png',
                'icon-144x144.png', 'icon-152x152.png', 'icon-192x192.png',
                'icon-384x384.png', 'icon-512x512.png',
                'icon-maskable-192x192.png', 'icon-maskable-512x512.png'
            ];
            
            icons.forEach(iconName => {
                const item = document.createElement('div');
                item.className = 'icon-item';
                
                const img = document.createElement('img');
                img.src = `icons/${iconName}?t=${Date.now()}`;
                img.alt = iconName;
                img.onerror = function() {
                    this.style.display = 'none';
                    this.parentNode.innerHTML = `<div style="color: #999;">${iconName}<br><small>Não encontrado</small></div>`;
                };
                
                const name = document.createElement('div');
                name.textContent = iconName;
                name.style.fontSize = '12px';
                name.style.marginTop = '5px';
                name.style.color = '#666';
                
                item.appendChild(img);
                item.appendChild(name);
                previewDiv.appendChild(item);
            });
        }
    </script>
</body>
</html>