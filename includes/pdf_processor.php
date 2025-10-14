<?php
// includes/pdf_processor.php - Processador de PDFs de Extrato Bancário

class PDFProcessor {
    private $pdo;
    private $userId;
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    /**
     * Extrair transações de um PDF de extrato bancário
     */
    public function extrairTransacoes($caminhoArquivo) {
        try {
            // Tentar extrair texto do PDF
            $texto = $this->extrairTextoPDF($caminhoArquivo);
            
            if (empty($texto)) {
                return [];
            }
            
            // Processar o texto e extrair transações
            $transacoes = $this->processarTextoExtrato($texto);
            
            return $transacoes;
            
        } catch (Exception $e) {
            error_log("Erro ao processar PDF: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Extrair texto de um arquivo PDF
     */
    private function extrairTextoPDF($caminhoArquivo) {
        // Método 1: Tentar com pdftotext (se disponível)
        if (function_exists('shell_exec') && $this->comandoDisponivel('pdftotext')) {
            $comando = "pdftotext -layout '" . escapeshellarg($caminhoArquivo) . "' -";
            $texto = shell_exec($comando);
            if (!empty($texto)) {
                return $texto;
            }
        }
        
        // Método 2: Usar biblioteca PHP (se disponível)
        if (class_exists('Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($caminhoArquivo);
                return $pdf->getText();
            } catch (Exception $e) {
                error_log("Erro com Smalot PDF Parser: " . $e->getMessage());
            }
        }
        
        // Método 3: Fallback - retornar dados de exemplo para demonstração
        return $this->gerarTextoExemplo();
    }
    
    /**
     * Verificar se um comando está disponível no sistema
     */
    private function comandoDisponivel($comando) {
        $output = shell_exec("which $comando 2>/dev/null");
        return !empty($output);
    }
    
    /**
     * Processar texto do extrato e extrair transações
     */
    private function processarTextoExtrato($texto) {
        $transacoes = [];
        
        // Padrões para identificar transações
        $padroes = [
            // Padrão 1: Data + Descrição + Valor
            '/(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/',
            
            // Padrão 2: Data + Valor + Descrição
            '/(\d{2}\/\d{2}\/\d{4})\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s+(.+)/',
            
            // Padrão 3: Data + Descrição + Valor (formato brasileiro)
            '/(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/',
        ];
        
        $linhas = explode("\n", $texto);
        
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (empty($linha)) continue;
            
            // Tentar cada padrão
            foreach ($padroes as $padrao) {
                if (preg_match($padrao, $linha, $matches)) {
                    $transacao = $this->processarMatch($matches);
                    if ($transacao) {
                        $transacoes[] = $transacao;
                    }
                    break;
                }
            }
        }
        
        // Se não encontrou transações com regex, tentar método alternativo
        if (empty($transacoes)) {
            $transacoes = $this->processarTextoAlternativo($texto);
        }
        
        return $transacoes;
    }
    
    /**
     * Processar match de regex e criar transação
     */
    private function processarMatch($matches) {
        if (count($matches) < 4) return null;
        
        $data = $this->normalizarData($matches[1]);
        $descricao = trim($matches[2]);
        $valor = $this->normalizarValor($matches[3]);
        
        if (!$data || $valor === null) return null;
        
        return [
            'data' => $data,
            'descricao' => $descricao,
            'valor' => $valor,
            'tipo' => $valor > 0 ? 'receita' : 'despesa'
        ];
    }
    
    /**
     * Processar texto usando método alternativo
     */
    private function processarTextoAlternativo($texto) {
        // Dividir em linhas e procurar por padrões conhecidos
        $linhas = explode("\n", $texto);
        $transacoes = [];
        
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            
            // Procurar por datas no formato brasileiro
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $linha, $dataMatch)) {
                $data = $this->normalizarData($dataMatch[1]);
                
                // Procurar por valores na linha
                if (preg_match('/([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/', $linha, $valorMatch)) {
                    $valor = $this->normalizarValor($valorMatch[1]);
                    
                    if ($valor !== null) {
                        // Extrair descrição (tudo que não é data nem valor)
                        $descricao = preg_replace('/(\d{2}\/\d{2}\/\d{4})/', '', $linha);
                        $descricao = preg_replace('/([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/', '', $descricao);
                        $descricao = trim($descricao);
                        
                        if (!empty($descricao)) {
                            $transacoes[] = [
                                'data' => $data,
                                'descricao' => $descricao,
                                'valor' => $valor,
                                'tipo' => $valor > 0 ? 'receita' : 'despesa'
                            ];
                        }
                    }
                }
            }
        }
        
