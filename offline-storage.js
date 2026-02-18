/**
 * Offline Storage Manager
 * Gerencia armazenamento offline para funcionalidades PWA
 */

class OfflineStorageManager {
    constructor() {
        this.dbName = 'PainelFinanceiroOffline';
        this.dbVersion = 1;
        this.db = null;
        this.isOnline = navigator.onLine;
        
        this.init();
    }
    
    async init() {
        console.log('Offline Storage: Inicializando...');
        
        // Configurar listeners de conexão
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.syncOfflineData();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.handleOfflineMode();
        });
        
        // Inicializar IndexedDB
        await this.initDatabase();
        
        console.log('Offline Storage: Inicializado com sucesso');
    }
    
    async initDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => {
                console.error('Offline Storage: Erro ao abrir database');
                reject(request.error);
            };
            
            request.onsuccess = () => {
                this.db = request.result;
                console.log('Offline Storage: Database aberto');
                resolve();
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Store para transações offline
                if (!db.objectStoreNames.contains('transactions')) {
                    const transactionStore = db.createObjectStore('transactions', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    transactionStore.createIndex('date', 'date', { unique: false });
                    transactionStore.createIndex('category', 'category', { unique: false });
                    transactionStore.createIndex('type', 'type', { unique: false });
                }
                
                // Store para tarefas offline
                if (!db.objectStoreNames.contains('tasks')) {
                    const taskStore = db.createObjectStore('tasks', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    taskStore.createIndex('status', 'status', { unique: false });
                    taskStore.createIndex('priority', 'priority', { unique: false });
                    taskStore.createIndex('date', 'date', { unique: false });
                }
                
                // Store para metas offline
                if (!db.objectStoreNames.contains('goals')) {
                    const goalStore = db.createObjectStore('goals', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    goalStore.createIndex('status', 'status', { unique: false });
                    goalStore.createIndex('category', 'category', { unique: false });
                }
                
                // Store para dados de sincronização
                if (!db.objectStoreNames.contains('syncQueue')) {
                    const syncStore = db.createObjectStore('syncQueue', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    syncStore.createIndex('type', 'type', { unique: false });
                    syncStore.createIndex('status', 'status', { unique: false });
                }
                
                console.log('Offline Storage: Database criado/atualizado');
            };
        });
    }
    
    // Métodos para transações
    async saveTransaction(transactionData) {
        if (this.isOnline) {
            try {
                // Tentar salvar online primeiro
                const response = await this.saveTransactionOnline(transactionData);
                return response;
            } catch (error) {
                console.log('Offline Storage: Erro ao salvar online, salvando offline');
                return await this.saveTransactionOffline(transactionData);
            }
        } else {
            return await this.saveTransactionOffline(transactionData);
        }
    }
    
    async saveTransactionOnline(transactionData) {
        // Implementar chamada para API online
        const response = await fetch('/seu_projeto/salvar_transacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(transactionData)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao salvar transação online');
        }
        
        return await response.json();
    }
    
    async saveTransactionOffline(transactionData) {
        const transaction = {
            ...transactionData,
            id: Date.now() + Math.random(),
            offline: true,
            syncStatus: 'pending',
            createdAt: new Date().toISOString()
        };
        
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['transactions'], 'readwrite');
            const store = dbTransaction.objectStore('transactions');
            const request = store.add(transaction);
            
            request.onsuccess = () => {
                console.log('Offline Storage: Transação salva offline');
                this.addToSyncQueue('transaction', transaction);
                resolve(transaction);
            };
            
            request.onerror = () => {
                console.error('Offline Storage: Erro ao salvar transação offline');
                reject(request.error);
            };
        });
    }
    
    async getTransactions(filters = {}) {
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['transactions'], 'readonly');
            const store = dbTransaction.objectStore('transactions');
            const request = store.getAll();
            
            request.onsuccess = () => {
                let transactions = request.result;
                
                // Aplicar filtros
                if (filters.category) {
                    transactions = transactions.filter(t => t.category === filters.category);
                }
                if (filters.type) {
                    transactions = transactions.filter(t => t.type === filters.type);
                }
                if (filters.dateFrom) {
                    transactions = transactions.filter(t => new Date(t.date) >= new Date(filters.dateFrom));
                }
                if (filters.dateTo) {
                    transactions = transactions.filter(t => new Date(t.date) <= new Date(filters.dateTo));
                }
                
                resolve(transactions);
            };
            
            request.onerror = () => {
                console.error('Offline Storage: Erro ao buscar transações');
                reject(request.error);
            };
        });
    }
    
    // Métodos para tarefas
    async saveTask(taskData) {
        if (this.isOnline) {
            try {
                const response = await this.saveTaskOnline(taskData);
                return response;
            } catch (error) {
                console.log('Offline Storage: Erro ao salvar online, salvando offline');
                return await this.saveTaskOffline(taskData);
            }
        } else {
            return await this.saveTaskOffline(taskData);
        }
    }
    
    async saveTaskOnline(taskData) {
        const response = await fetch('/seu_projeto/salvar_tarefa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(taskData)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao salvar tarefa online');
        }
        
        return await response.json();
    }
    
    async saveTaskOffline(taskData) {
        const task = {
            ...taskData,
            id: Date.now() + Math.random(),
            offline: true,
            syncStatus: 'pending',
            createdAt: new Date().toISOString()
        };
        
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['tasks'], 'readwrite');
            const store = dbTransaction.objectStore('tasks');
            const request = store.add(task);
            
            request.onsuccess = () => {
                console.log('Offline Storage: Tarefa salva offline');
                this.addToSyncQueue('task', task);
                resolve(task);
            };
            
            request.onerror = () => {
                console.error('Offline Storage: Erro ao salvar tarefa offline');
                reject(request.error);
            };
        });
    }
    
    async getTasks(filters = {}) {
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['tasks'], 'readonly');
            const store = dbTransaction.objectStore('tasks');
            const request = store.getAll();
            
            request.onsuccess = () => {
                let tasks = request.result;
                
                // Aplicar filtros
                if (filters.status) {
                    tasks = tasks.filter(t => t.status === filters.status);
                }
                if (filters.priority) {
                    tasks = tasks.filter(t => t.priority === filters.priority);
                }
                
                resolve(tasks);
            };
            
            request.onerror = () => {
                console.error('Offline Storage: Erro ao buscar tarefas');
                reject(request.error);
            };
        });
    }
    
    // Métodos para metas
    async saveGoal(goalData) {
        if (this.isOnline) {
            try {
                const response = await this.saveGoalOnline(goalData);
                return response;
            } catch (error) {
                console.log('Offline Storage: Erro ao salvar online, salvando offline');
                return await this.saveGoalOffline(goalData);
            }
        } else {
            return await this.saveGoalOffline(goalData);
        }
    }
    
    async saveGoalOnline(goalData) {
        const response = await fetch('/seu_projeto/salvar_meta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(goalData)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao salvar meta online');
        }
        
        return await response.json();
    }
    
    async saveGoalOffline(goalData) {
        const goal = {
            ...goalData,
            id: Date.now() + Math.random(),
            offline: true,
            syncStatus: 'pending',
            createdAt: new Date().toISOString()
        };
        
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['goals'], 'readwrite');
            const store = dbTransaction.objectStore('goals');
            const request = store.add(goal);
            
            request.onsuccess = () => {
                console.log('Offline Storage: Meta salva offline');
                this.addToSyncQueue('goal', goal);
                resolve(goal);
            };
            
            request.onerror = () => {
                console.error('Offline Storage: Erro ao salvar meta offline');
                reject(request.error);
            };
        });
    }
    
    async getGoals(filters = {}) {
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['goals'], 'readonly');
            const store = dbTransaction.objectStore('goals');
            const request = store.getAll();
            
            request.onsuccess = () => {
                let goals = request.result;
                
                // Aplicar filtros
                if (filters.status) {
                    goals = goals.filter(g => g.status === filters.status);
                }
                if (filters.category) {
                    goals = goals.filter(g => g.category === filters.category);
                }
                
                resolve(goals);
            };
            
            request.onerror = () => {
                console.error('Offline Storage: Erro ao buscar metas');
                reject(request.error);
            };
        });
    }
    
    // Métodos de sincronização
    async addToSyncQueue(type, data) {
        const syncItem = {
            type: type,
            data: data,
            status: 'pending',
            createdAt: new Date().toISOString(),
            retryCount: 0
        };
        
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = dbTransaction.objectStore('syncQueue');
            const request = store.add(syncItem);
            
            request.onsuccess = () => {
                console.log('Offline Storage: Item adicionado à fila de sincronização');
                resolve();
            };
            
            request.onerror = () => {
                console.error('Offline Storage: Erro ao adicionar à fila de sincronização');
                reject(request.error);
            };
        });
    }
    
    async syncOfflineData() {
        if (!this.isOnline) {
            console.log('Offline Storage: Sem conexão, não é possível sincronizar');
            return;
        }
        
        console.log('Offline Storage: Iniciando sincronização...');
        
        try {
            const pendingItems = await this.getPendingSyncItems();
            
            for (const item of pendingItems) {
                try {
                    await this.syncItem(item);
                    await this.markItemAsSynced(item.id);
                } catch (error) {
                    console.error('Offline Storage: Erro ao sincronizar item:', error);
                    await this.incrementRetryCount(item.id);
                }
            }
            
            console.log('Offline Storage: Sincronização concluída');
            this.showSyncNotification('Dados sincronizados com sucesso!');
            
        } catch (error) {
            console.error('Offline Storage: Erro na sincronização:', error);
        }
    }
    
    async getPendingSyncItems() {
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['syncQueue'], 'readonly');
            const store = dbTransaction.objectStore('syncQueue');
            const index = store.index('status');
            const request = index.getAll('pending');
            
            request.onsuccess = () => {
                resolve(request.result);
            };
            
            request.onerror = () => {
                reject(request.error);
            };
        });
    }
    
    async syncItem(item) {
        const { type, data } = item;
        
        switch (type) {
            case 'transaction':
                return await this.saveTransactionOnline(data);
            case 'task':
                return await this.saveTaskOnline(data);
            case 'goal':
                return await this.saveGoalOnline(data);
            default:
                throw new Error(`Tipo de item não suportado: ${type}`);
        }
    }
    
    async markItemAsSynced(itemId) {
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = dbTransaction.objectStore('syncQueue');
            const request = store.get(itemId);
            
            request.onsuccess = () => {
                const item = request.result;
                if (item) {
                    item.status = 'synced';
                    item.syncedAt = new Date().toISOString();
                    
                    const updateRequest = store.put(item);
                    updateRequest.onsuccess = () => resolve();
                    updateRequest.onerror = () => reject(updateRequest.error);
                } else {
                    resolve();
                }
            };
            
            request.onerror = () => reject(request.error);
        });
    }
    
    async incrementRetryCount(itemId) {
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = dbTransaction.objectStore('syncQueue');
            const request = store.get(itemId);
            
            request.onsuccess = () => {
                const item = request.result;
                if (item) {
                    item.retryCount = (item.retryCount || 0) + 1;
                    
                    if (item.retryCount >= 3) {
                        item.status = 'failed';
                    }
                    
                    const updateRequest = store.put(item);
                    updateRequest.onsuccess = () => resolve();
                    updateRequest.onerror = () => reject(updateRequest.error);
                } else {
                    resolve();
                }
            };
            
            request.onerror = () => reject(request.error);
        });
    }
    
    handleOfflineMode() {
        console.log('Offline Storage: Modo offline ativado');
        this.showOfflineNotification();
    }
    
    showOfflineNotification() {
        if (window.pwaManager) {
            window.pwaManager.showNotification('📡 Modo offline ativado - Dados serão sincronizados quando a conexão for restaurada', 'warning');
        }
    }
    
    showSyncNotification(message) {
        if (window.pwaManager) {
            window.pwaManager.showNotification(message, 'success');
        }
    }
    
    // Métodos utilitários
    async clearOfflineData() {
        return new Promise((resolve, reject) => {
            const dbTransaction = this.db.transaction(['transactions', 'tasks', 'goals', 'syncQueue'], 'readwrite');
            
            const clearStore = (storeName) => {
                return new Promise((resolveStore, rejectStore) => {
                    const store = dbTransaction.objectStore(storeName);
                    const request = store.clear();
                    
                    request.onsuccess = () => resolveStore();
                    request.onerror = () => rejectStore(request.error);
                });
            };
            
            Promise.all([
                clearStore('transactions'),
                clearStore('tasks'),
                clearStore('goals'),
                clearStore('syncQueue')
            ]).then(() => {
                console.log('Offline Storage: Dados offline limpos');
                resolve();
            }).catch(reject);
        });
    }
    
    async getOfflineDataStats() {
        const stats = {
            transactions: 0,
            tasks: 0,
            goals: 0,
            pendingSync: 0
        };
        
        try {
            stats.transactions = (await this.getTransactions()).length;
            stats.tasks = (await this.getTasks()).length;
            stats.goals = (await this.getGoals()).length;
            stats.pendingSync = (await this.getPendingSyncItems()).length;
        } catch (error) {
            console.error('Offline Storage: Erro ao obter estatísticas:', error);
        }
        
        return stats;
    }
}

// Inicializar Offline Storage Manager
document.addEventListener('DOMContentLoaded', () => {
    window.offlineStorage = new OfflineStorageManager();
});

// Exportar para uso global
window.OfflineStorageManager = OfflineStorageManager;
