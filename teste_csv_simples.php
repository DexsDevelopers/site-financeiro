<?php
// teste_csv_simples.php - Teste de importação CSV

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🧪 Teste de Importação CSV</h2>";

// Criar arquivo CSV de teste
$csvTeste = "Data,Descrição,Valor\n";
$csvTeste .= "15/01/2025,PIX RECEBIDO - JOÃO SILVA,150.00\n";
$csvTeste .= "14/01/2025,COMPRA CARTÃO - SUPERMERCADO,-85.50\n";
$csvTeste .= "13/01/2025,TRANSFERÊNCIA - PAGAMENTO,-200.00\n";
$csvTeste .= "12/01/2025,DEPÓSITO EM DINHEIRO,500.00\n";
$csvTeste .= "11/01/2025,SAQUE ATM,-100.00\n";

// Salvar arquivo de teste
$nomeArquivo = 'teste_extrato_' . $userId . '_' . time() . '.csv';
$caminhoArquivo = 'uploads/extratos/' . $nomeArquivo;

if (!is_dir('uploads/extratos/')) {
    mkdir('uploads/extratos/', 0755, true);
}

file_put_contents($caminhoArquivo, $csvTeste);

echo "<h3>📄 Arquivo CSV de Teste Criado:</h3>";
echo "<pre class='bg-light p-3 border rounded'>";
echo htmlspecialchars($csvTeste);
echo "</pre>";

// Processar CSV
echo "<h3>🔧 Processando CSV:</h3>";

