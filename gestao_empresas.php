<?php
// gestao_empresas.php - Painel de Gestão Empresarial

require_once 'templates/header.php';
// $pdo e $userId já estão disponíveis via header.php

// Buscar empresas principais do usuário (sem pai)
try {
    $stmt = $pdo->prepare("SELECT * FROM ge_empresas WHERE id_usuario = ? AND id_pai IS NULL ORDER BY data_criacao DESC");
    $stmt->execute([$userId]);
    $empresas = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tentar criar/atualizar as tabelas caso não existam (auto-setup)
    include_once 'setup_gestao_empresas.php';
    $empresas = [];
}
?>

<link rel="stylesheet" href="assets/css/gestao_empresas.css?v=<?php echo time(); ?>">

<div class="ge-container">
    <!-- View: Lista de Empresas -->
    <div id="ge-list-view" class="ge-list-view">
        <div class="ge-header">
            <div class="ge-title">
                <h1>Gestão de Empresas</h1>
                <p>Administre todos os seus negócios em um único lugar</p>
            </div>
            <button class="btn btn-custom-red" data-bs-toggle="modal" data-bs-target="#modalNovaEmpresa">
                <i class="bi bi-plus-lg me-2"></i>Nova Empresa
            </button>
        </div>

        <?php if (empty($empresas)): ?>
            <div class="ge-empty-state">
                <i class="bi bi-building"></i>
                <h3>Nenhuma empresa cadastrada</h3>
                <p>Comece cadastrando sua primeira empresa para gerenciar finanças, tarefas e muito mais.</p>
                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalNovaEmpresa">
                    Cadastrar Minha Primeira Empresa
                </button>
            </div>
        <?php else: ?>
            <div class="ge-grid">
                <?php foreach ($empresas as $empresa): ?>
                    <div class="ge-card" onclick="selecionarEmpresa(<?php echo $empresa['id']; ?>)">
                        <div class="ge-card-logo">
                            <?php if ($empresa['logo']): ?>
                                <img src="<?php echo htmlspecialchars($empresa['logo']); ?>" alt="Logo">
                            <?php else: ?>
                                <i class="bi bi-building"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="ge-card-title"><?php echo htmlspecialchars($empresa['nome']); ?></h3>
                        <?php if ($empresa['segmento']): ?>
                            <span class="ge-card-segment"><?php echo htmlspecialchars($empresa['segmento']); ?></span>
                        <?php endif; ?>
                        <div class="ge-card-info">
                            <p><i class="bi bi-person"></i> <?php echo htmlspecialchars($empresa['contato'] ?: 'N/A'); ?></p>
                            <p><i class="bi bi-card-text"></i> <?php echo htmlspecialchars($empresa['cnpj'] ?: 'S/CNPJ'); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- View: Detalhes da Empresa -->
    <div id="ge-detail-view" class="ge-detail-view">
        <div class="ge-back-btn" onclick="voltarParaLista()">
            <i class="bi bi-arrow-left"></i> Voltar para lista de empresas
        </div>

        <div class="ge-header">
            <div class="ge-title">
                <div class="d-flex align-items-center gap-2">
                    <h1 id="detalhe-empresa-nome">Nome da Empresa</h1>
                    <span id="ge-badge-tipo" class="badge bg-primary d-none">Matriz</span>
                </div>
                <p id="detalhe-empresa-segmento">Segmento</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" onclick="exportarDadosEmpresa()">
                    <i class="bi bi-download me-2"></i>Exportar
                </button>
                <button class="btn btn-custom-red" onclick="abrirConfiguracoesEmpresa()">
                    <i class="bi bi-gear me-2"></i>Configurações
                </button>
            </div>
        </div>

        <!-- Abas Internas -->
        <div class="ge-tabs">
            <button class="ge-tab-btn active" data-tab="resumo">
                <i class="bi bi-grid"></i> Resumo
            </button>
            <button class="ge-tab-btn" data-tab="financeiro">
                <i class="bi bi-cash-coin"></i> Financeiro
            </button>
            <button class="ge-tab-btn" data-tab="tarefas">
                <i class="bi bi-check2-square"></i> Tarefas
            </button>
            <button class="ge-tab-btn" data-tab="ideias">
                <i class="bi bi-lightbulb"></i> Ideias
            </button>
            <button class="ge-tab-btn" data-tab="conteudo">
                <i class="bi bi-megaphone"></i> Conteúdo
            </button>
            <button class="ge-tab-btn" data-tab="redes-sociais">
                <i class="bi bi-share"></i> Redes Sociais
            </button>
            <button class="ge-tab-btn" data-tab="subempresas">
                <i class="bi bi-diagram-3"></i> Subempresas
            </button>
            <button class="ge-tab-btn" data-tab="cadastro">
                <i class="bi bi-info-circle"></i> Informações
            </button>
        </div>

        <!-- Conteúdo das Abas -->
        <div id="tab-resumo" class="ge-tab-content active">
            <div class="ge-widget-grid">
                <div class="ge-widget entrada">
                    <div class="ge-widget-header">
                        <span class="ge-widget-title">Receitas (Mês)</span>
                        <i class="bi bi-arrow-up-circle"></i>
                    </div>
                    <div class="ge-widget-value" id="stats-receitas">R$ 0,00</div>
                </div>
                <div class="ge-widget saida">
                    <div class="ge-widget-header">
                        <span class="ge-widget-title">Despesas (Mês)</span>
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                    <div class="ge-widget-value" id="stats-despesas">R$ 0,00</div>
                </div>
                <div class="ge-widget">
                    <div class="ge-widget-header">
                        <span class="ge-widget-title">Saldo</span>
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div class="ge-widget-value" id="stats-saldo">R$ 0,00</div>
                </div>
                <div class="ge-widget">
                    <div class="ge-widget-header">
                        <span class="ge-widget-title">Tarefas Pendentes</span>
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="ge-widget-value" id="stats-tarefas">0</div>
                </div>
            </div>

            <!-- Visão Consolidada do Grupo -->
            <div id="ge-group-vision" class="mt-4 d-none">
                <h4 class="mb-3"><i class="bi bi-diagram-3-fill me-2"></i>Visão Consolidada do Grupo (Matriz + Subs)</h4>
                <div class="ge-widget-grid">
                    <div class="ge-widget bg-light">
                        <div class="ge-widget-header text-dark">
                            <span class="ge-widget-title">Receita Total Grupo</span>
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div class="ge-widget-value text-dark" id="stats-grupo-receitas">R$ 0,00</div>
                    </div>
                    <div class="ge-widget bg-light">
                        <div class="ge-widget-header text-dark">
                            <span class="ge-widget-title">Despesa Total Grupo</span>
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                        <div class="ge-widget-value text-dark" id="stats-grupo-despesas">R$ 0,00</div>
                    </div>
                    <div class="ge-widget bg-light">
                        <div class="ge-widget-header text-dark">
                            <span class="ge-widget-title">EBITDA Grupo (Saldo)</span>
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="ge-widget-value text-dark" id="stats-grupo-saldo">R$ 0,00</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-7">
                    <div class="ge-widget">
                        <h4 class="mb-4">Fluxo de Caixa Mensal</h4>
                        <div style="height: 300px;">
                            <canvas id="ge-finance-chart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="ge-widget">
                        <h4 class="mb-4">Próximos Compromissos</h4>
                        <div id="resumo-tarefas-lista" class="ge-item-list">
                            <!-- Inserido via JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-financeiro" class="ge-tab-content">
            <div class="ge-header">
                <h3>Gestão Financeira</h3>
                <button class="btn btn-success btn-sm" onclick="abrirNovoLancamento()">
                    <i class="bi bi-plus"></i> Novo Lançamento
                </button>
            </div>
            <div class="ge-widget mb-4">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="ge-finance-category-chart" style="height: 250px;"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h5>Metas Financeiras</h5>
                        <div id="ge-metas-lista" class="mt-3">
                            <!-- Inserido via JS -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="ge-finance-table-body">
                        <!-- Inserido via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Outras abas seguem estrutura similar, carregadas via AJAX -->
        <div id="tab-tarefas" class="ge-tab-content">
            <div class="ge-header">
                <h3>Tarefas e Projetos</h3>
                <button class="btn btn-primary btn-sm" onclick="abrirNovaTarefa()">
                    <i class="bi bi-plus"></i> Adicionar Tarefa
                </button>
            </div>
            <div id="ge-tasks-list" class="ge-item-list">
                <!-- Inserido via JS -->
            </div>
        </div>

        <div id="tab-ideias" class="ge-tab-content">
            <div class="ge-header">
                <h3>Banco de Ideias</h3>
                <button class="btn btn-info btn-sm text-white" onclick="abrirNovaIdeia()">
                    <i class="bi bi-lightbulb"></i> Nova Ideia
                </button>
            </div>
            <div id="ge-ideas-list" class="ge-item-list">
                <!-- Inserido via JS -->
            </div>
        </div>

        <div id="tab-conteudo" class="ge-tab-content">
            <div class="ge-header">
                <h3>Planejamento de Conteúdo</h3>
                <button class="btn btn-warning btn-sm" onclick="abrirNovoConteudo()">
                    <i class="bi bi-calendar-plus"></i> Novo Post
                </button>
            </div>
            <div id="ge-content-list" class="ge-item-list">
                <!-- Inserido via JS -->
            </div>
        </div>

        <div id="tab-redes-sociais" class="ge-tab-content">
            <div class="ge-header">
                <h3>Redes Sociais e Métricas</h3>
                <button class="btn btn-outline-info btn-sm" onclick="abrirNovaRedeSocial()">
                    <i class="bi bi-link-45deg"></i> Conectar Perfil
                </button>
            </div>
            <div class="ge-widget-grid" id="ge-social-list">
                <!-- Inserido via JS -->
            </div>
        </div>

        <!-- Subempresas -->
        <div id="tab-subempresas" class="ge-tab-content">
            <div class="ge-tab-header">
                <h3>Empresas Gerenciadas</h3>
                <button class="btn btn-custom-red" onclick="abrirNovaSubempresa()">
                    <i class="bi bi-plus-lg me-2"></i>Nova Subempresa
                </button>
            </div>
            <div id="ge-subs-list" class="ge-widget-grid mt-4">
                <!-- Lista de subempresas será carregada aqui -->
            </div>
        </div>

        <div id="tab-cadastro" class="ge-tab-content">
            <div class="ge-widget">
                <h3>Dados da Empresa</h3>
                <form id="form-editar-empresa" class="row mt-4">
                    <input type="hidden" id="edit-empresa-id" name="id">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" class="form-control" name="nome" id="edit-nome" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">CNPJ</label>
                        <input type="text" class="form-control" name="cnpj" id="edit-cnpj">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Segmento</label>
                        <input type="text" class="form-control" name="segmento" id="edit-segmento">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contato/Responsável</label>
                        <input type="text" class="form-control" name="contato" id="edit-contato">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Endereço</label>
                        <input type="text" class="form-control" name="endereco" id="edit-endereco">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Descrição do Negócio</label>
                        <textarea class="form-control" name="descricao" id="edit-descricao" rows="3"></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Observações Gerais</label>
                        <textarea class="form-control" name="observacoes" id="edit-observacoes" rows="3"></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center mt-4">
                        <button type="button" class="btn btn-outline-danger" onclick="confirmarExclusaoEmpresa()">
                            <i class="bi bi-trash me-2"></i>Excluir Empresa
                        </button>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Empresa -->
