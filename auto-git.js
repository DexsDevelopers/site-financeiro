const { execSync, exec } = require('child_process');

/**
 * Auto-Git System v1.0
 * Monitora e sincroniza alterações automaticamente com o GitHub
 */

const CHECK_INTERVAL = 30000; // 30 segundos
const RETRY_INTERVAL = 10000; // 10 segundos em caso de erro no push

function log(message) {
    const time = new Date().toLocaleTimeString();
    console.log(`[${time}] ${message}`);
}

async function runCommand(command) {
    return new Promise((resolve, reject) => {
        exec(command, (error, stdout, stderr) => {
            if (error) {
                reject({ error, stderr });
                return;
            }
            resolve(stdout.trim());
        });
    });
}

async function checkAndSync() {
    try {
        log('Verificando mudanças...');

        // 1. Verifica se há mudanças usando porcelain para facilitar o parse
        const status = await runCommand('git status --porcelain');

        if (!status) {
            // log('Nenhuma mudança detectada.');
            return;
        }

        log('Mudanças detectadas. Iniciando sincronização...');

        // 2. Add
        await runCommand('git add .');

        // 3. Commit
        await runCommand('git commit -m "auto update"');
        log('Commit criado: "auto update"');

        // 4. Push com retry
        await performPush();

    } catch (err) {
        if (err.stderr && err.stderr.includes('nothing to commit')) {
            return;
        }
        console.error('Erro no ciclo de sincronização:', err.error || err);
    }
}

async function performPush() {
    try {
        await runCommand('git push');
        log('Push realizado com sucesso!');
    } catch (err) {
        log('Erro ao realizar push. Tentando novamente em 10 segundos...');
        console.error('Detalhe do erro:', err.stderr || err.error);

        setTimeout(() => {
            performPush();
        }, RETRY_INTERVAL);
    }
}

// Inicia o loop
log('Sistema Auto-Git iniciado.');
log(`Monitorando mudanças a cada ${CHECK_INTERVAL / 1000} segundos...`);

setInterval(checkAndSync, CHECK_INTERVAL);

// Executa a primeira vez imediatamente
checkAndSync();
