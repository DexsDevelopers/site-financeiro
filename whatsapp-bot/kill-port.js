// kill-port.js - Script para matar processo na porta 3000
const { exec } = require('child_process');
const os = require('os');

const PORT = process.env.API_PORT || 3000;

console.log(`🔍 Verificando processos na porta ${PORT}...`);

if (os.platform() === 'win32') {
    // Windows
    exec(`netstat -ano | findstr :${PORT}`, (error, stdout, stderr) => {
        if (error || !stdout.trim()) {
            console.log(`✅ Nenhum processo encontrado na porta ${PORT}`);
            return;
        }

        const lines = stdout.trim().split('\n');
        const pids = new Set();

        lines.forEach(line => {
            const match = line.match(/\s+(\d+)\s*$/);
            if (match) {
                pids.add(match[1]);
            }
        });

        if (pids.size === 0) {
            console.log(`✅ Nenhum processo encontrado na porta ${PORT}`);
            return;
        }

        console.log(`⚠️  Encontrados ${pids.size} processo(s) na porta ${PORT}:`);
        pids.forEach(pid => console.log(`   - PID: ${pid}`));

        pids.forEach(pid => {
            exec(`taskkill /F /PID ${pid}`, (killError, killStdout, killStderr) => {
                if (killError) {
                    console.error(`❌ Erro ao matar processo ${pid}:`, killError.message);
                } else {
                    console.log(`✅ Processo ${pid} finalizado com sucesso`);
                }
            });
        });
    });
} else {
    // Linux/Mac
    exec(`lsof -ti:${PORT}`, (error, stdout, stderr) => {
        if (error || !stdout.trim()) {
            console.log(`✅ Nenhum processo encontrado na porta ${PORT}`);
            return;
        }

        const pids = stdout.trim().split('\n').filter(Boolean);
        console.log(`⚠️  Encontrados ${pids.length} processo(s) na porta ${PORT}:`);
        pids.forEach(pid => console.log(`   - PID: ${pid}`));

        pids.forEach(pid => {
            exec(`kill -9 ${pid}`, (killError) => {
                if (killError) {
                    console.error(`❌ Erro ao matar processo ${pid}:`, killError.message);
                } else {
                    console.log(`✅ Processo ${pid} finalizado com sucesso`);
                }
            });
        });
    });
}

