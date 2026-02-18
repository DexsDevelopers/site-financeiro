// kill-port.js - Script para matar processo na porta 3001 (Site Financeiro)
const { exec } = require('child_process');
const os = require('os');

const PORT = process.env.API_PORT || 3001;

// Verificação silenciosa da porta
if (os.platform() === 'win32') {
    // Windows
    exec(`netstat -ano | findstr :${PORT}`, (error, stdout, stderr) => {
        if (error || !stdout.trim()) {
            // Nenhum processo encontrado - continuar silenciosamente
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
            // Nenhum processo encontrado - continuar silenciosamente
            return;
        }

        // Processos encontrados - matar silenciosamente

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
            // Nenhum processo encontrado - continuar silenciosamente
            return;
        }

        const pids = stdout.trim().split('\n').filter(Boolean);
        // Processos encontrados - matar silenciosamente

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

