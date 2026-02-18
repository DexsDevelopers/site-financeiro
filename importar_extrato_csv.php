<?php
// importar_extrato_csv.php - Sistema de Importação de CSV de Extrato Bancário

date_default_timezone_set('America/Sao_Paulo');
require_once 'templates/header.php';

// Verificar se há upload em processamento
$processando = isset($_GET['processando']) ? true : false;
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_extrato'])) {
    $resultado = processarUploadCSV($_FILES['csv_extrato']);
}

function processarUploadCSV($arquivo) {
    global $pdo, $userId;
    
    $resultado = ['success' => false, 'message' => '', 'transacoes' => []];
    
    // Validar arquivo
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $resultado['message'] = 'Erro no upload do arquivo.';
        return $resultado;
    }
    
    // Verificar tipo de arquivo
    $tipoArquivo = mime_content_type($arquivo['tmp_name']);
    $tiposValidos = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
    
    if (!in_array($tipoArquivo, $tiposValidos)) {
        $resultado['message'] = 'Apenas arquivos CSV são permitidos.';
        return $resultado;
    }
    
    // Verificar extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if ($extensao !== 'csv') {
        $resultado['message'] = 'Apenas arquivos .csv são permitidos.';
        return $resultado;
    }
    
    // Verificar tamanho (máximo 5MB)
    if ($arquivo['size'] > 5 * 1024 * 1024) {
        $resultado['message'] = 'Arquivo muito grande. Máximo 5MB.';
        return $resultado;
    }
    
    try {
        // Salvar arquivo temporário
        $nomeArquivo = 'extrato_' . $userId . '_' . time() . '.csv';
        $caminhoArquivo = 'uploads/extratos/' . $nomeArquivo;
        
        // Criar diretório se não existir
        if (!is_dir('uploads/extratos/')) {
            mkdir('uploads/extratos/', 0755, true);
        }
        
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoArquivo)) {
            $resultado['message'] = 'Erro ao salvar arquivo.';
            return $resultado;
        }
        
        // Processar CSV
        $transacoes = extrairTransacoesCSV($caminhoArquivo);
        
        if (empty($transacoes)) {
            $resultado['message'] = 'Nenhuma transação encontrada no CSV.';
            return $resultado;
        }
        
        $resultado['success'] = true;
        $resultado['message'] = count($transacoes) . ' transações encontradas.';
        $resultado['transacoes'] = $transacoes;
        $resultado['arquivo'] = $caminhoArquivo;
        
    } catch (Exception $e) {
        $resultado['message'] = 'Erro ao processar CSV: ' . $e->getMessage();
    }
    
    return $resultado;
}

