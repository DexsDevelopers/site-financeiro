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
                                  'transferência','depositaram','caiu na conta','recebimento','pagamento recebido'];
    private const CONSULTA_KW = ['quanto gastei','quanto ganhei','meu saldo','ver saldo','saldo atual',
                                  'quanto tenho','resumo','relatório','extrato','minhas despesas',
                                  'minhas receitas','total do mês','gasto do mês','overview',
                                  'minhas tarefas','ver tarefas','listar tarefas','quais tarefas',
                                  'tarefas pendentes','o que tenho pra fazer','o que tenho que fazer',
                                  'minhas metas','ver metas','listar metas'];
    private const TAREFA_KW   = ['criar tarefa','nova tarefa','lembrete','to do','tarefa para','adicionar tarefa',
                                  'preciso fazer','não esquecer','anotar','me lembre','me lembra','lembrar','lembrar de',
                                  'anota ai','anota aí','por favor anota','adicionar lembrete'];
    private const META_KW     = ['criar meta','nova meta','meta de','objetivo de','quero juntar','poupar para'];
    private const CORRECAO_KW = ['errei','foi errado','era outro','na verdade','corrijo','estava errado',
                                  'não era','cancela','cancele','desfazer'];

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
            $isEscape = in_array($intencaoNova, ['tarefa','meta','consulta','correcao'], true)
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
            'meta'               => $this->processarMeta($texto),
            'correcao'           => $this->iniciarCorrecao(),
            default              => $this->respostaGenerica($texto),
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DETECÇÃO DE INTENÇÃO
    // ═══════════════════════════════════════════════════════════════════════

    private function detectarIntencao(string $texto): string
    {
        foreach (self::CORRECAO_KW as $kw) {
            if (str_contains($texto, $kw)) return 'correcao';
        }
        foreach (self::CONSULTA_KW as $kw) {
            if (str_contains($texto, $kw)) return 'consulta';
        }
        foreach (self::TAREFA_KW as $kw) {
            if (str_contains($texto, $kw)) return 'tarefa';
        }
        foreach (self::META_KW as $kw) {
            if (str_contains($texto, $kw)) return 'meta';
        }
        foreach (self::RECEITA_KW as $kw) {
            if (str_contains($texto, $kw)) return 'receita';
        }
        foreach (self::DESPESA_KW as $kw) {
            if (str_contains($texto, $kw)) return 'despesa';
        }
        // Verificar aprendizado pessoal
        foreach ($this->aprendizado as $expr => $dado) {
            if (str_contains($texto, $expr) && $dado['confirmacoes'] >= 2) {
                return $dado['tipo'];
            }
        }
        // Se tem valor monetário explícito (R$, reais, valor solto), provavelmente é despesa
        // Mas ignora padrões de hora: 22h, 22h30, 22:00, 22 horas
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
                SELECT SUM(valor) as total, COUNT(*) as qtd, c.nome as cat_nome
                FROM transacoes t
                JOIN categorias c ON c.id = t.id_categoria
                WHERE t.id_usuario = ? AND t.id_categoria = ? AND t.tipo = 'despesa'
                AND YEAR(t.data_transacao) = YEAR(CURDATE()) AND MONTH(t.data_transacao) = MONTH(CURDATE())
            ");
            $stmt->execute([$this->userId, $catId]);
            $row = $stmt->fetch();
            if ($row && $row['total'] > 0) {
                $total = number_format((float)$row['total'], 2, ',', '.');
                $vezes = $row['qtd'];
                return "📊 <i>Este mês você gastou <b>R$ {$total}</b> em {$row['cat_nome']} ({$vezes}x)</i>";
            }
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
        if (str_contains($texto, 'tarefa') || str_contains($texto, 'pra fazer') || str_contains($texto, 'que fazer')) {
            return $this->listarTarefas();
        }
        if (str_contains($texto, 'meta')) {
            return $this->listarMetas();
        }
        if (str_contains($texto, 'saldo') || str_contains($texto, 'tenho')) {
            return $this->consultarSaldo();
        }
        if (str_contains($texto, 'hoje')) {
            return $this->consultarPeriodo('hoje');
        }
        if (str_contains($texto, 'semana')) {
            return $this->consultarPeriodo('semana');
        }
        if (str_contains($texto, 'categor')) {
            return $this->consultarPorCategoria();
        }
        return $this->consultarPeriodo('mes');
    }

    private function consultarSaldo(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
              COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END),0) as receitas,
              COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) as despesas
            FROM transacoes
            WHERE id_usuario = ? AND YEAR(data_transacao) = YEAR(CURDATE()) AND MONTH(data_transacao) = MONTH(CURDATE())
        ");
        $stmt->execute([$this->userId]);
        $row = $stmt->fetch();
        $saldo  = (float)$row['receitas'] - (float)$row['despesas'];
        $icon   = $saldo >= 0 ? '🟢' : '🔴';
        $texto  = "💳 <b>Resumo deste mês</b>\n\n";
        $texto .= "💚 Receitas: <b>R$ " . number_format((float)$row['receitas'], 2, ',', '.') . "</b>\n";
        $texto .= "🔴 Despesas: <b>R$ " . number_format((float)$row['despesas'], 2, ',', '.') . "</b>\n";
        $texto .= "─────────────────\n";
        $texto .= "{$icon} Saldo: <b>R$ " . number_format((float)$saldo, 2, ',', '.') . "</b>";
        return $this->respComTeclado($texto, $this->tecladoRelatorio());
    }

    private function consultarPeriodo(string $periodo): array
    {
        [$label, $where] = match($periodo) {
            'hoje'   => ['Hoje',            "DATE(data_transacao) = CURDATE()"],
            'semana' => ['Esta semana',     "YEARWEEK(data_transacao,1) = YEARWEEK(CURDATE(),1)"],
            default  => ['Este mês',        "YEAR(data_transacao) = YEAR(CURDATE()) AND MONTH(data_transacao) = MONTH(CURDATE())"],
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
        $icons  = ['🍕','🚗','🏠','💊','🎮','📚','👕','📦'];
        foreach ($rows as $i => $r) {
            $icon   = $icons[$i] ?? '📌';
            $nome   = $r['nome'] ?? 'Sem categoria';
            $total  = number_format((float)$r['total'], 2, ',', '.');
            $texto .= "{$icon} <b>{$nome}</b>: R$ {$total} ({$r['qtd']}x)\n";
        }
        return $this->resp($texto);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TAREFAS E METAS
    // ═══════════════════════════════════════════════════════════════════════

    private function processarTarefa(string $texto): array
    {
        // Remove palavras-chave de intenção
        $desc = preg_replace('/\b(criar tarefa|nova tarefa|lembrete|to do|tarefa para|adicionar tarefa|preciso fazer|não esquecer|anotar|me lembre|me lembra|lembrar de|lembrar|anota ai|anota aí|por favor anota|adicionar lembrete)\b/iu', '', $texto);
        $desc = trim($desc);
        if (empty($desc)) return $this->resp("💬 Qual é a tarefa? Me diz o que precisa fazer.");
        try {
            // Prioridade
            $prioridade = 'Média';
            if (preg_match('/\b(urgente|urgência|alta|importante|prioridade alta)\b/iu', $desc)) $prioridade = 'Alta';
            if (preg_match('/\b(baixa|depois|quando der|sem pressa)\b/iu', $desc))              $prioridade = 'Baixa';

            // Data
            $dataLimite = null;
            if (preg_match('/\bamanhã\b/iu', $desc)) {
                $dataLimite = date('Y-m-d', strtotime('+1 day'));
                $desc = preg_replace('/\bamanhã\b/iu', '', $desc);
            } elseif (preg_match('/\bhoje\b/iu', $desc)) {
                $dataLimite = date('Y-m-d');
                $desc = preg_replace('/\bhoje\b/iu', '', $desc);
            }

            // Horário — suporta: 22h · 22h30 · 22:00 · 22 horas · às/as 22h · às/as 22:00 · às/as 22 horas
            $horaLembrete = null;
            $horaPattern  = '
                (?:às?|as|a|\b)      # prefixo opcional: às, as, a (ou início de palavra)
                \s*
                (\d{1,2})            # hora
                (?:
                    [h:]\s*(\d{2})?   # 22h · 22h30 · 22:00
                    |\s+horas?         # 22 horas · 22 hora
                )
            ';
            if (preg_match('/' . $horaPattern . '/ixu', $desc, $mH)) {
                $h = sprintf('%02d', (int)$mH[1]);
                $m = sprintf('%02d', (int)($mH[2] ?? 0));
                $horaLembrete = "{$h}:{$m}:00";
                // Remove o trecho de hora da descrição
                $desc = preg_replace('/' . $horaPattern . '/ixu', '', $desc);
                if (!$dataLimite) $dataLimite = date('Y-m-d');
            }

            $desc = trim(preg_replace('/\s+/', ' ', $desc));
            if (empty($desc)) $desc = $texto;

            // Garantir coluna hora_lembrete existe
            try { $this->pdo->exec("ALTER TABLE tarefas ADD COLUMN hora_lembrete TIME NULL DEFAULT NULL"); } catch (Throwable $e) {}
            try { $this->pdo->exec("ALTER TABLE tarefas ADD COLUMN tg_notificado TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}

            $this->pdo->prepare("
                INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, hora_lembrete, tg_notificado, status)
                VALUES (?, ?, ?, ?, ?, 0, 'pendente')
            ")->execute([$this->userId, $desc, $prioridade, $dataLimite, $horaLembrete]);

            $prazoText = $dataLimite ? ' · 📅 ' . date('d/m', strtotime($dataLimite)) : '';
            $horaText  = $horaLembrete ? ' · ⏰ ' . substr($horaLembrete, 0, 5) : '';
            $notifText = $horaLembrete ? "\n🔔 <i>Vou te lembrar às " . substr($horaLembrete, 0, 5) . " via Telegram!</i>" : '';

            return $this->resp("✅ <b>Tarefa criada!</b>\n\n📝 <i>{$desc}</i>\n🏷️ {$prioridade}{$prazoText}{$horaText}{$notifText}");
        } catch (Throwable $e) {
            return $this->resp("❌ Erro ao criar tarefa: " . $e->getMessage());
        }
    }

    private function processarMeta(string $texto): array
    {
        preg_match('/(\d+[.,]?\d*)/i', $texto, $mVal);
        $valor = isset($mVal[1]) ? (float)str_replace(',', '.', $mVal[1]) : null;
        $nome  = preg_replace('/\b(criar meta|nova meta|meta de|objetivo de|quero juntar|poupar para|\d+[.,]?\d*|reais|r\$)\b/i', '', $texto);
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
        if ($data === 'rel:hoje')       return $this->consultarPeriodo('hoje');
        if ($data === 'rel:semana')     return $this->consultarPeriodo('semana');
        if ($data === 'rel:mes')        return $this->consultarPeriodo('mes');
        if ($data === 'rel:categorias') return $this->consultarPorCategoria();
        if ($data === 'rel:saldo')      return $this->consultarSaldo();

        return $this->resp("❓ Ação não reconhecida.");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMANDOS /
    // ═══════════════════════════════════════════════════════════════════════

    private function processarComando(string $cmd, string $textoOriginal): array
    {
        return match (true) {
            str_starts_with($cmd, '/saldo')    => $this->consultarSaldo(),
            str_starts_with($cmd, '/hoje')     => $this->consultarPeriodo('hoje'),
            str_starts_with($cmd, '/semana')   => $this->consultarPeriodo('semana'),
            str_starts_with($cmd, '/mes')      => $this->consultarPeriodo('mes'),
            str_starts_with($cmd, '/resumo')   => $this->consultarPeriodo('mes'),
            str_starts_with($cmd, '/categorias') => $this->consultarPorCategoria(),
            str_starts_with($cmd, '/tarefas')  => $this->listarTarefas(),
            str_starts_with($cmd, '/metas')    => $this->listarMetas(),
            str_starts_with($cmd, '/ajuda')    => $this->respAjuda(),
            default => $this->respostaGenerica($textoOriginal),
        };
    }

    private function listarTarefas(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT descricao, prioridade, data_limite, hora_lembrete
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
        foreach ($rows as $r) {
            $pIcon = $iconsPrio[$r['prioridade']] ?? '⚪';
            $data  = $r['data_limite']    ? ' · 📅 ' . date('d/m', strtotime($r['data_limite']))    : '';
            $hora  = !empty($r['hora_lembrete']) ? ' · ⏰ ' . substr($r['hora_lembrete'], 0, 5) : '';
            $t .= "{$pIcon} {$r['descricao']}{$data}{$hora}\n";
        }
        return $this->resp($t);
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

    private function respAjuda(): array
    {
        $t  = "🤖 <b>Orion Finance Bot</b>\n\n";
        $t .= "<b>💸 Lançamentos</b>\n";
        $t .= "• <i>gastei 50 no mercado</i>\n";
        $t .= "• <i>paguei 120 conta de luz</i>\n";
        $t .= "• <i>recebi 3000 de salário</i>\n";
        $t .= "• <i>comprei pizza 35 ontem</i>\n\n";
        $t .= "<b>📊 Consultas</b>\n";
        $t .= "• <i>meu saldo</i> ou /saldo\n";
        $t .= "• <i>quanto gastei hoje</i> ou /hoje\n";
        $t .= "• <i>resumo do mês</i> ou /mes\n";
        $t .= "• <i>gastos por categoria</i>\n\n";
        $t .= "<b>✅ Produtividade</b>\n";
        $t .= "• <i>criar tarefa pagar boleto</i>\n";
        $t .= "• <i>criar meta viagem 5000</i>\n";
        $t .= "• /tarefas · /metas\n\n";
        $t .= "<b>↩️ Correções</b>\n";
        $t .= "• <i>errei</i> ou <i>cancela</i> → desfaz último\n";
        return $this->resp($t);
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
        $t  = "🤔 Não entendi exatamente, mas posso ajudar com:\n\n";
        $t .= "• <i>gastei X em Y</i> — lançar despesa\n";
        $t .= "• <i>recebi X de Y</i> — lançar receita\n";
        $t .= "• <i>meu saldo</i> — ver resumo\n";
        $t .= "• /ajuda — lista completa";
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
        $stmt = $this->pdo->prepare("SELECT id, nome FROM categorias WHERE id_usuario = ? ORDER BY nome LIMIT 12");
        $stmt->execute([$this->userId]);
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
            [['text' => '📅 Hoje', 'callback_data' => 'rel:hoje'], ['text' => '📊 Semana', 'callback_data' => 'rel:semana']],
            [['text' => '📆 Mês',  'callback_data' => 'rel:mes'],  ['text' => '🏷️ Categorias', 'callback_data' => 'rel:categorias']],
        ];
    }

    private function tecladoAtalhos(): array
    {
        return [
            [['text' => '💳 Meu saldo',   'callback_data' => 'rel:saldo'],  ['text' => '📊 Este mês', 'callback_data' => 'rel:mes']],
            [['text' => '📅 Hoje',         'callback_data' => 'rel:hoje'],   ['text' => '🏷️ Categorias', 'callback_data' => 'rel:categorias']],
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
        $t .= "💰 <b>R$ {$valor}</b>\n";
        $t .= "📅 {$data}";
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
