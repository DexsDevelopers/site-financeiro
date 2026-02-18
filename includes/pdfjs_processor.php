<?php
// includes/pdfjs_processor.php - Processador de PDF usando JavaScript no frontend

class PDFJSProcessor {
    private $pdo;
    private $userId;
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    /**
     * Processar transações extraídas pelo JavaScript
     */
    public function processarTransacoesExtraidas($texto) {
        if (empty($texto)) {
            return [];
        }
        
        // Log do texto recebido
        error_log("Texto recebido do JavaScript: " . substr($texto, 0, 500) . "...");
        
        $transacoes = [];
        $linhas = explode("\n", $texto);
        
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (empty($linha) || strlen($linha) < 10) continue;
            
            $transacao = $this->extrairTransacaoDaLinha($linha);
            if ($transacao) {
                $transacoes[] = $transacao;
            }
        }
        
        return $transacoes;
    }
    
    /**
     * Extrair transação de uma linha específica
     */
    private function extrairTransacaoDaLinha($linha) {
        // Padrões para extratos bancários brasileiros
        
        // Padrão 1: Data + Descrição + Valor (mais comum)
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)$/', $linha, $matches)) {
            return $this->criarTransacao($matches[1], $matches[2], $matches[3]);
        }
        
        // Padrão 2: Data + Valor + Descrição
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s+(.+)$/', $linha, $matches)) {
            return $this->criarTransacao($matches[1], $matches[3], $matches[2]);
        }
        
        // Padrão 3: Data + Descrição + Valor (com espaços extras)
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s+([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*$/', $linha, $matches)) {
            return $this->criarTransacao($matches[1], $matches[2], $matches[3]);
        }
        
        // Padrão 4: Procurar por data e valor na linha
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $linha, $dataMatch)) {
            $data = $dataMatch[1];
            
            // Procurar por valor na linha
            if (preg_match('/([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/', $linha, $valorMatch)) {
                $valor = $valorMatch[1];
                
                // Extrair descrição (tudo que não é data nem valor)
                $descricao = preg_replace('/(\d{2}\/\d{2}\/\d{4})/', '', $linha);
                $descricao = preg_replace('/([+-]?\d{1,3}(?:\.\d{3})*(?:,\d{2})?)/', '', $descricao);
                $descricao = trim($descricao);
                
                if (!empty($descricao)) {
                    return $this->criarTransacao($data, $descricao, $valor);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Criar transação a partir dos dados extraídos
     */
    private function criarTransacao($data, $descricao, $valor) {
        $dataNormalizada = $this->normalizarData($data);
        $valorNormalizado = $this->normalizarValor($valor);
        
        if (!$dataNormalizada || $valorNormalizado === null) {
            return null;
        }
        
        return [
            'data' => $dataNormalizada,
            'descricao' => trim($descricao),
            'valor' => $valorNormalizado,
            'tipo' => $valorNormalizado > 0 ? 'receita' : 'despesa'
        ];
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