try {
    $transacoes = extrairTransacoesCSV($caminhoArquivo);
    
    if (empty($transacoes)) {
        echo "<p>❌ Nenhuma transação encontrada.</p>";
    } else {
        echo "<div class='alert alert-success'>";
        echo "<h5>✅ Transações Encontradas: " . count($transacoes) . "</h5>";
        echo "</div>";
        
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
        
        // Testar importação
        echo "<h3>💾 Testando Importação no Banco:</h3>";
        
        $salvas = 0;
        $duplicadas = 0;
        $erros = 0;
        
        foreach ($transacoes as $transacao) {
            try {
                // Verificar se já existe
                $stmt = $pdo->prepare("
                    SELECT id FROM transacoes 
                    WHERE id_usuario = ? 
                    AND data = ? 
                    AND valor = ? 
                    AND descricao LIKE ?
                ");
                $stmt->execute([
                    $userId,
                    $transacao['data'],
                    $transacao['valor'],
                    '%' . substr($transacao['descricao'], 0, 20) . '%'
                ]);
                
                if ($stmt->fetch()) {
                    $duplicadas++;
                    echo "<p>⚠️ Transação duplicada: " . $transacao['descricao'] . "</p>";
                    continue;
                }
                
                // Inserir transação
                $stmt = $pdo->prepare("
                    INSERT INTO transacoes (
                        id_usuario, 
                        data, 
                        descricao, 
                        valor, 
                        tipo, 
                        categoria, 
                        data_criacao
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $userId,
                    $transacao['data'],
                    $transacao['descricao'],
                    $transacao['valor'],
                    $transacao['tipo'],
                    'Teste CSV'
                ]);
                
                $salvas++;
                echo "<p>✅ Transação salva: " . $transacao['descricao'] . "</p>";
                
            } catch (PDOException $e) {
                $erros++;
                echo "<p>❌ Erro ao salvar: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<div class='alert alert-info'>";
        echo "<h5>📊 Resultado da Importação:</h5>";
        echo "<ul>";
        echo "<li><strong>Salvas:</strong> $salvas</li>";
        echo "<li><strong>Duplicadas:</strong> $duplicadas</li>";
        echo "<li><strong>Erros:</strong> $erros</li>";
        echo "<li><strong>Total:</strong> " . count($transacoes) . "</li>";
        echo "</ul>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ Erro no Processamento:</h5>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

// Função para extrair transações do CSV (copiada do importar_extrato_csv.php)
function extrairTransacoesCSV($caminhoArquivo) {
    $transacoes = [];
    
    // Detectar encoding do arquivo
    $conteudo = file_get_contents($caminhoArquivo);
    $encoding = mb_detect_encoding($conteudo, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    
    if ($encoding !== 'UTF-8') {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding);
        file_put_contents($caminhoArquivo, $conteudo);
    }
    
    // Abrir arquivo CSV
    $handle = fopen($caminhoArquivo, 'r');
    if (!$handle) {
        throw new Exception('Não foi possível abrir o arquivo CSV');
    }
    
    $linha = 0;
    $cabecalhos = [];
    
    while (($dados = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $linha++;
        
        // Primeira linha - cabeçalhos
        if ($linha === 1) {
            $cabecalhos = array_map('trim', $dados);
            continue;
        }
        
        // Pular linhas vazias
        if (empty(array_filter($dados))) {
            continue;
        }
        
        // Processar linha de dados
        $transacao = processarLinhaCSV($dados, $cabecalhos);
        if ($transacao) {
            $transacoes[] = $transacao;
        }
    }
    
    fclose($handle);
    
    return $transacoes;
}

function processarLinhaCSV($dados, $cabecalhos) {
    // Mapear colunas comuns
    $mapeamento = [
        'data' => ['data', 'date', 'data_transacao', 'data_transaction'],
        'descricao' => ['descricao', 'description', 'desc', 'descricao_transacao'],
        'valor' => ['valor', 'value', 'amount', 'valor_transacao'],
        'tipo' => ['tipo', 'type', 'categoria', 'category']
    ];
    
    $transacao = [];
    
    // Encontrar índices das colunas
    $indices = [];
    foreach ($mapeamento as $campo => $variacoes) {
        foreach ($variacoes as $variacao) {
            $indice = array_search(strtolower($variacao), array_map('strtolower', $cabecalhos));
            if ($indice !== false) {
                $indices[$campo] = $indice;
                break;
            }
        }
    }
    
    // Se não encontrou as colunas necessárias, tentar por posição
    if (empty($indices['data']) || empty($indices['descricao']) || empty($indices['valor'])) {
        // Tentar formato padrão: Data, Descrição, Valor
        if (count($dados) >= 3) {
            $indices['data'] = 0;
            $indices['descricao'] = 1;
            $indices['valor'] = 2;
        } else {
            return null;
        }
    }
    
    // Extrair dados
    $data = isset($dados[$indices['data']]) ? trim($dados[$indices['data']]) : '';
    $descricao = isset($dados[$indices['descricao']]) ? trim($dados[$indices['descricao']]) : '';
    $valor = isset($dados[$indices['valor']]) ? trim($dados[$indices['valor']]) : '';
    
    if (empty($data) || empty($descricao) || empty($valor)) {
        return null;
    }
    
    // Normalizar data
    $dataNormalizada = normalizarDataCSV($data);
    if (!$dataNormalizada) {
        return null;
    }
    
    // Normalizar valor
    $valorNormalizado = normalizarValorCSV($valor);
    if ($valorNormalizado === null) {
        return null;
    }
    
    return [
        'data' => $dataNormalizada,
        'descricao' => $descricao,
        'valor' => $valorNormalizado,
        'tipo' => $valorNormalizado > 0 ? 'receita' : 'despesa'
    ];
}

function normalizarDataCSV($data) {
    // Tentar diferentes formatos de data
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
            return $dataObj->format('Y-m-d');
        }
    }
    
    // Tentar strtotime como último recurso
    $timestamp = strtotime($data);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

function normalizarValorCSV($valor) {
    // Remover espaços e caracteres especiais
    $valor = trim($valor);
    $valor = str_replace(['R$', '$', ' '], '', $valor);
    
    // Detectar formato brasileiro (1.234,56) vs americano (1234.56)
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        // Formato brasileiro: 1.234,56
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (strpos($valor, ',') !== false) {
        // Apenas vírgula: 1234,56
        $valor = str_replace(',', '.', $valor);
    }
    
    $valorFloat = floatval($valor);
    
    if ($valorFloat == 0 && $valor !== '0' && $valor !== '0,00' && $valor !== '0.00') {
        return null;
    }
    
    return $valorFloat;
}

echo "<hr>";
echo "<p><a href='importar_extrato_csv.php'>← Testar Importação CSV</a></p>";
echo "<p><a href='extrato_completo.php'>← Ver Extrato Completo</a></p>";
?>
