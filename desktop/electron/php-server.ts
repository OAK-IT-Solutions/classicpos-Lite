import { ChildProcess, spawn, execSync } from 'node:child_process';
import net from 'node:net';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import http from 'node:http';

let phpProcess: ChildProcess | null = null;
let currentPort: number = 0;
let logStream: fs.WriteStream | null = null;

// ─── Logging ──────────────────────────────────────────────────────────────────

function getAppDir(): string {
  return path.join(process.env.APPDATA || process.env.HOME || '', 'ClassicPOS');
}

export function getLogFile(): string {
  const logsDir = path.join(getAppDir(), 'logs');
  fs.mkdirSync(logsDir, { recursive: true });
  return path.join(logsDir, 'electron.log');
}

function log(level: string, message: string): void {
  const timestamp = new Date().toISOString();
  const line = `[${timestamp}] [${level}] ${message}`;
  console.log(line);
  try {
    if (!logStream || logStream.destroyed) {
      logStream = fs.createWriteStream(getLogFile(), { flags: 'a' });
    }
    logStream.write(line + '\n');
  } catch {}
}

// ─── Public API ───────────────────────────────────────────────────────────────

export function getPhpPort(): number {
  return currentPort;
}

export function getPhpStatus() {
  return {
    running: phpProcess !== null && !phpProcess.killed,
    port: currentPort,
    pid: phpProcess?.pid ?? null,
  };
}

export function stopPhpServer(): void {
  if (phpProcess && !phpProcess.killed) {
    phpProcess.kill();
    phpProcess = null;
    log('INFO', 'PHP server stopped');
  }
}

// ─── Port ─────────────────────────────────────────────────────────────────────

function findAvailablePort(): Promise<number> {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.listen(0, '127.0.0.1', () => {
      const port = (server.address() as net.AddressInfo).port;
      server.close(() => resolve(port));
    });
    server.on('error', reject);
  });
}

// ─── PHP Binary ───────────────────────────────────────────────────────────────

function findPhpBinary(resourceDir: string): string {
  const binaryName = 'php-x86_64-pc-windows-msvc.exe';

  const candidates = [
    path.join(resourceDir, 'binaries', binaryName),
    path.join(resourceDir, binaryName),
  ];

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) {
      const stat = fs.statSync(candidate);
      log('INFO', `PHP binary found: ${candidate} (${(stat.size / 1024 / 1024).toFixed(1)} MB)`);
      return candidate;
    }
  }

  throw new Error(
    `PHP binary '${binaryName}' not found in ${resourceDir}. ` +
    `Checked: ${candidates.join(', ')}`
  );
}

// ─── Extraction ───────────────────────────────────────────────────────────────

export function ensureBackendExtracted(resourceDir: string): string {
  const appDir = getAppDir();

  // Idempotent: skip extraction if already extracted
  const artisan = path.join(appDir, 'artisan');
  const vendorAutoload = path.join(appDir, 'vendor', 'autoload.php');
  const publicIndex = path.join(appDir, 'public', 'index.php');

  if (fs.existsSync(artisan) && fs.existsSync(vendorAutoload) && fs.existsSync(publicIndex)) {
    log('INFO', `Backend already extracted at ${appDir} — skipping extraction`);
    return appDir;
  }

  const tarball = path.join(resourceDir, 'backend-bundle.tar');
  if (!fs.existsSync(tarball)) {
    throw new Error(`Backend tarball not found: ${tarball}`);
  }

  fs.mkdirSync(appDir, { recursive: true });

  // Remove Windows reserved device name 'nul'
  const nulFile = path.join(appDir, 'nul');
  try { if (fs.existsSync(nulFile)) fs.unlinkSync(nulFile); } catch {}

  log('INFO', `Extracting backend tarball to ${appDir}`);
  log('INFO', `Tarball: ${tarball} (${(fs.statSync(tarball).size / 1024 / 1024).toFixed(1)} MB)`);

  try {
    execSync(`tar xf "${tarball}" -C "${appDir}"`, { stdio: 'pipe' });
    log('INFO', 'Tar extraction completed');
  } catch (e: any) {
    const stderr = e.stderr ? e.stderr.toString() : 'unknown error';
    log('ERROR', `Tar extraction failed: ${stderr}`);
    throw new Error(`Failed to extract backend: ${e.message}`);
  }

  // Clean up Windows reserved device name artifacts
  try { if (fs.existsSync(nulFile)) fs.unlinkSync(nulFile); } catch {}

  // Verify extraction
  if (!fs.existsSync(artisan)) {
    throw new Error(`Extraction failed: artisan not found at ${artisan}`);
  }
  if (!fs.existsSync(vendorAutoload)) {
    throw new Error(`Extraction failed: vendor/autoload.php not found at ${appDir}`);
  }

  log('INFO', `Backend extracted successfully: artisan=${fs.existsSync(artisan)}, vendor=${fs.existsSync(vendorAutoload)}`);
  return appDir;
}

