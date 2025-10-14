<?php
// debug_pdf.php - Debug do processamento de PDF

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/pdf_processor.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🔍 Debug do Processamento de PDF</h2>";

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

// Processar o arquivo mais recente
$arquivoMaisRecente = $diretorio . $arquivos[0];
echo "<h3>🔍 Processando: " . $arquivos[0] . "</h3>";

try {
    $processor = new PDFProcessor($pdo, $userId);
    
    // Extrair texto
    echo "<h4>1. Texto Extraído do PDF:</h4>";
    echo "<div style='background: #f8f9fa; padding: 1rem; border: 1px solid #dee2e6; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
    
    // Usar reflexão para acessar método privado
    $reflection = new ReflectionClass($processor);
    $method = $reflection->getMethod('extrairTextoPDF');
    $method->setAccessible(true);
    $texto = $method->invoke($processor, $arquivoMaisRecente);
    
    echo "<pre>" . htmlspecialchars($texto) . "</pre>";
    echo "</div>";
    
    // Processar transações
    echo "<h4>2. Transações Encontradas:</h4>";
    $transacoes = $processor->extrairTransacoes($arquivoMaisRecente);
    
    if (empty($transacoes)) {
        echo "<p>❌ Nenhuma transação encontrada.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>Data</th><th>Descrição</th><th>Valor</th><th>Tipo</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($transacoes as $transacao) {
            $cor = $transacao['valor'] > 0 ? 'text-success' : 'text-danger';
            echo "<tr>";
            echo "<td>" . $transacao['data'] . "</td>";
            echo "<td>" . htmlspecialchars($transacao['descricao']) . "</td>";
            echo "<td class='$cor'>R$ " . number_format($transacao['valor'], 2, ',', '.') . "</td>";
            echo "<td><span class='badge bg-" . ($transacao['tipo'] === 'receita' ? 'success' : 'danger') . "'>" . ucfirst($transacao['tipo']) . "</span></td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        
        echo "<p><strong>Total de transações encontradas:</strong> " . count($transacoes) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ Erro ao processar PDF:</h5>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>🛠️ Informações do Sistema:</h3>";

// Verificar comandos disponíveis
echo "<h4>Comandos Disponíveis:</h4>";
$comandos = ['pdftotext', 'python3', 'python'];
foreach ($comandos as $comando) {
    $disponivel = function_exists('shell_exec') && !empty(shell_exec("which $comando 2>/dev/null"));
    echo "<p><strong>$comando:</strong> " . ($disponivel ? "✅ Disponível" : "❌ Não disponível") . "</p>";
}

// Verificar bibliotecas PHP
echo "<h4>Bibliotecas PHP:</h4>";
$bibliotecas = [
    'Smalot\PdfParser\Parser' => class_exists('Smalot\PdfParser\Parser'),
    'ReflectionClass' => class_exists('ReflectionClass')
];

foreach ($bibliotecas as $nome => $disponivel) {
    echo "<p><strong>$nome:</strong> " . ($disponivel ? "✅ Disponível" : "❌ Não disponível") . "</p>";
}

echo "<hr>";
echo "<p><a href='importar_extrato_pdf.php'>← Voltar para Importação</a></p>";
echo "<p><a href='extrato_completo.php'>← Ver Extrato</a></p>";
?>
