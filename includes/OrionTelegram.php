<?php
/**
 * OrionTelegram.php — IA Conversacional para Telegram
 * Contexto persistente · Aprendizado por uso · Teclados inline · Insights financeiros
 */
declare(strict_types=1);

class OrionTelegram
{
    private PDO    $pdo;
    private int    $userId;
    private int    $chatId;
    private string $userName;
    private array  $aprendizado = [];

    // ─── Padrões de intenção ──────────────────────────────────────────────────
    private const DESPESA_KW  = ['gastei','comprei','paguei','saiu','gasto','comi','bebi','fui','tomei',
                                  'conta de','boleto','aluguel','uber','ifood','compra','despesa','taxa',
                                  'assinatura','mensalidade','multa','mercado','farmácia','academia'];
    private const RECEITA_KW  = ['recebi','entrada','salário','ganhei','ganho','renda','freelance',
                                  'transferência','depositaram','caiu na conta','recebimento','pagamento recebido',
                                  'vendi','fiz uma venda','fiz venda','realizei uma venda','venda de','vendas de',
                                  'faturei','faturamento','lucrei','lucro de','cobrei','cobrança de',
                                  'cliente pagou','cliente me pagou','receita de','entrou na conta'];
    private const CONSULTA_KW = ['quanto gastei','quanto ganhei','meu saldo','ver saldo','saldo atual',
                                  'quanto tenho','resumo','relatório','extrato','minhas despesas',
                                  'minhas receitas','total do mês','gasto do mês','overview',
                                  'minhas tarefas','ver tarefas','listar tarefas','quais tarefas',
                                  'tarefas pendentes','o que tenho pra fazer','o que tenho que fazer',
                                  'minhas metas','ver metas','listar metas','tarefas','metas',
                                  'mês passado','semana passada','ano passado','este ano','insights',
                                  'análise','como estou','situação financeira','comparativo',
                                  'quanto gastei este ano','maior gasto','onde gastei mais',
                                  'quanto gastei em','quanto paguei de','quanto ganhei com',
                                  'gastos com','gastos de','despesas com','receitas de',
                                  // palavra solo 'saldo' e frases de histórico (devem vir ANTES de DESPESA_KW)
                                  'saldo','que gastei','que ganhei','que comprei','que paguei',
                                  'últimas compras','últimos gastos','últimos lançamentos',
                                  'últimas coisas','histórico','recentes','ver gastos','ver receitas',
                                  'tudo que gastei','tudo que ganhei','o que eu gastei'];
    private const TAREFA_KW   = ['criar tarefa','nova tarefa','lembrete','to do','tarefa para','adicionar tarefa',
                                  'preciso fazer','não esquecer','anotar','me lembre','me lembra','lembrar','lembrar de',
                                  'anota ai','anota aí','por favor anota','adicionar lembrete',
                                  'preciso ir','tenho que ir','não posso esquecer','coloca na agenda',
                                  'agenda para','agendar','coloca um lembrete','vou ter que'];
    private const META_KW     = ['criar meta','nova meta','meta de','objetivo de','quero juntar','poupar para',
                                  'adicionei na meta','depositei na meta','juntei para','quero economizar',
                                  'quero poupar','juntei na meta','contribui para a meta'];
    private const CORRECAO_KW  = ['errei','foi errado','era outro','na verdade','corrijo','estava errado',
                                  'não era','cancela','cancele','desfazer'];
    private const GERENCIAR_KW = ['prioridade alta','prioridade baixa','prioridade media','prioridade média',
                                  'coloque todas','marcar como feita','concluir tarefa','concluir todas',
                                  'deletar tarefa','remover tarefa','apagar tarefa',
                                  'marcar tarefa','todas as tarefas','todas tarefas',
                                  // reagendamento
                                  'para amanhã','para amanha','para hoje','adiar','reagendar',
                                  'mudar data','alterar data','coloque para','coloca para',
                                  'mover para','mova para','muda para','novo prazo',
                                  'alterar prazo','mudar prazo','trocar data'];
    private const ORCAMENTO_KW = ['definir orçamento','meu orçamento','orçamento de','limite de gastos',
                                  'orçamento para','ver orçamento','orçamento mensal','quanto posso gastar',
                                  'limite mensal','budget'];
    private const DIVIDA_KW    = ['devo para','me deve','emprestei para','me emprestou','tenho que pagar para',
                                  'dívida com','pagar para','cobrar de','minha dívida','minhas dívidas',
                                  'ver dívidas','quem me deve',
                                  'paguei a dívida','quitei a dívida','quitar dívida','me pagou a dívida',
                                  'recebi do','recebi da','paguei o que devia'];

    // ─── Constructor ──────────────────────────────────────────────────────────
    public function __construct(PDO $pdo, int $userId, int $chatId, string $userName = '')
    {
        $this->pdo      = $pdo;
        $this->userId   = $userId;
        $this->chatId   = $chatId;
        $this->userName = $userName ?: 'você';
        $this->criarTabelas();
        $this->carregarAprendizado();
    }

