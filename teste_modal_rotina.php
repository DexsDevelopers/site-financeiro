<?php
session_start();
require_once 'includes/db_connect.php';

// Simular usuário logado
$_SESSION['user_id'] = 1;

require_once 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Modal Rotina</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Teste Modal Rotina Fixa</h1>
        
        <button class="btn btn-primary" onclick="adicionarRotinaFixa()">
            <i class="bi bi-plus-circle me-1"></i>
            Adicionar Rotina Fixa
        </button>
        
        <!-- Modal de Adicionar Rotina Fixa -->
        <div class="modal fade" id="modalAdicionarRotinaFixa" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle me-2"></i>Adicionar Rotina Fixa
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="formAdicionarRotinaFixa">
                            <div class="mb-3">
                                <label for="nomeRotinaFixa" class="form-label">
                                    <i class="bi bi-tag me-1"></i>Nome da Rotina
                                </label>
                                <input type="text" class="form-control" id="nomeRotinaFixa" placeholder="Ex: Treinar, Estudar, Meditar..." required>
                            </div>
                            <div class="mb-3">
                                <label for="horarioRotinaFixa" class="form-label">
                                    <i class="bi bi-clock me-1"></i>Horário Sugerido (Opcional)
                                </label>
                                <input type="time" class="form-control" id="horarioRotinaFixa">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>Defina um horário ideal para esta rotina
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="descricaoRotinaFixa" class="form-label">
                                    <i class="bi bi-card-text me-1"></i>Descrição (Opcional)
                                </label>
                                <textarea class="form-control" id="descricaoRotinaFixa" rows="2" placeholder="Adicione uma descrição ou observações sobre esta rotina..."></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="salvarRotinaFixa()">
                            <i class="bi bi-check-circle me-1"></i>Adicionar Rotina
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function adicionarRotinaFixa() {
        console.log('Abrindo modal...');
        // Limpar formulário
        document.getElementById('formAdicionarRotinaFixa').reset();
        
        // Abrir modal
        const modal = new bootstrap.Modal(document.getElementById('modalAdicionarRotinaFixa'));
        modal.show();
    }

    function salvarRotinaFixa() {
        const nome = document.getElementById('nomeRotinaFixa').value.trim();
        const horario = document.getElementById('horarioRotinaFixa').value;
        const descricao = document.getElementById('descricaoRotinaFixa').value.trim();
        
        console.log('Dados:', { nome, horario, descricao });
        
        if (!nome) {
            alert('Nome da rotina é obrigatório');
            return;
        }
        
        alert('Modal funcionando! Dados: ' + JSON.stringify({ nome, horario, descricao }));
        
        // Fechar modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalAdicionarRotinaFixa'));
        modal.hide();
    }
    </script>
</body>
</html>
