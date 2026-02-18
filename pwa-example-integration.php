<?php
/**
 * Exemplo de Integração PWA
 * Mostra como integrar os componentes PWA nas páginas existentes
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exemplo de Integração PWA</title>
    
    <!-- PWA Meta Tags -->
    <meta name="application-name" content="Painel Financeiro">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Painel Financeiro">
    <meta name="description" content="Sistema completo de gestão financeira pessoal">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-config" content="/seu_projeto/browserconfig.xml">
    <meta name="msapplication-TileColor" content="#667eea">
    <meta name="msapplication-tap-highlight" content="no">
    <meta name="theme-color" content="#667eea">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/seu_projeto/manifest.json">
    
    <!-- PWA Icons -->
    <link rel="apple-touch-icon" href="/seu_projeto/icons/icon-152x152.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/seu_projeto/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/seu_projeto/icons/icon-16x16.png">
    <link rel="shortcut icon" href="/seu_projeto/icons/icon-192x192.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .pwa-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin: 20px;
            padding: 30px;
        }
        
        .pwa-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
        }
        
        .pwa-status.online {
            background: #d4edda;
            color: #155724;
        }
        
        .pwa-status.offline {
            background: #fff3cd;
            color: #856404;
        }
        
        .pwa-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .pwa-control-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .pwa-control-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .pwa-stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .pwa-stats h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .pwa-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .pwa-stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .pwa-stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .pwa-stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="pwa-container">
        <h1 class="text-center mb-4">🚀 Exemplo de Integração PWA</h1>
        
        <!-- Status de Conexão -->
        <div id="pwa-status" class="pwa-status">
            <i class="bi bi-wifi"></i>
            <span id="connection-text">Verificando conexão...</span>
        </div>
        
        <!-- Controles PWA -->
        <div class="pwa-controls">
            <button class="pwa-control-btn" onclick="checkPWAStatus()">
                <i class="bi bi-info-circle"></i> Status PWA
            </button>
            
            <button class="pwa-control-btn" onclick="forceSync()">
                <i class="bi bi-arrow-clockwise"></i> Sincronizar
            </button>
            
            <button class="pwa-control-btn" onclick="clearCache()">
                <i class="bi bi-trash"></i> Limpar Cache
            </button>
            
            <button class="pwa-control-btn" onclick="showOfflineStats()">
                <i class="bi bi-database"></i> Dados Offline
            </button>
        </div>
        
        <!-- Estatísticas PWA -->
        <div class="pwa-stats">
            <h4>📊 Estatísticas PWA</h4>
            <div class="pwa-stats-grid" id="pwa-stats-grid">
                <div class="pwa-stat-item">
                    <div class="pwa-stat-value" id="online-status">-</div>
                    <div class="pwa-stat-label">Status Online</div>
                </div>
                
                <div class="pwa-stat-item">
                    <div class="pwa-stat-value" id="installed-status">-</div>
                    <div class="pwa-stat-label">App Instalado</div>
                </div>
                
                <div class="pwa-stat-item">
                    <div class="pwa-stat-value" id="offline-transactions">-</div>
                    <div class="pwa-stat-label">Transações Offline</div>
                </div>
                
                <div class="pwa-stat-item">
                    <div class="pwa-stat-value" id="offline-tasks">-</div>
                    <div class="pwa-stat-label">Tarefas Offline</div>
                </div>
                
                <div class="pwa-stat-item">
                    <div class="pwa-stat-value" id="offline-goals">-</div>
                    <div class="pwa-stat-label">Metas Offline</div>
                </div>
                
                <div class="pwa-stat-item">
                    <div class="pwa-stat-value" id="pending-sync">-</div>
                    <div class="pwa-stat-label">Pendentes Sync</div>
                </div>
            </div>
        </div>
        
        <!-- Informações do PWA -->
        <div class="pwa-stats">
            <h4>ℹ️ Informações do PWA</h4>
            <div id="pwa-info">
                <p><strong>Service Worker:</strong> <span id="sw-status">Verificando...</span></p>
                <p><strong>Cache Strategy:</strong> <span id="cache-strategy">Cache First</span></p>
                <p><strong>Offline Support:</strong> <span id="offline-support">✅ Suportado</span></p>
                <p><strong>Install Prompt:</strong> <span id="install-prompt">Verificando...</span></p>
            </div>
        </div>
        
        <!-- Teste de Funcionalidades -->
        <div class="pwa-stats">
            <h4>🧪 Teste de Funcionalidades</h4>
            <div class="pwa-controls">
                <button class="pwa-control-btn" onclick="testOfflineStorage()">
                    <i class="bi bi-database"></i> Testar Armazenamento
                </button>
                
                <button class="pwa-control-btn" onclick="testNotifications()">
                    <i class="bi bi-bell"></i> Testar Notificações
                </button>
                
                <button class="pwa-control-btn" onclick="testInstallPrompt()">
                    <i class="bi bi-download"></i> Testar Instalação
                </button>
            </div>
        </div>
    </div>
    
    <!-- Scripts PWA -->
    <script src="/seu_projeto/pwa-manager.js"></script>
    <script src="/seu_projeto/pwa-install-prompt.js"></script>
    <script src="/seu_projeto/offline-storage.js"></script>
    <script src="/seu_projeto/pwa-integration.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Funções de teste
        async function checkPWAStatus() {
            const status = {
                online: navigator.onLine,
                installed: window.pwaIntegration ? window.pwaIntegration.isInstalled() : false,
                serviceWorker: 'serviceWorker' in navigator,
                offlineStorage: window.offlineStorage ? true : false
            };
            
            alert(`Status PWA:
Online: ${status.online ? '✅' : '❌'}
Instalado: ${status.installed ? '✅' : '❌'}
Service Worker: ${status.serviceWorker ? '✅' : '❌'}
Offline Storage: ${status.offlineStorage ? '✅' : '❌'}`);
        }
        
        async function forceSync() {
            if (window.pwaIntegration) {
                await window.pwaIntegration.forceSync();
                alert('Sincronização forçada iniciada!');
            } else {
                alert('PWA Integration não disponível');
            }
        }
        
        async function clearCache() {
            if (window.pwaManager) {
                await window.pwaManager.clearCache();
                alert('Cache limpo com sucesso!');
            } else {
                alert('PWA Manager não disponível');
            }
        }
        
        async function showOfflineStats() {
            if (window.pwaIntegration) {
                const stats = await window.pwaIntegration.getOfflineStats();
                if (stats) {
                    alert(`Dados Offline:
Transações: ${stats.transactions}
Tarefas: ${stats.tasks}
Metas: ${stats.goals}
Pendentes Sync: ${stats.pendingSync}`);
                } else {
                    alert('Nenhum dado offline encontrado');
                }
            } else {
                alert('PWA Integration não disponível');
            }
        }
        
        async function testOfflineStorage() {
            if (window.offlineStorage) {
                try {
                    // Testar salvamento offline
                    const testData = {
                        description: 'Teste PWA - ' + new Date().toLocaleString(),
                        amount: 100.00,
                        type: 'expense',
                        category: 'teste',
                        date: new Date().toISOString()
                    };
                    
                    await window.offlineStorage.saveTransaction(testData);
                    alert('✅ Teste de armazenamento offline realizado com sucesso!');
                } catch (error) {
                    alert('❌ Erro no teste de armazenamento: ' + error.message);
                }
            } else {
                alert('Offline Storage não disponível');
            }
        }
        
        async function testNotifications() {
            if ('Notification' in window) {
                if (Notification.permission === 'granted') {
                    new Notification('Teste PWA', {
                        body: 'Esta é uma notificação de teste do PWA',
                        icon: '/seu_projeto/icons/icon-192x192.png'
                    });
                    alert('✅ Notificação enviada!');
                } else if (Notification.permission !== 'denied') {
                    const permission = await Notification.requestPermission();
                    if (permission === 'granted') {
                        new Notification('Teste PWA', {
                            body: 'Esta é uma notificação de teste do PWA',
                            icon: '/seu_projeto/icons/icon-192x192.png'
                        });
                        alert('✅ Notificação enviada!');
                    } else {
                        alert('❌ Permissão de notificação negada');
                    }
                } else {
                    alert('❌ Notificações bloqueadas');
                }
            } else {
                alert('❌ Notificações não suportadas');
            }
        }
        
        function testInstallPrompt() {
            if (window.pwaManager && window.pwaManager.installPrompt) {
                window.pwaManager.installPrompt.forceShowInstallButton();
                alert('✅ Prompt de instalação ativado!');
            } else {
                alert('❌ Prompt de instalação não disponível');
            }
        }
        
        // Atualizar status automaticamente
        async function updatePWAStatus() {
            // Status de conexão
            const statusElement = document.getElementById('pwa-status');
            const connectionText = document.getElementById('connection-text');
            
            if (navigator.onLine) {
                statusElement.className = 'pwa-status online';
                connectionText.textContent = 'Conectado';
            } else {
                statusElement.className = 'pwa-status offline';
                connectionText.textContent = 'Offline';
            }
            
            // Estatísticas
            document.getElementById('online-status').textContent = navigator.onLine ? '✅' : '❌';
            document.getElementById('installed-status').textContent = window.pwaIntegration ? 
                (window.pwaIntegration.isInstalled() ? '✅' : '❌') : '❓';
            
            // Service Worker status
            if ('serviceWorker' in navigator) {
                document.getElementById('sw-status').textContent = '✅ Registrado';
            } else {
                document.getElementById('sw-status').textContent = '❌ Não suportado';
            }
            
            // Install prompt status
            if (window.pwaManager && window.pwaManager.installPrompt) {
                document.getElementById('install-prompt').textContent = '✅ Disponível';
            } else {
                document.getElementById('install-prompt').textContent = '❌ Indisponível';
            }
            
            // Dados offline
            if (window.pwaIntegration) {
                try {
                    const stats = await window.pwaIntegration.getOfflineStats();
                    if (stats) {
                        document.getElementById('offline-transactions').textContent = stats.transactions;
                        document.getElementById('offline-tasks').textContent = stats.tasks;
                        document.getElementById('offline-goals').textContent = stats.goals;
                        document.getElementById('pending-sync').textContent = stats.pendingSync;
                    }
                } catch (error) {
                    console.error('Erro ao obter estatísticas offline:', error);
                }
            }
        }
        
        // Atualizar status a cada 5 segundos
        setInterval(updatePWAStatus, 5000);
        
        // Atualizar status inicial
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(updatePWAStatus, 1000);
        });
    </script>
</body>
</html>
