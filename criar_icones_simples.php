<?php
/**
 * Gerador Simples de Ícones PWA
 */

// Configurações
$iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];
$maskableSizes = [192, 512];

// Criar diretório de ícones
if (!is_dir('icons')) {
    mkdir('icons', 0755, true);
    echo "Diretório 'icons' criado.\n";
}

/**
 * Criar ícone simples
 */
function createSimpleIcon($size, $isMaskable = false) {
    // Criar imagem
    $image = imagecreatetruecolor($size, $size);
    
    // Cores
    $bgColor = imagecolorallocate($image, 102, 126, 234); // #667eea
    $white = imagecolorallocate($image, 255, 255, 255);
    $darkBlue = imagecolorallocate($image, 118, 75, 162); // #764ba2
    
    // Preenchimento de fundo
    imagefill($image, 0, 0, $bgColor);
    
    // Para ícones maskable, adicionar padding
    $padding = $isMaskable ? $size * 0.1 : 0;
    $centerX = $size / 2;
    $centerY = $size / 2;
    $radius = ($size - $padding * 2) * 0.3;
    
    // Círculo de fundo branco
    imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $white);
    
    // Símbolo de dinheiro ($) usando fonte padrão
    $fontSize = 5; // Fonte padrão do GD
    $text = '$';
    
    // Calcular posição do texto
    $textX = $centerX - 5;
    $textY = $centerY + 5;
    
    // Desenhar texto
    imagestring($image, $fontSize, $textX, $textY - 10, $text, $darkBlue);
    
    // Borda
    imageellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $darkBlue);
    
    return $image;
}

echo "Gerando ícones PWA...\n\n";

// Gerar ícones normais
foreach ($iconSizes as $size) {
    $filename = "icons/icon-{$size}x{$size}.png";
    $image = createSimpleIcon($size);
    
    if (imagepng($image, $filename)) {
        echo "✅ Gerado: {$filename}\n";
    } else {
        echo "❌ Erro: {$filename}\n";
    }
    
    imagedestroy($image);
}

// Gerar ícones maskable
foreach ($maskableSizes as $size) {
    $filename = "icons/icon-maskable-{$size}x{$size}.png";
    $image = createSimpleIcon($size, true);
    
    if (imagepng($image, $filename)) {
        echo "✅ Gerado: {$filename} (Maskable)\n";
    } else {
        echo "❌ Erro: {$filename}\n";
    }
    
    imagedestroy($image);
}

echo "\n🎉 Todos os ícones foram gerados com sucesso!\n";
echo "📁 Localização: " . __DIR__ . "/icons/\n";
?>
