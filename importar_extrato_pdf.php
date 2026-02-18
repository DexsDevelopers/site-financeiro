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
        
        // Salvar arquivo e processar com JavaScript no frontend
        $resultado['success'] = true;
        $resultado['message'] = 'Arquivo PDF carregado. Processando...';
        $resultado['arquivo'] = $caminhoArquivo;
        $resultado['transacoes'] = []; // Será preenchido pelo JavaScript
        
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
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <?php echo $resultado['message']; ?>
                            </div>
                            
                            <div id="processamento-pdf" class="mt-4">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Processando...</span>
                                    </div>
                                    <p class="mt-2">Extraindo texto do PDF...</p>
                                </div>
                            </div>
                            
                            <div id="resultado-processamento" style="display: none;">
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
                                        <tbody id="tabela-transacoes">
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

<!-- PDF.js para processamento no frontend -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<script>
// Configurar PDF.js
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let transacoesEncontradas = [];

// Processar PDF quando a página carregar (se houver resultado)
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($resultado && $resultado['success']): ?>
        processarPDF('<?php echo $resultado['arquivo']; ?>');
    <?php endif; ?>
});

async function processarPDF(caminhoArquivo) {
    try {
        // Buscar o arquivo PDF
        const response = await fetch(caminhoArquivo);
        if (!response.ok) {
            throw new Error(`Erro ao carregar arquivo: ${response.status}`);
        }
        
        const arrayBuffer = await response.arrayBuffer();
        
        // Verificar se é um PDF válido
        const uint8Array = new Uint8Array(arrayBuffer);
        const pdfHeader = String.fromCharCode(...uint8Array.slice(0, 4));
        
        if (pdfHeader !== '%PDF') {
            throw new Error('Arquivo não é um PDF válido');
        }
        
        // Configurar opções do PDF.js
        const loadingTask = pdfjsLib.getDocument({
            data: arrayBuffer,
            verbosity: 0, // Reduzir logs
            disableAutoFetch: true,
            disableStream: true
        });
        
        // Carregar PDF com timeout
        const pdf = await Promise.race([
            loadingTask.promise,
            new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Timeout ao carregar PDF')), 30000)
            )
        ]);
        
        let textoCompleto = '';
        
        // Extrair texto de todas as páginas
        for (let i = 1; i <= pdf.numPages; i++) {
            try {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(' ');
                textoCompleto += pageText + '\n';
            } catch (pageError) {
                console.warn(`Erro ao processar página ${i}:`, pageError);
                // Continuar com as outras páginas
            }
        }
        
        console.log('Texto extraído:', textoCompleto);
        
        if (textoCompleto.trim().length === 0) {
            throw new Error('Não foi possível extrair texto do PDF. O arquivo pode estar corrompido ou ser uma imagem escaneada.');
        }
        
        // Processar transações
        transacoesEncontradas = extrairTransacoesDoTexto(textoCompleto);
        
        // Mostrar resultado
        mostrarResultado(transacoesEncontradas);
        
    } catch (error) {
        console.error('Erro ao processar PDF:', error);
        
        let mensagemErro = 'Erro ao processar PDF: ' + error.message;
        
        // Mensagens mais amigáveis para erros comuns
        if (error.message.includes('Invalid PDF structure')) {
            mensagemErro = 'O arquivo PDF parece estar corrompido ou em formato não suportado. Tente com outro arquivo.';
        } else if (error.message.includes('Timeout')) {
            mensagemErro = 'O PDF é muito grande ou complexo. Tente com um arquivo menor.';
        } else if (error.message.includes('não é um PDF válido')) {
            mensagemErro = 'O arquivo não é um PDF válido. Verifique se o arquivo está correto.';
        }
        
        document.getElementById('processamento-pdf').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                ${mensagemErro}
                <div class="mt-2">
                    <small>
                        <strong>Dicas:</strong><br>
                        • Certifique-se de que o PDF tem texto selecionável (não é uma imagem escaneada)<br>
                        • Tente com um arquivo menor (menos de 5MB)<br>
                        • Verifique se o PDF não está corrompido
                    </small>
                </div>
                <div class="mt-3">
                    <button class="btn btn-outline-secondary btn-sm" onclick="reprocessarPDF()">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Tentar Novamente
                    </button>
                </div>
            </div>
        `;
    }
}

function extrairTransacoesDoTexto(texto) {
    const transacoes = [];
    const linhas = texto.split('\n');
    
    linhas.forEach(linha => {
        linha = linha.trim();
        if (linha.length < 10) return;
        
        // Padrões para extratos bancários brasileiros
        const padroes = [
            // Data + Descrição + Valor
            /(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)$/,
            // Data + Valor + Descrição
            /(\d{2}\/\d{2}\/\d{4})\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s+(.+)$/,
            // Data + Descrição + Valor (com espaços extras)
            /(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*$/
        ];
        
        for (const padrao of padroes) {
            const match = linha.match(padrao);
            if (match) {
                const transacao = criarTransacao(match);
                if (transacao) {
                    transacoes.push(transacao);
                }
                break;
            }
        }
    });
    
    return transacoes;
}

function criarTransacao(match) {
    let data, descricao, valor;
    
    if (match.length === 4) {
        // Padrão: Data + Descrição + Valor
        data = match[1];
        descricao = match[2];
        valor = match[3];
    } else if (match.length === 4) {
        // Padrão: Data + Valor + Descrição
        data = match[1];
        valor = match[2];
        descricao = match[3];
    }
    
    if (!data || !descricao || !valor) return null;
    
    // Normalizar data
    const dataNormalizada = normalizarData(data);
    if (!dataNormalizada) return null;
    
    // Normalizar valor
    const valorNormalizado = normalizarValor(valor);
    if (valorNormalizado === null) return null;
    
    return {
        data: dataNormalizada,
        descricao: descricao.trim(),
        valor: valorNormalizado,
        tipo: valorNormalizado > 0 ? 'receita' : 'despesa'
    };
}

function normalizarData(data) {
    // Converter DD/MM/YYYY para YYYY-MM-DD
    const match = data.match(/(\d{2})\/(\d{2})\/(\d{4})/);
    if (match) {
        const dia = match[1];
        const mes = match[2];
        const ano = match[3];
        
        // Validar data
        const dataObj = new Date(ano, mes - 1, dia);
        if (dataObj.getDate() == dia && dataObj.getMonth() == mes - 1 && dataObj.getFullYear() == ano) {
            return `${ano}-${mes}-${dia}`;
        }
    }
    return null;
}

function normalizarValor(valor) {
    // Remover espaços
    valor = valor.trim();
    
    // Converter formato brasileiro (1.234,56) para formato americano (1234.56)
    valor = valor.replace(/\./g, '');
    valor = valor.replace(',', '.');
    
    // Converter para float
    const valorFloat = parseFloat(valor);
    
    // Verificar se é um valor válido
    if (isNaN(valorFloat)) return null;
    
    return valorFloat;
}

function mostrarResultado(transacoes) {
    document.getElementById('processamento-pdf').style.display = 'none';
    document.getElementById('resultado-processamento').style.display = 'block';
    
    const tbody = document.getElementById('tabela-transacoes');
    tbody.innerHTML = '';
    
    if (transacoes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nenhuma transação encontrada</td></tr>';
    } else {
        transacoes.forEach(transacao => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${transacao.data}</td>
                <td>${transacao.descricao}</td>
                <td class="${transacao.valor > 0 ? 'text-success' : 'text-danger'}">
                    R$ ${transacao.valor.toFixed(2).replace('.', ',')}
                </td>
                <td>
                    <span class="badge bg-${transacao.tipo === 'receita' ? 'success' : 'danger'}">
                        ${transacao.tipo === 'receita' ? 'Receita' : 'Despesa'}
                    </span>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

function importarTransacoes() {
    if (transacoesEncontradas.length === 0) {
        alert('Nenhuma transação para importar.');
        return;
    }
    
    // Confirmar importação
    if (!confirm(`Deseja importar ${transacoesEncontradas.length} transações para seu extrato?`)) {
        return;
    }
    
    // Mostrar loading
    const btn = document.querySelector('button[onclick="importarTransacoes()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Importando...';
    btn.disabled = true;
    
    // Enviar para processamento
    const formData = new FormData();
    formData.append('transacoes', JSON.stringify(transacoesEncontradas));
    
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
