<?php
/**
 * OrionEngine v5.0 - Sistema de Controle Total com IA Avançada
 * 
 * Capacidades Completas:
 * ✅ Finanças: Receitas, Despesas, Transferências, Orçamentos
 * ✅ Tarefas: Criar, Editar, Concluir, Priorizar
 * ✅ Metas: Criar, Atualizar, Acompanhar
 * ✅ Rotinas: Diárias, Fixas, Academia
 * ✅ Cursos: Adicionar, Notas, Progresso
 * ✅ Análises: Relatórios, Insights, Previsões
 * ✅ Configurações: Categorias, Contas, Preferências
 */

class OrionEngine {
    private $pdo;
    private $userId;
    private $userName;
    private $conversationHistory = [];
    private $userContext = [];
    private $capabilities = [];

    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->loadUserInfo();
        $this->loadConversationHistory();
        $this->loadUserContext();
        $this->initializeCapabilities();
    }

    private function initializeCapabilities() {
        $this->capabilities = [
            'finance' => ['receita', 'despesa', 'transferência', 'orçamento', 'saldo', 'extrato'],
            'tasks' => ['tarefa', 'subtarefa', 'prioridade', 'prazo', 'concluir'],
            'goals' => ['meta', 'objetivo', 'progresso', 'acompanhar'],
            'routines' => ['rotina', 'hábito', 'academia', 'treino'],
            'courses' => ['curso', 'nota', 'estudo', 'aprendizado'],
            'analysis' => ['relatório', 'análise', 'insight', 'previsão', 'tendência'],
            'config' => ['categoria', 'conta', 'configuração', 'preferência']
        ];
    }

    private function loadUserInfo() {
        try {
            $stmt = $this->pdo->prepare("SELECT nome_completo FROM usuarios WHERE id = ?");
            $stmt->execute([$this->userId]);
            $this->userName = $stmt->fetchColumn() ?: 'Usuário';
        } catch (Exception $e) {
            $this->userName = 'Usuário';
        }
    }

    private function loadConversationHistory() {
        if (!isset($_SESSION['orion_history'])) {
            $_SESSION['orion_history'] = [];
        }
        $this->conversationHistory = $_SESSION['orion_history'];
    }

    private function saveToHistory($query, $response) {
        $this->conversationHistory[] = [
            'query' => $query,
            'response' => $response,
            'timestamp' => time()
        ];
        
        if (count($this->conversationHistory) > 10) {
            array_shift($this->conversationHistory);
        }
        
        $_SESSION['orion_history'] = $this->conversationHistory;
    }

    private function loadUserContext() {
        try {
            // Categoria mais usada
            $stmt = $this->pdo->prepare("
                SELECT c.nome, COUNT(*) as freq 
                FROM transacoes t 
                JOIN categorias c ON t.id_categoria = c.id 
                WHERE t.id_usuario = ? 
                GROUP BY c.nome 
                ORDER BY freq DESC 
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            $topCat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->userContext['top_category'] = $topCat ? $topCat['nome'] : null;
            $this->userContext['last_interaction'] = time();
        } catch (Exception $e) {
            // Silencioso
        }
    }

    public function processQuery($query) {
        $query = trim($query);
        $queryLower = mb_strtolower($query);

        // Análise de Intenção Avançada
        $intent = $this->detectIntent($queryLower);
        $entities = $this->extractEntities($query, $queryLower);

        $response = '';

        // Roteamento baseado em intenção
        switch ($intent['type']) {
            // ===== FINANÇAS =====
            case 'add_income':
                $response = $this->actionAddTransaction($query, $queryLower, $entities, 'receita');
                break;
            
            case 'add_expense':
                $response = $this->actionAddTransaction($query, $queryLower, $entities, 'despesa');
                break;
            
            case 'add_transfer':
                $response = $this->actionAddTransfer($query, $entities);
                break;
            
            case 'query_spending':
                $response = $this->analyzeSpending($queryLower, $entities);
                break;
            
            case 'query_income':
                $response = $this->analyzeIncome($queryLower, $entities);
                break;
            
            case 'query_balance':
                $response = $this->analyzeBalance($queryLower);
                break;
            
            case 'set_budget':
                $response = $this->actionSetBudget($query, $entities);
                break;
            
            // ===== TAREFAS =====
            case 'add_task':
                $response = $this->actionAddTask($query, $entities);
                break;
            
            case 'complete_task':
                $response = $this->actionCompleteTask($query, $entities);
                break;
            
            case 'edit_task':
                $response = $this->actionEditTask($query, $entities);
                break;
            
            case 'query_tasks':
                $response = $this->analyzeTasks($queryLower);
                break;
            
            // ===== METAS =====
            case 'add_goal':
                $response = $this->actionAddGoal($query, $entities);
                break;
            
            case 'update_goal':
                $response = $this->actionUpdateGoal($query, $entities);
                break;
            
            case 'query_goals':
                $response = $this->analyzeGoals($queryLower);
                break;
            
            // ===== ROTINAS =====
            case 'add_routine':
                $response = $this->actionAddRoutine($query, $entities);
                break;
            
            case 'query_routines':
                $response = $this->analyzeRoutines($queryLower);
                break;
            
            // ===== CURSOS =====
            case 'add_course':
                $response = $this->actionAddCourse($query, $entities);
                break;
            
            case 'add_note':
                $response = $this->actionAddCourseNote($query, $entities);
                break;
            
            case 'query_courses':
                $response = $this->analyzeCourses($queryLower);
                break;
            
            // ===== ANÁLISES =====
            case 'query_overview':
                $response = $this->getGeneralOverview();
                break;
            
            case 'query_report':
                $response = $this->generateReport($queryLower, $entities);
                break;
            
            case 'query_insights':
                $response = $this->generateInsights($queryLower);
                break;
            
            // ===== CONFIGURAÇÕES =====
            case 'add_category':
                $response = $this->actionAddCategory($query, $entities);
                break;
            
            case 'add_account':
                $response = $this->actionAddAccount($query, $entities);
                break;
            
            // ===== AJUDA =====
            case 'help':
                $response = $this->getHelp();
                break;
            
            default:
                $response = $this->intelligentFallback($query, $queryLower, $intent);
        }

        // Salva na memória
        $this->saveToHistory($query, $response);

        return $response;
    }

    /**
     * Detecta a intenção principal da mensagem com IA avançada
     */
    private function detectIntent($queryLower) {
        $intent = ['type' => 'unknown', 'confidence' => 0];

        // FINANÇAS - Receitas/Vendas
        if (preg_match('/\b(vendi|venda|recebi|ganhei|lucro|faturei|faturamento|entrada|depósito|pagamento recebido)\b/i', $queryLower)) {
            $intent = ['type' => 'add_income', 'confidence' => 0.9];
        }
        
        // FINANÇAS - Despesas/Compras
        elseif (preg_match('/\b(gastei|paguei|comprei|compra|gasto|despesa|saída|débito)\b/i', $queryLower)) {
            $intent = ['type' => 'add_expense', 'confidence' => 0.9];
        }
        
        // FINANÇAS - Transferências
        elseif (preg_match('/\b(transferi|transferência|mover|passar)\b.*\b(conta|banco)\b/i', $queryLower)) {
            $intent = ['type' => 'add_transfer', 'confidence' => 0.85];
        }
        
        // FINANÇAS - Orçamento
        elseif (preg_match('/\b(definir|criar|estabelecer)\b.*\b(orçamento|limite|teto)\b/i', $queryLower)) {
            $intent = ['type' => 'set_budget', 'confidence' => 0.85];
        }
        
        // TAREFAS - Adicionar
        elseif (preg_match('/\b(adicionar|criar|nova|novo|lembrar)\b.*\b(tarefa|lembrete|fazer)\b/i', $queryLower)) {
            $intent = ['type' => 'add_task', 'confidence' => 0.85];
        }
        
        // TAREFAS - Concluir
        elseif (preg_match('/\b(concluir|marcar|finalizar|feito|feita|completar)\b.*\b(tarefa)?\b/i', $queryLower)) {
            $intent = ['type' => 'complete_task', 'confidence' => 0.8];
        }
        
        // TAREFAS - Editar
        elseif (preg_match('/\b(editar|alterar|mudar|modificar)\b.*\b(tarefa)\b/i', $queryLower)) {
            $intent = ['type' => 'edit_task', 'confidence' => 0.8];
        }
        
        // METAS - Adicionar
        elseif (preg_match('/\b(criar|adicionar|nova|definir)\b.*\b(meta|objetivo)\b/i', $queryLower)) {
            $intent = ['type' => 'add_goal', 'confidence' => 0.85];
        }
        
        // METAS - Atualizar
        elseif (preg_match('/\b(atualizar|progredir|avançar)\b.*\b(meta|objetivo)\b/i', $queryLower)) {
            $intent = ['type' => 'update_goal', 'confidence' => 0.8];
        }
        
        // ROTINAS - Adicionar
        elseif (preg_match('/\b(criar|adicionar|nova)\b.*\b(rotina|hábito)\b/i', $queryLower)) {
            $intent = ['type' => 'add_routine', 'confidence' => 0.8];
        }
        
        // CURSOS - Adicionar
        elseif (preg_match('/\b(adicionar|criar|novo)\b.*\b(curso|aula|estudo)\b/i', $queryLower)) {
            $intent = ['type' => 'add_course', 'confidence' => 0.8];
        }
        
        // CURSOS - Nota
        elseif (preg_match('/\b(anotar|nota|anotação)\b.*\b(curso|aula)\b/i', $queryLower)) {
            $intent = ['type' => 'add_note', 'confidence' => 0.8];
        }
        
        // CONFIGURAÇÕES - Categoria
        elseif (preg_match('/\b(criar|adicionar|nova)\b.*\b(categoria)\b/i', $queryLower)) {
            $intent = ['type' => 'add_category', 'confidence' => 0.85];
        }
        
        // CONFIGURAÇÕES - Conta
        elseif (preg_match('/\b(criar|adicionar|nova)\b.*\b(conta|banco)\b/i', $queryLower)) {
            $intent = ['type' => 'add_account', 'confidence' => 0.85];
        }
        
        // CONSULTAS - Gastos
        elseif (preg_match('/\b(quanto|qual|mostre|liste|ver)\b.*\b(gasto|gastei|despesa)\b/i', $queryLower)) {
            $intent = ['type' => 'query_spending', 'confidence' => 0.85];
        }
        
        // CONSULTAS - Receitas
        elseif (preg_match('/\b(quanto|qual|mostre|liste|ver)\b.*\b(receita|ganhei|lucro)\b/i', $queryLower)) {
            $intent = ['type' => 'query_income', 'confidence' => 0.85];
        }
        
        // CONSULTAS - Tarefas (ANTES DE SALDO - PRIORIDADE)
        elseif (preg_match('/\b(tarefa|tarefas|fazer|pendente|agenda|to do)\b/i', $queryLower)) {
            $intent = ['type' => 'query_tasks', 'confidence' => 0.85];
        }
        
        // CONSULTAS - Saldo (DEPOIS DE TAREFAS)
        elseif (preg_match('/\b(saldo|dinheiro|quanto tenho)\b/i', $queryLower)) {
            $intent = ['type' => 'query_balance', 'confidence' => 0.8];
        }
        
        // CONSULTAS - Metas
        elseif (preg_match('/\b(meta|objetivo|progresso)\b/i', $queryLower)) {
            $intent = ['type' => 'query_goals', 'confidence' => 0.75];
        }
        
        // CONSULTAS - Rotinas
        elseif (preg_match('/\b(rotina|hábito|diário)\b/i', $queryLower)) {
            $intent = ['type' => 'query_routines', 'confidence' => 0.75];
        }
        
        // CONSULTAS - Cursos
        elseif (preg_match('/\b(curso|estudo|aprendizado)\b/i', $queryLower)) {
            $intent = ['type' => 'query_courses', 'confidence' => 0.75];
        }
        
        // ANÁLISES - Resumo Geral
        elseif (preg_match('/\b(resumo|panorama|visão geral|dashboard|overview)\b/i', $queryLower)) {
            $intent = ['type' => 'query_overview', 'confidence' => 0.8];
        }
        
        // ANÁLISES - Relatório
        elseif (preg_match('/\b(relatório|report|análise detalhada)\b/i', $queryLower)) {
            $intent = ['type' => 'query_report', 'confidence' => 0.8];
        }
        
        // ANÁLISES - Insights / Economia
        elseif (preg_match('/\b(insight|dica|sugestão|recomendação|conselho|economizar|economia)\b/i', $queryLower)) {
            $intent = ['type' => 'query_insights', 'confidence' => 0.75];
        }
        
        // AJUDA
        elseif (preg_match('/\b(ajuda|help|socorro|comandos|o que você faz|capacidades)\b/i', $queryLower)) {
            $intent = ['type' => 'help', 'confidence' => 0.9];
        }

        return $intent;
    }

    /**
     * Extrai entidades da mensagem (valores, datas, categorias, etc)
     */
    private function extractEntities($query, $queryLower) {
        $entities = [
            'value' => null,
            'description' => null,
            'category' => null,
            'date' => null,
            'priority' => null,
            'account' => null
        ];

        // Extrair VALOR (R$ 100, 100 reais, 100,50)
        if (preg_match('/(?:r\$\s*)?(\d+(?:[.,]\d{1,2})?)\s*(?:reais)?/i', $query, $matches)) {
            $entities['value'] = (float) str_replace(',', '.', $matches[1]);
        }

        // Extrair PRIORIDADE
        if (preg_match('/\b(alta|urgente|importante)\b/i', $queryLower)) {
            $entities['priority'] = 'Alta';
        } elseif (preg_match('/\b(média|normal)\b/i', $queryLower)) {
            $entities['priority'] = 'Média';
        } elseif (preg_match('/\b(baixa)\b/i', $queryLower)) {
            $entities['priority'] = 'Baixa';
        }

        // Extrair DATA (hoje, amanhã, próxima semana, 15/02)
        if (preg_match('/\bhoje\b/i', $queryLower)) {
            $entities['date'] = date('Y-m-d');
        } elseif (preg_match('/\bamanhã\b/i', $queryLower)) {
            $entities['date'] = date('Y-m-d', strtotime('+1 day'));
        } elseif (preg_match('/\bpróxima semana\b/i', $queryLower)) {
            $entities['date'] = date('Y-m-d', strtotime('+1 week'));
        } elseif (preg_match('/(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?/', $query, $dateMatch)) {
            $day = $dateMatch[1];
            $month = $dateMatch[2];
            $year = isset($dateMatch[3]) ? $dateMatch[3] : date('Y');
            if (strlen($year) == 2) $year = '20' . $year;
            $entities['date'] = "$year-$month-$day";
        }

        // Extrair DESCRIÇÃO (texto após valor ou verbo)
        $desc = $query;
        $stopwords = ['fiz', 'uma', 'venda', 'de', 'vendi', 'recebi', 'ganhei', 'gastei', 'paguei', 'comprei', 
                      'com', 'no', 'na', 'em', 'para', 'por', 'r$', 'reais', 'hoje', 'ontem', 'adicionar', 
                      'criar', 'nova', 'novo', 'tarefa', 'meta', 'rotina', 'curso'];
        foreach ($stopwords as $word) {
            $desc = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', '', $desc);
        }
        if ($entities['value']) {
            $desc = preg_replace('/\d+(?:[.,]\d{1,2})?/', '', $desc);
        }
        if ($entities['priority']) {
            $desc = preg_replace('/\b(alta|média|baixa|urgente|importante|normal)\b/i', '', $desc);
        }
        $desc = trim(preg_replace('/\s+/', ' ', $desc));
        $entities['description'] = $desc;

        // Detectar CATEGORIA
        try {
            $cats = $this->pdo->query("SELECT id, nome FROM categorias")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cats as $cat) {
                if (mb_stripos($queryLower, mb_strtolower($cat['nome'])) !== false) {
                    $entities['category'] = $cat;
                    break;
                }
            }
        } catch (Exception $e) {
            // Silencioso
        }

        return $entities;
    }

    // ===== AÇÕES FINANCEIRAS =====

    private function actionAddTransaction($query, $queryLower, $entities, $type) {
        $valor = $entities['value'];
        $descricao = $entities['description'];
        $categoria = $entities['category'];

        if (!$valor) {
            return "💭 Não consegui identificar o valor. Tente: 'Fiz uma venda de R$ 90' ou 'Gastei 50 em lanche'.";
        }

        if (strlen($descricao) < 3) {
            $descricao = $type == 'receita' ? "Receita via Orion" : "Despesa via Orion";
        } else {
            $descricao = ucfirst($descricao);
        }

        $idCategoria = $categoria ? $categoria['id'] : 18;

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO transacoes (id_usuario, tipo, valor, descricao, id_categoria, data_transacao, id_conta) 
                VALUES (?, ?, ?, ?, ?, CURDATE(), 0)
            ");
            $stmt->execute([$this->userId, $type, $valor, $descricao, $idCategoria]);
            
            $icon = $type == 'receita' ? '💰' : '💸';
            $tipoNome = $type == 'receita' ? 'Receita' : 'Despesa';
            $catInfo = $categoria ? " em **{$categoria['nome']}**" : "";
            
            return "$icon **Registrado!** $tipoNome de **R$ " . number_format($valor, 2, ',', '.') . "**$catInfo - $descricao";
        } catch (Exception $e) {
            return "❌ Erro ao salvar transação: " . $e->getMessage();
        }
    }

    private function actionAddTransfer($query, $entities) {
        return "🔄 Transferências entre contas ainda estão em desenvolvimento. Use a interface manual por enquanto.";
    }

    private function actionSetBudget($query, $entities) {
        $valor = $entities['value'];
        $categoria = $entities['category'];

        if (!$valor) {
            return "💭 Preciso saber o valor do orçamento. Ex: 'Definir orçamento de R$ 500 para alimentação'.";
        }

        if (!$categoria) {
            return "💭 Para qual categoria você quer definir o orçamento?";
        }

        try {
            // Verifica se já existe orçamento
            $stmt = $this->pdo->prepare("
                SELECT id FROM orcamentos 
                WHERE id_usuario = ? AND id_categoria = ? AND mes = MONTH(CURDATE()) AND ano = YEAR(CURDATE())
            ");
            $stmt->execute([$this->userId, $categoria['id']]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $stmt = $this->pdo->prepare("UPDATE orcamentos SET valor_limite = ? WHERE id = ?");
                $stmt->execute([$valor, $exists]);
                $action = "atualizado";
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO orcamentos (id_usuario, id_categoria, valor_limite, mes, ano) 
                    VALUES (?, ?, ?, MONTH(CURDATE()), YEAR(CURDATE()))
                ");
                $stmt->execute([$this->userId, $categoria['id'], $valor]);
                $action = "definido";
            }

            return "📊 **Orçamento $action!** R$ " . number_format($valor, 2, ',', '.') . " para **{$categoria['nome']}** este mês.";
        } catch (Exception $e) {
            return "❌ Erro ao definir orçamento.";
        }
    }

    // ===== AÇÕES DE TAREFAS =====

    private function actionAddTask($query, $entities) {
        $descricao = $entities['description'];
        $prioridade = $entities['priority'] ?: 'Média';
        $dataLimite = $entities['date'];

        if (strlen($descricao) < 3) {
            return "Por favor, especifique melhor a tarefa. Ex: 'Adicionar tarefa Pagar internet'.";
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO tarefas (id_usuario, descricao, status, prioridade, data_limite, data_criacao) 
                VALUES (?, ?, 'pendente', ?, ?, NOW())
            ");
            $stmt->execute([$this->userId, ucfirst($descricao), $prioridade, $dataLimite]);
            
            $prazoInfo = $dataLimite ? " (prazo: " . date('d/m/Y', strtotime($dataLimite)) . ")" : "";
            return "✅ **Tarefa criada!** \"$descricao\" - Prioridade: $prioridade$prazoInfo";
        } catch (Exception $e) {
            return "❌ Erro ao criar tarefa.";
        }
    }

    private function actionCompleteTask($query, $entities) {
        $termo = $entities['description'];

        if (empty($termo)) {
            return "Qual tarefa você quer concluir? Diga parte do nome.";
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, descricao 
                FROM tarefas 
                WHERE id_usuario = ? AND status = 'pendente' AND descricao LIKE ? 
                ORDER BY prioridade='Alta' DESC
                LIMIT 1
            ");
            $stmt->execute([$this->userId, "%$termo%"]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                $stmtUpd = $this->pdo->prepare("UPDATE tarefas SET status = 'concluida', data_conclusao = NOW() WHERE id = ?");
                $stmtUpd->execute([$task['id']]);
                return "🎉 **Parabéns!** Tarefa \"{$task['descricao']}\" concluída!";
            } else {
                return "🤔 Não encontrei tarefa pendente com \"$termo\".";
            }
        } catch (Exception $e) {
            return "❌ Erro ao concluir tarefa.";
        }
    }

    private function actionEditTask($query, $entities) {
        return "✏️ Edição de tarefas via comando ainda está em desenvolvimento. Use a interface por enquanto.";
    }

    // ===== AÇÕES DE METAS =====

    private function actionAddGoal($query, $entities) {
        $descricao = $entities['description'];
        $valor = $entities['value'];

        if (strlen($descricao) < 3) {
            return "Descreva melhor sua meta. Ex: 'Criar meta de economizar R$ 1000'.";
        }

        if (!$valor) {
            return "Qual o valor da meta? Ex: 'Meta de R$ 1000'.";
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO metas (id_usuario, descricao, valor_alvo, valor_atual, data_criacao) 
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$this->userId, ucfirst($descricao), $valor]);
            
            return "🎯 **Meta criada!** \"$descricao\" - Alvo: R$ " . number_format($valor, 2, ',', '.');
        } catch (Exception $e) {
            return "❌ Erro ao criar meta.";
        }
    }

    private function actionUpdateGoal($query, $entities) {
        return "📈 Atualização de metas via comando em desenvolvimento.";
    }

    // ===== AÇÕES DE ROTINAS =====

    private function actionAddRoutine($query, $entities) {
        return "📅 Criação de rotinas via comando em desenvolvimento.";
    }

    // ===== AÇÕES DE CURSOS =====

    private function actionAddCourse($query, $entities) {
        $descricao = $entities['description'];

        if (strlen($descricao) < 3) {
            return "Qual o nome do curso? Ex: 'Adicionar curso Python Avançado'.";
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cursos (id_usuario, nome, status, data_criacao) 
                VALUES (?, ?, 'em_andamento', NOW())
            ");
            $stmt->execute([$this->userId, ucfirst($descricao)]);
            
            return "📚 **Curso adicionado!** \"$descricao\"";
        } catch (Exception $e) {
            return "❌ Erro ao adicionar curso.";
        }
    }

    private function actionAddCourseNote($query, $entities) {
        return "📝 Anotações de curso via comando em desenvolvimento.";
    }

    // ===== AÇÕES DE CONFIGURAÇÃO =====

    private function actionAddCategory($query, $entities) {
        $nome = $entities['description'];

        if (strlen($nome) < 2) {
            return "Qual o nome da categoria? Ex: 'Criar categoria Freelance'.";
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO categorias (nome, id_usuario) VALUES (?, ?)");
            $stmt->execute([ucfirst($nome), $this->userId]);
            
            return "🏷️ **Categoria criada!** \"$nome\"";
        } catch (Exception $e) {
            return "❌ Erro ao criar categoria.";
        }
    }

    private function actionAddAccount($query, $entities) {
        return "🏦 Criação de contas via comando em desenvolvimento.";
    }

    // ===== ANÁLISES =====

    private function analyzeSpending($query, $entities) {
        $periodo = "MONTH(data_transacao) = MONTH(CURDATE()) AND YEAR(data_transacao) = YEAR(CURDATE())";
        $periodoNome = "este mês";
        
        if (mb_strpos($query, 'passado') !== false) {
            $periodo = "MONTH(data_transacao) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
            $periodoNome = "no mês passado";
        }

        $catFilter = "";
        $catName = "";
        
        if ($entities['category']) {
            $catFilter = "AND id_categoria = " . $entities['category']['id'];
            $catName = $entities['category']['nome'];
        }

        $sql = "SELECT SUM(valor) FROM transacoes WHERE id_usuario = ? AND tipo = 'despesa' AND $periodo $catFilter";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->userId]);
        $total = $stmt->fetchColumn() ?: 0;

        if ($catName) {
            return "💸 Gastos com **$catName** $periodoNome: **R$ " . number_format($total, 2, ',', '.') . "**";
        } else {
            if ($total > 0) {
                $sqlTop = "
                    SELECT c.nome, SUM(t.valor) as v 
                    FROM transacoes t 
                    JOIN categorias c ON t.id_categoria = c.id 
                    WHERE t.id_usuario = ? AND t.tipo = 'despesa' AND $periodo 
                    GROUP BY c.nome 
                    ORDER BY v DESC 
                    LIMIT 3
                ";
                $stmtTop = $this->pdo->prepare($sqlTop);
                $stmtTop->execute([$this->userId]);
                $tops = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
                
                $msg = "💸 Total de despesas $periodoNome: **R$ " . number_format($total, 2, ',', '.') . "**\n\n**Top 3 Categorias:**\n";
                foreach ($tops as $t) {
                    $msg .= "- {$t['nome']}: R$ " . number_format($t['v'], 2, ',', '.') . "\n";
                }
                return $msg;
            }
            return "Nenhuma despesa registrada $periodoNome.";
        }
    }

    private function analyzeIncome($query, $entities) {
        $sql = "SELECT SUM(valor) FROM transacoes WHERE id_usuario = ? AND tipo = 'receita' AND MONTH(data_transacao) = MONTH(CURDATE())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->userId]);
        $total = $stmt->fetchColumn() ?: 0;
        
        return "💰 Receitas este mês: **R$ " . number_format($total, 2, ',', '.') . "**";
    }

    private function analyzeBalance($query) {
        try {
            // Saldo inicial das contas
            $stmtContas = $this->pdo->prepare("SELECT COALESCE(SUM(saldo_inicial), 0) FROM contas WHERE id_usuario = ?");
            $stmtContas->execute([$this->userId]);
            $saldoInicial = $stmtContas->fetchColumn() ?: 0;
            
            // Receitas DO MÊS ATUAL (Igual ao Dashboard)
            $sqlRec = "SELECT COALESCE(SUM(valor), 0) FROM transacoes WHERE id_usuario = ? AND tipo = 'receita' AND MONTH(data_transacao) = MONTH(CURDATE()) AND YEAR(data_transacao) = YEAR(CURDATE())";
            $stmtReceitas = $this->pdo->prepare($sqlRec);
            $stmtReceitas->execute([$this->userId]);
            $receitas = $stmtReceitas->fetchColumn() ?: 0;
            
            // Despesas DO MÊS ATUAL (Igual ao Dashboard)
            $sqlDesp = "SELECT COALESCE(SUM(valor), 0) FROM transacoes WHERE id_usuario = ? AND tipo = 'despesa' AND MONTH(data_transacao) = MONTH(CURDATE()) AND YEAR(data_transacao) = YEAR(CURDATE())";
            $stmtDespesas = $this->pdo->prepare($sqlDesp);
            $stmtDespesas->execute([$this->userId]);
            $despesas = $stmtDespesas->fetchColumn() ?: 0;
            
            // Saldo total calculado da mesma forma que o Dashboard
            $saldo = $saldoInicial + $receitas - $despesas;
            
            $status = $saldo >= 0 ? "✅" : "⚠️";
            // Adicional: Mostra o fluxo do mês para clareza
            return "$status **Saldo Atual (Mês):** R$ " . number_format($saldo, 2, ',', '.') . "\n" .
                   "📊 (Inicial: " . number_format($saldoInicial, 2, ',', '.') . " + Rec: " . number_format($receitas, 2, ',', '.') . " - Desp: " . number_format($despesas, 2, ',', '.') . ")";
        } catch (Exception $e) {
            return "❌ Erro ao calcular saldo: " . $e->getMessage();
        }
    }

    private function analyzeTasks($query) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tarefas 
            WHERE id_usuario = ? AND status='pendente' 
            ORDER BY prioridade='Alta' DESC, data_limite ASC 
            LIMIT 5
        ");
        $stmt->execute([$this->userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tasks)) {
            return "🎉 Nenhuma tarefa pendente!";
        }
        
        $msg = "📋 **Suas Tarefas:**\n\n";
        foreach ($tasks as $t) {
            $icon = $t['prioridade'] === 'Alta' ? '🔴' : ($t['prioridade'] === 'Média' ? '🟡' : '🟢');
            $msg .= "$icon **{$t['descricao']}**";
            if ($t['data_limite']) {
                $msg .= " (até " . date('d/m', strtotime($t['data_limite'])) . ")";
            }
            $msg .= "\n";
        }
        
        return $msg;
    }

    private function analyzeGoals($query) {
        $stmt = $this->pdo->prepare("SELECT * FROM metas WHERE id_usuario = ? ORDER BY data_criacao DESC LIMIT 3");
        $stmt->execute([$this->userId]);
        $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($goals)) {
            return "🎯 Você ainda não tem metas definidas.";
        }
        
        $msg = "🎯 **Suas Metas:**\n\n";
        foreach ($goals as $g) {
            $progresso = ($g['valor_atual'] / $g['valor_alvo']) * 100;
            $msg .= "- **{$g['descricao']}**: R$ " . number_format($g['valor_atual'], 2, ',', '.') . " / R$ " . number_format($g['valor_alvo'], 2, ',', '.') . " (" . round($progresso) . "%)\n";
        }
        
        return $msg;
    }

    private function analyzeRoutines($query) {
        return "📅 Análise de rotinas em desenvolvimento.";
    }

    private function analyzeCourses($query) {
        $stmt = $this->pdo->prepare("SELECT * FROM cursos WHERE id_usuario = ? ORDER BY data_criacao DESC LIMIT 3");
        $stmt->execute([$this->userId]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($courses)) {
            return "📚 Nenhum curso cadastrado.";
        }
        
        $msg = "📚 **Seus Cursos:**\n\n";
        foreach ($courses as $c) {
            $status = $c['status'] == 'em_andamento' ? '📖' : '✅';
            $msg .= "$status **{$c['nome']}**\n";
        }
        
        return $msg;
    }

    private function getGeneralOverview() {
        $saldo = $this->analyzeBalance("");
        $tarefas = $this->analyzeTasks("");
        
        return "### 📊 Resumo Geral\n\n$saldo\n\n$tarefas\n\n💡 *Posso fazer muito mais! Digite 'ajuda' para ver.*";
    }

    private function generateReport($query, $entities) {
        return "📊 Relatórios detalhados em desenvolvimento.";
    }

    private function generateInsights($query) {
        // Análise inteligente de padrões
        try {
            $insights = [];
            
            // Insight 1: Categoria com mais gastos
            $stmt = $this->pdo->prepare("
                SELECT c.nome, SUM(t.valor) as total 
                FROM transacoes t 
                JOIN categorias c ON t.id_categoria = c.id 
                WHERE t.id_usuario = ? AND t.tipo = 'despesa' AND MONTH(t.data_transacao) = MONTH(CURDATE())
                GROUP BY c.nome 
                ORDER BY total DESC 
                LIMIT 1
            ");
            $stmt->execute([$this->userId]);
            $topCat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($topCat) {
                $categoryName = strtolower($topCat['nome']);
                $val = number_format($topCat['total'], 2, ',', '.');
                $insights[] = "💡 **Análise de Gastos:** Sua maior despesa este mês é em **{$topCat['nome']}** (R$ $val).";
                
                // Dica contextual baseada na categoria
                if (strpos($categoryName, 'alimentação') !== false || strpos($categoryName, 'restaurante') !== false || strpos($categoryName, 'ifood') !== false) {
                    $insights[] = "🥗 **Dica:** Gastos com alimentação costumam ser os maiores vilões. Que tal definir um limite semanal para delivery?";
                } elseif (strpos($categoryName, 'transporte') !== false || strpos($categoryName, 'uber') !== false || strpos($categoryName, 'combustível') !== false) {
                    $insights[] = "🚗 **Dica:** Para economizar em transporte, tente planejar rotas ou usar alternativas mais baratas em dias específicos.";
                } elseif (strpos($categoryName, 'lazer') !== false) {
                    $insights[] = "h **Dica:** Lazer é importante, mas tente buscar opções gratuitas no fim de semana para equilibrar o orçamento.";
                } else {
                    $insights[] = "💰 **Dica:** Tente reduzir em 10% os gastos com {$categoryName} no próximo mês para criar uma reserva.";
                }
            } else {
                $insights[] = "✨ Você ainda não tem despesas registradas este mês. Ótimo começo para economizar!";
            }
            
            // Insight 2: Tarefas atrasadas
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM tarefas 
                WHERE id_usuario = ? AND status = 'pendente' AND data_limite < CURDATE()
            ");
            $stmt->execute([$this->userId]);
            $atrasadas = $stmt->fetchColumn();
            
            if ($atrasadas > 0) {
                $insights[] = "⚠️ **Atenção:** Você tem **$atrasadas tarefa(s) atrasada(s)**. Resolver pendências reduz o estresse e aumenta a produtividade.";
            }
            
            if (empty($insights)) {
                return "✨ Tudo está em ordem! Continue mantendo suas finanças e tarefas organizadas.";
            }
            
            return implode("\n\n", $insights);
        } catch (Exception $e) {
            return "❌ Erro ao gerar insights.";
        }
    }

    private function getHelp() {
        return "### 🤖 Orion Engine v5.0 - Controle Total\n\n" .
               "Eu posso fazer TUDO no sistema! Exemplos:\n\n" .
               "**💰 Finanças:**\n" .
               "- \"Fiz uma venda de 90 reais\"\n" .
               "- \"Gastei 50 em lanche\"\n" .
               "- \"Definir orçamento de 500 para alimentação\"\n" .
               "- \"Quanto gastei este mês?\"\n" .
               "- \"Meu saldo\"\n\n" .
               "**📋 Tarefas:**\n" .
               "- \"Adicionar tarefa pagar internet com prioridade alta\"\n" .
               "- \"Concluir tarefa pagar luz\"\n" .
               "- \"Minhas tarefas pendentes\"\n\n" .
               "**🎯 Metas:**\n" .
               "- \"Criar meta de economizar 1000 reais\"\n" .
               "- \"Minhas metas\"\n\n" .
               "**📚 Cursos:**\n" .
               "- \"Adicionar curso Python Avançado\"\n" .
               "- \"Meus cursos\"\n\n" .
               "**🏷️ Configurações:**\n" .
               "- \"Criar categoria Freelance\"\n\n" .
               "**📊 Análises:**\n" .
               "- \"Resumo geral\"\n" .
               "- \"Me dê insights\"\n\n" .
               "💬 *Fale naturalmente comigo!*";
    }

    private function intelligentFallback($query, $queryLower, $intent) {
        if (count($this->conversationHistory) > 0) {
            $lastQuery = end($this->conversationHistory)['query'];
            
            if (mb_strpos($lastQuery, 'gasto') !== false && mb_strpos($queryLower, 'quanto') !== false) {
                return $this->analyzeSpending($queryLower, $this->extractEntities($query, $queryLower));
            }
        }

        $responses = [
            "💭 Não entendi completamente. Pode reformular?",
            "🤔 Interessante! Mas preciso de mais clareza. Tente 'Fiz uma venda de X' ou 'Gastei X em Y'.",
            "📝 Estou focado em **finanças**, **tarefas**, **metas** e muito mais. Digite 'ajuda' para ver tudo que posso fazer!",
        ];
        
        return $responses[array_rand($responses)] . "\n\n*Dica: Seja específico com valores e ações!*";
    }
}
