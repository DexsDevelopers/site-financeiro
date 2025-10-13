<?php
// /admin/index.php (Versão Final Organizada e Moderna)

require_once 'header_admin.php';

try {
    // A query agora busca também o telefone, em uma única consulta.
    $stmt = $pdo->prepare("SELECT id, usuario, nome_completo, data_criacao, telefone FROM usuarios WHERE tipo = 'usuario' ORDER BY data_criacao DESC");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar usuários: " . $e->getMessage());
}
?>

<!-- Header da Página -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <div>
        <h1 class="h2 mb-1">
            <i class="bi bi-people-fill me-2 text-danger"></i>
            Gerenciamento de Usuários
        </h1>
        <p class="text-muted mb-0">Gerencie usuários do sistema financeiro</p>
    </div>
    <button class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
        <i class="bi bi-person-plus-fill me-2"></i>
        <span class="d-none d-sm-inline">Adicionar Usuário</span>
        <span class="d-sm-none">Adicionar</span>
    </button>
</div>

<!-- Estatísticas Rápidas -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="admin-card text-center">
            <div class="card-body py-3">
                <i class="bi bi-people-fill fs-2 text-primary mb-2"></i>
                <h5 class="mb-1"><?php echo count($usuarios); ?></h5>
                <small class="text-muted">Total de Usuários</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card text-center">
            <div class="card-body py-3">
                <i class="bi bi-person-check-fill fs-2 text-success mb-2"></i>
                <h5 class="mb-1"><?php echo count(array_filter($usuarios, function($u) { return !empty($u['telefone']); })); ?></h5>
                <small class="text-muted">Com Telefone</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card text-center">
            <div class="card-body py-3">
                <i class="bi bi-calendar-check-fill fs-2 text-info mb-2"></i>
                <h5 class="mb-1"><?php echo count(array_filter($usuarios, function($u) { return date('Y-m', strtotime($u['data_criacao'])) == date('Y-m'); })); ?></h5>
                <small class="text-muted">Este Mês</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card text-center">
            <div class="card-body py-3">
                <i class="bi bi-shield-check-fill fs-2 text-warning mb-2"></i>
                <h5 class="mb-1"><?php echo count($usuarios); ?></h5>
                <small class="text-muted">Usuários Ativos</small>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Usuários -->
