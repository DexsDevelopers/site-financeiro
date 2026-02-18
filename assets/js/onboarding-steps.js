// assets/js/onboarding-steps.js
// Mapa de tours por página – define etapas específicas por rota
(function(){
    function step(selector, title, description, side){
        return { element: selector, popover: { title: title, description: description, side: side || 'bottom' } };
    }

    function safePush(arr, exists, s){ if (exists(s.element)) arr.push(s); }
    function intro(arr, exists, title, description){
        // tenta ancorar no título da página; fallback para .main-content e body
        const targets = ['h1', '.page-title', '.card .card-title', '.main-content', 'body'];
        for (let i = 0; i < targets.length; i++) {
            if (exists(targets[i])) { arr.push(step(targets[i], title, description, 'bottom')); return; }
        }
    }

    window.ONBOARDING_STEPS = {
        'dashboard.php': function(exists, isMobile){
            const steps = [];
            intro(steps, exists, 'Bem-vindo ao Dashboard', 'Visão geral do seu financeiro e produtividade. Acompanhe métricas, gráficos e atalhos rápidos.');
            safePush(steps, exists, step('#btn-toggle-saldo', 'Ocultar/Mostrar valores', 'Clique para ocultar valores sensíveis do dashboard.', 'right'));
            safePush(steps, exists, step('#formIaRapida', 'Lançamento Rápido com IA', 'Digite: "Comprei pizza por R$ 25 hoje". A IA entende, classifica e lança.', isMobile ? 'top' : 'bottom'));
            safePush(steps, exists, step('#pieChart', 'Despesas por Categoria', 'Veja suas despesas agrupadas por categoria.', 'left'));
            safePush(steps, exists, step('#barChart', 'Despesas Diárias', 'Acompanhe a evolução diária dos seus gastos.', 'left'));
            safePush(steps, exists, step('a[href="tarefas.php"]', 'Tarefas', 'Abra a tela de tarefas para gerenciar sua rotina.', 'left'));
            return steps;
        },
        'tarefas.php': function(exists, isMobile){
            const steps = [];
            intro(steps, exists, 'Rotina de Tarefas', 'Crie, priorize e conclua tarefas e subtarefas. Organize sua rotina de forma simples.');
            safePush(steps, exists, step('.btn.btn-primary, .btn.btn-danger, button[type="submit"]', 'Adicionar Tarefa', 'Crie novas tarefas rapidamente.', 'right'));
            safePush(steps, exists, step('.task-list, #lista-tarefas-pendentes', 'Lista de Tarefas', 'Arraste, conclua e edite suas tarefas.', 'top'));
            safePush(steps, exists, step('.subtask-list, .subtasks-container', 'Subtarefas', 'Gerencie subtarefas: concluir, editar e excluir.', 'top'));
            return steps;
        },
        'calendario.php': function(exists){
            const steps = [];
            intro(steps, exists, 'Calendário', 'Visualize tarefas e eventos por data. Planeje sua semana com clareza.');
            safePush(steps, exists, step('#calendar, .calendar', 'Calendário', 'Veja e navegue pelos seus eventos.', 'bottom'));
            safePush(steps, exists, step('.btn.btn-danger, .btn.btn-primary', 'Novo Evento', 'Adicione rapidamente um novo evento.', 'right'));
            return steps;
        },
        'relatorios.php': function(exists){
            const steps = [];
            intro(steps, exists, 'Relatórios', 'Gere relatórios para entender seus gastos, receitas e tendências.');
            safePush(steps, exists, step('.filters, form[action*="relatorios"]', 'Filtros', 'Selecione período e filtros para gerar relatórios.', 'right'));
            safePush(steps, exists, step('canvas, .chart-container', 'Gráficos', 'Analise seus resultados em gráficos interativos.', 'left'));
            return steps;
        },
        'categorias.php': function(exists){
            const steps = [];
            intro(steps, exists, 'Categorias', 'Organize suas transações em categorias personalizadas.');
            safePush(steps, exists, step('form[action*="criar_categoria"], .btn.btn-danger, .btn.btn-primary', 'Criar Categoria', 'Defina novas categorias para organizar suas finanças.', 'right'));
            safePush(steps, exists, step('table, .list-group', 'Gerenciar Categorias', 'Edite, reordene e exclua categorias existentes.', 'top'));
            return steps;
        },
        'perfil.php': function(exists){
            const steps = [];
            intro(steps, exists, 'Meu Perfil', 'Atualize seus dados pessoais e preferências com segurança.');
            safePush(steps, exists, step('form[action*="atualizar_perfil"], #perfil, .card', 'Perfil', 'Edite suas informações principais.', 'bottom'));
            safePush(steps, exists, step('button[type="submit"], .btn.btn-danger, .btn.btn-primary', 'Salvar Alterações', 'Salve qualquer atualização realizada.', 'right'));
            return steps;
        },
        'academy.php': function(exists){
            const steps = [];
            intro(steps, exists, 'Academy', 'Central de desenvolvimento pessoal: cursos, treinos, rotina e alimentação.');
            // Removido :contains (não suportado em querySelector). Usar ícones e fallbacks genéricos.
            safePush(steps, exists, step('.feature-icon .bi-book-half, .bi-book-half', 'Meus Cursos', 'Organize seus cursos entre pendentes, assistindo e concluídos.', 'bottom'));
            safePush(steps, exists, step('.feature-icon .bi-barbell, .bi-barbell', 'Registro de Treinos', 'Registre cargas, séries e repetições.', 'bottom'));
            return steps;
        }
    };
})();


