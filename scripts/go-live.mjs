// Puts the site online for testers: `npm run live`
//
//   Internet ── Cloudflare Tunnel ── proxy :8090 ──┬── PHP worker :8101
//   (https://<random>.trycloudflare.com)           ├── PHP worker :8102
//                                                  └── ... (WORKERS, default 4)
//
// PHP's built-in server answers one request at a time on Windows, so several
// workers run behind a least-busy proxy: a long chatbot reply for one tester
// does not freeze the site for everyone else. MySQL (XAMPP) and Ollama must
// already be running. Press Ctrl+C to take the site offline.
import { spawn, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const WORKERS = Number(process.env.WORKERS ?? 4);

// First free local port at or after `from` (other apps may already use common ports).
function freePort(from) {
    return new Promise((resolve) => {
        const probe = http.createServer();
        probe.once('error', () => resolve(freePort(from + 1)));
        probe.listen(from, '127.0.0.1', () => probe.close(() => resolve(from)));
    });
}
const PROXY_PORT = await freePort(Number(process.env.LIVE_PORT ?? 8190));
const workerPorts = [];
for (let i = 0, next = 8201; i < WORKERS; i++) {
    next = await freePort(next);
    workerPorts.push(next++);
}
const children = [];
let proxy = null;

const log = (...args) => console.log(new Date().toLocaleTimeString(), ...args);

// ---- Shutdown ---------------------------------------------------------------
let shuttingDown = false;
function shutdown(code = 0) {
    if (shuttingDown) return;
    shuttingDown = true;
    log('Taking the site offline...');
    proxy?.close();
    for (const child of children) {
        if (child.exitCode === null && child.pid) {
            spawnSync('taskkill', ['/PID', String(child.pid), '/T', '/F'], { stdio: 'ignore' });
        }
    }
    process.exit(code);
}
process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));

function findCloudflared() {
    const candidates = [process.env.CLOUDFLARED, 'C:\\Program Files (x86)\\cloudflared\\cloudflared.exe', 'C:\\Program Files\\cloudflared\\cloudflared.exe'].filter(Boolean);
    return candidates.find((p) => existsSync(p)) ?? 'cloudflared';
}

function canConnect(port) {
    return new Promise((resolve) => {
        const req = http.get({ host: '127.0.0.1', port, path: '/up', timeout: 2000 }, (res) => { res.resume(); resolve(res.statusCode < 500); });
        req.on('error', () => resolve(false));
        req.on('timeout', () => { req.destroy(); resolve(false); });
    });
}

// ---- Pre-flight checks -------------------------------------------------------
const tcpOpen = (port) => new Promise((resolve) => {
    const req = http.get({ host: '127.0.0.1', port, timeout: 1500 }, (res) => { res.resume(); resolve(true); });
    req.on('error', (e) => resolve(e.code !== 'ECONNREFUSED'));
    req.on('timeout', () => { req.destroy(); resolve(true); });
});
if (!(await tcpOpen(3306))) {
    console.error('MySQL is not running on port 3306. Start MySQL in the XAMPP Control Panel, then run `npm run live` again.');
    process.exit(1);
}
if (!(await tcpOpen(11434))) {
    log('Warning: Ollama is not running, so the Career Hub chatbot will show its offline guidance. Start Ollama for AI replies.');
}
// Compile every Blade view once up front. Several workers compiling the same
// view at the same moment can collide on Windows file locks.
spawnSync('php', ['artisan', 'view:cache'], { cwd: root, stdio: 'ignore' });
if (!existsSync(path.join(root, 'public', 'build', 'manifest.json'))) {
    log('Building front-end assets (first run)...');
    spawnSync('npm', ['run', 'build'], { cwd: root, stdio: 'inherit', shell: true });
}

// ---- Least-busy reverse proxy -----------------------------------------------
const backends = workerPorts.map((port) => ({ port, active: 0, downUntil: 0 }));
let ready = false;
let turn = 0;

function pickBackend(exclude = new Set()) {
    const now = Date.now();
    const usable = backends.filter((b) => b.downUntil < now && !exclude.has(b));
    if (!usable.length) return null;
    const least = Math.min(...usable.map((b) => b.active));
    const candidates = usable.filter((b) => b.active === least);
    return candidates[turn++ % candidates.length];
}