<div class="modal fade" id="modalNovaEmpresa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cadastrar Nova Empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaEmpresa">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" class="form-control" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Segmento</label>
                        <input type="text" class="form-control" name="segmento" placeholder="Ex: E-commerce, TI, Consultoria">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição (Opcional)</label>
                        <textarea class="form-control" name="descricao" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-custom-red">Criar Empresa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Novo Lançamento -->
<div class="modal fade" id="modalNovoLancamentoGE" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Lançamento Financeiro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovoLancamentoGE">
                <input type="hidden" name="id_empresa" id="ge-finance-id-empresa">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" class="form-control" name="descricao" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valor (R$)</label>
                            <input type="number" step="0.01" class="form-control" name="valor" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo">
                                <option value="entrada">Entrada</option>
                                <option value="saida">Saída</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <input type="text" class="form-control" name="categoria" placeholder="Ex: Venda, Material, Impostos">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data</label>
                        <input type="date" class="form-control" name="data_transacao" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nova Tarefa -->
<div class="modal fade" id="modalNovaTarefa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Nova Tarefa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaTarefa">
                <input type="hidden" name="id_empresa" class="id_empresa_hidden">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título da Tarefa</label>
                        <input type="text" class="form-control" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Responsável</label>
                        <input type="text" class="form-control" name="responsavel">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prazo</label>
                            <input type="date" class="form-control" name="prazo">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prioridade</label>
                            <select class="form-select" name="prioridade">
                                <option value="baixa">Baixa</option>
                                <option value="media" selected>Média</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Adicionar Tarefa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nova Ideia -->
