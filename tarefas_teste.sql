
-- ===============================================
-- TAREFAS DE TESTE PARA VISUALIZAR NA APLICAÇÃO
-- ===============================================

-- Importante: Substituir "1" pelo ID do seu usuário
-- Você pode descobrir executando: SELECT id, usuario FROM usuarios LIMIT 5;

INSERT INTO tarefas (id_usuario, titulo, descricao, prioridade, data_limite, tempo_estimado, status, data_criacao) VALUES
(1, '📝 Revisar documentação', 'Revisar e atualizar a documentação do projeto', 'Alta', DATE_ADD(NOW(), INTERVAL 3 DAY), 120, 'pendente', NOW()),
(1, '🐛 Corrigir bugs da aplicação', 'Identificar e corrigir bugs reportados pelos usuários', 'Alta', DATE_ADD(NOW(), INTERVAL 5 DAY), 240, 'pendente', NOW()),
(1, '📊 Preparar relatório mensal', 'Compilar dados e preparar relatório de progresso', 'Média', DATE_ADD(NOW(), INTERVAL 7 DAY), 180, 'pendente', NOW()),
(1, '💬 Responder emails pendentes', 'Responder todos os emails da caixa de entrada', 'Média', DATE_ADD(NOW(), INTERVAL 1 DAY), 60, 'pendente', NOW()),
(1, '🎨 Atualizar design da interface', 'Implementar novo design conforme aprovado pelo time', 'Baixa', DATE_ADD(NOW(), INTERVAL 10 DAY), 300, 'pendente', NOW()),
(1, '📚 Estudar nova tecnologia', 'Aprender sobre a nova stack que vamos usar no próximo projeto', 'Baixa', DATE_ADD(NOW(), INTERVAL 14 DAY), 420, 'pendente', NOW());

-- Se tiver múltiplos usuários, repita a inserção alterando o ID do usuário (1 para 2, 3, etc)

-- Verificar tarefas inseridas:
SELECT COUNT(*) as 'Total de tarefas' FROM tarefas WHERE status = 'pendente';
SELECT titulo, prioridade, data_limite FROM tarefas WHERE status = 'pendente' ORDER BY prioridade DESC;