// ─── .env Generation ──────────────────────────────────────────────────────────

function generateAppKey(): string {
  const bytes = crypto.randomBytes(32);
  return `base64:${bytes.toString('base64')}`;
}

function generateEnvFile(laravelRoot: string, port: number): void {
  const envPath = path.join(laravelRoot, '.env');
  const appDir = getAppDir();
  const dbPath = path.join(appDir, 'data', 'classicpos.sqlite');
  const storageDir = path.join(appDir, 'storage');

  // Always overwrite .env for desktop mode — the tarball may contain a Docker .env
  // that points to PostgreSQL/Redis which don't exist in desktop mode.
  // Preserve the existing APP_KEY if present.
  let appKey = generateAppKey();
  if (fs.existsSync(envPath)) {
    const existing = fs.readFileSync(envPath, 'utf-8');
    const match = existing.match(/APP_KEY=(.+)/);
    if (match && match[1].trim()) {
      appKey = match[1].trim();
    }
  }

  const content = [
      'APP_NAME=ClassicPOS',
      'APP_ENV=production',
      'APP_DEBUG=false',
      `APP_URL=http://127.0.0.1:${port}`,
      `APP_KEY=${appKey}`,
      'DB_CONNECTION=sqlite',
      `DB_DATABASE=${dbPath.replace(/\\/g, '\\\\')}`,
      'CACHE_STORE=file',
      'SESSION_DRIVER=file',
      'QUEUE_CONNECTION=sync',
      'CLASSICPOS_SELF_HOSTED=true',
      'SANCTUM_STATEFUL_DOMAINS=localhost',
      'CORS_ALLOWED_ORIGINS=*',
    ].join('\n');

  fs.writeFileSync(envPath, content, 'utf-8');
  log('INFO', `Generated .env at ${envPath}`);

  // Ensure required directories exist
  const dirs = [
    path.join(appDir, 'data'),
    path.join(storageDir, 'app'),
    path.join(storageDir, 'framework'),
    path.join(storageDir, 'framework', 'cache'),
    path.join(storageDir, 'framework', 'sessions'),
    path.join(storageDir, 'framework', 'views'),
    path.join(storageDir, 'logs'),
  ];
  for (const dir of dirs) {
    fs.mkdirSync(dir, { recursive: true });
  }

  const bootstrapCache = path.join(laravelRoot, 'bootstrap', 'cache');
  if (!fs.existsSync(bootstrapCache)) {
    fs.mkdirSync(bootstrapCache, { recursive: true });
  }
}

// ─── Server Health Check ──────────────────────────────────────────────────────

function waitForServer(port: number, maxAttempts: number = 30): Promise<void> {
  // PHP built-in server is single-threaded. Laravel first-boot can take 20-30s.
  // We send one HTTP request at a time with a 15s socket timeout,
  // and wait 2s between retries to avoid flooding PHP with queued connections.
  return new Promise((resolve, reject) => {
    let attempt = 0;
    let lastLogAttempt = 0;

    const check = () => {
      attempt++;
      const req = http.get(`http://127.0.0.1:${port}/`, { timeout: 15000 }, (res) => {
        const status = res.statusCode || 0;
        // Drain response body
        res.resume();
        log('INFO', `PHP responded HTTP ${status} on attempt ${attempt}/${maxAttempts}`);
        if (status < 500 || (status >= 500 && attempt > 3)) {
          resolve();
        } else if (attempt < maxAttempts) {
          setTimeout(check, 2000);
        } else {
          reject(new Error(`PHP server responded with HTTP ${status} after ${maxAttempts} attempts`));
        }
      });
      req.on('error', (err: NodeJS.ErrnoException) => {
        if (attempt < maxAttempts) {
          // Log every 3rd attempt to avoid log spam
          if (attempt - lastLogAttempt >= 3) {
            lastLogAttempt = attempt;
            log('WARN', `Waiting for PHP... attempt ${attempt}/${maxAttempts} — ${err.code || err.message}`);
          }
          // ECONNRESET = PHP is busy processing, wait longer before retry
          // ECONNREFUSED = PHP not yet listening, short wait
          const delay = (err.code === 'ECONNRESET' || err.code === 'EPIPE') ? 3000 : 2000;
          setTimeout(check, delay);
        } else {
          reject(new Error(`PHP server failed after ${maxAttempts} attempts: ${err.message}`));
        }
      });
      req.on('timeout', () => {
        req.destroy();
        if (attempt < maxAttempts) {
          setTimeout(check, 2000);
        } else {
          reject(new Error(`PHP server timeout after ${maxAttempts} attempts`));
        }
      });
    };

    check();
  });
}

