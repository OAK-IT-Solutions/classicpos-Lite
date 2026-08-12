import { execSync, exec } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

export interface TunnelStatus {
  installed: boolean;
  running: boolean;
  hostname: string | null;
  tunnel_id: string | null;
  error: string | null;
}

function getConfigDir(): string {
  return path.join(
    process.env.PROGRAMDATA || 'C:\\ProgramData',
    'classicpos',
    'cloudflared'
  );
}

function findCloudflared(): string | null {
  const candidates = [
    'C:\\Program Files\\cloudflared\\cloudflared.exe',
    'C:\\Program Files (x86)\\cloudflared\\cloudflared.exe',
    path.join(process.env.LOCALAPPDATA || '', 'cloudflared.exe'),
    path.join(process.env.USERPROFILE || '', 'cloudflared.exe'),
  ];

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) return candidate;
  }

  try {
    return execSync('where cloudflared', { encoding: 'utf-8' }).trim().split('\n')[0];
  } catch {}

  return null;
}

export function getTunnelStatus(): TunnelStatus {
  const cloudflared = findCloudflared();
  if (!cloudflared) {
    return {
      installed: false,
      running: false,
      hostname: null,
      tunnel_id: null,
      error: 'cloudflared not found',
    };
  }

  try {
    const output = execSync('sc query ClassicPOSTunnel', { encoding: 'utf-8' });
    const running = output.includes('RUNNING');
    const installed = !output.includes('1060');

    let hostname: string | null = null;
    let tunnel_id: string | null = null;

    const configPath = path.join(getConfigDir(), 'config.yml');
    if (fs.existsSync(configPath)) {
      const content = fs.readFileSync(configPath, 'utf-8');
      const hostnameMatch = content.match(/hostname:\s*(.+)/);
      const tunnelMatch = content.match(/tunnel:\s*(.+)/);
      if (hostnameMatch) hostname = hostnameMatch[1].trim();
      if (tunnelMatch) tunnel_id = tunnelMatch[1].trim();
    }

    return { installed, running, hostname, tunnel_id, error: null };
  } catch {
    return {
      installed: false,
      running: false,
      hostname: null,
      tunnel_id: null,
      error: null,
    };
  }
}

export function generateTunnelConfig(
  tunnelId: string,
  credentialsPath: string,
  hostname: string,
  localPort: number
): string {
  const configDir = getConfigDir();
  fs.mkdirSync(configDir, { recursive: true });

  const config = [
    `tunnel: ${tunnelId}`,
    `credentials-file: ${credentialsPath}`,
    '',
    'ingress:',
    `  - hostname: ${hostname}`,
    `    service: http://127.0.0.1:${localPort}`,
    '  - service: http_status:404',
  ].join('\n');

  const configPath = path.join(configDir, 'config.yml');
  fs.writeFileSync(configPath, config, 'utf-8');
  console.log(`[Tunnel] Config written to ${configPath}`);

  return configPath;
}

export function installTunnel(configPath: string): void {
  const cloudflared = findCloudflared();
  if (!cloudflared) throw new Error('cloudflared not found');

  try {
    execSync(
      `sc create ClassicPOSTunnel binPath= "${cloudflared} service run --config ${configPath}" start= auto`,
      { stdio: 'pipe' }
    );
    console.log('[Tunnel] Service installed');
  } catch (e: any) {
    throw new Error(`Failed to install tunnel service: ${e.message}`);
  }
}

export function startTunnel(): void {
  try {
    execSync('sc start ClassicPOSTunnel', { stdio: 'pipe' });
    console.log('[Tunnel] Service started');
  } catch (e: any) {
    throw new Error(`Failed to start tunnel: ${e.message}`);
  }
}

export function stopTunnel(): void {
  try {
    execSync('sc stop ClassicPOSTunnel', { stdio: 'pipe' });
    console.log('[Tunnel] Service stopped');
  } catch (e: any) {
    throw new Error(`Failed to stop tunnel: ${e.message}`);
  }
}

export function uninstallTunnel(): void {
  try {
    execSync('sc delete ClassicPOSTunnel', { stdio: 'pipe' });
    console.log('[Tunnel] Service uninstalled');
  } catch (e: any) {
    throw new Error(`Failed to uninstall tunnel: ${e.message}`);
  }
}