<div class="admin-card">
    <div class="card-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h5 class="mb-1">
                    <i class="bi bi-table me-2"></i>
                    Lista de Usuários
                </h5>
                <small class="text-muted">Gerencie todos os usuários do sistema</small>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="btnRefresh">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span class="d-none d-sm-inline ms-1">Atualizar</span>
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="btnExport">
                    <i class="bi bi-download"></i>
                    <span class="d-none d-sm-inline ms-1">Exportar</span>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="admin-table-container">
        <div class="table-responsive">
                <!-- Tabela Desktop/Tablet -->
                <table class="table admin-table align-middle mb-0 d-none d-md-table">
                <thead>
                    <tr>
                            <th class="border-0">
                                <i class="bi bi-person-fill me-1"></i>
                                Nome Completo
                            </th>
                            <th class="border-0">
                                <i class="bi bi-person-badge-fill me-1"></i>
                                Usuário
                            </th>
                            <th class="border-0">
                                <i class="bi bi-telephone-fill me-1"></i>
                                Telefone
                            </th>
                            <th class="border-0">
                                <i class="bi bi-calendar-fill me-1"></i>
                                Data de Criação
                            </th>
                            <th class="border-0 text-end">
                                <i class="bi bi-gear-fill me-1"></i>
                                Ações
                            </th>
                    </tr>
                </thead>
                <tbody id="user-table-body">
                        <?php if (empty($usuarios)): ?>
                            <tr id="no-users-row">
                                <td colspan="5" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="bi bi-people fs-1 text-muted mb-3"></i>
                                        <h6 class="text-muted mb-2">Nenhum usuário cadastrado</h6>
                                        <p class="text-muted small mb-3">Comece adicionando o primeiro usuário ao sistema</p>
                                        <button class="btn btn-admin btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
                                            <i class="bi bi-person-plus-fill me-2"></i>
                                            Adicionar Primeiro Usuário
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($usuarios as $user): ?>
                                <tr id="user-row-<?php echo $user['id']; ?>" class="user-row">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <i class="bi bi-person-fill text-white"></i>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($user['nome_completo']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="text-info"><?php echo htmlspecialchars($user['usuario']); ?></code>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['telefone'])): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                <?php echo htmlspecialchars($user['telefone']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-x-circle-fill me-1"></i>
                                                Não cadastrado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="d-block"><?php echo date('d/m/Y', strtotime($user['data_criacao'])); ?></span>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($user['data_criacao'])); ?></small>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-info btn-redefinir-senha" 
                                                    data-id="<?php echo $user['id']; ?>" 
                                                    data-nome="<?php echo htmlspecialchars($user['nome_completo']); ?>"
                                                    title="Redefinir Senha">
                                                <i class="bi bi-key-fill"></i>
                                                <span class="d-none d-lg-inline ms-1">Senha</span>
                                            </button>
                                            <button class="btn btn-outline-danger btn-excluir-usuario" 
                                                    data-id="<?php echo $user['id']; ?>" 
                                                    data-nome="<?php echo htmlspecialchars($user['nome_completo']); ?>"
                                                    title="Excluir Usuário">
                                                <i class="bi bi-trash-fill"></i>
                                                <span class="d-none d-lg-inline ms-1">Excluir</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Cards Mobile -->
                <div class="d-md-none" id="user-cards-mobile">
                    <?php if (empty($usuarios)): ?>
                        <div class="text-center py-5">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                                <h6 class="text-muted mb-2">Nenhum usuário cadastrado</h6>
                                <p class="text-muted small mb-3">Comece adicionando o primeiro usuário ao sistema</p>
                                <button class="btn btn-admin btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
                                    <i class="bi bi-person-plus-fill me-2"></i>
                                    Adicionar Primeiro Usuário
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($usuarios as $user): ?>
                            <div class="admin-card mb-3" id="user-card-<?php echo $user['id']; ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <i class="bi bi-person-fill text-white"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($user['nome_completo']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-person-badge-fill me-1"></i>
                                                    <?php echo htmlspecialchars($user['usuario']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-info btn-redefinir-senha" 
                                                    data-id="<?php echo $user['id']; ?>" 
                                                    data-nome="<?php echo htmlspecialchars($user['nome_completo']); ?>"
                                                    title="Redefinir Senha">
                                                <i class="bi bi-key-fill"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-excluir-usuario" 
                                                    data-id="<?php echo $user['id']; ?>" 
                                                    data-nome="<?php echo htmlspecialchars($user['nome_completo']); ?>"
                                                    title="Excluir Usuário">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-telephone-fill text-muted me-2"></i>
                                                <div>
                                                    <small class="text-muted d-block">Telefone</small>
                                                    <?php if (!empty($user['telefone'])): ?>
                                                        <span class="badge bg-success">
                                                            <?php echo htmlspecialchars($user['telefone']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Não cadastrado</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-calendar-fill text-muted me-2"></i>
                                                <div>
                                                    <small class="text-muted d-block">Data de Criação</small>
                                                    <small class="fw-bold"><?php echo date('d/m/Y', strtotime($user['data_criacao'])); ?></small>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('H:i', strtotime($user['data_criacao'])); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Usuário -->
<div class="modal fade admin-modal" id="modalNovoUsuario" tabindex="-1" aria-labelledby="modalNovoUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoUsuarioLabel">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    Criar Novo Usuário
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form id="formNovoUsuario" action="adicionar_usuario.php" method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="form-nome" class="form-label">
                                <i class="bi bi-person-fill me-1"></i>
                                Nome Completo
                            </label>
                            <input type="text" name="nome_completo" id="form-nome" class="form-control" 
                                   placeholder="Digite o nome completo do usuário" required>
                            <div class="form-text">Nome que será exibido no sistema</div>
                        </div>
                        <div class="col-md-6">
                            <label for="form-usuario" class="form-label">
                                <i class="bi bi-person-badge-fill me-1"></i>
                                Nome de Usuário
                            </label>
                            <input type="text" name="usuario" id="form-usuario" class="form-control" 
                                   placeholder="usuario123" required>
                            <div class="form-text">Usado para fazer login</div>
                        </div>
                        <div class="col-md-6">
                            <label for="form-senha" class="form-label">
                                <i class="bi bi-key-fill me-1"></i>
                                Senha
                            </label>
                            <div class="input-group">
                                <input type="password" name="senha" id="form-senha" class="form-control" 
                                       placeholder="Digite uma senha segura" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                            <div class="form-text">Mínimo 6 caracteres</div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info d-flex align-items-center" role="alert">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <div>
                                    <strong>Importante:</strong> O usuário receberá acesso imediato ao sistema com as credenciais fornecidas.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle-fill me-1"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-admin">
                        <i class="bi bi-person-plus-fill me-1"></i>
                        Criar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Senha Redefinida -->
<div class="modal fade admin-modal" id="modalSenhaRedefinida" tabindex="-1" aria-labelledby="modalSenhaRedefinidaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSenhaRedefinidaLabel">
                    <i class="bi bi-key-fill me-2"></i>
                    Senha Redefinida com Sucesso
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                </div>
                
                <div class="alert alert-success" role="alert">
                    <h6 class="alert-heading">
                        <i class="bi bi-person-fill me-1"></i>
                        Usuário: <span id="nomeUsuarioSenha"></span>
                    </h6>
                    <p class="mb-0">A senha foi redefinida com sucesso!</p>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-key-fill me-1"></i>
                        Nova Senha Temporária:
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control text-center fs-4 fw-bold" id="novaSenhaDisplay" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="copyPassword">
                            <i class="bi bi-clipboard-fill"></i>
                        </button>
                    </div>
                </div>

                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Importante:</strong> Copie esta senha e envie para o usuário. Ele deverá alterá-la no primeiro acesso ao sistema.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle-fill me-1"></i>
                    Fechar
                </button>
                <button type="button" class="btn btn-admin" id="sendPasswordToUser">
                    <i class="bi bi-send-fill me-1"></i>
                    Enviar por WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalNovoUsuario = new bootstrap.Modal(document.getElementById('modalNovoUsuario'));
    const formNovoUsuario = document.getElementById('formNovoUsuario');
    const userTableBody = document.getElementById('user-table-body');
    const modalSenha = new bootstrap.Modal(document.getElementById('modalSenhaRedefinida'));

    // === FUNCIONALIDADES RESPONSIVAS ===
    
    // Toggle de senha no modal
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('form-senha');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('bi-eye-fill');
            icon.classList.toggle('bi-eye-slash-fill');
        });
    }

    // Copiar senha para clipboard
    const copyPasswordBtn = document.getElementById('copyPassword');
    const novaSenhaDisplay = document.getElementById('novaSenhaDisplay');
    
    if (copyPasswordBtn && novaSenhaDisplay) {
        copyPasswordBtn.addEventListener('click', function() {
            novaSenhaDisplay.select();
            novaSenhaDisplay.setSelectionRange(0, 99999);
            
            navigator.clipboard.writeText(novaSenhaDisplay.value).then(() => {
                const icon = this.querySelector('i');
                const originalIcon = icon.className;
                
                icon.className = 'bi bi-check-fill text-success';
                this.classList.add('btn-success');
                this.classList.remove('btn-outline-secondary');
                
                setTimeout(() => {
                    icon.className = originalIcon;
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-secondary');
                }, 2000);
                
                window.showAdminToast('Sucesso!', 'Senha copiada para a área de transferência!');
            }).catch(() => {
                window.showAdminToast('Erro!', 'Não foi possível copiar a senha.', true);
            });
        });
    }

    // Enviar senha por WhatsApp
    const sendPasswordBtn = document.getElementById('sendPasswordToUser');
    if (sendPasswordBtn) {
        sendPasswordBtn.addEventListener('click', function() {
            const userName = document.getElementById('nomeUsuarioSenha').textContent;
            const password = document.getElementById('novaSenhaDisplay').value;
            
            const message = `Olá! Sua senha foi redefinida.\n\nUsuário: ${userName}\nNova senha: ${password}\n\nPor favor, altere esta senha no primeiro acesso.`;
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
            
            window.open(whatsappUrl, '_blank');
        });
    }

    // Botões de ação da tabela
    const btnRefresh = document.getElementById('btnRefresh');
    const btnExport = document.getElementById('btnExport');
    
    if (btnRefresh) {
        btnRefresh.addEventListener('click', function() {
            this.classList.add('loading');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        });
    }
    
    if (btnExport) {
        btnExport.addEventListener('click', function() {
            // Implementar exportação de dados
            window.showAdminToast('Info', 'Funcionalidade de exportação em desenvolvimento.');
        });
    }

    // === LÓGICA PARA ADICIONAR USUÁRIO COM AJAX ===
    if (formNovoUsuario) {
    formNovoUsuario.addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(formNovoUsuario);
        const button = formNovoUsuario.querySelector('button[type="submit"]');
        const originalButtonText = button.innerHTML;
            
        button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Criando...`;
            button.classList.add('loading');

        fetch('adicionar_usuario.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.showAdminToast('Sucesso!', data.message);
                modalNovoUsuario.hide();
                formNovoUsuario.reset();
                    
                    // Animação de sucesso
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
            } else {
                window.showAdminToast('Erro!', data.message, true);
            }
        })
        .catch(error => {
                console.error('Erro:', error);
            window.showAdminToast('Erro de Rede!', 'Não foi possível se conectar ao servidor.', true);
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalButtonText;
                button.classList.remove('loading');
            });
        });
    }

    // === LÓGICA PARA EXCLUIR E REDEFINIR SENHA (DELEGAÇÃO DE EVENTOS) ===
    
    // Event listener para a tabela desktop
    if (userTableBody) {
        userTableBody.addEventListener('click', function(event) {
        const target = event.target;
        const deleteButton = target.closest('.btn-excluir-usuario');
        const resetButton = target.closest('.btn-redefinir-senha');

        // --- AÇÃO DE EXCLUIR ---
        if (deleteButton) {
            const userId = deleteButton.dataset.id;
            const userName = deleteButton.dataset.nome;
                
            Swal.fire({
                    title: 'Tem certeza?',
                    html: `
                        <div class="text-center">
                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                            <p class="mt-3">Excluir o usuário <strong>"${userName}"</strong>?</p>
                            <p class="text-muted small">Todos os dados deste usuário serão perdidos permanentemente.</p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-trash-fill me-1"></i>Sim, excluir!',
                    cancelButtonText: '<i class="bi bi-x-circle-fill me-1"></i>Cancelar',
                    background: '#1a1a1a',
                    color: '#fff',
                    customClass: {
                        popup: 'swal-admin-popup',
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-secondary'
                    }
            }).then((result) => {
                if (result.isConfirmed) {
                        deleteButton.disabled = true;
                        deleteButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                        
                        fetch('excluir_usuario.php', { 
                            method: 'POST', 
                            headers: { 'Content-Type': 'application/json' }, 
                            body: JSON.stringify({ id: userId }) 
                        })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.showAdminToast('Sucesso!', data.message);
                                removeUserFromBothViews(userId);
                            } else {
                                window.showAdminToast('Erro!', data.message, true);
                                deleteButton.disabled = false;
                                deleteButton.innerHTML = '<i class="bi bi-trash-fill"></i><span class="d-none d-lg-inline ms-1">Excluir</span>';
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            window.showAdminToast('Erro de Rede!', 'Não foi possível se conectar.', true);
                            deleteButton.disabled = false;
                            deleteButton.innerHTML = '<i class="bi bi-trash-fill"></i><span class="d-none d-lg-inline ms-1">Excluir</span>';
                        });
                }
            });
        }

        // --- AÇÃO DE REDEFINIR SENHA ---
        if (resetButton) {
            const userId = resetButton.dataset.id;
            const userName = resetButton.dataset.nome;
                
            Swal.fire({
                    title: 'Redefinir Senha?',
                    html: `
                        <div class="text-center">
                            <i class="bi bi-key-fill text-info" style="font-size: 3rem;"></i>
                            <p class="mt-3">Gerar uma nova senha para <strong>"${userName}"</strong>?</p>
                            <p class="text-muted small">O usuário não conseguirá mais acessar com a senha atual.</p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-key-fill me-1"></i>Sim, redefinir!',
                    cancelButtonText: '<i class="bi bi-x-circle-fill me-1"></i>Cancelar',
                    background: '#1a1a1a',
                    color: '#fff',
                    customClass: {
                        popup: 'swal-admin-popup',
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-secondary'
                    }
            }).then((result) => {
                if (result.isConfirmed) {
                        resetButton.disabled = true;
                        resetButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                        
                        fetch('redefinir_senha.php', { 
                            method: 'POST', 
                            headers: { 'Content-Type': 'application/json' }, 
                            body: JSON.stringify({ id: userId }) 
                        })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('nomeUsuarioSenha').textContent = userName;
                                document.getElementById('novaSenhaDisplay').value = data.nova_senha;
                            modalSenha.show();
                        } else {
                            window.showAdminToast('Erro!', data.message, true);
                        }
                    })
                        .catch(error => {
                            console.error('Erro:', error);
                            window.showAdminToast('Erro de Rede!', 'Não foi possível se conectar.', true);
                        })
                        .finally(() => {
                            resetButton.disabled = false;
                            resetButton.innerHTML = '<i class="bi bi-key-fill"></i><span class="d-none d-lg-inline ms-1">Senha</span>';
                        });
                    }
                });
            }
        });
    }

    // Event listener para os cards mobile
    const mobileCardsContainer = document.getElementById('user-cards-mobile');
    if (mobileCardsContainer) {
        mobileCardsContainer.addEventListener('click', function(event) {
            const target = event.target;
            const deleteButton = target.closest('.btn-excluir-usuario');
            const resetButton = target.closest('.btn-redefinir-senha');

            // --- AÇÃO DE EXCLUIR ---
            if (deleteButton) {
                const userId = deleteButton.dataset.id;
                const userName = deleteButton.dataset.nome;
                
                Swal.fire({
                    title: 'Tem certeza?',
                    html: `
                        <div class="text-center">
                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                            <p class="mt-3">Excluir o usuário <strong>"${userName}"</strong>?</p>
                            <p class="text-muted small">Todos os dados deste usuário serão perdidos permanentemente.</p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-trash-fill me-1"></i>Sim, excluir!',
                    cancelButtonText: '<i class="bi bi-x-circle-fill me-1"></i>Cancelar',
                    background: '#1a1a1a',
                    color: '#fff',
                    customClass: {
                        popup: 'swal-admin-popup',
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-secondary'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteButton.disabled = true;
                        deleteButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                        
                        fetch('excluir_usuario.php', { 
                            method: 'POST', 
                            headers: { 'Content-Type': 'application/json' }, 
                            body: JSON.stringify({ id: userId }) 
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.showAdminToast('Sucesso!', data.message);
                                removeUserFromBothViews(userId);
                            } else {
                                window.showAdminToast('Erro!', data.message, true);
                                deleteButton.disabled = false;
                                deleteButton.innerHTML = '<i class="bi bi-trash-fill"></i>';
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            window.showAdminToast('Erro de Rede!', 'Não foi possível se conectar.', true);
                            deleteButton.disabled = false;
                            deleteButton.innerHTML = '<i class="bi bi-trash-fill"></i>';
                        });
                    }
                });
            }

            // --- AÇÃO DE REDEFINIR SENHA ---
            if (resetButton) {
                const userId = resetButton.dataset.id;
                const userName = resetButton.dataset.nome;
                
                Swal.fire({
                    title: 'Redefinir Senha?',
                    html: `
                        <div class="text-center">
                            <i class="bi bi-key-fill text-info" style="font-size: 3rem;"></i>
                            <p class="mt-3">Gerar uma nova senha para <strong>"${userName}"</strong>?</p>
                            <p class="text-muted small">O usuário não conseguirá mais acessar com a senha atual.</p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-key-fill me-1"></i>Sim, redefinir!',
                    cancelButtonText: '<i class="bi bi-x-circle-fill me-1"></i>Cancelar',
                    background: '#1a1a1a',
                    color: '#fff',
                    customClass: {
                        popup: 'swal-admin-popup',
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-secondary'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        resetButton.disabled = true;
                        resetButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                        
                        fetch('redefinir_senha.php', { 
                            method: 'POST', 
                            headers: { 'Content-Type': 'application/json' }, 
                            body: JSON.stringify({ id: userId }) 
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('nomeUsuarioSenha').textContent = userName;
                                document.getElementById('novaSenhaDisplay').value = data.nova_senha;
                                modalSenha.show();
                            } else {
                                window.showAdminToast('Erro!', data.message, true);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            window.showAdminToast('Erro de Rede!', 'Não foi possível se conectar.', true);
                        })
                        .finally(() => {
                            resetButton.disabled = false;
                            resetButton.innerHTML = '<i class="bi bi-key-fill"></i>';
                        });
                    }
                });
            }
        });
    }

    // Função para atualizar estatísticas
    function updateUserStats() {
        const userRows = document.querySelectorAll('.user-row');
        const mobileCards = document.querySelectorAll('#user-cards-mobile .admin-card');
        const totalUsers = Math.max(userRows.length, mobileCards.length);
        const usersWithPhone = document.querySelectorAll('.badge.bg-success').length;
        
        // Atualizar contadores
        const statCards = document.querySelectorAll('.col-6.col-md-3');
        if (statCards.length >= 4) {
            statCards[0].querySelector('h5').textContent = totalUsers;
            statCards[1].querySelector('h5').textContent = usersWithPhone;
            statCards[2].querySelector('h5').textContent = totalUsers; // Simplificado
            statCards[3].querySelector('h5').textContent = totalUsers;
        }
    }

    // Função para remover usuário de ambas as visualizações
    function removeUserFromBothViews(userId) {
        // Remover da tabela desktop
        const tableRow = document.getElementById('user-row-' + userId);
        if (tableRow) {
            tableRow.style.transition = 'all 0.5s ease';
            tableRow.style.opacity = '0';
            tableRow.style.transform = 'translateX(-100%)';
            setTimeout(() => {
                tableRow.remove();
            }, 500);
        }
        
        // Remover dos cards mobile
        const mobileCard = document.getElementById('user-card-' + userId);
        if (mobileCard) {
            mobileCard.style.transition = 'all 0.5s ease';
            mobileCard.style.opacity = '0';
            mobileCard.style.transform = 'translateX(-100%)';
            setTimeout(() => {
                mobileCard.remove();
            }, 500);
        }
        
        // Verificar se não há mais usuários após a animação
        setTimeout(() => {
            const remainingTableRows = document.querySelectorAll('.user-row').length;
            const remainingMobileCards = document.querySelectorAll('#user-cards-mobile .admin-card').length;
            
            if (remainingTableRows === 0 && remainingMobileCards === 0) {
                showNoUsersMessage();
            }
            
            updateUserStats();
        }, 600);
    }

    // Função para mostrar mensagem de "nenhum usuário"
    function showNoUsersMessage() {
        const tableBody = document.getElementById('user-table-body');
        const mobileContainer = document.getElementById('user-cards-mobile');
        
        if (tableBody) {
            tableBody.innerHTML = `
                <tr id="no-users-row">
                    <td colspan="5" class="text-center py-5">
                        <div class="d-flex flex-column align-items-center">
                            <i class="bi bi-people fs-1 text-muted mb-3"></i>
                            <h6 class="text-muted mb-2">Nenhum usuário cadastrado</h6>
                            <p class="text-muted small mb-3">Comece adicionando o primeiro usuário ao sistema</p>
                            <button class="btn btn-admin btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
                                <i class="bi bi-person-plus-fill me-2"></i>
                                Adicionar Primeiro Usuário
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        if (mobileContainer) {
            mobileContainer.innerHTML = `
                <div class="text-center py-5">
                    <div class="d-flex flex-column align-items-center">
                        <i class="bi bi-people fs-1 text-muted mb-3"></i>
                        <h6 class="text-muted mb-2">Nenhum usuário cadastrado</h6>
                        <p class="text-muted small mb-3">Comece adicionando o primeiro usuário ao sistema</p>
                        <button class="btn btn-admin btn-sm" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
                            <i class="bi bi-person-plus-fill me-2"></i>
                            Adicionar Primeiro Usuário
                        </button>
                    </div>
                </div>
            `;
        }
    }

    // === ANIMAÇÕES E EFEITOS ===
    
    // Animação de entrada dos cards
    const cards = document.querySelectorAll('.admin-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });

    // Efeito de hover nos botões
    const buttons = document.querySelectorAll('.btn-admin, .btn-outline-info, .btn-outline-danger');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // === RESPONSIVIDADE ===
    
    // Ajustar layout em telas pequenas
    function adjustLayout() {
        const isMobile = window.innerWidth < 768;
        const tableContainer = document.querySelector('.admin-table-container');
        
        if (tableContainer) {
            if (isMobile) {
                tableContainer.style.overflowX = 'auto';
            } else {
                tableContainer.style.overflowX = 'visible';
            }
        }
    }

    // Executar no carregamento e redimensionamento
    adjustLayout();
    window.addEventListener('resize', adjustLayout);

    // === ACESSIBILIDADE ===
    
    // Navegação por teclado
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            // Fechar modais com ESC
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
        }
    });

    // Foco automático no primeiro campo dos modais
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const firstInput = this.querySelector('input, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });
});
</script>

<?php
require_once 'footer_admin.php';
?>