// ─── PHP Process Management ───────────────────────────────────────────────────

export async function startPhpServer(
  resourceDir: string,
  onAttempt?: (attempt: number) => void
): Promise<number> {
  const port = await findAvailablePort();
  currentPort = port;

  log('INFO', `Starting PHP server on port ${port}`);

  const laravelRoot = ensureBackendExtracted(resourceDir);
  const publicDir = path.join(laravelRoot, 'public');

  if (!fs.existsSync(publicDir)) {
    throw new Error(`Public directory not found: ${publicDir}`);
  }

  const phpBinary = findPhpBinary(resourceDir);
  const routerScript = path.join(resourceDir, 'php-router.php');
  if (!fs.existsSync(routerScript)) {
    throw new Error(`Router script not found: ${routerScript}`);
  }

  generateEnvFile(laravelRoot, port);

  const appDir = getAppDir();
  const storageDir = path.join(appDir, 'storage');

  for (let attempt = 1; attempt <= 3; attempt++) {
    log('INFO', `PHP server start attempt ${attempt}/3`);
    onAttempt?.(attempt);

    const env: Record<string, string> = {
      ...process.env as Record<string, string>,
      APP_ENV: 'production',
      APP_DATA_DIR: appDir,
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: path.join(appDir, 'data', 'classicpos.sqlite'),
      CACHE_STORE: 'file',
      SESSION_DRIVER: 'file',
      QUEUE_CONNECTION: 'sync',
      LARAVEL_STORAGE_PATH: storageDir,
      LARAVEL_ROOT: laravelRoot,
      SERVER_NAME: `127.0.0.1:${port}`,
    };

    log('INFO', `Spawning: ${phpBinary} -S 127.0.0.1:${port} -t ${publicDir} ${routerScript}`);

    const child = spawn(phpBinary, [
      '-S',
      `127.0.0.1:${port}`,
      '-t',
      publicDir,
      routerScript,
    ], {
      env,
      stdio: ['ignore', 'pipe', 'pipe'],
      windowsHide: true,
    });

    child.stdout?.on('data', (data: Buffer) => {
      const lines = data.toString().split('\n').filter(Boolean);
      for (const line of lines) log('PHP', line);
    });

    child.stderr?.on('data', (data: Buffer) => {
      const lines = data.toString().split('\n').filter(Boolean);
      for (const line of lines) log('PHPERR', line);
    });

    child.on('error', (err) => {
      log('ERROR', `PHP process error: ${err.message}`);
    });

    let exited = false;
    child.on('exit', (code, signal) => {
      log('INFO', `PHP process exited with code=${code} signal=${signal}`);
      exited = true;
      if (phpProcess === child) {
        phpProcess = null;
      }
    });

    phpProcess = child;

    // If PHP exits within 3 seconds, it crashed — don't waste time on waitForServer
    await new Promise((r) => setTimeout(r, 3000));
    if (exited) {
      log('ERROR', `PHP process exited immediately on attempt ${attempt} — binary may be blocked by antivirus or missing dependencies`);
      if (child && !child.killed) child.kill();
      if (attempt < 3) await new Promise((r) => setTimeout(r, 2000));
      continue;
    }

    try {
      // 20 attempts × ~18s avg = ~360s max per attempt (enough for Laravel first-boot + migrations)
      // Each attempt: 15s http timeout + 2s retry delay, or immediate ECONNRESET + 3s cooldown
      await waitForServer(port, 20);
      log('INFO', `PHP server ready on port ${port}`);
      return port;
    } catch (e: any) {
      log('ERROR', `Attempt ${attempt} failed: ${e.message}`);
      if (child && !child.killed) child.kill();
      if (attempt < 3) await new Promise((r) => setTimeout(r, 2000));
    }
  }

  const logFile = getLogFile();
  throw new Error(
    `PHP server failed to start after 3 attempts. Check log: ${logFile}`
  );
}