    private function criarTabelas(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tg_historico (
                id         BIGINT AUTO_INCREMENT PRIMARY KEY,
                chat_id    BIGINT NOT NULL,
                user_id    INT    NOT NULL,
                role       ENUM('user','bot') NOT NULL,
                mensagem   TEXT NOT NULL,
                intencao   VARCHAR(40) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_chat (chat_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tg_aprendizado (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                user_id      INT NOT NULL,
                expressao    VARCHAR(120) NOT NULL,
                categoria_id INT DEFAULT NULL,
                tipo         ENUM('despesa','receita') DEFAULT 'despesa',
                confirmacoes INT DEFAULT 1,
                updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_expr (user_id, expressao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tg_estados (
                chat_id    BIGINT PRIMARY KEY,
                estado     VARCHAR(40) DEFAULT 'idle',
                dados      JSON DEFAULT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tg_orcamentos (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario  INT NOT NULL,
                id_categoria INT NOT NULL,
                valor_limite DECIMAL(10,2) NOT NULL,
                mes         TINYINT NOT NULL COMMENT '1-12',
                ano         SMALLINT NOT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_oc (id_usuario, id_categoria, mes, ano)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tg_dividas (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario  INT NOT NULL,
                pessoa      VARCHAR(100) NOT NULL,
                valor       DECIMAL(10,2) NOT NULL,
                tipo        ENUM('devo','me_devem') NOT NULL,
                descricao   VARCHAR(255) DEFAULT '',
                status      ENUM('aberta','paga') DEFAULT 'aberta',
                data        DATE NOT NULL,
                created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_div (id_usuario)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tg_contexto (
                chat_id    BIGINT PRIMARY KEY,
                tipo       VARCHAR(40) NOT NULL,
                dados      JSON DEFAULT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function carregarAprendizado(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT expressao, categoria_id, tipo, confirmacoes FROM tg_aprendizado
            WHERE user_id = ? ORDER BY confirmacoes DESC
        ");
        $stmt->execute([$this->userId]);
        foreach ($stmt->fetchAll() as $row) {
            $this->aprendizado[mb_strtolower($row['expressao'])] = $row;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PONTO DE ENTRADA PRINCIPAL
    // ═══════════════════════════════════════════════════════════════════════

    public function processar(string $texto, ?string $callbackData = null): array
    {
        // Salva mensagem do usuário
        $this->salvarHistorico('user', $texto);

        // ── Callback de botão inline ────────────────────────────────────────
        if ($callbackData !== null) {
            $resp = $this->processarCallback($callbackData, $texto);
            $this->salvarHistorico('bot', $resp['texto'], $callbackData);
            return $resp;
        }

        $textoNorm = $this->normalizar($texto);

        // ── Estado atual (state machine) ────────────────────────────────────
        $estado = $this->getEstado();

        // Escape: se há estado pendente mas o usuário enviou um novo comando claro,
        // limpa o estado e processa como nova intenção
        if ($estado['estado'] !== 'idle') {
            $intencaoNova = $this->detectarIntencao($textoNorm);
            $isEscape = in_array($intencaoNova, ['tarefa','meta','consulta','correcao','despesa','receita','gerenciar','orcamento','divida'], true)
                || str_starts_with($textoNorm, '/')
                || in_array($textoNorm, ['cancelar','cancela','sair','voltar','pare','para'], true);
            if ($isEscape) {
                $this->limparEstado();
                $estado = ['estado' => 'idle', 'dados' => []];
            }
        }

        if ($estado['estado'] === 'aguardando_categoria') {
            return $this->processarEscolhaCategoria($textoNorm, $estado['dados']);
        }
        if ($estado['estado'] === 'aguardando_confirmacao') {
            return $this->processarConfirmacao($textoNorm, $estado['dados']);
        }
        if ($estado['estado'] === 'aguardando_valor') {
            return $this->processarValorFaltante($textoNorm, $estado['dados']);
        }
        if ($estado['estado'] === 'aguardando_correcao') {
            return $this->processarCorrecao($textoNorm, $estado['dados']);
        }

        // ── Comandos especiais ──────────────────────────────────────────────
        if (str_starts_with($textoNorm, '/')) {
            return $this->processarComando($textoNorm, $texto);
        }

        // ── Detectar intenção ───────────────────────────────────────────────
        $intencao = $this->detectarIntencao($textoNorm);
        $entidades = $this->extrairEntidades($texto);

        return match ($intencao) {
            'despesa', 'receita' => $this->iniciarLancamento($intencao, $entidades, $texto),
            'consulta'           => $this->processarConsulta($textoNorm),
            'tarefa'             => $this->processarTarefa($texto),
            'gerenciar'          => $this->gerenciarTarefas($textoNorm),
            'meta'               => $this->processarMeta($texto),
            'correcao'           => $this->iniciarCorrecao(),
            'orcamento'          => $this->processarOrcamento($texto),
            'divida'             => $this->processarDivida($texto),
            default              => $this->respostaGenerica($texto),
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DETECÇÃO DE INTENÇÃO
    // ═══════════════════════════════════════════════════════════════════════

    private function detectarIntencao(string $texto): string
    {
        // ── Frases interrogativas — SEMPRE consulta ──────────────────────────────
        if (preg_match('/^(que horas|a que horas|quando (eu |vc )?tenho que|quando (eu |vc )?preciso|que dia (eu )?tenho|qual (o )?hor[aá]rio|qual (o )?prazo)/iu', $texto)) {
            return 'consulta';
        }

        // ── Busca de transação específica — SEMPRE consulta ──────────────────────
        if (preg_match('/quanto\s+(gastei|paguei|ganhei|recebi)\s+(em|de|no|na|com)\s+\S/iu', $texto) ||
            preg_match('/gastos?\s+(com|em|de|no|na)\s+\S/iu', $texto)) {
            return 'consulta';
        }

        // ── Sistema de pontuação: frases mais longas e específicas valem mais ────
        $scores = ['correcao'=>0,'gerenciar'=>0,'orcamento'=>0,'divida'=>0,
                   'consulta'=>0,'tarefa'=>0,'meta'=>0,'receita'=>0,'despesa'=>0];
        $mapas  = [
            'correcao'  => self::CORRECAO_KW,
            'gerenciar' => self::GERENCIAR_KW,
            'orcamento' => self::ORCAMENTO_KW,
            'divida'    => self::DIVIDA_KW,
            'consulta'  => self::CONSULTA_KW,
            'tarefa'    => self::TAREFA_KW,
            'meta'      => self::META_KW,
            'receita'   => self::RECEITA_KW,
            'despesa'   => self::DESPESA_KW,
        ];
        foreach ($mapas as $intent => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($texto, $kw)) {
                    // Frases com espaço (multi-palavra) recebem bônus de especificidade
                    $scores[$intent] += mb_strlen($kw) + (str_contains($kw, ' ') ? 6 : 0);
                }
            }
        }

        // Intents de alta prioridade: se pontuaram, retornam imediatamente (antes de despesa/receita)
        foreach (['correcao','gerenciar','orcamento','divida'] as $prio) {
            if ($scores[$prio] > 0) return $prio;
        }

        // Entre consulta/tarefa/meta/receita/despesa → maior pontuação vence
        $melhor = null; $maxScore = 0;
        foreach (['consulta','tarefa','meta','receita','despesa'] as $intent) {
            if ($scores[$intent] > $maxScore) {
                $maxScore = $scores[$intent];
                $melhor   = $intent;
            }
        }
        if ($melhor) return $melhor;

        // Verificar aprendizado pessoal
        foreach ($this->aprendizado as $expr => $dado) {
            if (str_contains($texto, $expr) && $dado['confirmacoes'] >= 2) return $dado['tipo'];
        }

        // Valor monetário explícito (R$, reais, valor solto) → despesa
        // Ignora padrões de hora: 22h, 22h30, 22:00, 22 horas
        $textoSemHora = preg_replace('/\b\d{1,2}h(?:\d{2})?\b/i', '', $texto);
        $textoSemHora = preg_replace('/\b\d{1,2}:\d{2}\b/', '', $textoSemHora);
        $textoSemHora = preg_replace('/\b\d{1,2}\s+horas?\b/i', '', $textoSemHora);
        if (preg_match('/r\$|reais|\breais\b/i', $textoSemHora)) return 'despesa';
        if (preg_match('/\b\d+[,.]\d{2}\b/', $textoSemHora)) return 'despesa';
        return 'desconhecido';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EXTRAÇÃO DE ENTIDADES
    // ═══════════════════════════════════════════════════════════════════════

    private function extrairEntidades(string $texto): array
    {
        $entidades = ['valor' => null, 'descricao' => '', 'data' => date('Y-m-d'), 'categoria_id' => null];

        // Valor — strip padrões de hora (22h, 22h30, 22:00, 22 horas) antes de extrair
        $textoValor = preg_replace('/\b\d{1,2}h(?:\d{2})?\b/i', '', $texto);
        $textoValor = preg_replace('/\b\d{1,2}:\d{2}\b/', '', $textoValor);
        $textoValor = preg_replace('/\b\d{1,2}\s+horas?\b/i', '', $textoValor);
        if (preg_match('/r?\$?\s*(\d{1,3}(?:\.\d{3})*(?:,\d{2})?|\d+(?:[.,]\d{2})?)/i', $textoValor, $m)) {
            $entidades['valor'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        // Data
        $textoNorm = $this->normalizar($texto);
        if (str_contains($textoNorm, 'ontem')) {
            $entidades['data'] = date('Y-m-d', strtotime('-1 day'));
        } elseif (str_contains($textoNorm, 'anteontem')) {
            $entidades['data'] = date('Y-m-d', strtotime('-2 days'));
        } elseif (preg_match('/(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?/', $texto, $m)) {
            $ano = isset($m[3]) ? (strlen($m[3]) === 2 ? '20'.$m[3] : $m[3]) : date('Y');
            $entidades['data'] = "{$ano}-{$m[2]}-{$m[1]}";
        }

        // Parcelado: "em 12x de 150" | "12 parcelas de 50" | "12 vezes de 50"
        if (preg_match('/\bem\s+(\d+)\s*[xX]\s+de\s+r?\$?\s*([\d.,]+)/iu', $textoValor, $mP) ||
            preg_match('/(\d+)\s+(?:vezes?|parcelas?)\s+de\s+r?\$?\s*([\d.,]+)/iu', $textoValor, $mP)) {
            $qtd     = (int)$mP[1];
            $parcela = (float)str_replace(['.', ','], ['', '.'], $mP[2]);
            $entidades['valor']         = round($parcela * $qtd, 2);
            $entidades['parcelas']      = $qtd;
            $entidades['valor_parcela'] = $parcela;
        }

        // Descrição (remove valor e palavras-chave)
        $desc = preg_replace('/r?\$?\s*\d+[.,]?\d*/i', '', $texto);
        $desc = preg_replace('/\b(gastei|comprei|paguei|recebi|hoje|ontem|reais|real|no|na|de|em|do|da|pelo|pela|por)\b/i', ' ', $desc);
        $desc = preg_replace('/\s+/', ' ', trim($desc));
        $entidades['descricao'] = $desc ?: $texto;

        // Categoria pelo aprendizado
        $textoLow = mb_strtolower($texto);
        foreach ($this->aprendizado as $expr => $dado) {
            if (str_contains($textoLow, $expr) && $dado['confirmacoes'] >= 1 && $dado['categoria_id']) {
                $entidades['categoria_id'] = $dado['categoria_id'];
                break;
            }
        }

        // Categoria por palavras fixas
        if (!$entidades['categoria_id']) {
            $entidades['categoria_id'] = $this->inferirCategoria($textoNorm);
        }

        return $entidades;
    }

    private function inferirCategoria(string $texto): ?int
    {
        $mapa = [
            'Alimentação'  => ['mercado','supermercado','ifood','restaurante','lanche','pizza','hamburguer',
                                'almoço','jantar','café','comida','açougue','padaria','hortifruti','bebida'],
            'Transporte'   => ['uber','99','combustível','gasolina','estacionamento','ônibus','metrô',
                                'passagem','pedágio','táxi','moto','carro'],
            'Moradia'      => ['aluguel','condomínio','luz','energia','água','internet','gás','iptu','reforma'],
            'Saúde'        => ['farmácia','remédio','consulta','médico','plano de saúde','dentista',
                                'exame','hospital','academia','treino'],
            'Lazer'        => ['cinema','netflix','spotify','game','jogo','viagem','hotel','show','ingresso'],
            'Educação'     => ['curso','livro','escola','faculdade','mensalidade','material'],
            'Vestuário'    => ['roupa','calçado','tênis','blusa','camisa','sapato','loja'],
        ];
        foreach ($mapa as $nome => $palavras) {
            foreach ($palavras as $p) {
                if (str_contains($texto, $p)) {
                    $stmt = $this->pdo->prepare("SELECT id FROM categorias WHERE id_usuario = ? AND LOWER(nome) LIKE ? LIMIT 1");
                    $stmt->execute([$this->userId, '%'.mb_strtolower($nome).'%']);
                    $id = $stmt->fetchColumn();
                    if ($id) return (int)$id;
                    break;
                }
            }
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LANÇAMENTO (DESPESA / RECEITA)
    // ═══════════════════════════════════════════════════════════════════════

    private function iniciarLancamento(string $tipo, array $entidades, string $textoOriginal): array
    {
        if (!$entidades['valor']) {
            $this->setEstado('aguardando_valor', ['tipo' => $tipo, 'texto_original' => $textoOriginal, 'entidades' => $entidades]);
            return $this->resp("💬 Entendi que é uma <b>{$tipo}</b>, mas qual foi o valor?");
        }

        $dados = array_merge($entidades, ['tipo' => $tipo, 'texto_original' => $textoOriginal]);

        if (!$dados['categoria_id']) {
            // Pede categoria via teclado
            $this->setEstado('aguardando_categoria', $dados);
            return $this->respComTeclado(
                $this->formatarResumoLancamento($dados) . "\n\n📂 <b>Qual categoria?</b>",
                $this->tecladoCategorias($tipo)
            );
        }

        // Confirmação antes de salvar
        $this->setEstado('aguardando_confirmacao', $dados);
        return $this->respComTeclado(
            $this->formatarResumoLancamento($dados) . "\n\nConfirmar?",
            $this->tecladoConfirmacao()
        );
    }

    private function processarConfirmacao(string $texto, array $dados): array
    {
        if (in_array($texto, ['sim','s','yes','confirmar','ok','pode','salva','salvar','isso'])) {
            return $this->salvarLancamento($dados);
        }
        $this->limparEstado();
        return $this->resp("❌ Lançamento cancelado. Tudo bem!");
    }

    private function processarEscolhaCategoria(string $texto, array $dados): array
    {
        // Tenta achar a categoria pelo texto digitado
        $stmt = $this->pdo->prepare("SELECT id, nome FROM categorias WHERE id_usuario = ? AND LOWER(nome) LIKE ? LIMIT 1");
        $stmt->execute([$this->userId, '%'.trim($texto).'%']);
        $cat = $stmt->fetch();
        if ($cat) {
            $dados['categoria_id'] = $cat['id'];
            $this->setEstado('aguardando_confirmacao', $dados);
            return $this->respComTeclado(
                $this->formatarResumoLancamento($dados) . "\n\nConfirmar?",
                $this->tecladoConfirmacao()
            );
        }
        return $this->respComTeclado(
            "❓ Não encontrei essa categoria. Escolha uma:",
            $this->tecladoCategorias($dados['tipo'])
        );
    }

    private function processarValorFaltante(string $texto, array $dados): array
    {
        if (preg_match('/(\d+[.,]?\d*)/', $texto, $m)) {
            $dados['entidades']['valor'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
            $dados['entidades'] = array_merge($dados['entidades'], $this->extrairEntidades($dados['texto_original']));
            $dados['entidades']['valor'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
            $merged = array_merge($dados['entidades'], ['tipo' => $dados['tipo'], 'texto_original' => $dados['texto_original']]);
            $this->limparEstado();
            return $this->iniciarLancamento($dados['tipo'], $merged, $dados['texto_original']);
        }
        return $this->resp("🔢 Por favor, informe um valor numérico. Ex: <code>45.90</code>");
    }

    private function salvarLancamento(array $dados): array
    {
        $this->limparEstado();
        try {
            $tipo = $dados['tipo'];
            $valor = (float)$dados['valor'];
            $descricao = trim($dados['descricao'] ?: $dados['texto_original']);
            $data = $dados['data'] ?? date('Y-m-d');
            $catId = $dados['categoria_id'] ?? null;

            // Buscar conta principal
            $stmtConta = $this->pdo->prepare("SELECT id FROM contas WHERE id_usuario = ? LIMIT 1");
            $stmtConta->execute([$this->userId]);
            $contaId = $stmtConta->fetchColumn() ?: null;

            $tipoDb = ($tipo === 'receita') ? 'receita' : 'despesa';
            $this->pdo->prepare("
                INSERT INTO transacoes (id_usuario, descricao, valor, tipo, data_transacao, id_categoria, id_conta)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$this->userId, $descricao, $valor, $tipoDb, $data, $catId, $contaId]);

            // Aprender com este lançamento
            $this->aprenderPadrão($dados['texto_original'], $catId, $tipo);

            // Salvar no histórico
            $this->pdo->prepare("
                INSERT INTO tg_historico (chat_id, user_id, role, mensagem, intencao)
                VALUES (?, ?, 'bot', ?, 'lancamento_salvo')
            ")->execute([$this->chatId, $this->userId, "Salvo: $tipoDb R$ $valor"]);

            $icon   = ($tipo === 'receita') ? '💚' : '🔴';
            $sinal  = ($tipo === 'receita') ? '+' : '-';
            $resp   = "{$icon} <b>Salvo!</b>\n\n";
            $resp  .= "📝 <i>{$descricao}</i>\n";
            $resp  .= "💰 <b>R$ " . number_format($valor, 2, ',', '.') . "</b>\n";
            $resp  .= "📅 " . date('d/m/Y', strtotime($data)) . "\n\n";

            // Insight pós-lançamento
            $resp .= $this->insightPosLancamento($tipo, $catId, $valor);

            return $this->resp($resp);

        } catch (Throwable $e) {
            error_log('[OrionTelegram] salvarLancamento: ' . $e->getMessage());
            return $this->resp("❌ Erro ao salvar. Tente novamente.");
        }
    }

    private function insightPosLancamento(string $tipo, ?int $catId, float $valorAtual): string
    {
        if ($tipo !== 'despesa' || !$catId) return '';
        try {
            $stmt = $this->pdo->prepare("
                SELECT SUM(t.valor) as total, COUNT(*) as qtd, c.nome as cat_nome
                FROM transacoes t
                JOIN categorias c ON c.id = t.id_categoria
                WHERE t.id_usuario = ? AND t.id_categoria = ? AND t.tipo = 'despesa'
                AND YEAR(t.data_transacao) = YEAR(CURDATE()) AND MONTH(t.data_transacao) = MONTH(CURDATE())
            ");
            $stmt->execute([$this->userId, $catId]);
            $row = $stmt->fetch();
            if (!$row || !$row['total']) return '';

            $totalMes = (float)$row['total'];
            $vezes    = $row['qtd'];
            $catNome  = $row['cat_nome'];
            $out      = "📊 <i>Este mês: <b>R$ " . number_format($totalMes, 2, ',', '.') . "</b> em {$catNome} ({$vezes}x)</i>";

            // Verificar orçamento definido
            $mes = (int)date('n'); $ano = (int)date('Y');
            $stmtOrc = $this->pdo->prepare("
                SELECT valor_limite FROM tg_orcamentos
                WHERE id_usuario = ? AND id_categoria = ? AND mes = ? AND ano = ?
            ");
            $stmtOrc->execute([$this->userId, $catId, $mes, $ano]);
            $orc = $stmtOrc->fetchColumn();
            if ($orc && (float)$orc > 0) {
                $pct = round(($totalMes / (float)$orc) * 100);
                $limite = number_format((float)$orc, 2, ',', '.');
                if ($pct >= 100) {
                    $out .= "\n🔴 <b>Orçamento estourado!</b> Limite de R$ {$limite} ({$pct}% usado)";
                } elseif ($pct >= 80) {
                    $out .= "\n🟡 <i>Atenção: {$pct}% do orçamento de {$catNome} usado (R$ {$limite})</i>";
                }
            }
            return $out;
        } catch (Throwable $e) {}
        return '';
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CORREÇÃO
    // ═══════════════════════════════════════════════════════════════════════

    private function iniciarCorrecao(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, descricao, valor, tipo FROM transacoes
            WHERE id_usuario = ? ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        $ultima = $stmt->fetch();
        if (!$ultima) {
            return $this->resp("❓ Não encontrei nenhum lançamento recente para corrigir.");
        }
        $this->setEstado('aguardando_correcao', ['ultima_transacao' => $ultima]);
        return $this->respComTeclado(
            "↩️ Último lançamento:\n<b>{$ultima['descricao']}</b> — R$ " . number_format((float)$ultima['valor'], 2, ',', '.') . "\n\nO que quer fazer?",
            [
                [['text' => '🗑️ Excluir este lançamento', 'callback_data' => 'corr:excluir']],
                [['text' => '✏️ Digitar o correto agora', 'callback_data' => 'corr:redigitar']],
                [['text' => '↩️ Voltar sem alterar', 'callback_data' => 'corr:cancelar']],
            ]
        );
    }

    private function processarCorrecao(string $texto, array $dados): array
    {
        $this->limparEstado();
        // Redigitação direta
        return $this->processar($texto);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CONSULTAS FINANCEIRAS
    // ═══════════════════════════════════════════════════════════════════════

    private function processarConsulta(string $texto): array
    {
        // Pergunta sobre horário/data de tarefa específica
        if (preg_match('/que horas|a que horas|quando.*tenho.*que|quando.*preciso|que dia.*tenho|qual.*hor[aá]rio.*tarefa|qual.*prazo/iu', $texto)) {
            return $this->consultarHorarioTarefa($texto);
        }

        // Busca de transação específica: "quanto gastei em uber", "gastos com pizza"
        if (preg_match('/quanto\s+(gastei|paguei|ganhei|recebi)\s+(?:em|de|no|na|com)\s+(.+)/iu', $texto, $m)) {
            return $this->buscarTransacao(trim($m[2]), $m[1]);
        }
        if (preg_match('/gastos?\s+(?:com|em|de|no|na)\s+(.+)/iu', $texto, $m)) {
            return $this->buscarTransacao(trim($m[1]), 'gastei');
        }
        if (preg_match('/despesas?\s+(?:com|em|de|no|na)\s+(.+)/iu', $texto, $m)) {
            return $this->buscarTransacao(trim($m[1]), 'gastei');
        }

        // Follow-up contextual: "e ontem?", "e semana passada?"
        $periodoFU = null;
        if (preg_match('/^(?:e\s+|mas\s+|e\s+o\s+|e\s+a\s+)?(ontem|hoje|esta\s+semana|semana\s+passada|m[eê]s\s+passado|este\s+m[eê]s|este\s+ano|ano\s+passado)\??$/iu', $texto, $mFU)) {
            $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $mFU[1])));
            $mapFU = ['ontem'=>'ontem','hoje'=>'hoje','esta semana'=>'semana','semana passada'=>'semana_passada',
                      'mes passado'=>'mes_passado','mês passado'=>'mes_passado',
                      'este mes'=>'mes','este mês'=>'mes','este ano'=>'ano','ano passado'=>'ano'];
            foreach ($mapFU as $k => $v) {
                if (str_contains($key, $k)) { $periodoFU = $v; break; }
            }
        }
        if ($periodoFU) {
            if ($periodoFU === 'ontem') {
                $this->salvarContexto('periodo', ['periodo' => 'ontem']);
                $stmt = $this->pdo->prepare("SELECT tipo, SUM(valor) as total, COUNT(*) as qtd FROM transacoes WHERE id_usuario = ? AND DATE(data_transacao) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) GROUP BY tipo");
                $stmt->execute([$this->userId]);
                $rec = $desp = 0; $qR = $qD = 0;
                foreach ($stmt->fetchAll() as $r) {
                    if ($r['tipo']==='receita') { $rec=(float)$r['total']; $qR=(int)$r['qtd']; }
                    else { $desp=(float)$r['total']; $qD=(int)$r['qtd']; }
                }
                $saldo = $rec - $desp; $icon = $saldo >= 0 ? '🟢' : '🔴';
                $t = "📅 <b>Ontem</b>\n\n💚 Receitas: R$ ".number_format($rec,2,',','.').(" ({$qR}x)\n").
                     "🔴 Despesas: R$ ".number_format($desp,2,',','.').(" ({$qD}x)\n─────────────────\n").
                     "{$icon} <b>R$ ".number_format($saldo,2,',','.')."</b>";
                return $this->respComTeclado($t, $this->tecladoRelatorio());
            }
            return $this->consultarPeriodo($periodoFU);
        }

        if (str_contains($texto, 'tarefa') || str_contains($texto, 'pra fazer') || str_contains($texto, 'que fazer')) {
            return $this->listarTarefas();
        }
        if (str_contains($texto, 'meta')) {
            return $this->listarMetas();
        }
        if (str_contains($texto, 'insight') || str_contains($texto, 'análise') || str_contains($texto, 'como estou') || str_contains($texto, 'situação')) {
            return $this->consultarInsights();
        }
        if (str_contains($texto, 'últim') || str_contains($texto, 'histórico') || str_contains($texto, 'recente')
            || str_contains($texto, 'que gastei') || str_contains($texto, 'que ganhei')
            || str_contains($texto, 'que comprei') || str_contains($texto, 'que paguei')) {
            return $this->listarUltimas($texto);
        }
        if (str_contains($texto, 'saldo') || str_contains($texto, 'tenho')) {
            return $this->consultarSaldo();
        }
        if (str_contains($texto, 'mês passado') || str_contains($texto, 'mes passado')) {
            return $this->consultarPeriodo('mes_passado');
        }
        if (str_contains($texto, 'semana passada')) {
            return $this->consultarPeriodo('semana_passada');
        }
        if (str_contains($texto, 'ano') && (str_contains($texto, 'este') || str_contains($texto, 'esse') || str_contains($texto, 'gastei este ano'))) {
            return $this->consultarPeriodo('ano');
        }
        if (str_contains($texto, 'hoje')) {
            return $this->consultarPeriodo('hoje');
        }
        if (str_contains($texto, 'semana')) {
            return $this->consultarPeriodo('semana');
        }
        if (str_contains($texto, 'categor') || str_contains($texto, 'maior gasto') || str_contains($texto, 'onde gastei')) {
            return $this->consultarPorCategoria();
        }
        if (str_contains($texto, 'comparativo') || str_contains($texto, 'compara')) {
            return $this->consultarComparativo();
        }
        return $this->consultarPeriodo('mes');
    }

    private function consultarSaldo(): array
    {
        // Mês atual
        $stmt = $this->pdo->prepare("
            SELECT
              COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END),0) as receitas,
              COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) as despesas
            FROM transacoes
            WHERE id_usuario = ? AND YEAR(data_transacao) = YEAR(CURDATE()) AND MONTH(data_transacao) = MONTH(CURDATE())
        ");
        $stmt->execute([$this->userId]);
        $row = $stmt->fetch();
        $saldo = (float)$row['receitas'] - (float)$row['despesas'];

        // Mês anterior (para comparativo)
        $stmtAnt = $this->pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) as despesas
            FROM transacoes
            WHERE id_usuario = ?
            AND YEAR(data_transacao) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            AND MONTH(data_transacao) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        ");
        $stmtAnt->execute([$this->userId]);
        $antRow  = $stmtAnt->fetch();
        $despAnt = (float)$antRow['despesas'];
        $despAtu = (float)$row['despesas'];

        $icon  = $saldo >= 0 ? '🟢' : '🔴';
        $texto = "💳 <b>Resumo deste mês — " . date('M/Y') . "</b>\n\n";
        $texto .= "💚 Receitas: <b>R$ " . number_format((float)$row['receitas'], 2, ',', '.') . "</b>\n";
        $texto .= "🔴 Despesas: <b>R$ " . number_format($despAtu, 2, ',', '.') . "</b>";

        if ($despAnt > 0) {
            $diff = $despAtu - $despAnt;
            $pct  = round(abs($diff) / $despAnt * 100);
            $trend = $diff > 0 ? "↗️ +{$pct}% vs mês passado" : "↘️ -{$pct}% vs mês passado";
            $texto .= " <i>({$trend})</i>";
        }

        $texto .= "\n─────────────────\n";
        $texto .= "{$icon} Saldo: <b>R$ " . number_format($saldo, 2, ',', '.') . "</b>";

        // Alerta se saldo negativo
        if ($saldo < 0) {
            $texto .= "\n\n⚠️ <i>Atenção: suas despesas superam as receitas este mês!</i>";
        }
        return $this->respComTeclado($texto, $this->tecladoRelatorio());
    }

    private function consultarPeriodo(string $periodo): array
    {
        [$label, $where] = match($periodo) {
            'hoje'          => ['Hoje',            "DATE(data_transacao) = CURDATE()"],
            'semana'        => ['Esta semana',     "YEARWEEK(data_transacao,1) = YEARWEEK(CURDATE(),1)"],
            'semana_passada'=> ['Semana passada',  "YEARWEEK(data_transacao,1) = YEARWEEK(DATE_SUB(CURDATE(),INTERVAL 1 WEEK),1)"],
            'mes_passado'   => ['Mês passado',     "YEAR(data_transacao) = YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND MONTH(data_transacao) = MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))"],
            'ano'           => ['Este ano',        "YEAR(data_transacao) = YEAR(CURDATE())"],
            default         => ['Este mês',        "YEAR(data_transacao) = YEAR(CURDATE()) AND MONTH(data_transacao) = MONTH(CURDATE())"],
        };
        $stmt = $this->pdo->prepare("
            SELECT tipo, SUM(valor) as total, COUNT(*) as qtd
            FROM transacoes WHERE id_usuario = ? AND {$where}
            GROUP BY tipo
        ");
        $stmt->execute([$this->userId]);
        $rec = $desp = 0; $qRec = $qDesp = 0;
        foreach ($stmt->fetchAll() as $r) {
            if ($r['tipo'] === 'receita') { $rec  = (float)$r['total']; $qRec  = (int)$r['qtd']; }
            else                          { $desp = (float)$r['total']; $qDesp = (int)$r['qtd']; }
        }
        $saldo = $rec - $desp;
        $icon  = $saldo >= 0 ? '🟢' : '🔴';
        $t  = "📅 <b>{$label}</b>\n\n";
        $t .= "💚 Receitas: R$ " . number_format((float)$rec, 2, ',', '.') . " ({$qRec}x)\n";
        $t .= "🔴 Despesas: R$ " . number_format((float)$desp, 2, ',', '.') . " ({$qDesp}x)\n";
        $t .= "─────────────────\n";
        $t .= "{$icon} <b>R$ " . number_format((float)$saldo, 2, ',', '.') . "</b>";
        return $this->respComTeclado($t, $this->tecladoRelatorio());
    }

    private function consultarPorCategoria(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.nome, SUM(t.valor) as total, COUNT(*) as qtd
            FROM transacoes t
            LEFT JOIN categorias c ON c.id = t.id_categoria
            WHERE t.id_usuario = ? AND t.tipo = 'despesa'
            AND YEAR(t.data_transacao) = YEAR(CURDATE()) AND MONTH(t.data_transacao) = MONTH(CURDATE())
            GROUP BY t.id_categoria ORDER BY total DESC LIMIT 8
        ");
        $stmt->execute([$this->userId]);
        $rows = $stmt->fetchAll();
        if (!$rows) return $this->resp("📭 Nenhuma despesa registrada este mês.");
        $texto  = "📊 <b>Gastos por categoria — " . date('M/Y') . "</b>\n\n";
        // Calcula total para percentual
        $totalGeral = array_sum(array_column($rows, 'total'));
        $icons  = ['🍕','🚗','🏠','💊','🎮','📚','👕','📦'];
        foreach ($rows as $i => $r) {
            $icon   = $icons[$i] ?? '📌';
            $nome   = $r['nome'] ?? 'Sem categoria';
            $total  = number_format((float)$r['total'], 2, ',', '.');
            $pct    = $totalGeral > 0 ? round(((float)$r['total'] / $totalGeral) * 100) : 0;
            $bar    = str_repeat('█', (int)($pct / 10)) . str_repeat('░', 10 - (int)($pct / 10));
            $texto .= "{$icon} <b>{$nome}</b>: R$ {$total} ({$pct}%)\n";
            $texto .= "   {$bar}\n";
        }
        return $this->respComTeclado($texto, $this->tecladoRelatorio());
    }

    private function consultarInsights(): array
    {
        try {
            // Top categoria do mês
            $stmtCat = $this->pdo->prepare("
                SELECT c.nome, SUM(t.valor) as total
                FROM transacoes t LEFT JOIN categorias c ON c.id = t.id_categoria
                WHERE t.id_usuario = ? AND t.tipo = 'despesa'
                AND YEAR(t.data_transacao) = YEAR(CURDATE()) AND MONTH(t.data_transacao) = MONTH(CURDATE())
                GROUP BY t.id_categoria ORDER BY total DESC LIMIT 1
            ");
            $stmtCat->execute([$this->userId]);
            $topCat = $stmtCat->fetch();

            // Total mês atual vs anterior
            $stmtComp = $this->pdo->prepare("
                SELECT
                  COALESCE(SUM(CASE WHEN MONTH(data_transacao)=MONTH(CURDATE()) THEN valor END),0) as atual,
                  COALESCE(SUM(CASE WHEN MONTH(data_transacao)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) THEN valor END),0) as anterior
                FROM transacoes
                WHERE id_usuario = ? AND tipo='despesa'
                AND data_transacao >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
            ");
            $stmtComp->execute([$this->userId]);
            $comp = $stmtComp->fetch();

            // Média diária este mês
            $diaAtual = (int)date('j');
            $mediaDia = $diaAtual > 0 ? (float)$comp['atual'] / $diaAtual : 0;
            $projecao = $mediaDia * (int)date('t'); // dias no mês

            // Maior despesa individual
            $stmtMax = $this->pdo->prepare("
                SELECT descricao, valor FROM transacoes
                WHERE id_usuario = ? AND tipo='despesa'
                AND YEAR(data_transacao) = YEAR(CURDATE()) AND MONTH(data_transacao) = MONTH(CURDATE())
                ORDER BY valor DESC LIMIT 1
            ");
            $stmtMax->execute([$this->userId]);
            $maiorDesp = $stmtMax->fetch();

            // Qtd lançamentos este mês
            $stmtQtd = $this->pdo->prepare("
                SELECT COUNT(*) as qtd FROM transacoes
                WHERE id_usuario = ? AND YEAR(data_transacao) = YEAR(CURDATE()) AND MONTH(data_transacao) = MONTH(CURDATE())
            ");
            $stmtQtd->execute([$this->userId]);
            $qtdRow = $stmtQtd->fetch();

            $t  = "🧠 <b>Insights Financeiros — " . date('M/Y') . "</b>\n\n";

            if ($topCat) {
                $t .= "🏆 <b>Maior gasto:</b> {$topCat['nome']} — R$ " . number_format((float)$topCat['total'], 2, ',', '.') . "\n";
            }
            if ($maiorDesp) {
                $t .= "💸 <b>Maior despesa:</b> {$maiorDesp['descricao']} — R$ " . number_format((float)$maiorDesp['valor'], 2, ',', '.') . "\n";
            }

            $t .= "📅 <b>Média diária:</b> R$ " . number_format($mediaDia, 2, ',', '.') . "\n";
            $t .= "📈 <b>Projeção do mês:</b> R$ " . number_format($projecao, 2, ',', '.') . "\n";
            $t .= "🔢 <b>Lançamentos:</b> {$qtdRow['qtd']} este mês\n";

            if ((float)$comp['anterior'] > 0) {
                $diff = (float)$comp['atual'] - (float)$comp['anterior'];
                $pct  = round(abs($diff) / (float)$comp['anterior'] * 100);
                $icon = $diff > 0 ? '↗️' : '↘️';
                $t .= "\n{$icon} Você gastou <b>" . ($diff > 0 ? "+{$pct}%" : "-{$pct}%") . "</b> em relação ao mês passado\n";
            }

            // Dica baseada nos dados
            if ($mediaDia > 0) {
                $diasRestantes = (int)date('t') - (int)date('j');
                $gastoRestante = $mediaDia * $diasRestantes;
                $t .= "\n💡 <i>Nos próximos {$diasRestantes} dias, no ritmo atual, você gastará mais R$ " . number_format($gastoRestante, 2, ',', '.') . "</i>";
            }

            return $this->respComTeclado($t, $this->tecladoRelatorio());
        } catch (Throwable $e) {
            return $this->resp("❌ Erro ao gerar insights: " . $e->getMessage());
        }
    }

    private function consultarComparativo(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE_FORMAT(data_transacao, '%Y-%m') as mes_ref,
                    DATE_FORMAT(data_transacao, '%b/%Y') as mes_label,
                    SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END) as receitas,
                    SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END) as despesas
                FROM transacoes
                WHERE id_usuario = ? AND data_transacao >= DATE_SUB(CURDATE(), INTERVAL 4 MONTH)
                GROUP BY mes_ref ORDER BY mes_ref ASC
            ");
            $stmt->execute([$this->userId]);
            $rows = $stmt->fetchAll();
            if (!$rows) return $this->resp("📭 Sem dados suficientes para comparativo.");

            $t = "📊 <b>Comparativo últimos meses</b>\n\n";
            foreach ($rows as $r) {
                $saldo = (float)$r['receitas'] - (float)$r['despesas'];
                $icon  = $saldo >= 0 ? '🟢' : '🔴';
                $t .= "📅 <b>{$r['mes_label']}</b>\n";
                $t .= "  💚 R$ " . number_format((float)$r['receitas'], 2, ',', '.') . "  🔴 R$ " . number_format((float)$r['despesas'], 2, ',', '.') . "\n";
                $t .= "  {$icon} Saldo: <b>R$ " . number_format($saldo, 2, ',', '.') . "</b>\n\n";
            }
            return $this->respComTeclado($t, $this->tecladoRelatorio());
        } catch (Throwable $e) {
            return $this->resp("❌ Erro ao gerar comparativo.");
        }
    }

    private function consultarHorarioTarefa(string $texto): array
    {
        // Remove palavras interrogativas para extrair o nome da tarefa
        $stop = '/\b(que horas|a que horas|quando|tenho que|preciso|que dia|qual|o|a|hor[aá]rio|prazo|da|do|de|eu|ir|pra|para|na|no|é|são|tem|tenho|minha|meu|pelo|pela|às|as|at[eé])\b/iu';
        $busca = trim(preg_replace('/\s+/', ' ', preg_replace($stop, '', $texto)));

        $tarefa = null;
        if (strlen($busca) >= 3) {
            foreach (array_filter(explode(' ', $busca), fn($w) => strlen($w) >= 3) as $palavra) {
                $stmt = $this->pdo->prepare("
                    SELECT descricao, data_limite, hora_lembrete
                    FROM tarefas
                    WHERE id_usuario = ? AND status = 'pendente' AND descricao LIKE ?
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$this->userId, "%{$palavra}%"]);
                $tarefa = $stmt->fetch();
                if ($tarefa) break;
            }
        }

        if (!$tarefa) {
            // Nenhuma tarefa encontrada — listar pendentes
            return $this->listarTarefas();
        }

        $t = "📋 <b>{$tarefa['descricao']}</b>\n";
        if ($tarefa['data_limite']) {
            $ds = ['Sun'=>'domingo','Mon'=>'segunda','Tue'=>'terça','Wed'=>'quarta','Thu'=>'quinta','Fri'=>'sexta','Sat'=>'sábado'];
            $dsFull = $ds[date('D', strtotime($tarefa['data_limite']))] ?? '';
            $t .= "\n📅 <b>" . date('d/m/Y', strtotime($tarefa['data_limite'])) . "</b> ({$dsFull})";
        } else {
            $t .= "\n📅 Sem data definida";
        }
        if ($tarefa['hora_lembrete']) {
            $t .= "\n⏰ <b>" . substr($tarefa['hora_lembrete'], 0, 5) . "</b>";
        } else {
            $t .= "\n⏰ Sem horário definido";
        }

        return $this->resp($t);
    }

    private function buscarTransacao(string $termo, string $verbo = ''): array
    {
        $tipo = (preg_match('/ganh|receb/iu', $verbo)) ? 'receita' : 'despesa';
        $termo = trim(preg_replace('/\s+/', ' ', $termo));
        // Remove "este mês", "esse mês", "hoje" do termo para isolar o sujeito
        $periodo = 'mes';
        if (preg_match('/\bhoje\b/iu', $termo))               { $periodo = 'hoje';         $termo = preg_replace('/\bhoje\b/iu', '', $termo); }
        elseif (preg_match('/\besta semana\b/iu', $termo))    { $periodo = 'semana';        $termo = preg_replace('/\besta semana\b/iu', '', $termo); }
        elseif (preg_match('/\bm[eê]s passado\b/iu', $termo)) { $periodo = 'mes_passado';   $termo = preg_replace('/\bm[eê]s passado\b/iu', '', $termo); }
        elseif (preg_match('/\beste ano\b/iu', $termo))       { $periodo = 'ano';           $termo = preg_replace('/\beste ano\b/iu', '', $termo); }
        $termo = trim(preg_replace('/\b(este|esse|no|na|em|de|do|da|o|a)\b/iu', '', $termo));
        $termo = trim(preg_replace('/\s+/', ' ', $termo));

        $whereData = match($periodo) {
            'hoje'        => "AND DATE(t.data_transacao) = CURDATE()",
            'semana'      => "AND YEARWEEK(t.data_transacao,1) = YEARWEEK(CURDATE(),1)",
            'mes_passado' => "AND YEAR(t.data_transacao)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND MONTH(t.data_transacao)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))",
            'ano'         => "AND YEAR(t.data_transacao)=YEAR(CURDATE())",
            default       => "AND YEAR(t.data_transacao)=YEAR(CURDATE()) AND MONTH(t.data_transacao)=MONTH(CURDATE())",
        };
        $labelData = match($periodo) {
            'hoje'=>'hoje','semana'=>'esta semana','mes_passado'=>'mês passado','ano'=>'este ano',default=>'este mês'
        };

        try {
            $like = '%' . mb_strtolower($termo) . '%';
            $stmt = $this->pdo->prepare("
                SELECT t.valor, t.descricao, t.data_transacao, c.nome as cat_nome
                FROM transacoes t
                LEFT JOIN categorias c ON c.id = t.id_categoria
                WHERE t.id_usuario = ? AND t.tipo = ?
                AND (LOWER(t.descricao) LIKE ? OR LOWER(c.nome) LIKE ?)
                {$whereData}
                ORDER BY t.data_transacao DESC LIMIT 20
            ");
            $stmt->execute([$this->userId, $tipo, $like, $like]);
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            return $this->resp("❌ Erro na busca.");
        }

        if (!$rows) {
            return $this->resp("📭 Nenhum lançamento encontrado para <b>\"{$termo}\"</b> {$labelData}.\n\n<i>Tente uma palavra diferente ou verifique o período.</i>");
        }

        $total = array_sum(array_column($rows, 'valor'));
        $icon  = $tipo === 'receita' ? '💚' : '🔴';
        $t = "{$icon} <b>\"" . mb_strtolower($termo) . "\"</b> — {$labelData}\n\n";
        foreach ($rows as $r) {
            $data = date('d/m', strtotime($r['data_transacao']));
            $desc = mb_strtolower(trim($r['descricao']));
            $cat  = $r['cat_nome'] ? " [{$r['cat_nome']}]" : '';
            $t .= "· <b>R$ " . number_format((float)$r['valor'], 2, ',', '.') . "</b> — {$desc}{$cat} <i>{$data}</i>\n";
        }
        $t .= "\n─────────────────\n";
        $t .= "<b>Total: R$ " . number_format($total, 2, ',', '.') . "</b> (" . count($rows) . " lançamento(s))";
        return $this->respComTeclado($t, $this->tecladoRelatorio());
    }

    private function marcarDividaPaga(string $texto): array
    {
        $stop = '/\b(quit|quitei|paguei|pago|pagou|dívida|divida|com|para|a|o|de|r\$|reais|me|pagou|recebi|do|da|\d+)\b/iu';
        $pessoa = trim(preg_replace('/\s+/', ' ', preg_replace($stop, '', $texto)));

        if (strlen($pessoa) < 2) {
            return $this->listarDividas();
        }

        $stmt = $this->pdo->prepare("
            SELECT id, pessoa, valor, tipo FROM tg_dividas
            WHERE id_usuario = ? AND status = 'aberta' AND LOWER(pessoa) LIKE ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$this->userId, '%' . mb_strtolower($pessoa) . '%']);
        $divida = $stmt->fetch();

        if (!$divida) {
            return $this->resp("🔍 Não encontrei dívida em aberto com <b>\"{$pessoa}\"</b>.\n\nDigite <code>ver dívidas</code> para ver a lista.");
        }

        $this->pdo->prepare("UPDATE tg_dividas SET status = 'paga' WHERE id = ?")->execute([$divida['id']]);

        $tipoLabel = $divida['tipo'] === 'devo' ? 'Você pagou para' : 'Recebido de';
        $icon      = $divida['tipo'] === 'devo' ? '✅' : '💚';
        return $this->resp(
            "{$icon} <b>Dívida quitada!</b>\n\n" .
            "{$tipoLabel} <b>{$divida['pessoa']}</b>\n" .
            "💰 R$ " . number_format((float)$divida['valor'], 2, ',', '.') . "\n\n" .
            "<i>Use <code>ver dívidas</code> para ver o saldo atualizado.</i>"
        );
    }

    private function depositarNaMeta(string $texto): array
    {
        preg_match('/(\d+[.,]?\d*)/', $texto, $mVal);
        $valor = isset($mVal[1]) ? (float)str_replace(',', '.', $mVal[1]) : null;
        if (!$valor) return $this->resp("💬 Qual o valor guardado? Ex: <code>guardei 200 para viagem</code>");

        $stop    = '/\b(adicionei|guardei|poupei|economizei|juntei|depositei|na|meta|para|pra|em|de|r\$|reais|\d+[.,]?\d*)\b/iu';
        $nomeMeta = trim(preg_replace('/\s+/', ' ', preg_replace($stop, '', $texto)));

        // Busca meta pelo nome ou usa a mais recente
        $meta = null;
        if (strlen($nomeMeta) >= 2) {
            $stmt = $this->pdo->prepare("SELECT id, nome, valor_objetivo, valor_atual FROM metas WHERE id_usuario = ? AND LOWER(nome) LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$this->userId, '%' . mb_strtolower($nomeMeta) . '%']);
            $meta = $stmt->fetch();
        }
        if (!$meta) {
            $stmt2 = $this->pdo->prepare("SELECT id, nome, valor_objetivo, valor_atual FROM metas WHERE id_usuario = ? ORDER BY id DESC LIMIT 1");
            $stmt2->execute([$this->userId]);
            $meta = $stmt2->fetch();
        }
        if (!$meta) {
            return $this->resp("🎯 Nenhuma meta encontrada. Crie uma: <code>criar meta viagem 5000</code>");
        }

        $novoValor = (float)$meta['valor_atual'] + $valor;
        $this->pdo->prepare("UPDATE metas SET valor_atual = ? WHERE id = ?")->execute([$novoValor, $meta['id']]);

        $pct     = $meta['valor_objetivo'] > 0 ? min(100, round(($novoValor / (float)$meta['valor_objetivo']) * 100)) : 0;
        $barsN   = (int)($pct / 10);
        $bar     = str_repeat('█', $barsN) . str_repeat('░', 10 - $barsN);
        $resp    = "🎯 <b>{$meta['nome']}</b> atualizada!\n\n";
        $resp   .= "{$bar} <b>{$pct}%</b>\n";
        $resp   .= "R$ " . number_format($novoValor, 2, ',', '.') . " / R$ " . number_format((float)$meta['valor_objetivo'], 2, ',', '.') . "\n";
        $resp   .= "\n💚 <b>+R$ " . number_format($valor, 2, ',', '.') . "</b> adicionados!";
        if ($pct >= 100) $resp .= "\n\n🎉 <b>Meta atingida! Parabéns!</b> 🏆";
        return $this->resp($resp);
    }

    private function salvarContexto(string $tipo, array $dados = []): void
    {
        try {
            $this->pdo->prepare("
                INSERT INTO tg_contexto (chat_id, tipo, dados)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE tipo = ?, dados = ?, updated_at = NOW()
            ")->execute([$this->chatId, $tipo, json_encode($dados), $tipo, json_encode($dados)]);
        } catch (Throwable $e) {}
    }

    private function pegarContexto(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT tipo, dados FROM tg_contexto WHERE chat_id = ?");
            $stmt->execute([$this->chatId]);
            $row = $stmt->fetch();
            if ($row) return ['tipo' => $row['tipo'], 'dados' => json_decode($row['dados'] ?? '{}', true) ?: []];
        } catch (Throwable $e) {}
        return ['tipo' => '', 'dados' => []];
    }

    private function listarUltimas(string $texto = ''): array
    {
        // Detecta se quer só receitas ou só despesas
        $filtroTipo = '';
        if (str_contains($texto, 'receita') || str_contains($texto, 'ganhei') || str_contains($texto, 'entrada')) {
            $filtroTipo = "AND t.tipo = 'receita'";
        } elseif (str_contains($texto, 'despesa') || str_contains($texto, 'gastei') || str_contains($texto, 'comprei') || str_contains($texto, 'paguei')) {
            $filtroTipo = "AND t.tipo = 'despesa'";
        }

        // Extrai quantidade solicitada ("últimas 10", "últimos 5")
        $limite = 8;
        if (preg_match('/(\d+)/', $texto, $m) && (int)$m[1] <= 50) {
            $limite = (int)$m[1];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT t.tipo, t.valor, t.descricao, t.data_transacao, c.nome as cat_nome
                FROM transacoes t
                LEFT JOIN categorias c ON c.id = t.id_categoria
                WHERE t.id_usuario = ? {$filtroTipo}
                ORDER BY t.data_transacao DESC, t.id DESC
                LIMIT {$limite}
            ");
            $stmt->execute([$this->userId]);
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            return $this->resp("❌ Erro ao buscar transações.");
        }

        if (!$rows) return $this->resp("📭 Nenhuma transação encontrada.");

        $label = $filtroTipo === "AND t.tipo = 'receita'" ? 'receitas' : ($filtroTipo === "AND t.tipo = 'despesa'" ? 'despesas' : 'transações');
        $t = "📋 <b>Últimas {$limite} {$label}</b>\n\n";
        foreach ($rows as $r) {
            $icon  = $r['tipo'] === 'receita' ? '💚' : '🔴';
            $data  = date('d/m', strtotime($r['data_transacao']));
            $valor = number_format((float)$r['valor'], 2, ',', '.');
            $cat   = $r['cat_nome'] ? " [{$r['cat_nome']}]" : '';
            $desc  = mb_strtolower(trim($r['descricao']));
            $t .= "{$icon} <b>R$ {$valor}</b> — {$desc}{$cat} <i>{$data}</i>\n";
        }
        return $this->respComTeclado($t, $this->tecladoRelatorio());
    }

    private function processarOrcamento(string $texto): array
    {
        $textoNorm = $this->normalizar($texto);

        // ── Definir orçamento: "orçamento alimentação 500" ─────────────────
        preg_match('/(\d+[.,]?\d*)/', $texto, $mVal);
        $limite = isset($mVal[1]) ? (float)str_replace(',', '.', $mVal[1]) : null;

        // ── Ver orçamentos (sem valor, ou palavra explícita de consulta) ───
        if (!$limite) {
            return $this->listarOrcamentos();
        }
        if (preg_match('/\b(ver|meu|meus|listar|show)\b/iu', $textoNorm)) {
            return $this->listarOrcamentos();
        }

        // Extrai nome da categoria
        $stopOrc = '/\b(definir|or[çc]amento|limite|de|gastos|para|mensal|budget|\d+[.,]?\d*|reais|r\$)\b/iu';
        $catNome  = trim(preg_replace('/\s+/', ' ', preg_replace($stopOrc, '', $texto)));
        if (strlen($catNome) < 2) {
            return $this->resp("💬 Qual categoria? Ex: <code>orçamento alimentação 500</code>");
        }

        // Busca categoria
        $stmt = $this->pdo->prepare("SELECT id, nome FROM categorias WHERE id_usuario = ? AND LOWER(nome) LIKE ? LIMIT 1");
        $stmt->execute([$this->userId, '%' . mb_strtolower($catNome) . '%']);
        $cat = $stmt->fetch();
        if (!$cat) {
            return $this->resp("🔍 Não encontrei categoria <b>\"{$catNome}\"</b>. Verifique o nome em Gerenciar Categorias.");
        }

        // Salva/atualiza orçamento
        $mes = (int)date('n'); $ano = (int)date('Y');
        $this->pdo->prepare("
            INSERT INTO tg_orcamentos (id_usuario, id_categoria, valor_limite, mes, ano)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE valor_limite = ?
        ")->execute([$this->userId, $cat['id'], $limite, $mes, $ano, $limite]);

        return $this->resp(
            "✅ <b>Orçamento definido!</b>\n\n" .
            "📂 {$cat['nome']}\n" .
            "💰 Limite: <b>R$ " . number_format($limite, 2, ',', '.') . "/mês</b>\n\n" .
            "<i>Vou te avisar quando você se aproximar do limite.</i>"
        );
    }

    private function listarOrcamentos(): array
    {
        try {
            $mes = (int)date('n'); $ano = (int)date('Y');
            $stmt = $this->pdo->prepare("
                SELECT c.nome, o.valor_limite,
                    COALESCE((
                        SELECT SUM(t.valor) FROM transacoes t
                        WHERE t.id_usuario = o.id_usuario AND t.id_categoria = o.id_categoria
                        AND t.tipo='despesa' AND MONTH(t.data_transacao)=? AND YEAR(t.data_transacao)=?
                    ),0) as gasto_atual
                FROM tg_orcamentos o
                JOIN categorias c ON c.id = o.id_categoria
                WHERE o.id_usuario = ? AND o.mes = ? AND o.ano = ?
                ORDER BY c.nome
            ");
            $stmt->execute([$mes, $ano, $this->userId, $mes, $ano]);
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            return $this->resp("❌ Erro ao buscar orçamentos: " . $e->getMessage());
        }
        if (!$rows) {
            return $this->resp(
                "📭 Nenhum orçamento definido para este mês.\n\n" .
                "💡 Diga: <code>orçamento alimentação 500</code>"
            );
        }
        $t = "💼 <b>Orçamentos — " . date('M/Y') . "</b>\n\n";
        foreach ($rows as $r) {
            $pct  = $r['valor_limite'] > 0 ? round(((float)$r['gasto_atual'] / (float)$r['valor_limite']) * 100) : 0;
            $barsUsed = min(10, (int)($pct / 10));
            $bar  = str_repeat('█', $barsUsed) . str_repeat('░', 10 - $barsUsed);
            $icon = $pct >= 100 ? '🔴' : ($pct >= 80 ? '🟡' : '🟢');
            $t .= "{$icon} <b>{$r['nome']}</b>\n";
            $t .= "   {$bar} {$pct}%\n";
            $t .= "   R$ " . number_format((float)$r['gasto_atual'], 2, ',', '.') . " / R$ " . number_format((float)$r['valor_limite'], 2, ',', '.') . "\n\n";
        }
        return $this->respComTeclado($t, $this->tecladoRelatorio());
    }
    private function processarDivida(string $texto): array
    {
        $textoNorm = $this->normalizar($texto);

        // ── Quitar / marcar como paga ──────────────────────────────────
        if (preg_match('/\b(quitei|quitar|paguei a d[ií]vida|me pagou a d[ií]vida|paguei o que devia|pagou a d[ií]vida)\b/iu', $textoNorm) ||
            (preg_match('/\b(paguei|pago|pagou)\b/iu', $textoNorm) && preg_match('/\b(d[ií]vida|devia|para|pro|pra)\b/iu', $textoNorm))) {
            return $this->marcarDividaPaga($texto);
        }
        // "João me pagou" ou "recebi do/da João"
        if (preg_match('/\bme\s+pagou\b/iu', $textoNorm) || preg_match('/\b(recebi do|recebi da)\b/iu', $textoNorm)) {
            return $this->marcarDividaPaga($texto);
        }

        // ── Ver dívidas ────────────────────────────────────────────
        if (preg_match('/\b(ver|minhas|listar|quem|show|resumo)\b/iu', $textoNorm)) {
            return $this->listarDividas();
        }

        // ── Registrar: "devo para João 150" | "João me deve 200" ──────────
        $tipo   = 'devo';
        $pessoa = '';
        $valor  = null;

        // Detecta "me deve" → tipo me_devem
        if (preg_match('/(.+?)\s+me\s+deve\s+r?\$?\s*([\d.,]+)/iu', $texto, $m)) {
            $tipo   = 'me_devem';
            $pessoa = trim($m[1]);
            $valor  = (float)str_replace(['.', ','], ['', '.'], $m[2]);
        }
        // Detecta "devo para X valor"
        elseif (preg_match('/devo\s+(?:para\s+)?(.+?)\s+r?\$?\s*([\d.,]+)/iu', $texto, $m)) {
            $tipo   = 'devo';
            $pessoa = trim($m[1]);
            $valor  = (float)str_replace(['.', ','], ['', '.'], $m[2]);
        }
        // Detecta "emprestei para X valor"
        elseif (preg_match('/emprestei\s+(?:para\s+)?(.+?)\s+r?\$?\s*([\d.,]+)/iu', $texto, $m)) {
            $tipo   = 'me_devem';
            $pessoa = trim($m[1]);
            $valor  = (float)str_replace(['.', ','], ['', '.'], $m[2]);
        }
        // Detecta "X me emprestou valor"
        elseif (preg_match('/(.+?)\s+me\s+emprestou\s+r?\$?\s*([\d.,]+)/iu', $texto, $m)) {
            $tipo   = 'devo';
            $pessoa = trim($m[1]);
            $valor  = (float)str_replace(['.', ','], ['', '.'], $m[2]);
        }

        if (!$valor || !$pessoa) {
            return $this->resp(
                "💬 Para registrar uma dívida:\n\n" .
                "<code>devo para João 150</code>\n" .
                "<code>João me deve 200</code>\n" .
                "<code>emprestei para Maria 300</code>\n\n" .
                "Ou: <code>ver dívidas</code>"
            );
        }

        // Remove stopwords da pessoa
        $pessoa = trim(preg_replace('/\b(para|a|o|r\$|reais)\b/iu', '', $pessoa));

        try {
            $this->pdo->prepare("
                INSERT INTO tg_dividas (id_usuario, pessoa, valor, tipo, data)
                VALUES (?, ?, ?, ?, CURDATE())
            ")->execute([$this->userId, ucfirst($pessoa), $valor, $tipo]);

            $tipoLabel = $tipo === 'devo' ? 'Você deve para' : 'Te deve';
            $icon      = $tipo === 'devo' ? '🔴' : '💚';
            return $this->resp(
                "✅ <b>Dívida registrada!</b>\n\n" .
                "{$icon} <b>{$tipoLabel}:</b> {$pessoa}\n" .
                "💰 <b>R$ " . number_format($valor, 2, ',', '.') . "</b>\n\n" .
                "<i>Use <code>quem me deve</code> ou <code>minhas dívidas</code> para ver o resumo.</i>"
            );
        } catch (Throwable $e) {
            return $this->resp("❌ Erro ao registrar dívida.");
        }
    }

    private function processarMeta(string $texto): array
    {
        // Detectar depósito em meta existente
        if (preg_match('/\b(adicionei|guardei|poupei|economizei|juntei|depositei)\b/iu', $texto)) {
            return $this->depositarNaMeta($texto);
        }

        preg_match('/(\d+[.,]?\d*)/i', $texto, $mVal);
        $valor = isset($mVal[1]) ? (float)str_replace(',', '.', $mVal[1]) : null;
        $nome  = preg_replace('/\b(criar meta|nova meta|meta de|objetivo de|quero juntar|poupar para|quero economizar|quero poupar|\d+[.,]?\d*|reais|r\$)\b/i', '', $texto);
        $nome  = trim($nome) ?: 'Nova meta';
        if (!$valor) return $this->resp("💬 Ótimo! Qual o valor da meta? Ex: <code>5000</code>");
        try {
            $this->pdo->prepare("
                INSERT INTO metas (id_usuario, nome, valor_objetivo, valor_atual, data_limite)
                VALUES (?, ?, ?, 0, DATE_ADD(CURDATE(), INTERVAL 12 MONTH))
            ")->execute([$this->userId, $nome, $valor]);
            return $this->resp("🎯 <b>Meta criada!</b>\n\n🏆 <i>{$nome}</i>\n💰 Objetivo: <b>R$ " . number_format($valor, 2, ',', '.') . "</b>");
        } catch (Throwable $e) {
            return $this->resp("❌ Erro ao criar meta.");
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TAREFAS
    // ═══════════════════════════════════════════════════════════════════════

    private function processarTarefa(string $texto): array
    {
        // Remove palavras-chave de intenção
        $desc = preg_replace('/\b(criar tarefa|nova tarefa|lembrete|to do|tarefa para|adicionar tarefa|preciso fazer|não esquecer|anotar|me lembre|me lembra|lembrar de|lembrar|anota ai|anota aí|por favor anota|adicionar lembrete)\b/iu', '', $texto);
        $desc = preg_replace('/^\s*(de|para|que|ao?|da?|o|em|na?|num?)\s+/iu', '', $desc);
        $desc = trim($desc);
        if (empty($desc)) return $this->resp("💬 Qual é a tarefa? Me diz o que precisa fazer.");
        try {
            // Prioridade
            $prioridade = 'Média';
            if (preg_match('/\b(urgente|urgência|alta|importante|prioridade alta)\b/iu', $desc)) $prioridade = 'Alta';
            if (preg_match('/\b(baixa|depois|quando der|sem pressa)\b/iu', $desc))               $prioridade = 'Baixa';

            // Data
            $dataLimite = null;
            if (preg_match('/\bamanhã\b|\bamanha\b/iu', $desc)) {
                $dataLimite = date('Y-m-d', strtotime('+1 day'));
                $desc = preg_replace('/\bamanhã\b|\bamanha\b/iu', '', $desc);
            } elseif (preg_match('/\bhoje\b/iu', $desc)) {
                $dataLimite = date('Y-m-d');
                $desc = preg_replace('/\bhoje\b/iu', '', $desc);
            } elseif (preg_match('/\b(segunda|segunda-feira)\b/iu', $desc)) {
                $dataLimite = date('Y-m-d', strtotime('next monday'));
                $desc = preg_replace('/\b(segunda|segunda-feira)\b/iu', '', $desc);
            } elseif (preg_match('/\b(terca|terça|terça-feira)\b/iu', $desc)) {
                $dataLimite = date('Y-m-d', strtotime('next tuesday'));
                $desc = preg_replace('/\b(terca|terça|terça-feira)\b/iu', '', $desc);
            } elseif (preg_match('/\b(quarta|quarta-feira)\b/iu', $desc)) {
                $dataLimite = date('Y-m-d', strtotime('next wednesday'));
                $desc = preg_replace('/\b(quarta|quarta-feira)\b/iu', '', $desc);
            } elseif (preg_match('/\b(quinta|quinta-feira)\b/iu', $desc)) {
                $dataLimite = date('Y-m-d', strtotime('next thursday'));
                $desc = preg_replace('/\b(quinta|quinta-feira)\b/iu', '', $desc);
            } elseif (preg_match('/\b(sexta|sexta-feira)\b/iu', $desc)) {
                $dataLimite = date('Y-m-d', strtotime('next friday'));
                $desc = preg_replace('/\b(sexta|sexta-feira)\b/iu', '', $desc);
            } elseif (preg_match('/\bdia\s+(\d{1,2})\b/iu', $desc, $mDia)) {
                $dia = (int)$mDia[1];
                $mes = (int)date('n'); $ano = (int)date('Y');
                if ($dia < (int)date('j')) { $mes++; if ($mes > 12) { $mes = 1; $ano++; } }
                $dataLimite = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                $desc = preg_replace('/\bdia\s+\d{1,2}\b/iu', '', $desc);
            }

            // Horário — suporta: 22h · 22h30 · 22:00 · 22 horas · às/as 22h
            $horaLembrete = null;
            $horaPattern  = '
                (?:(?:às?|as)\s+)?
                (?<![a-zA-Z\x{00C0}-\x{024F}])
                (\d{1,2})
                (?:
                    [h:]\s*(\d{2})?
                    |\s+horas?
                )
            ';
            if (preg_match('/' . $horaPattern . '/ixu', $desc, $mH)) {
                $h = sprintf('%02d', (int)$mH[1]);
                $m = sprintf('%02d', (int)($mH[2] ?? 0));
                $horaLembrete = "{$h}:{$m}:00";
                $desc = preg_replace('/' . $horaPattern . '/ixu', '', $desc);
                if (!$dataLimite) $dataLimite = date('Y-m-d');
            }

            $desc = trim(preg_replace('/\s+/', ' ', $desc));
            if (empty($desc)) $desc = $texto;

            try { $this->pdo->exec("ALTER TABLE tarefas ADD COLUMN hora_lembrete TIME NULL DEFAULT NULL"); } catch (Throwable $e) {}
            try { $this->pdo->exec("ALTER TABLE tarefas ADD COLUMN tg_notificado TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}

            $this->pdo->prepare("
                INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, hora_lembrete, tg_notificado, status)
                VALUES (?, ?, ?, ?, ?, 0, 'pendente')
            ")->execute([$this->userId, $desc, $prioridade, $dataLimite, $horaLembrete]);

            $prazoText = $dataLimite ? ' · 📅 ' . date('d/m', strtotime($dataLimite)) : '';
            $horaText  = $horaLembrete ? ' · ⏰ ' . substr($horaLembrete, 0, 5) : '';
            $notifText = $horaLembrete ? "\n🔔 <i>Vou te lembrar às " . substr($horaLembrete, 0, 5) . " via Telegram!</i>" : '';

            return $this->resp("✅ <b>Tarefa criada!</b>\n\n📝 <i>{$desc}</i>\n🚦 {$prioridade}{$prazoText}{$horaText}{$notifText}");
        } catch (Throwable $e) {
            return $this->resp("❌ Erro ao criar tarefa: " . $e->getMessage());
        }
    }

    private function gerenciarTarefas(string $texto): array
    {
        // ── Alterar prioridade de TODAS ──────────────────────────────────────
        $prio = null;
        if (preg_match('/prioridade\s+(alta|urgente)/iu', $texto))          $prio = 'Alta';
        if (preg_match('/prioridade\s+(media|m[eé]dia|normal)/iu', $texto)) $prio = 'Média';
        if (preg_match('/prioridade\s+(baixa|menor)/iu', $texto))           $prio = 'Baixa';

        $todasKw = str_contains($texto, 'todas') || str_contains($texto, 'todo');

        if ($prio && $todasKw) {
            $stmt = $this->pdo->prepare("UPDATE tarefas SET prioridade = ? WHERE id_usuario = ? AND status = 'pendente'");
            $stmt->execute([$prio, $this->userId]);
            return $this->resp("✅ <b>{$stmt->rowCount()} tarefa(s)</b> marcada(s) como prioridade <b>{$prio}</b>!");
        }

        $eConcluir = (bool)preg_match('/conclu|feita|feito|done|finaliz/iu', $texto);
        $eDeletar  = (bool)preg_match('/delet|remov|apag|exclu/iu', $texto);

        // ── Concluir / Deletar TODAS ─────────────────────────────────────────
        if ($todasKw && $eConcluir) {
            $stmt = $this->pdo->prepare("UPDATE tarefas SET status = 'concluido', data_conclusao = NOW() WHERE id_usuario = ? AND status = 'pendente'");
            $stmt->execute([$this->userId]);
            return $this->resp("✅ <b>{$stmt->rowCount()} tarefa(s)</b> concluídas!");
        }
        if ($todasKw && $eDeletar) {
            $stmt = $this->pdo->prepare("DELETE FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
            $stmt->execute([$this->userId]);
            return $this->resp("🗑️ <b>{$stmt->rowCount()} tarefa(s)</b> removida(s).");
        }

        // ── Concluir / Deletar tarefa ESPECÍFICA por nome ────────────────────
        if ($eConcluir || $eDeletar) {
            $stopCmd = '/\b(concluir?|finalizar|marcar|como|feita|feito|apagar|deletar|remover|tarefa|lembrete|de|do|da|o|a|que|foi)\b/iu';
            $busca   = trim(preg_replace('/\s+/', ' ', preg_replace($stopCmd, '', $texto)));
            if (strlen($busca) >= 3) {
                $palavras = array_filter(explode(' ', $busca), fn($w) => strlen($w) >= 3);
                foreach ($palavras as $palavra) {
                    $stmt = $this->pdo->prepare("
                        SELECT id, descricao FROM tarefas
                        WHERE id_usuario = ? AND status = 'pendente' AND descricao LIKE ?
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt->execute([$this->userId, "%{$palavra}%"]);
                    $tarefa = $stmt->fetch();
                    if ($tarefa) {
                        if ($eDeletar) {
                            $this->pdo->prepare("DELETE FROM tarefas WHERE id = ?")->execute([$tarefa['id']]);
                            return $this->resp("🗑️ Tarefa <b>\"{$tarefa['descricao']}\"</b> removida!");
                        }
                        $this->pdo->prepare("UPDATE tarefas SET status = 'concluido', data_conclusao = NOW() WHERE id = ?")->execute([$tarefa['id']]);
                        return $this->resp("✅ Tarefa <b>\"{$tarefa['descricao']}\"</b> marcada como concluída!");
                    }
                }
                return $this->resp("🔍 Não encontrei nenhuma tarefa pendente com <b>\"{$busca}\"</b>.\n\nDigite <code>tarefas</code> para ver a lista.");
            }
        }

        // ── Prioridade + nome específico ─────────────────────────────────────
        if ($prio) {
            $stopCmd = '/\b(prioridade|alta|baixa|media|m[eé]dia|urgente|coloque|mude|deixe|tarefa|como|de|do|da)\b/iu';
            $busca   = trim(preg_replace('/\s+/', ' ', preg_replace($stopCmd, '', $texto)));
            if (strlen($busca) >= 3) {
                $palavras = array_filter(explode(' ', $busca), fn($w) => strlen($w) >= 3);
                foreach ($palavras as $palavra) {
                    $stmt = $this->pdo->prepare("SELECT id, descricao FROM tarefas WHERE id_usuario = ? AND status = 'pendente' AND descricao LIKE ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$this->userId, "%{$palavra}%"]);
                    $tarefa = $stmt->fetch();
                    if ($tarefa) {
                        $this->pdo->prepare("UPDATE tarefas SET prioridade = ? WHERE id = ?")->execute([$prio, $tarefa['id']]);
                        return $this->resp("✅ Tarefa <b>\"{$tarefa['descricao']}\"</b> → prioridade <b>{$prio}</b>!");
                    }
                }
            }
            // Sem nome → última tarefa
            $stmt = $this->pdo->prepare("UPDATE tarefas SET prioridade = ? WHERE id_usuario = ? AND status = 'pendente' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$prio, $this->userId]);
            return $this->resp("✅ Última tarefa marcada como prioridade <b>{$prio}</b>!");
        }

        // ── Reagendar / mudar data ───────────────────────────────────────────
        $eReagendar = (bool)preg_match(
            '/adiar|reagendar|mudar?\s*(data|prazo)|alterar?\s*(data|prazo)|novo\s*prazo|colou?que?\s+para|coloca\s+para|mou?v[ae]r?\s+para|muda\s+para|para\s+(amanhã|amanha|hoje|depois|sexta|segunda|ter[cç]a|quarta|quinta|s[aá]bado|domingo|dia\s*\d)/iu',
            $texto
        );

        if ($eReagendar) {
            $novaData = null;
            $horaLemb = null;

            if (preg_match('/depois\s+de\s+amanhã|depois\s+de\s+amanha/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('+2 days'));
            } elseif (preg_match('/\bamanhã\b|\bamanha\b/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('+1 day'));
            } elseif (preg_match('/\bhoje\b/iu', $texto)) {
                $novaData = date('Y-m-d');
            } elseif (preg_match('/próxima\s+semana|proxima\s+semana/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('next monday'));
            } elseif (preg_match('/\b(segunda|segunda-feira)\b/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('next monday'));
            } elseif (preg_match('/\b(terca|terça|terça-feira)\b/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('next tuesday'));
            } elseif (preg_match('/\b(quarta|quarta-feira)\b/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('next wednesday'));
            } elseif (preg_match('/\b(quinta|quinta-feira)\b/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('next thursday'));
            } elseif (preg_match('/\b(sexta|sexta-feira)\b/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('next friday'));
            } elseif (preg_match('/\bs[aá]bado\b/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('next saturday'));
            } elseif (preg_match('/\bdomingo\b/iu', $texto)) {
                $novaData = date('Y-m-d', strtotime('next sunday'));
            } elseif (preg_match('/\bdia\s+(\d{1,2})\b/iu', $texto, $mDia)) {
                $dia = (int)$mDia[1];
                $mes = (int)date('n'); $ano = (int)date('Y');
                if ($dia < (int)date('j')) { $mes++; if ($mes > 12) { $mes = 1; $ano++; } }
                $novaData = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            }

            // Hora opcional no reagendamento
            $horaPattern = '(?:(?:às?|as)\s+)?(?<![a-zA-Z\x{00C0}-\x{024F}])(\d{1,2})(?:[h:]\s*(\d{2})?|\s+horas?)';
            if (preg_match('/' . $horaPattern . '/ixu', $texto, $mH)) {
                $h = sprintf('%02d', (int)$mH[1]);
                $m = sprintf('%02d', (int)($mH[2] ?? 0));
                $horaLemb = "{$h}:{$m}:00";
            }

            if ($novaData) {
                // Extrai nome da tarefa: remove palavras de reagendamento
                $stopRe = '/\b(adiar|reagendar|coloque?|coloca|mude?|muda|mover|para|tarefa|lembrete|amanhã|amanha|hoje|segunda|terça|terca|quarta|quinta|sexta|sábado|sabado|domingo|próxima|proxima|semana|dia|depois|de|do|da|o|a)\b/iu';
                $busca  = trim(preg_replace('/\s+/', ' ', preg_replace($stopRe, '', $texto)));
                $busca  = preg_replace('/\b\d{1,2}[h:]\d{0,2}\b|\b\d{1,2}\s+horas?\b/iu', '', $busca);
                $busca  = trim(preg_replace('/\s+/', ' ', $busca));

                $tarefa = null;
                if (strlen($busca) >= 3) {
                    $palavras = array_filter(explode(' ', $busca), fn($w) => strlen($w) >= 3);
                    foreach ($palavras as $palavra) {
                        $stmt = $this->pdo->prepare("SELECT id, descricao FROM tarefas WHERE id_usuario = ? AND status = 'pendente' AND descricao LIKE ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$this->userId, "%{$palavra}%"]);
                        $tarefa = $stmt->fetch();
                        if ($tarefa) break;
                    }
                }
                // Fallback: última tarefa pendente
                if (!$tarefa) {
                    $stmt = $this->pdo->prepare("SELECT id, descricao FROM tarefas WHERE id_usuario = ? AND status = 'pendente' ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$this->userId]);
                    $tarefa = $stmt->fetch();
                }

                if ($tarefa) {
                    if ($horaLemb) {
                        $this->pdo->prepare("UPDATE tarefas SET data_limite = ?, hora_lembrete = ? WHERE id = ?")->execute([$novaData, $horaLemb, $tarefa['id']]);
                        $horaFmt = substr($horaLemb, 0, 5);
                        return $this->resp("📅 <b>\"{$tarefa['descricao']}\"</b> reagendada para <b>" . date('d/m', strtotime($novaData)) . "</b> às <b>{$horaFmt}</b>!");
                    }
                    $this->pdo->prepare("UPDATE tarefas SET data_limite = ? WHERE id = ?")->execute([$novaData, $tarefa['id']]);
                    return $this->resp("📅 <b>\"{$tarefa['descricao']}\"</b> reagendada para <b>" . date('d/m', strtotime($novaData)) . "</b>!");
                }
            }
        }

        // Fallback: listar tarefas
        return $this->listarTarefas();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CALLBACKS (BOTÕES INLINE)
    // ═══════════════════════════════════════════════════════════════════════

    public function processarCallback(string $data, string $textoDisplay = ''): array
    {
        $estado = $this->getEstado();
        $dados  = $estado['dados'] ?? [];

        if (str_starts_with($data, 'cat:')) {
            $catId = (int)substr($data, 4);
            $dados['categoria_id'] = $catId ?: null;
            $this->setEstado('aguardando_confirmacao', $dados);
            return $this->respComTeclado(
                $this->formatarResumoLancamento($dados) . "\n\nConfirmar?",
                $this->tecladoConfirmacao()
            );
        }

        if ($data === 'confirm:sim') {
            return $this->salvarLancamento($dados);
        }
        if ($data === 'confirm:nao') {
            $this->limparEstado();
            return $this->resp("❌ Cancelado. O que mais posso fazer?");
        }

        if ($data === 'corr:excluir') {
            $id = $dados['ultima_transacao']['id'] ?? null;
            if ($id) { $this->pdo->prepare("DELETE FROM transacoes WHERE id = ? AND id_usuario = ?")->execute([$id, $this->userId]); }
            $this->limparEstado();
            return $this->resp("🗑️ Lançamento excluído!");
        }
        if ($data === 'corr:cancelar') {
            $this->limparEstado();
            return $this->resp("👍 Ok, mantive como estava.");
        }
        if ($data === 'corr:redigitar') {
            $this->limparEstado();
            return $this->resp("✏️ Pode digitar o lançamento correto agora:");
        }

        // Quick actions de relatório
        if ($data === 'rel:hoje')        return $this->consultarPeriodo('hoje');
        if ($data === 'rel:semana')      return $this->consultarPeriodo('semana');
        if ($data === 'rel:mes')         return $this->consultarPeriodo('mes');
        if ($data === 'rel:mes_passado') return $this->consultarPeriodo('mes_passado');
        if ($data === 'rel:ano')         return $this->consultarPeriodo('ano');
        if ($data === 'rel:categorias')  return $this->consultarPorCategoria();
        if ($data === 'rel:saldo')       return $this->consultarSaldo();
        if ($data === 'rel:insights')    return $this->consultarInsights();
        if ($data === 'rel:comparativo') return $this->consultarComparativo();
        if ($data === 'rel:orcamento')   return $this->listarOrcamentos();
        if ($data === 'rel:dividas')     return $this->listarDividas();
        if ($data === 'rel:tarefas')     return $this->listarTarefas();

        // Concluir tarefa individual pelo botão inline
        if (str_starts_with($data, 'done_task:')) {
            $tarefaId = (int)substr($data, 10);
            $stmt = $this->pdo->prepare("SELECT descricao FROM tarefas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$tarefaId, $this->userId]);
            $tarefa = $stmt->fetch();
            if ($tarefa) {
                $this->pdo->prepare("UPDATE tarefas SET status = 'concluido', data_conclusao = NOW() WHERE id = ?")->execute([$tarefaId]);
                return $this->resp("✅ <b>\"{$tarefa['descricao']}\"</b> concluída! 🎉");
            }
            return $this->resp("❌ Tarefa não encontrada.");
        }

        // Adiar lembrete +1 hora (snooze)
        if (str_starts_with($data, 'snooze_task:')) {
            $tarefaId = (int)substr($data, 12);
            $stmt = $this->pdo->prepare("SELECT descricao, hora_lembrete FROM tarefas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$tarefaId, $this->userId]);
            $tarefa = $stmt->fetch();
            if ($tarefa) {
                $novaHora = date('H:i:s', strtotime('+1 hour', strtotime($tarefa['hora_lembrete'] ?? 'now')));
                $this->pdo->prepare("UPDATE tarefas SET hora_lembrete = ?, tg_notificado = 0 WHERE id = ?")->execute([$novaHora, $tarefaId]);
                return $this->resp("⏰ Lembrete de <b>\"{$tarefa['descricao']}\"</b> adiado para <b>" . substr($novaHora, 0, 5) . "</b>!");
            }
            return $this->resp("❌ Tarefa não encontrada.");
        }

        return $this->resp("❓ Ação não reconhecida.");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMANDOS /
    // ═══════════════════════════════════════════════════════════════════════

    private function processarComando(string $cmd, string $textoOriginal): array
    {
        // /buscar <termo> — busca transações por descrição
        if (str_starts_with($cmd, '/buscar')) {
            $termo = trim(substr($textoOriginal, 7));
            return $termo ? $this->buscarTransacao($termo) : $this->resp("🔍 Use: <code>/buscar uber</code> ou <code>/buscar mercado</code>");
        }
        // /ultimas [n] — últimas N transações
        if (str_starts_with($cmd, '/ultimas')) {
            return $this->listarUltimas($textoOriginal);
        }
        return match (true) {
            str_starts_with($cmd, '/start')      => $this->respBemVindo(),
            str_starts_with($cmd, '/saldo')      => $this->consultarSaldo(),
            str_starts_with($cmd, '/hoje')       => $this->consultarPeriodo('hoje'),
            str_starts_with($cmd, '/semana')     => $this->consultarPeriodo('semana'),
            str_starts_with($cmd, '/mes')        => $this->consultarPeriodo('mes'),
            str_starts_with($cmd, '/resumo')     => $this->consultarPeriodo('mes'),
            str_starts_with($cmd, '/ano')        => $this->consultarPeriodo('ano'),
            str_starts_with($cmd, '/categorias') => $this->consultarPorCategoria(),
            str_starts_with($cmd, '/insights')   => $this->consultarInsights(),
            str_starts_with($cmd, '/comparativo')=> $this->consultarComparativo(),
            str_starts_with($cmd, '/tarefas')    => $this->listarTarefas(),
            str_starts_with($cmd, '/metas')      => $this->listarMetas(),
            str_starts_with($cmd, '/orcamento')  => $this->listarOrcamentos(),
            str_starts_with($cmd, '/dividas')    => $this->listarDividas(),
            str_starts_with($cmd, '/ajuda')      => $this->respAjuda(),
            default => $this->respostaGenerica($textoOriginal),
        };
    }

    private function listarTarefas(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, descricao, prioridade, data_limite, hora_lembrete
                FROM tarefas
                WHERE id_usuario = ? AND status = 'pendente'
                ORDER BY FIELD(prioridade,'Alta','Média','Baixa'), data_limite ASC
                LIMIT 10
            ");
            $stmt->execute([$this->userId]);
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            return $this->resp('❌ Erro: ' . $e->getMessage());
        }
        if (!$rows) return $this->resp("🎉 Nenhuma tarefa pendente! Tudo em dia.");
        $t = "📋 <b>Tarefas pendentes</b>\n\n";
        $iconsPrio = ['Alta' => '🔴', 'Média' => '🟡', 'Baixa' => '🟢'];
        $teclado   = [];
        foreach ($rows as $r) {
            $pIcon = $iconsPrio[$r['prioridade']] ?? '⚪';
            $data  = $r['data_limite']    ? ' · 📅 ' . date('d/m', strtotime($r['data_limite']))    : '';
            $hora  = !empty($r['hora_lembrete']) ? ' · ⏰ ' . substr($r['hora_lembrete'], 0, 5) : '';
            $t .= "{$pIcon} {$r['descricao']}{$data}{$hora}\n";
            $label     = '✅ ' . mb_substr($r['descricao'], 0, 28);
            $teclado[] = [['text' => $label, 'callback_data' => 'done_task:' . $r['id']]];
        }
        $teclado[] = [['text' => '📋 Atualizar lista', 'callback_data' => 'rel:tarefas']];
        return $this->respComTeclado($t, $teclado);
    }

    private function listarMetas(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT nome, valor_objetivo, valor_atual FROM metas
            WHERE id_usuario = ? ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute([$this->userId]);
        $rows = $stmt->fetchAll();
        if (!$rows) return $this->resp("🎯 Nenhuma meta cadastrada. Diga <i>criar meta viagem 5000</i>!");
        $t = "🎯 <b>Suas metas</b>\n\n";
        foreach ($rows as $r) {
            $pct = $r['valor_objetivo'] > 0 ? round(($r['valor_atual'] / $r['valor_objetivo']) * 100) : 0;
            $bar = str_repeat('█', (int)($pct / 10)) . str_repeat('░', 10 - (int)($pct / 10));
            $t  .= "🏆 <b>{$r['nome']}</b>\n";
            $t  .= "{$bar} {$pct}%\n";
            $t  .= "R$ " . number_format((float)$r['valor_atual'], 2, ',', '.') . " / R$ " . number_format((float)$r['valor_objetivo'], 2, ',', '.') . "\n\n";
        }
        return $this->resp($t);
    }

    private function respBemVindo(): array
    {
        $hora   = (int)date('H');
        $period = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
        $t  = "🤖 <b>{$period}, {$this->userName}! Sou o Orion.</b>\n";
        $t .= "<i>Seu assistente financeiro pessoal inteligente.</i>\n\n";
        $t .= "💸 Registre gastos e receitas falando naturalmente\n";
        $t .= "📊 Consulte saldo, insights e comparativos\n";
        $t .= "✅ Gerencie tarefas com lembretes\n";
        $t .= "🎯 Defina metas financeiras\n";
        $t .= "💼 Controle orçamentos por categoria\n";
        $t .= "🧑‍🤝‍🧑 Registre dívidas e créditos\n\n";
        $t .= "Digite /ajuda para ver todos os comandos.";
        return $this->respComTeclado($t, $this->tecladoAtalhos());
    }

    private function respAjuda(): array
    {
        $nome = $this->userName;
        $t  = "🤖 <b>Orion — Assistente Financeiro</b>\n";
        $t .= "<i>Olá, {$nome}! Aqui está tudo que posso fazer.</i>\n";
        $t .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $t .= "💸 <b>REGISTRAR GASTOS</b>\n";
        $t .= "  <code>gastei 50 no mercado</code>\n";
        $t .= "  <code>paguei 120 conta de luz</code>\n";
        $t .= "  <code>comprei pizza 35 ontem</code>\n";
        $t .= "  <code>comprei TV em 12x de 150</code> ← parcelado\n\n";

        $t .= "💰 <b>REGISTRAR RECEITAS</b>\n";
        $t .= "  <code>recebi 3000 de salário</code>\n";
        $t .= "  <code>vendi produto 150</code>\n\n";

        $t .= "� <b>BUSCA E CONSULTAS</b>\n";
        $t .= "  <code>quanto gastei em uber</code>  →  busca específica\n";
        $t .= "  <code>gastos com alimentação</code>  →  por categoria\n";
        $t .= "  <code>meu saldo</code>  →  resumo com comparativo\n";
        $t .= "  <code>insights</code>  →  análise inteligente\n";
        $t .= "  <code>e ontem?</code> / <code>e semana passada?</code>  →  follow-up\n";
        $t .= "  /saldo · /hoje · /mes · /ano · /insights · /buscar\n\n";

        $t .= "💼 <b>ORÇAMENTOS</b>\n";
        $t .= "  <code>orçamento alimentação 500</code>\n";
        $t .= "  <code>ver orçamento</code>  →  progresso por categoria\n\n";

        $t .= "🧑‍🤝‍🧑 <b>DÍVIDAS</b>\n";
        $t .= "  <code>devo para João 150</code>\n";
        $t .= "  <code>João me deve 200</code>\n";
        $t .= "  <code>quitei a dívida com João</code>  ← pagar\n";
        $t .= "  <code>João me pagou</code>  ← recebido\n\n";

        $t .= "✅ <b>TAREFAS E LEMBRETES</b>\n";
        $t .= "  <code>me lembre de ligar para o banco às 10h</code>\n";
        $t .= "  <code>tenho que ir pra academia amanhã às 18h</code>\n";
        $t .= "  <code>que horas eu tenho que ir pra academia?</code>\n";
        $t .= "  /tarefas  →  lista com botões para concluir\n\n";

        $t .= "🎯 <b>METAS FINANCEIRAS</b>\n";
        $t .= "  <code>criar meta viagem 5000</code>\n";
        $t .= "  <code>guardei 200 para viagem</code>  ← depositar\n";
        $t .= "  <code>juntei 500 na meta</code>  ← depositar\n\n";

        $t .= "↩️ <b>CORREÇÕES</b>\n";
        $t .= "  <code>errei</code>  →  desfaz o último lançamento\n\n";

        $t .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $t .= "<i>💡 Fale naturalmente — sou treinado para entender contexto!</i>";
        return $this->respComTeclado($t, $this->tecladoAtalhos());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // APRENDIZADO
    // ═══════════════════════════════════════════════════════════════════════

    private function aprenderPadrão(string $texto, ?int $catId, string $tipo): void
    {
        if (!$catId) return;
        // Extrai palavras-chave significativas (>3 chars, sem stopwords)
        $stopwords = ['gastei','comprei','paguei','recebi','hoje','ontem','reais','real','para','pela','pelo','com','uma','uns','que'];
        $palavras  = preg_split('/\s+/', mb_strtolower(preg_replace('/[^a-zàáãâéêíóôõúç\s]/i', '', $texto)));
        foreach ($palavras as $p) {
            $p = trim($p);
            if (strlen($p) < 4 || in_array($p, $stopwords)) continue;
            try {
                $this->pdo->prepare("
                    INSERT INTO tg_aprendizado (user_id, expressao, categoria_id, tipo, confirmacoes)
                    VALUES (?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE confirmacoes = confirmacoes + 1, categoria_id = ?, updated_at = NOW()
                ")->execute([$this->userId, $p, $catId, $tipo, $catId]);
            } catch (Throwable $e) {}
        }
        $this->carregarAprendizado();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RESPOSTA GENÉRICA (fallback)
    // ═══════════════════════════════════════════════════════════════════════

    private function respostaGenerica(string $texto): array
    {
        $saudacoes = ['oi','olá','ola','bom dia','boa tarde','boa noite','e aí','eai','hello','hi'];
        $norm = $this->normalizar($texto);
        foreach ($saudacoes as $s) {
            if (str_contains($norm, $s)) {
                $hora  = (int)date('H');
                $period = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
                return $this->respComTeclado(
                    "👋 {$period}, {$this->userName}! Pronto para controlar suas finanças.\n\nO que vamos registrar?",
                    $this->tecladoAtalhos()
                );
            }
        }
        $t  = "🤔 <b>Não entendi bem, mas posso ajudar!</b>\n\n";
        $t .= "Tenta assim:\n\n";
        $t .= "💸 <code>gastei 50 no mercado</code>\n";
        $t .= "💰 <code>recebi 3000 de salário</code>\n";
        $t .= "📊 <code>meu saldo</code> ou <code>quanto gastei hoje</code>\n";
        $t .= "✅ <code>me lembre de pagar boleto às 10h</code>\n\n";
        $t .= "<i>Digite /ajuda para ver tudo que sei fazer.</i>";
        return $this->respComTeclado($t, $this->tecladoAtalhos());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // STATE MACHINE
    // ═══════════════════════════════════════════════════════════════════════

    private function getEstado(): array
    {
        $stmt = $this->pdo->prepare("SELECT estado, dados FROM tg_estados WHERE chat_id = ?");
        $stmt->execute([$this->chatId]);
        $row = $stmt->fetch();
        return $row ? ['estado' => $row['estado'], 'dados' => json_decode($row['dados'] ?? '{}', true) ?: []] : ['estado' => 'idle', 'dados' => []];
    }

    private function setEstado(string $estado, array $dados = []): void
    {
        $this->pdo->prepare("
            INSERT INTO tg_estados (chat_id, estado, dados) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE estado = ?, dados = ?, updated_at = NOW()
        ")->execute([$this->chatId, $estado, json_encode($dados), $estado, json_encode($dados)]);
    }

    private function limparEstado(): void
    {
        $this->pdo->prepare("DELETE FROM tg_estados WHERE chat_id = ?")->execute([$this->chatId]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TECLADOS INLINE
    // ═══════════════════════════════════════════════════════════════════════

    private function tecladoCategorias(string $tipo): array
    {
        $stmt = $this->pdo->prepare("SELECT id, nome FROM categorias WHERE id_usuario = ? AND tipo = ? ORDER BY nome LIMIT 12");
        $stmt->execute([$this->userId, $tipo]);
        $cats = $stmt->fetchAll();
        $icons = ['Alimentação'=>'🍕','Transporte'=>'🚗','Moradia'=>'🏠','Saúde'=>'💊','Lazer'=>'🎮',
                  'Educação'=>'📚','Vestuário'=>'👕','Trabalho'=>'💼','Investimento'=>'📈','Outros'=>'📦'];
        $linhas = [];
        $linha  = [];
        foreach ($cats as $i => $c) {
            $icon   = $icons[$c['nome']] ?? '📌';
            $linha[] = ['text' => "{$icon} {$c['nome']}", 'callback_data' => "cat:{$c['id']}"];
            if (count($linha) === 2) { $linhas[] = $linha; $linha = []; }
        }
        if ($linha) $linhas[] = $linha;
        $linhas[] = [['text' => '📦 Outros', 'callback_data' => 'cat:0']];
        return $linhas;
    }

    private function tecladoConfirmacao(): array
    {
        return [[
            ['text' => '✅ Confirmar', 'callback_data' => 'confirm:sim'],
            ['text' => '❌ Cancelar',  'callback_data' => 'confirm:nao'],
        ]];
    }

    private function tecladoRelatorio(): array
    {
        return [
            [['text' => '📅 Hoje',        'callback_data' => 'rel:hoje'],
             ['text' => '📊 Semana',      'callback_data' => 'rel:semana']],
            [['text' => '📆 Mês',         'callback_data' => 'rel:mes'],
             ['text' => '📆 Mês passado', 'callback_data' => 'rel:mes_passado']],
            [['text' => '🏷️ Categorias',  'callback_data' => 'rel:categorias'],
             ['text' => '🧠 Insights',    'callback_data' => 'rel:insights']],
            [['text' => '📊 Comparativo',  'callback_data' => 'rel:comparativo'],
             ['text' => '💳 Saldo',        'callback_data' => 'rel:saldo']],
        ];
    }

    private function tecladoAtalhos(): array
    {
        return [
            [['text' => '💳 Meu saldo',    'callback_data' => 'rel:saldo'],
             ['text' => '📊 Este mês',     'callback_data' => 'rel:mes']],
            [['text' => '📅 Hoje',          'callback_data' => 'rel:hoje'],
             ['text' => '🏷️ Categorias',  'callback_data' => 'rel:categorias']],
            [['text' => '✅ Tarefas',          'callback_data' => 'rel:tarefas'],
             ['text' => '🧠 Insights',    'callback_data' => 'rel:insights']],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function normalizar(string $texto): string
    {
        return mb_strtolower(trim($texto));
    }

    private function formatarResumoLancamento(array $d): string
    {
        $tipo   = $d['tipo'] ?? 'despesa';
        $icon   = ($tipo === 'receita') ? '💚' : '🔴';
        $valor  = isset($d['valor']) ? number_format((float)$d['valor'], 2, ',', '.') : '?';
        $desc   = $d['descricao'] ?? $d['texto_original'] ?? '';
        $data   = isset($d['data']) ? date('d/m/Y', strtotime($d['data'])) : date('d/m/Y');
        $catNome = '';
        if (!empty($d['categoria_id'])) {
            $stmt = $this->pdo->prepare("SELECT nome FROM categorias WHERE id = ?");
            $stmt->execute([$d['categoria_id']]);
            $catNome = $stmt->fetchColumn() ?: '';
        }
        $t  = "{$icon} <b>" . ucfirst($tipo) . "</b>\n\n";
        $t .= "📝 <i>{$desc}</i>\n";
        $t .= "💰 <b>R$ {$valor}</b>";
        if (!empty($d['parcelas'])) {
            $t .= " <i>(" . $d['parcelas'] . "x de R$ " . number_format((float)$d['valor_parcela'], 2, ',', '.') . ")</i>";
        }
        $t .= "\n📅 {$data}";
        if ($catNome) $t .= "\n📂 {$catNome}";
        return $t;
    }

    private function salvarHistorico(string $role, string $texto, string $intencao = ''): void
    {
        try {
            $this->pdo->prepare("
                INSERT INTO tg_historico (chat_id, user_id, role, mensagem, intencao)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$this->chatId, $this->userId, $role, mb_substr($texto, 0, 1000), $intencao]);
        } catch (Throwable $e) {}
    }

    private function resp(string $texto, ?array $teclado = null): array
    {
        $this->salvarHistorico('bot', $texto);
        return ['texto' => $texto, 'teclado' => $teclado];
    }

    private function respComTeclado(string $texto, array $teclado): array
    {
        $this->salvarHistorico('bot', $texto);
        return ['texto' => $texto, 'teclado' => $teclado];
    }
}
