<?php
// teste_pdf_simples.php - Teste simples de upload e acesso a PDF

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🧪 Teste Simples de PDF</h2>";

// Listar arquivos PDF recentes
$diretorio = 'uploads/extratos/';
$arquivos = [];

if (is_dir($diretorio)) {
    $files = scandir($diretorio);
    foreach ($files as $file) {
        if (strpos($file, 'extrato_' . $userId . '_') === 0 && strpos($file, '.pdf') !== false) {
            $arquivos[] = $file;
        }
    }
    
    // Ordenar por data de modificação (mais recente primeiro)
    usort($arquivos, function($a, $b) {
        return filemtime($diretorio . $b) - filemtime($diretorio . $a);
    });
}

if (empty($arquivos)) {
    echo "<p>❌ Nenhum arquivo PDF encontrado para o usuário.</p>";
    echo "<p><a href='importar_extrato_pdf.php'>← Voltar para Importação</a></p>";
    exit;
}

echo "<h3>📁 Arquivos PDF Encontrados:</h3>";
echo "<ul>";
foreach ($arquivos as $arquivo) {
    $caminho = $diretorio . $arquivo;
    $tamanho = filesize($caminho);
    $data = date('d/m/Y H:i:s', filemtime($caminho));
    echo "<li><strong>$arquivo</strong> - " . number_format($tamanho / 1024, 2) . " KB - $data</li>";
}
echo "</ul>";

// Testar acesso ao arquivo mais recente
$arquivoMaisRecente = $arquivos[0];
$caminhoCompleto = $diretorio . $arquivoMaisRecente;

echo "<h3>🔍 Teste de Acesso ao Arquivo:</h3>";
echo "<p><strong>Arquivo:</strong> $arquivoMaisRecente</p>";
echo "<p><strong>Caminho:</strong> $caminhoCompleto</p>";

// Verificar se arquivo existe
if (file_exists($caminhoCompleto)) {
    echo "<p>✅ <strong>Arquivo existe</strong></p>";
    
    // Verificar permissões
    if (is_readable($caminhoCompleto)) {
        echo "<p>✅ <strong>Arquivo é legível</strong></p>";
    } else {
        echo "<p>❌ <strong>Arquivo não é legível</strong></p>";
    }
    
    // Verificar cabeçalho do PDF
    $handle = fopen($caminhoCompleto, 'rb');
    if ($handle) {
        $header = fread($handle, 4);
        fclose($handle);
        
        if ($header === '%PDF') {
            echo "<p>✅ <strong>Cabeçalho PDF válido</strong></p>";
        } else {
            echo "<p>❌ <strong>Cabeçalho PDF inválido:</strong> " . bin2hex($header) . "</p>";
        }
    } else {
        echo "<p>❌ <strong>Não foi possível abrir o arquivo</strong></p>";
    }
    
    // Testar URL de acesso
    $urlArquivo = "https://gold-quail-250128.hostingersite.com/seu_projeto/$caminhoCompleto";
    echo "<p><strong>URL de acesso:</strong> <a href='$urlArquivo' target='_blank'>$urlArquivo</a></p>";
    
} else {
    echo "<p>❌ <strong>Arquivo não existe</strong></p>";
}

echo "<hr>";

// Teste com JavaScript
echo "<h3>🧪 Teste com JavaScript:</h3>";
echo "<div id='teste-js'>";
echo "<button onclick='testarPDF()' class='btn btn-primary'>Testar Carregamento do PDF</button>";
echo "<div id='resultado-teste' class='mt-3'></div>";
echo "</div>";

echo "<hr>";
echo "<p><a href='importar_extrato_pdf.php'>← Voltar para Importação</a></p>";
echo "<p><a href='debug_pdf.php'>← Ver Debug Completo</a></p>";
?>

<!-- PDF.js para teste -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<script>
// Configurar PDF.js
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

async function testarPDF() {
    const resultado = document.getElementById('resultado-teste');
    resultado.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Testando...';
    
    try {
        const urlArquivo = '<?php echo $caminhoCompleto; ?>';
        console.log('Testando URL:', urlArquivo);
        
        // Testar fetch primeiro
        const response = await fetch(urlArquivo);
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const arrayBuffer = await response.arrayBuffer();
        console.log('ArrayBuffer size:', arrayBuffer.byteLength);
        
        // Verificar cabeçalho
        const uint8Array = new Uint8Array(arrayBuffer);
        const pdfHeader = String.fromCharCode(...uint8Array.slice(0, 4));
        console.log('PDF Header:', pdfHeader);
        
        if (pdfHeader !== '%PDF') {
            throw new Error('Arquivo não é um PDF válido');
        }
        
        // Tentar carregar com PDF.js
        const loadingTask = pdfjsLib.getDocument({
            data: arrayBuffer,
            verbosity: 0
        });
        
        const pdf = await loadingTask.promise;
        console.log('PDF carregado com sucesso! Páginas:', pdf.numPages);
        
        resultado.innerHTML = `
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <strong>PDF carregado com sucesso!</strong><br>
                Páginas: ${pdf.numPages}<br>
                Tamanho: ${(arrayBuffer.byteLength / 1024).toFixed(2)} KB
            </div>
        `;
        
    } catch (error) {
        console.error('Erro no teste:', error);
        resultado.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Erro:</strong> ${error.message}
            </div>
        `;
    }
}
</script>
