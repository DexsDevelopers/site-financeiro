<?php
// importar_extrato_pdf.php - Sistema de Importação de PDF de Extrato Bancário

date_default_timezone_set('America/Sao_Paulo');
require_once 'templates/header.php';

// Verificar se há upload em processamento
$processando = isset($_GET['processando']) ? true : false;
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_extrato'])) {
    $resultado = processarUploadPDF($_FILES['pdf_extrato']);
}

function processarUploadPDF($arquivo) {
    global $pdo, $userId;
    
    $resultado = ['success' => false, 'message' => '', 'transacoes' => []];
    
    // Validar arquivo
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $resultado['message'] = 'Erro no upload do arquivo.';
        return $resultado;
    }
    
    // Verificar tipo de arquivo
    $tipoArquivo = mime_content_type($arquivo['tmp_name']);
    if ($tipoArquivo !== 'application/pdf') {
        $resultado['message'] = 'Apenas arquivos PDF são permitidos.';
        return $resultado;
    }
    
    // Verificar tamanho (máximo 10MB)
    if ($arquivo['size'] > 10 * 1024 * 1024) {
        $resultado['message'] = 'Arquivo muito grande. Máximo 10MB.';
        return $resultado;
    }
    
    try {
        // Salvar arquivo temporário
        $nomeArquivo = 'extrato_' . $userId . '_' . time() . '.pdf';
        $caminhoArquivo = 'uploads/extratos/' . $nomeArquivo;
        
        // Criar diretório se não existir
        if (!is_dir('uploads/extratos/')) {
            mkdir('uploads/extratos/', 0755, true);
        }
        
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoArquivo)) {
            $resultado['message'] = 'Erro ao salvar arquivo.';
            return $resultado;
        }
        
        // Processar PDF usando o processador
        require_once 'includes/pdf_processor.php';
        $processor = new PDFProcessor($pdo, $userId);
        $transacoes = $processor->extrairTransacoes($caminhoArquivo);
        
        if (empty($transacoes)) {
            $resultado['message'] = 'Nenhuma transação encontrada no PDF.';
            return $resultado;
        }
        
        $resultado['success'] = true;
        $resultado['message'] = count($transacoes) . ' transações encontradas.';
        $resultado['transacoes'] = $transacoes;
        $resultado['arquivo'] = $caminhoArquivo;
        
    } catch (Exception $e) {
        $resultado['message'] = 'Erro ao processar PDF: ' . $e->getMessage();
    }
    
    return $resultado;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-file-earmark-pdf me-2"></i>
                    Importar Extrato PDF
                </h1>
                <a href="extrato_completo.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Voltar ao Extrato
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-upload me-2"></i>
                        Upload do Extrato Bancário
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
                                    <button class="btn btn-outline-secondary ms-2" onclick="reprocessarPDF()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>
                                        Reprocessar PDF
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
                                <label for="pdf_extrato" class="form-label">
                                    <strong>Selecionar Arquivo PDF</strong>
                                </label>
                                <input type="file" class="form-control" id="pdf_extrato" name="pdf_extrato" 
                                       accept=".pdf" required>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Apenas arquivos PDF são aceitos. Tamanho máximo: 10MB.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-lightbulb me-2"></i>
                                            Dicas para Melhor Processamento
                                        </h6>
                                        <ul class="mb-0 small">
                                            <li>Use extratos em formato PDF padrão (não escaneados)</li>
                                            <li>Certifique-se de que o texto está selecionável</li>
                                            <li>Evite PDFs com imagens ou tabelas complexas</li>
                                            <li>O sistema reconhece automaticamente datas, valores e descrições</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-upload me-2"></i>
                                    Processar PDF
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
                        <h6 class="text-primary">1. Upload do PDF</h6>
                        <p class="small mb-0">Faça upload do seu extrato bancário em PDF.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary">2. Processamento Automático</h6>
                        <p class="small mb-0">O sistema extrai automaticamente as transações.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary">3. Revisão e Importação</h6>
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
                <div class="card-header bg-success text-white">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-check-circle me-2"></i>
                        Bancos Suportados
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li><i class="bi bi-check text-success me-2"></i> Banco do Brasil</li>
                        <li><i class="bi bi-check text-success me-2"></i> Caixa Econômica</li>
                        <li><i class="bi bi-check text-success me-2"></i> Bradesco</li>
                        <li><i class="bi bi-check text-success me-2"></i> Itaú</li>
                        <li><i class="bi bi-check text-success me-2"></i> Santander</li>
                        <li><i class="bi bi-check text-success me-2"></i> Nubank</li>
                        <li><i class="bi bi-check text-success me-2"></i> Inter</li>
                        <li><i class="bi bi-check text-success me-2"></i> Outros bancos</li>
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
    
    fetch('processar_importacao_pdf.php', {
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

function reprocessarPDF() {
    // Voltar ao formulário de upload
    window.location.href = 'importar_extrato_pdf.php';
}

// Preview do arquivo selecionado
document.getElementById('pdf_extrato').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        console.log('Arquivo selecionado:', file.name, 'Tamanho:', fileSize + 'MB');
        
        // Mostrar preview do arquivo
        const preview = document.createElement('div');
        preview.className = 'alert alert-info mt-2';
        preview.innerHTML = `
            <i class="bi bi-file-earmark-pdf me-2"></i>
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