function extrairTransacoesCSV($caminhoArquivo) {
    $transacoes = [];
    
    // Detectar encoding do arquivo
    $conteudo = file_get_contents($caminhoArquivo);
    $encoding = mb_detect_encoding($conteudo, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    
    if ($encoding !== 'UTF-8') {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', $encoding);
        file_put_contents($caminhoArquivo, $conteudo);
    }
    
    // Detectar separador (vírgula ou ponto e vírgula)
    $separador = strpos($conteudo, ';') !== false ? ';' : ',';
    
    // Abrir arquivo CSV
    $handle = fopen($caminhoArquivo, 'r');
    if (!$handle) {
        throw new Exception('Não foi possível abrir o arquivo CSV');
    }
    
    $linha = 0;
    $cabecalhos = [];
    
    while (($dados = fgetcsv($handle, 1000, $separador)) !== FALSE) {
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
    // Mapear colunas comuns (mais variações)
    $mapeamento = [
        'data' => ['data', 'date', 'data_transacao', 'data_transaction', 'dt', 'data_movimentacao'],
        'descricao' => ['descricao', 'description', 'desc', 'descricao_transacao', 'historico', 'detalhes', 'observacao'],
        'valor' => ['valor', 'value', 'amount', 'valor_transacao', 'vlr', 'saldo', 'movimentacao'],
        'tipo' => ['tipo', 'type', 'categoria', 'category', 'natureza']
    ];
    
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
    
    // Validações básicas
    if (empty($data) || empty($descricao) || empty($valor)) {
        return null;
    }
    
    // Pular linhas que parecem ser cabeçalhos ou totais
    if (stripos($descricao, 'total') !== false || 
        stripos($descricao, 'saldo') !== false ||
        stripos($descricao, 'resumo') !== false) {
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
    $valor = str_replace(['R$', '$', ' ', '€', '£'], '', $valor);
    
    // Se estiver vazio após limpeza
    if (empty($valor)) {
        return null;
    }
    
    // Detectar formato brasileiro (1.234,56) vs americano (1234.56)
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        // Formato brasileiro: 1.234,56
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (strpos($valor, ',') !== false) {
        // Apenas vírgula: 1234,56
        $valor = str_replace(',', '.', $valor);
    }
    
    // Verificar se é um número válido
    if (!is_numeric($valor)) {
        return null;
    }
    
    $valorFloat = floatval($valor);
    
    // Aceitar zero apenas se for explicitamente zero
    if ($valorFloat == 0 && $valor !== '0' && $valor !== '0,00' && $valor !== '0.00' && $valor !== '0,0' && $valor !== '0.0') {
        return null;
    }
    
    return $valorFloat;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                    Importar Extrato CSV
                </h1>
                <div>
                    <a href="importar_extrato_pdf.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                    </a>
                    <a href="extrato_completo.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Voltar ao Extrato
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-upload me-2"></i>
                        Upload do Extrato CSV
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($resultado): ?>
                        <?php if ($resultado['success']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <?php echo $resultado['message']; ?>
                            </div>
                            
                            <div class="mt-4">
                                <h6>Transações Encontradas:</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Descrição</th>
                                                <th>Valor</th>
                                                <th>Tipo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($resultado['transacoes'] as $transacao): ?>
                                                <tr>
                                                    <td><?php echo $transacao['data']; ?></td>
                                                    <td><?php echo htmlspecialchars($transacao['descricao']); ?></td>
                                                    <td class="<?php echo $transacao['valor'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                        R$ <?php echo number_format($transacao['valor'], 2, ',', '.'); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $transacao['tipo'] === 'receita' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($transacao['tipo']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <button class="btn btn-success" onclick="importarTransacoes()">
                                        <i class="bi bi-check-lg me-1"></i>
                                        Importar Transações
                                    </button>
                                    <button class="btn btn-outline-secondary ms-2" onclick="reprocessarCSV()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>
                                        Reprocessar CSV
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo $resultado['message']; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="mb-4">
                                <label for="csv_extrato" class="form-label">
                                    <strong>Selecionar Arquivo CSV</strong>
                                </label>
                                <input type="file" class="form-control" id="csv_extrato" name="csv_extrato" 
                                       accept=".csv" required>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Apenas arquivos CSV são aceitos. Tamanho máximo: 5MB.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-lightbulb me-2"></i>
                                            Formato do CSV
                                        </h6>
                                        <p class="mb-2">O arquivo CSV deve ter as seguintes colunas:</p>
                                        <ul class="mb-0 small">
                                            <li><strong>Data:</strong> dd/mm/aaaa ou aaaa-mm-dd</li>
                                            <li><strong>Descrição:</strong> Descrição da transação</li>
                                            <li><strong>Valor:</strong> Valor numérico (positivo para receita, negativo para despesa)</li>
                                        </ul>
                                        
                                        <div class="mt-3">
                                            <h6>Exemplo de CSV:</h6>
                                            <pre class="bg-white p-2 border rounded small">Data,Descrição,Valor
15/01/2025,PIX RECEBIDO - JOÃO SILVA,150.00
14/01/2025,COMPRA CARTÃO - SUPERMERCADO,-85.50
13/01/2025,TRANSFERÊNCIA - PAGAMENTO,-200.00</pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-upload me-2"></i>
                                    Processar CSV
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Como Funciona
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-success">1. Upload do CSV</h6>
                        <p class="small mb-0">Faça upload do seu extrato bancário em CSV.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-success">2. Processamento Automático</h6>
                        <p class="small mb-0">O sistema detecta automaticamente as colunas.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-success">3. Revisão e Importação</h6>
                        <p class="small mb-0">Revise as transações encontradas e importe para seu painel.</p>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-warning small">
                        <i class="bi bi-shield-exclamation me-2"></i>
                        <strong>Importante:</strong> As transações importadas serão adicionadas ao seu extrato. 
                        Verifique se não há duplicatas antes de importar.
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-primary text-white">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-check-circle me-2"></i>
                        Formatos Suportados
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li><i class="bi bi-check text-success me-2"></i> CSV com separador vírgula (,)</li>
                        <li><i class="bi bi-check text-success me-2"></i> Encoding UTF-8 ou ISO-8859-1</li>
                        <li><i class="bi bi-check text-success me-2"></i> Cabeçalhos em português ou inglês</li>
                        <li><i class="bi bi-check text-success me-2"></i> Valores em formato brasileiro</li>
                        <li><i class="bi bi-check text-success me-2"></i> Datas em formato brasileiro</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function importarTransacoes() {
    const transacoes = <?php echo json_encode($resultado['transacoes'] ?? []); ?>;
    
    if (transacoes.length === 0) {
        alert('Nenhuma transação para importar.');
        return;
    }
    
    // Confirmar importação
    if (!confirm(`Deseja importar ${transacoes.length} transações para seu extrato?`)) {
        return;
    }
    
    // Mostrar loading
    const btn = document.querySelector('button[onclick="importarTransacoes()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Importando...';
    btn.disabled = true;
    
    // Enviar para processamento
    const formData = new FormData();
    formData.append('transacoes', JSON.stringify(transacoes));
    
    fetch('importar_transacoes_pdf.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Redirecionar para extrato
            window.location.href = 'extrato_completo.php';
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao importar transações.');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function reprocessarCSV() {
    // Voltar ao formulário de upload
    window.location.href = 'importar_extrato_csv.php';
}

// Preview do arquivo selecionado
document.getElementById('csv_extrato').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        console.log('Arquivo selecionado:', file.name, 'Tamanho:', fileSize + 'MB');
        
        // Mostrar preview do arquivo
        const preview = document.createElement('div');
        preview.className = 'alert alert-info mt-2';
        preview.innerHTML = `
            <i class="bi bi-file-earmark-spreadsheet me-2"></i>
            <strong>Arquivo selecionado:</strong> ${file.name}<br>
            <small>Tamanho: ${fileSize}MB</small>
        `;
        
        // Remover preview anterior se existir
        const previewAnterior = document.querySelector('.file-preview');
        if (previewAnterior) {
            previewAnterior.remove();
        }
        
        preview.className += ' file-preview';
        e.target.parentNode.appendChild(preview);
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