<div class="modal fade" id="modalNovaIdeia" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Nova Ideia / Projeto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaIdeia">
                <input type="hidden" name="id_empresa" class="id_empresa_hidden">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" class="form-control" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="descricao" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prioridade Estratégica</label>
                        <select class="form-select" name="prioridade">
                            <option value="baixa">Baixa</option>
                            <option value="media" selected>Média</option>
                            <option value="alta">Alta</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-info text-white">Salvar Ideia</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Novo Conteúdo -->
<div class="modal fade" id="modalNovoConteudo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Planejamento de Postagem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovoConteudo">
                <input type="hidden" name="id_empresa" class="id_empresa_hidden">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Título/Tema do Post</label>
                        <input type="text" class="form-control" name="titulo" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plataforma</label>
                            <select class="form-select" name="plataforma">
                                <option value="Instagram">Instagram</option>
                                <option value="Facebook">Facebook</option>
                                <option value="LinkedIn">LinkedIn</option>
                                <option value="YouTube">YouTube</option>
                                <option value="TikTok">TikTok</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Prevista</label>
                            <input type="date" class="form-control" name="data_publicacao">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Roteiro / Legenda</label>
                        <textarea class="form-control" name="legenda" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning">Agendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nova Rede Social -->
<div class="modal fade" id="modalNovaRedeSocial" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Conectar Perfil Social</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNovaRedeSocial">
                <input type="hidden" name="id_empresa" class="id_empresa_hidden">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Plataforma</label>
                        <select class="form-select" name="plataforma">
                            <option value="Instagram">Instagram</option>
                            <option value="Facebook">Facebook</option>
                            <option value="LinkedIn">LinkedIn</option>
                            <option value="YouTube">YouTube</option>
                            <option value="TikTok">TikTok</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nome de Usuário (ex: @suaempresa)</label>
                        <input type="text" class="form-control" name="usuario" placeholder="@usuario">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL do Perfil (Opcional)</label>
                        <input type="url" class="form-control" name="url_perfil" placeholder="https://instagram.com/suaempresa">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Número Inicial de Seguidores</label>
                        <input type="number" class="form-control" name="seguidores" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Conectar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let empresaAtiva = null;
