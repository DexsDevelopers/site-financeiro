<?php
// debug_csv.php - Debug completo do processamento CSV

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🔍 Debug CSV - Análise Completa</h2>";

// Criar CSV de teste com diferentes formatos
$csvFormatos = [
    'formato1' => "Data,Descrição,Valor\n15/01/2025,PIX RECEBIDO,150.00\n14/01/2025,COMPRA CARTÃO,-85.50",
    'formato2' => "Date,Description,Value\n2025-01-15,PIX RECEIVED,150.00\n2025-01-14,CARD PURCHASE,-85.50",
    'formato3' => "data,descricao,valor\n15/01/2025,PIX RECEBIDO,150.00\n14/01/2025,COMPRA CARTÃO,-85.50",
    'formato4' => "Data;Descrição;Valor\n15/01/2025;PIX RECEBIDO;150.00\n14/01/2025;COMPRA CARTÃO;-85.50"
];

foreach ($csvFormatos as $nome => $conteudo) {
    echo "<h3>📄 Testando $nome:</h3>";
    echo "<pre class='bg-light p-2 border rounded small'>";
    echo htmlspecialchars($conteudo);
    echo "</pre>";
    
    // Salvar arquivo de teste
    $nomeArquivo = "teste_$nome.csv";
    $caminhoArquivo = "uploads/extratos/$nomeArquivo";
    
    if (!is_dir('uploads/extratos/')) {
        mkdir('uploads/extratos/', 0755, true);
    }
    
    file_put_contents($caminhoArquivo, $conteudo);
    
    // Analisar arquivo
    echo "<h4>🔍 Análise do Arquivo:</h4>";
    
    // 1. Verificar se arquivo existe
    echo "<p><strong>Arquivo existe:</strong> " . (file_exists($caminhoArquivo) ? "✅ Sim" : "❌ Não") . "</p>";
    
    // 2. Verificar tamanho
    echo "<p><strong>Tamanho:</strong> " . filesize($caminhoArquivo) . " bytes</p>";
    
    // 3. Verificar encoding
    $conteudoArquivo = file_get_contents($caminhoArquivo);
    $encoding = mb_detect_encoding($conteudoArquivo, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    echo "<p><strong>Encoding detectado:</strong> $encoding</p>";
    
    // 4. Verificar separador
    $separador = strpos($conteudoArquivo, ';') !== false ? ';' : ',';
    echo "<p><strong>Separador detectado:</strong> '$separador'</p>";
    
    // 5. Ler linhas
    $linhas = file($caminhoArquivo, FILE_IGNORE_NEW_LINES);
    echo "<p><strong>Número de linhas:</strong> " . count($linhas) . "</p>";
    
    echo "<h5>📋 Linhas do arquivo:</h5>";
    echo "<ol>";
    foreach ($linhas as $i => $linha) {
        echo "<li><code>" . htmlspecialchars($linha) . "</code></li>";
    }
    echo "</ol>";
    
    // 6. Testar fgetcsv
    echo "<h4>🧪 Teste com fgetcsv:</h4>";
    $handle = fopen($caminhoArquivo, 'r');
    if ($handle) {
        $linhaNum = 0;
        while (($dados = fgetcsv($handle, 1000, $separador)) !== FALSE) {
            $linhaNum++;
            echo "<p><strong>Linha $linhaNum:</strong> " . json_encode($dados) . "</p>";
        }
        fclose($handle);
    } else {
        echo "<p>❌ Erro ao abrir arquivo</p>";
    }
    
    // 7. Testar processamento
    echo "<h4>⚙️ Teste de Processamento:</h4>";
    try {
        $transacoes = processarCSVDebug($caminhoArquivo);
        echo "<p><strong>Transações encontradas:</strong> " . count($transacoes) . "</p>";
        
        if (!empty($transacoes)) {
            echo "<div class='table-responsive'>";
            echo "<table class='table table-sm table-striped'>";
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
        } else {
            echo "<p>❌ Nenhuma transação processada</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

// Função de processamento com debug
function processarCSVDebug($caminhoArquivo) {
    $transacoes = [];
    
    // Detectar encoding
    $conteudo = file_get_contents($caminhoArquivo);
    $encoding = mb_detect_encoding($conteudo, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    
    echo "<p><strong>Encoding original:</strong> $encoding</p>";
    
    if ($encoding !== 'UTF-8') {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding);
        file_put_contents($caminhoArquivo, $conteudo);
        echo "<p><strong>Encoding convertido para:</strong> UTF-8</p>";
    }
    
    // Detectar separador
    $separador = strpos($conteudo, ';') !== false ? ';' : ',';
    echo "<p><strong>Separador usado:</strong> '$separador'</p>";
    
    // Abrir arquivo
    $handle = fopen($caminhoArquivo, 'r');
    if (!$handle) {
        throw new Exception('Não foi possível abrir o arquivo CSV');
    }
    
    $linha = 0;
    $cabecalhos = [];
    
    while (($dados = fgetcsv($handle, 1000, $separador)) !== FALSE) {
        $linha++;
        echo "<p><strong>Processando linha $linha:</strong> " . json_encode($dados) . "</p>";
        
        // Primeira linha - cabeçalhos
        if ($linha === 1) {
            $cabecalhos = array_map('trim', $dados);
            echo "<p><strong>Cabeçalhos encontrados:</strong> " . json_encode($cabecalhos) . "</p>";
            continue;
        }
        
        // Pular linhas vazias
        if (empty(array_filter($dados))) {
            echo "<p>⚠️ Linha vazia, pulando...</p>";
            continue;
        }
        
        // Processar linha
        $transacao = processarLinhaCSVDebug($dados, $cabecalhos);
        if ($transacao) {
            $transacoes[] = $transacao;
            echo "<p>✅ Transação processada: " . json_encode($transacao) . "</p>";
        } else {
            echo "<p>❌ Falha ao processar linha: " . json_encode($dados) . "</p>";
        }
    }
    
    fclose($handle);
    
    return $transacoes;
}

function processarLinhaCSVDebug($dados, $cabecalhos) {
    echo "<p><strong>Dados da linha:</strong> " . json_encode($dados) . "</p>";
    echo "<p><strong>Cabeçalhos disponíveis:</strong> " . json_encode($cabecalhos) . "</p>";
    
    // Mapear colunas
    $mapeamento = [
        'data' => ['data', 'date', 'data_transacao', 'data_transaction'],
        'descricao' => ['descricao', 'description', 'desc', 'descricao_transacao'],
        'valor' => ['valor', 'value', 'amount', 'valor_transacao']
    ];
    
    $indices = [];
    foreach ($mapeamento as $campo => $variacoes) {
        foreach ($variacoes as $variacao) {
            $indice = array_search(strtolower($variacao), array_map('strtolower', $cabecalhos));
            if ($indice !== false) {
                $indices[$campo] = $indice;
                echo "<p><strong>Campo '$campo' encontrado no índice $indice (coluna: $variacao)</strong></p>";
                break;
            }
        }
    }
    
    // Se não encontrou, tentar por posição
    if (empty($indices['data']) || empty($indices['descricao']) || empty($indices['valor'])) {
        echo "<p>⚠️ Campos não encontrados por nome, tentando por posição...</p>";
        if (count($dados) >= 3) {
            $indices['data'] = 0;
            $indices['descricao'] = 1;
            $indices['valor'] = 2;
            echo "<p><strong>Usando posições padrão:</strong> Data=0, Descrição=1, Valor=2</p>";
        } else {
            echo "<p>❌ Dados insuficientes (mínimo 3 colunas)</p>";
            return null;
        }
    }
    
    // Extrair dados
    $data = isset($dados[$indices['data']]) ? trim($dados[$indices['data']]) : '';
    $descricao = isset($dados[$indices['descricao']]) ? trim($dados[$indices['descricao']]) : '';
    $valor = isset($dados[$indices['valor']]) ? trim($dados[$indices['valor']]) : '';
    
    echo "<p><strong>Dados extraídos:</strong> Data='$data', Descrição='$descricao', Valor='$valor'</p>";
    
    if (empty($data) || empty($descricao) || empty($valor)) {
        echo "<p>❌ Dados obrigatórios vazios</p>";
        return null;
    }
    
    // Normalizar data
    $dataNormalizada = normalizarDataCSVDebug($data);
    echo "<p><strong>Data normalizada:</strong> '$dataNormalizada'</p>";
    if (!$dataNormalizada) {
        echo "<p>❌ Falha na normalização da data</p>";
        return null;
    }
    
    // Normalizar valor
    $valorNormalizado = normalizarValorCSVDebug($valor);
    echo "<p><strong>Valor normalizado:</strong> $valorNormalizado</p>";
    if ($valorNormalizado === null) {
        echo "<p>❌ Falha na normalização do valor</p>";
        return null;
    }
    
    $resultado = [
        'data' => $dataNormalizada,
        'descricao' => $descricao,
        'valor' => $valorNormalizado,
        'tipo' => $valorNormalizado > 0 ? 'receita' : 'despesa'
    ];
    
    echo "<p><strong>✅ Transação final:</strong> " . json_encode($resultado) . "</p>";
    return $resultado;
}

function normalizarDataCSVDebug($data) {
    echo "<p><strong>Normalizando data:</strong> '$data'</p>";
    
    $formatos = [
        'd/m/Y',
        'Y-m-d',
        'd-m-Y',
        'm/d/Y',
        'Y/m/d'
    ];
    
    foreach ($formatos as $formato) {
        $dataObj = DateTime::createFromFormat($formato, $data);
        if ($dataObj !== false) {
            $resultado = $dataObj->format('Y-m-d');
            echo "<p><strong>✅ Data normalizada com formato '$formato':</strong> $resultado</p>";
            return $resultado;
        }
    }
    
    // Tentar strtotime
    $timestamp = strtotime($data);
    if ($timestamp !== false) {
        $resultado = date('Y-m-d', $timestamp);
        echo "<p><strong>✅ Data normalizada com strtotime:</strong> $resultado</p>";
        return $resultado;
    }
    
    echo "<p><strong>❌ Falha na normalização da data</strong></p>";
    return null;
}

function normalizarValorCSVDebug($valor) {
    echo "<p><strong>Normalizando valor:</strong> '$valor'</p>";
    
    $valorOriginal = $valor;
    $valor = trim($valor);
    $valor = str_replace(['R$', '$', ' '], '', $valor);
    
    echo "<p><strong>Valor após limpeza:</strong> '$valor'</p>";
    
    // Detectar formato brasileiro vs americano
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        // Formato brasileiro: 1.234,56
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        echo "<p><strong>Formato brasileiro detectado:</strong> '$valor'</p>";
    } elseif (strpos($valor, ',') !== false) {
        // Apenas vírgula: 1234,56
        $valor = str_replace(',', '.', $valor);
        echo "<p><strong>Formato com vírgula detectado:</strong> '$valor'</p>";
    }
    
    $valorFloat = floatval($valor);
    echo "<p><strong>Valor como float:</strong> $valorFloat</p>";
    
    if ($valorFloat == 0 && $valorOriginal !== '0' && $valorOriginal !== '0,00' && $valorOriginal !== '0.00') {
        echo "<p><strong>❌ Valor inválido (zero sem ser zero explícito)</strong></p>";
        return null;
    }
    
    echo "<p><strong>✅ Valor normalizado:</strong> $valorFloat</p>";
    return $valorFloat;
}

echo "<hr>";
echo "<p><a href='importar_extrato_csv.php'>← Voltar para Importação CSV</a></p>";
echo "<p><a href='teste_csv_simples.php'>← Teste CSV Simples</a></p>";
?>