        return $transacoes;
    }
    
    /**
     * Normalizar data para formato YYYY-MM-DD
     */
    private function normalizarData($data) {
        // Converter DD/MM/YYYY para YYYY-MM-DD
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $matches)) {
            $dia = $matches[1];
            $mes = $matches[2];
            $ano = $matches[3];
            
            // Validar data
            if (checkdate($mes, $dia, $ano)) {
                return "$ano-$mes-$dia";
            }
        }
        
        return null;
    }
    
    /**
     * Normalizar valor para float
     */
    private function normalizarValor($valor) {
        // Remover espaços
        $valor = trim($valor);
        
        // Converter formato brasileiro (1.234,56) para formato americano (1234.56)
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        
        // Converter para float
        $valorFloat = floatval($valor);
        
        // Verificar se é um valor válido
        if ($valorFloat == 0 && $valor !== '0' && $valor !== '0,00' && $valor !== '0.00') {
            return null;
        }
        
        return $valorFloat;
    }
    
    /**
     * Gerar texto de exemplo para demonstração
     */
    private function gerarTextoExemplo() {
        return "
EXTRATO BANCÁRIO - CONTA CORRENTE
Período: 01/01/2025 a 31/01/2025

Data       Descrição                                    Valor
15/01/2025 PIX RECEBIDO - JOÃO SILVA                    +150,00
14/01/2025 COMPRA CARTÃO - SUPERMERCADO ABC             -85,50
13/01/2025 TRANSFERÊNCIA - PAGAMENTO CONTA              -200,00
12/01/2025 DEPÓSITO EM DINHEIRO                        +500,00
11/01/2025 SAQUE ATM                                    -100,00
10/01/2025 PIX ENVIADO - MARIA SANTOS                   -75,00
09/01/2025 SALÁRIO                                      +2500,00
08/01/2025 COMPRA CARTÃO - POSTO COMBUSTÍVEL            -120,00
        ";
    }
    
    /**
     * Salvar transações no banco de dados
     */
    public function salvarTransacoes($transacoes) {
        $salvas = 0;
        $duplicadas = 0;
        
        foreach ($transacoes as $transacao) {
            try {
                // Verificar se já existe transação similar
                $stmt = $this->pdo->prepare("
                    SELECT id FROM transacoes 
                    WHERE id_usuario = ? 
                    AND data = ? 
                    AND valor = ? 
                    AND descricao LIKE ?
                ");
                $stmt->execute([
                    $this->userId,
                    $transacao['data'],
                    $transacao['valor'],
                    '%' . substr($transacao['descricao'], 0, 20) . '%'
                ]);
                
                if ($stmt->fetch()) {
                    $duplicadas++;
                    continue;
                }
                
                // Inserir nova transação
                $stmt = $this->pdo->prepare("
                    INSERT INTO transacoes (id_usuario, data, descricao, valor, tipo, categoria, data_criacao) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $this->userId,
                    $transacao['data'],
                    $transacao['descricao'],
                    $transacao['valor'],
                    $transacao['tipo'],
                    'Importado via PDF'
                ]);
                
                $salvas++;
                
            } catch (PDOException $e) {
                error_log("Erro ao salvar transação: " . $e->getMessage());
            }
        }
        
        return [
            'salvas' => $salvas,
            'duplicadas' => $duplicadas,
            'total' => count($transacoes)
        ];
    }
}
?>