let charts = {};

document.addEventListener('DOMContentLoaded', function() {
    // Configuração das abas
    const tabBtns = document.querySelectorAll('.ge-tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            
            // Ativa botão
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // Ativa conteúdo
            document.querySelectorAll('.ge-tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(`tab-${tabId}`).classList.add('active');
            
            // Carrega dados se necessário
            carregarDadosAba(tabId);
        });
    });

    // Form Nova Empresa
    const formNovaEmpresa = document.getElementById('formNovaEmpresa');
    if (formNovaEmpresa) {
        formNovaEmpresa.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('acao', 'criar_empresa');
            
            const btnSubmit = this.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Criando...';

            fetch('ge_handlers.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.text()) // Primeiro pega como texto para debug se necessário
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch(e) {
                    console.error('Resposta não-JSON:', text);
                    throw new Error('O servidor retornou uma resposta inválida.');
                }
            })
            .then(d => {
                if (d.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: 'Empresa cadastrada com sucesso!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro!', d.message || 'Erro ao criar empresa', 'error');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error('Erro Fetch:', err);
                Swal.fire('Erro!', 'Não foi possível conectar ao servidor.', 'error');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalText;
            });
        });
    }

    // Form Novo Lançamento
    const formNovoLancamentoGE = document.getElementById('formNovoLancamentoGE');
    if (formNovoLancamentoGE) {
        formNovoLancamentoGE.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('acao', 'adicionar_financeiro');

            fetch('ge_handlers.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const modalEl = document.getElementById('modalNovoLancamentoGE');
                    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                    
                    carregarDadosAba('financeiro');
                    carregarStatsResumo();
                    this.reset();
                    Swal.fire('Sucesso!', 'Lançamento adicionado.', 'success');
                } else {
                    Swal.fire('Erro!', d.message, 'error');
                }
            });
        });
    }
});

function selecionarEmpresa(id) {
    empresaAtiva = id;
    document.getElementById('ge-list-view').style.display = 'none';
    document.getElementById('ge-detail-view').style.display = 'block';
    
    // Reset tabs
    document.querySelectorAll('.ge-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-tab="resumo"]').classList.add('active');
    document.querySelectorAll('.ge-tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-resumo').classList.add('active');

    // Carregar informações básicas da empresa
    fetch(`ge_handlers.php?acao=get_empresa&id=${id}`)
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('detalhe-empresa-nome').textContent = d.data.nome;
            document.getElementById('detalhe-empresa-segmento').textContent = d.data.segmento || 'Sem segmento';
            
            // Preencher formulário de edição
            document.getElementById('edit-empresa-id').value = d.data.id;
            document.getElementById('edit-nome').value = d.data.nome;
            document.getElementById('edit-cnpj').value = d.data.cnpj || '';
            document.getElementById('edit-segmento').value = d.data.segmento || '';
            document.getElementById('edit-contato').value = d.data.contato || '';
            document.getElementById('edit-endereco').value = d.data.endereco || '';
            document.getElementById('edit-descricao').value = d.data.descricao || '';
            document.getElementById('edit-observacoes').value = d.data.observacoes || '';
            
            // Badge de tipo
            const badge = document.getElementById('ge-badge-tipo');
            if (d.data.id_pai) {
                badge.textContent = 'Subempresa';
                badge.className = 'badge bg-secondary';
                badge.classList.remove('d-none');
            } else {
                badge.textContent = 'Matriz';
                badge.className = 'badge bg-primary';
                badge.classList.add('d-none'); // Esconde por padrão, carregarStatsResumo vai mostrar se tiver subs
            }

            // Carregar dados iniciais (Resumo)
            carregarStatsResumo();
            inicializarGraficos();
        }
    });
}

function voltarParaLista() {
    document.getElementById('ge-list-view').style.display = 'block';
    document.getElementById('ge-detail-view').style.display = 'none';
    empresaAtiva = null;
}

function abrirConfiguracoesEmpresa() {
    // Simular clique na aba de cadastro/informações
    const tabBtn = document.querySelector('[data-tab="cadastro"]');
    if (tabBtn) tabBtn.click();
}

function exportarDadosEmpresa() {
    Swal.fire({
        title: 'Exportar Dados',
        text: 'Deseja exportar os dados desta empresa para Excel?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, exportar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `ge_handlers.php?acao=exportar_excel&id=${empresaAtiva}`;
        }
    });
}