proxy = http.createServer((req, res) => {
    if (!ready) {
        res.writeHead(503, { 'Content-Type': 'text/plain', 'Retry-After': '5' });
        return res.end('The site is starting. Please refresh in a few seconds.');
    }
    const tried = new Set();
    const attempt = () => {
        const backend = pickBackend(tried);
        if (!backend) {
            res.writeHead(502, { 'Content-Type': 'text/plain' });
            return res.end('The site is temporarily unavailable. Please try again shortly.');
        }
        tried.add(backend);
        backend.active++;
        let done = false;
        const finish = () => { if (!done) { done = true; backend.active--; } };
        const headers = { ...req.headers };
        headers['x-forwarded-proto'] ??= 'https';
        headers['x-forwarded-port'] ??= headers['x-forwarded-proto'] === 'https' ? '443' : '80';
        headers['x-forwarded-host'] ??= req.headers.host;
        const upstream = http.request({ host: '127.0.0.1', port: backend.port, method: req.method, path: req.url, headers }, (upRes) => {
            res.writeHead(upRes.statusCode, upRes.statusMessage, upRes.headers);
            res.flushHeaders();
            upRes.pipe(res); // streamed straight through, so chatbot replies appear as they are written
            upRes.on('end', finish);
            upRes.on('error', finish);
        });
        upstream.on('error', (err) => {
            finish();
            if (err.code === 'ECONNREFUSED' && !res.headersSent) {
                backend.downUntil = Date.now() + 5000;
                return attempt(); // worker restarting: try another one
            }
            if (!res.headersSent) { res.writeHead(502); }
            res.end();
        });
        res.on('close', () => { finish(); upstream.destroy(); });
        req.pipe(upstream);
    };
    attempt();
});
proxy.on('error', (err) => { console.error(`The local proxy could not start on port ${PROXY_PORT}: ${err.message}`); shutdown(1); });
proxy.listen(PROXY_PORT, '127.0.0.1');

// ---- Cloudflare quick tunnel ------------------------------------------------
const tunnelUrl = await new Promise((resolve, reject) => {
    const cf = spawn(findCloudflared(), ['tunnel', '--no-autoupdate', '--url', `http://127.0.0.1:${PROXY_PORT}`], { windowsHide: true });
    children.push(cf);
    let found = false;
    const scan = (chunk) => {
        const match = String(chunk).match(/https:\/\/[a-z0-9-]+\.trycloudflare\.com/);
        if (match && !found) { found = true; resolve(match[0]); }
    };
    cf.stdout.on('data', scan);
    cf.stderr.on('data', scan);
    cf.on('error', (err) => reject(new Error(`Could not start cloudflared (${err.message}). Install it with: winget install Cloudflare.cloudflared`)));
    cf.on('exit', (code) => { if (!found) reject(new Error(`cloudflared exited (code ${code}) before giving a public address. Check your internet connection.`)); else { log('Tunnel closed.'); shutdown(1); } });
    setTimeout(() => !found && reject(new Error('Timed out waiting for the Cloudflare tunnel address.')), 60000);
}).catch((err) => { console.error(err.message); shutdown(1); });

// ---- PHP workers ------------------------------------------------------------
function startWorker(port) {
    const php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(root, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php')], {
        cwd: path.join(root, 'public'),
        windowsHide: true,
        // Public site: never show debug pages; build links with the public address.
        env: { ...process.env, APP_DEBUG: 'false', APP_URL: tunnelUrl },
    });
    children.push(php);
    php.stderr.on('data', (chunk) => {
        const line = String(chunk);
        if (/Fatal|Warning|Error/i.test(line) && !/Accepted|Closing|\[200\]|\[30\d\]/.test(line)) log(`worker ${port}:`, line.trim());
    });
    php.on('exit', (code) => {
        if (shuttingDown) return;
        log(`worker ${port} stopped (code ${code}); restarting`);
        setTimeout(() => startWorker(port), 1000);
    });
}
workerPorts.forEach(startWorker);
for (let i = 0; i < 40 && !(await Promise.all(workerPorts.map(canConnect))).every(Boolean); i++) {
    await new Promise((r) => setTimeout(r, 500));
}
ready = true;

console.log(`
  ┌───────────────────────────────────────────────────────────────┐
     Fiji Employment Hub is LIVE
     ${tunnelUrl}

     Share that link with your testers. ${WORKERS} PHP workers are running.
     Keep this window, XAMPP MySQL and Ollama running while they test.
     Press Ctrl+C to take the site offline.
  └───────────────────────────────────────────────────────────────┘
`);