function carregarStatsResumo() {
    fetch(`ge_handlers.php?acao=get_resumo&id=${empresaAtiva}`)
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('stats-receitas').textContent = formataBRL(d.data.receitas);
            document.getElementById('stats-despesas').textContent = formataBRL(d.data.despesas);
            document.getElementById('stats-saldo').textContent = formataBRL(d.data.saldo);
            document.getElementById('stats-tarefas').textContent = d.data.tarefas_pendentes;
            
            // Stats do Grupo
            const groupView = document.getElementById('ge-group-vision');
            const typeBadge = document.getElementById('ge-badge-tipo');
            
            if (d.data.is_matriz) {
                groupView.classList.remove('d-none');
                typeBadge.classList.remove('d-none');
                document.getElementById('stats-grupo-receitas').textContent = formataBRL(d.data.receitas_grupo);
                document.getElementById('stats-grupo-despesas').textContent = formataBRL(d.data.despesas_grupo);
                document.getElementById('stats-grupo-saldo').textContent = formataBRL(d.data.saldo_grupo);
            } else {
                groupView.classList.add('d-none');
            }

            atualizarListaResumoTarefas(d.data.ultimas_tarefas);
        }
    });
}

function carregarDadosAba(tab) {
    if (!empresaAtiva) return;

    switch(tab) {
        case 'financeiro':
            carregarTabelaFinanceira();
            break;
        case 'tarefas':
            carregarListaTarefas();
            break;
        case 'ideias':
            carregarListaIdeias();
            break;
        case 'conteudo':
            carregarListaConteudo();
            break;
        case 'redes-sociais':
            carregarListaRedesSociais();
            break;
        case 'subempresas':
            carregarListaSubempresas();
            break;
    }
}

function carregarListaSubempresas() {
    fetch(`ge_handlers.php?acao=get_subempresas&id=${empresaAtiva}`)
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const list = document.getElementById('ge-subs-list');
            list.innerHTML = '';
            if (d.data.length === 0) {
                list.innerHTML = '<div class="col-12 text-center p-5"><i class="bi bi-diagram-3 mb-3 d-block" style="font-size: 3rem; opacity: 0.2;"></i><p class="text-muted">Nenhuma subempresa cadastrada para esta matriz.</p></div>';
                return;
            }
            d.data.forEach(s => {
                const widget = `
                    <div class="ge-widget subempresa-card" onclick="selecionarEmpresa(${s.id})">
                        <div class="ge-widget-header">
                            <span class="ge-widget-title">${s.nome}</span>
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="ge-widget-stats mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Receitas:</small>
                                <span class="text-success small">${formataBRL(s.receitas)}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Despesas:</small>
                                <span class="text-danger small">${formataBRL(s.despesas)}</span>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-1 mt-1">
                                <strong>Saldo:</strong>
                                <strong class="${s.saldo >= 0 ? 'text-success' : 'text-danger'}">${formataBRL(s.saldo)}</strong>
                            </div>
                        </div>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', widget);
            });
        }
    });
}

function abrirNovaSubempresa() {
    Swal.fire({
        title: 'Nova Subempresa',
        background: '#1a1a1c',
        color: '#ffffff',
        html: `
            <div style="text-align: left; padding: 0 10px;">
                <label style="color: #aaa; font-size: 0.8rem; margin-bottom: 5px; display: block;">Nome da Unidade</label>
                <input id="swal-sub-nome" class="swal2-input" placeholder="Ex: Filial Centro" style="margin: 0 0 15px 0; width: 100%; background: #252527; border: 1px solid #333; color: white;">
                
                <label style="color: #aaa; font-size: 0.8rem; margin-bottom: 5px; display: block;">Segmento</label>
                <input id="swal-sub-segmento" class="swal2-input" placeholder="Ex: Varejo" style="margin: 0 0 15px 0; width: 100%; background: #252527; border: 1px solid #333; color: white;">
                
                <label style="color: #aaa; font-size: 0.8rem; margin-bottom: 5px; display: block;">Descrição Curta</label>
                <textarea id="swal-sub-desc" class="swal2-textarea" placeholder="Breve descrição da operação..." style="margin: 0; width: 100%; background: #252527; border: 1px solid #333; color: white;"></textarea>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Criar Subempresa',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#e50914',
        cancelButtonColor: '#444',
        customClass: {
            popup: 'ge-swal-custom',
            confirmButton: 'ge-swal-confirm',
            cancelButton: 'ge-swal-cancel'
        },
        preConfirm: () => {
            const nome = document.getElementById('swal-sub-nome').value;
            if (!nome) {
                Swal.showValidationMessage('O nome é obrigatório');
                return false;
            }
            return {
                nome: nome,
                segmento: document.getElementById('swal-sub-segmento').value,
                descricao: document.getElementById('swal-sub-desc').value,
                id_pai: empresaAtiva
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('acao', 'criar_empresa');
            formData.append('nome', result.value.nome);
            formData.append('segmento', result.value.segmento);
            formData.append('descricao', result.value.descricao);
            formData.append('id_pai', result.value.id_pai);

            fetch('ge_handlers.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Subempresa criada com sucesso.',
                        icon: 'success',
                        background: '#1a1a1c',
                        color: '#ffffff',
                        confirmButtonColor: '#e50914'
                    });
                    carregarListaSubempresas();
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: d.message,
                        icon: 'error',
                        background: '#1a1a1c',
                        color: '#ffffff',
                        confirmButtonColor: '#e50914'
                    });
                }
            });
        }
    });
}

function carregarTabelaFinanceira() {
    fetch(`ge_handlers.php?acao=get_financeiro&id=${empresaAtiva}`)
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const body = document.getElementById('ge-finance-table-body');
            body.innerHTML = '';
            d.data.forEach(t => {
                const row = `
                    <tr>
                        <td>${new Date(t.data_transacao).toLocaleDateString()}</td>
                        <td>${t.descricao}</td>
                        <td>${t.categoria || '-'}</td>
                        <td><span class="ge-badge badge-${t.tipo === 'entrada' ? 'concluida' : 'saida'}">${t.tipo}</span></td>
                        <td class="${t.tipo === 'entrada' ? 'text-success' : 'text-danger'}">${formataBRL(t.valor)}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirFinanceiro(${t.id})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                `;
                body.insertAdjacentHTML('beforeend', row);
            });
            
            inicializarGraficoFinanceiro(d.chartData);
            inicializarGraficoCategorias(d.categoryData);
        }
    });
}

function abrirNovoLancamento() {
    document.getElementById('ge-finance-id-empresa').value = empresaAtiva;
    const modalEl = document.getElementById('modalNovoLancamentoGE');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function excluirFinanceiro(id) {
    if (confirm('Deseja excluir este lançamento?')) {
        fetch(`ge_handlers.php?acao=excluir_financeiro&id=${id}`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                carregarTabelaFinanceira();
                carregarStatsResumo();
            }
        });
    }
}

// Helpers
function formataBRL(valor) {
    return parseFloat(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function confirmarExclusaoEmpresa() {
    if (!empresaAtiva) return;
    
    Swal.fire({
        title: 'Excluir Empresa?',
        text: "Esta ação é irreversível e apagará TODOS os dados financeiros, tarefas e ideias vinculados a esta empresa!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, excluir tudo!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('acao', 'excluir_empresa');
            formData.append('id', empresaAtiva);

            fetch('ge_handlers.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire(
                        'Excluída!',
                        'A empresa foi removida com sucesso.',
                        'success'
                    ).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Erro!', d.message, 'error');
                }
            });
        }
    });
}

function inicializarGraficoFinanceiro(data) {
    const ctx = document.getElementById('ge-finance-chart').getContext('2d');
    if (charts.finance) charts.finance.destroy();
    
    charts.finance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'], // Exemplo, deveria vir do back
            datasets: [{
                label: 'Receitas',
                data: [1200, 1900, 3000, 2500, 2000, 3000],
                borderColor: '#00b894',
                fill: false
            }, {
                label: 'Despesas',
                data: [1000, 1500, 2000, 1800, 1500, 2200],
                borderColor: '#d63031',
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
}

function inicializarGraficoCategorias(data) {
    const ctx = document.getElementById('ge-finance-category-chart').getContext('2d');
    if (charts.categories) charts.categories.destroy();

    const labels = data.map(d => d.categoria || 'Outros');
    const values = data.map(d => d.total);

    charts.categories = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: ['#6c5ce7', '#00b894', '#fdcb6e', '#d63031', '#0984e3']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

function atualizarListaResumoTarefas(tarefas) {
    const lista = document.getElementById('resumo-tarefas-lista');
    lista.innerHTML = '';
    if (tarefas.length === 0) {
        lista.innerHTML = '<p class="text-muted">Nenhuma tarefa pendente.</p>';
        return;
    }
    tarefas.forEach(t => {
        const item = `
            <div class="ge-list-item">
                <div class="ge-item-info">
                    <h4>${t.titulo}</h4>
                    <p><i class="bi bi-calendar"></i> ${t.prazo ? new Date(t.prazo).toLocaleDateString() : 'Sem prazo'}</p>
                </div>
                <span class="ge-badge badge-pendente">pendente</span>
            </div>
        `;
        lista.insertAdjacentHTML('beforeend', item);
    });
}

// Stubs para as outras áreas que você completará
function carregarListaTarefas() {
    fetch(`ge_handlers.php?acao=get_tarefas&id=${empresaAtiva}`)
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const list = document.getElementById('ge-tasks-list');
            list.innerHTML = '';
            d.data.forEach(t => {
                const item = `
                    <div class="ge-list-item">
                        <div class="ge-item-info">
                            <h4 style="${t.status === 'concluida' ? 'text-decoration: line-through;' : ''}">${t.titulo}</h4>
                            <p>${t.responsavel ? 'Resp: ' + t.responsavel : ''} | Prazo: ${t.prazo ? new Date(t.prazo).toLocaleDateString() : 'N/A'}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="ge-badge badge-${t.status}">${t.status}</span>
                            <button class="btn btn-sm btn-outline-success" onclick="alternarStatusTarefa(${t.id}, '${t.status}')">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="excluirTarefa(${t.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', item);
            });
        }
    });
}

function alternarStatusTarefa(id, status) {
    const novoStatus = status === 'concluida' ? 'pendente' : 'concluida';
    fetch('ge_handlers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `acao=atualizar_status_tarefa&id=${id}&status=${novoStatus}`
    }).then(() => carregarListaTarefas());
}

function carregarListaIdeias() {
    fetch(`ge_handlers.php?acao=get_ideias&id=${empresaAtiva}`)
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const list = document.getElementById('ge-ideas-list');
            list.innerHTML = '';
            d.data.forEach(i => {
                const item = `
                    <div class="ge-list-item">
                        <div class="ge-item-info">
                            <h4>${i.titulo}</h4>
                            <p>${i.descricao || ''}</p>
                        </div>
                        <span class="ge-badge badge-${i.status}">${i.status}</span>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', item);
            });
        }
    });
}

function carregarListaConteudo() {
    fetch(`ge_handlers.php?acao=get_conteudo&id=${empresaAtiva}`)
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const list = document.getElementById('ge-content-list');
            list.innerHTML = '';
            d.data.forEach(c => {
                const item = `
                    <div class="ge-list-item">
                        <div class="ge-item-info">
                            <h4>${c.titulo}</h4>
                            <p><i class="bi bi-${c.plataforma.toLowerCase()}"></i> ${c.plataforma} | ${c.data_publicacao ? new Date(c.data_publicacao).toLocaleDateString() : 'Planejado'}</p>
                        </div>
                        <span class="ge-badge badge-${c.status}">${c.status}</span>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', item);
            });
        }
    });
}

function carregarListaRedesSociais() {
    fetch(`ge_handlers.php?acao=get_redes_sociais&id=${empresaAtiva}`)
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const list = document.getElementById('ge-social-list');
            list.innerHTML = '';
            d.data.forEach(s => {
                const icon = s.plataforma.toLowerCase().includes('instagram') ? 'instagram' : 
                             s.plataforma.toLowerCase().includes('facebook') ? 'facebook' :
                             s.plataforma.toLowerCase().includes('tiktok') ? 'tiktok' :
                             s.plataforma.toLowerCase().includes('youtube') ? 'youtube' :
                             s.plataforma.toLowerCase().includes('linkedin') ? 'linkedin' : 'share';
                const widget = `
                const widget = `
                    <div class="ge-widget shadow-sm position-relative" data-usuario="${s.usuario}" data-plataforma="${s.plataforma}">
                        <button class="btn btn-sm text-danger position-absolute top-0 end-0 m-2" onclick="excluirRedeSocial(${s.id})" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                        <div class="ge-widget-header mb-3">
                            <div>
                                <h5 class="mb-0">${s.plataforma}</h5>
                                <small class="text-muted">${s.usuario || 'N/A'}</small>
                            </div>
                            <i class="bi bi-${icon} fs-3 text-primary"></i>
                        </div>
                        <div class="row text-center mb-3">
                            <div class="col-4 border-end">
                                <div class="fw-bold">${parseInt(s.seguidores || 0).toLocaleString()}</div>
                                <small class="text-muted x-small">Seguidores</small>
                            </div>
                            <div class="col-4 border-end">
                                <div class="fw-bold">${parseInt(s.seguindo || 0).toLocaleString()}</div>
                                <small class="text-muted x-small">Seguindo</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold">${parseInt(s.posts || 0).toLocaleString()}</div>
                                <small class="text-muted x-small">Posts</small>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="sincronizarRedeSocial(${s.id}, this)">
                                <i class="bi bi-arrow-repeat me-1"></i> Sincronizar
                            </button>
                            <a href="${s.url_perfil}" target="_blank" class="btn btn-sm btn-link text-muted">Ver Perfil</a>
                        </div>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', widget);
            });
        }
    });
}

function sincronizarRedeSocial(id, btn) {
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';

    // 1. Obter dados da rede social do elemento DOM (mais rápido que fetch)
    // O widget agora precisa ter atributos data-usuario e data-plataforma
    const widget = btn.closest('.ge-widget');
    const usuario = widget.dataset.usuario;
    const plataforma = widget.dataset.plataforma.toLowerCase();

    if (!usuario || usuario === 'N/A') {
        Swal.fire('Erro!', 'Usuário não configurado.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalContent;
        return;
    }

    // 2. Lógica de Scraping Client-Side (CORS Proxy)
    let urlScrape = '';
    if (plataforma.includes('instagram')) {
        const cleanUser = usuario.replace('@', '').trim();
        urlScrape = `https://corsproxy.io/?https://www.picuki.com/profile/${cleanUser}`;
    } else {
        // Implementar futuramente outras redes
        Swal.fire('Aviso', 'Sincronização automática disponível apenas para Instagram no momento.', 'info');
        btn.disabled = false;
        btn.innerHTML = originalContent;
        return;
    }

    fetch(urlScrape)
    .then(r => r.text())
    .then(html => {
        // 3. Parse HTML
        let seguidores = 0, seguindo = 0, posts = 0;
        let success = false;

        // Picuki Parser
        const parseNum = (str) => {
            str = str.toLowerCase().replace(/,/g, '').trim();
            if (str.includes('k')) return parseFloat(str) * 1000;
            if (str.includes('m')) return parseFloat(str) * 1000000;
            return parseInt(str) || 0;
        };

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const followersEl = doc.querySelector('.followed_by');
        if (followersEl) {
            seguidores = parseNum(followersEl.textContent);
            success = true;
        }
        
        const followingEl = doc.querySelector('.follows');
        if (followingEl) seguindo = parseNum(followingEl.textContent);

        const postsEl = doc.querySelector('.total_posts');
        if (postsEl) posts = parseNum(postsEl.textContent);

        if (!success) {
            throw new Error('Não foi possível ler os dados do perfil (Perfil privado ou inexistente).');
        }

        // 4. Salvar no Backend
        const formData = new FormData();
        formData.append('acao', 'salvar_metricas_social');
        formData.append('id', id);
        formData.append('seguidores', seguidores);
        formData.append('seguindo', seguindo);
        formData.append('posts', posts);

        return fetch('ge_handlers.php', {
            method: 'POST',
            body: formData
        });
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            Swal.fire({
                title: 'Atualizado!',
                text: 'Dados sincronizados com sucesso.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                background: '#1a1a1c',
                color: '#fff'
            });
            carregarListaRedesSociais();
        } else {
            throw new Error(d.message);
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            title: 'Erro na Sincronização',
            text: 'O Instagram bloqueou a leitura automática. Tente novamente mais tarde ou atualize manualmente.',
            icon: 'error',
            background: '#1a1a1c',
            color: '#fff'
        });
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

function excluirRedeSocial(id) {
    Swal.fire({
        title: 'Excluir Rede Social?',
        text: "Esta ação removerá o perfil e todas as métricas salvas.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e50914',
        cancelButtonColor: '#444',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar',
        background: '#1a1a1c',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ge_handlers.php?acao=excluir_rede_social&id=${id}`)
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({
                        title: 'Excluído!',
                        icon: 'success',
                        timer: 1000,
                        showConfirmButton: false,
                        background: '#1a1a1c',
                        color: '#fff'
                    });
                    carregarListaRedesSociais();
                }
            });
        }
    });
}

function abrirNovaRedeSocial() {
    document.querySelectorAll('.id_empresa_hidden').forEach(el => el.value = empresaAtiva);
    const modalEl = document.getElementById('modalNovaRedeSocial');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function abrirNovaTarefa() {
    document.querySelectorAll('.id_empresa_hidden').forEach(el => el.value = empresaAtiva);
    const modalEl = document.getElementById('modalNovaTarefa');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function abrirNovaIdeia() {
    document.querySelectorAll('.id_empresa_hidden').forEach(el => el.value = empresaAtiva);
    const modalEl = document.getElementById('modalNovaIdeia');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function abrirNovoConteudo() {
    document.querySelectorAll('.id_empresa_hidden').forEach(el => el.value = empresaAtiva);
    const modalEl = document.getElementById('modalNovoConteudo');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

// Handler para o form de edição da empresa
document.getElementById('form-editar-empresa').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('acao', 'atualizar_empresa');

    fetch('ge_handlers.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            Swal.fire({
                icon: 'success',
                title: 'Atualizado!',
                text: 'Dados da empresa salvos com sucesso.',
                timer: 1500,
                showConfirmButton: false,
                background: '#1a1a1c',
                color: '#fff'
            });
            selecionarEmpresa(empresaAtiva);
        } else {
            Swal.fire('Erro!', d.message || 'Erro ao atualizar dados.', 'error');
        }
    });
});

// Generic form submission for remaining modals
['formNovaTarefa', 'formNovaIdeia', 'formNovoConteudo', 'formNovaRedeSocial'].forEach(formId => {
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const action = formId === 'formNovaTarefa' ? 'adicionar_tarefa' : 
                       formId === 'formNovaIdeia' ? 'adicionar_ideia' :
                       formId === 'formNovoConteudo' ? 'adicionar_conteudo' : 'adicionar_rede_social';
        formData.append('acao', action);

        fetch('ge_handlers.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const modalId = `modal${formId.replace('form', '')}`;
                const modalEl = document.getElementById(modalId);
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.hide();
                this.reset();
                
                const tabMap = {
                    'formNovaTarefa': 'tarefas',
                    'formNovaIdeia': 'ideias',
                    'formNovoConteudo': 'conteudo',
                    'formNovaRedeSocial': 'redes-sociais'
                };
                carregarDadosAba(tabMap[formId]);
                Swal.fire('Sucesso!', 'Item adicionado.', 'success');
            } else {
                Swal.fire('Erro!', d.message, 'error');
            }
        });
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>
