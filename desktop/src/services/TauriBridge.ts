/**
 * TauriBridge — TypeScript bridge to Tauri Rust backend
 *
 * Provides:
 * - PHP server lifecycle (start, stop, status)
 * - Native USB/Serial/Network printing (ESC/POS)
 * - Cash drawer control
 * - Printer enumeration
 * - Auto-updater (GitHub Releases)
 * - Secure credential storage
 */

import { invoke } from '@tauri-apps/api/core';

// ─── Detection ─────────────────────────────────────────────────────────────────

export const isTauri = typeof window !== 'undefined' && '__TAURI__' in window;

// ─── PHP Server Management ─────────────────────────────────────────────────────

export interface PhpStatus {
  running: boolean;
  port: number;
  pid: number | null;
}

export async function getPhpPort(): Promise<number> {
  if (!isTauri) return 18900;
  return invoke<number>('get_php_port');
}

export async function getPhpStatus(): Promise<PhpStatus> {
  if (!isTauri) return { running: false, port: 18900, pid: null };
  return invoke<PhpStatus>('get_php_status');
}

export async function startPhpServer(): Promise<number> {
  if (!isTauri) return 18900;
  return invoke<number>('start_php_server');
}

export async function stopPhpServer(): Promise<void> {
  if (!isTauri) return;
  return invoke<void>('stop_php_server');
}

// ─── App Info ──────────────────────────────────────────────────────────────────

export async function getAppVersion(): Promise<string> {
  if (!isTauri) return 'web';
  return invoke<string>('get_app_version');
}

// ─── Environment Generation ────────────────────────────────────────────────────

export async function generateEnv(): Promise<string> {
  if (!isTauri) return '';
  return invoke<string>('generate_env');
}

// ─── Native Printing ───────────────────────────────────────────────────────────

export interface PrinterInfo {
  name: string;
  port_type: 'usb' | 'serial' | 'network';
  vendor_id: number | null;
  product_id: number | null;
  port_name: string | null;
  connected: boolean;
}

/**
 * Print ESC/POS bytes to the default printer.
 * Tries USB first, falls back to file output.
 * Returns the printer type used ('usb', 'serial', 'network', or 'file').
 */
export async function printReceipt(bytes: number[]): Promise<string> {
  if (!isTauri) {
    console.warn('[TauriBridge] Native printing requires desktop app');
    return 'unavailable';
  }
  return invoke<string>('print_receipt', { bytes });
}

/**
 * Open cash drawer via ESC/POS pulse command.
 * Tries USB printer first.
 */
export async function openCashDrawer(): Promise<string> {
  if (!isTauri) {
    console.warn('[TauriBridge] Cash drawer requires desktop app');
    return 'unavailable';
  }
  return invoke<string>('open_drawer');
}

/**
 * List all available printers (USB + Serial).
 */
export async function listPrinters(): Promise<PrinterInfo[]> {
  if (!isTauri) return [];
  return invoke<PrinterInfo[]>('list_printers');
}

/**
 * Print to a specific printer by type and port.
 */
export async function printToPort(
  portType: 'usb' | 'serial' | 'network',
  portName: string,
  ip: string,
  networkPort: number,
  bytes: number[]
): Promise<void> {
  if (!isTauri) return;
  return invoke<void>('print_to_port', {
    portType,
    portName,
    ip,
    networkPort,
    bytes,
  });
}

/**
 * Open cash drawer on a specific printer.
 */
export async function openDrawerToPort(
  portType: 'usb' | 'serial' | 'network',
  portName: string,
  ip: string,
  networkPort: number
): Promise<void> {
  if (!isTauri) return;
  return invoke<void>('open_drawer_to_port', {
    portType,
    portName,
    ip,
    networkPort,
  });
}

// ─── Secure Storage ────────────────────────────────────────────────────────────

export async function secureStore(key: string, value: string): Promise<void> {
  localStorage.setItem(key, value);
}

export async function secureGet(key: string): Promise<string | null> {
  return localStorage.getItem(key);
}

export async function secureRemove(key: string): Promise<void> {
  localStorage.removeItem(key);
}

// ─── Auto-Updater ──────────────────────────────────────────────────────────────

export interface UpdateInfo {
  version: string;
  date: string;
  body: string;
}

export interface DownloadProgress {
  event: 'started' | 'progress' | 'finished';
  data: { contentLength?: number; chunkLength?: number };
}

/**
 * Check for available updates from GitHub Releases.
 */
export async function checkForUpdates(): Promise<UpdateInfo | null> {
  if (!isTauri) return null;
  try {
    const { check } = await import('@tauri-apps/plugin-updater');
    const update = await check();
    if (update) {
      return {
        version: update.version,
        date: update.date ?? '',
        body: update.body ?? '',
      };
    }
    return null;
  } catch (e) {
    console.warn('[TauriBridge] Updater check failed:', e);
    return null;
  }
}

/**
 * Download and install an update.
 * Returns when the update is installed and the app restarts.
 */
export async function installUpdate(
  onProgress?: (progress: DownloadProgress) => void
): Promise<void> {
  if (!isTauri) return;
  try {
    const { check } = await import('@tauri-apps/plugin-updater');
    const { relaunch } = await import('@tauri-apps/plugin-process');

    const update = await check();
    if (!update) return;

    await update.downloadAndInstall((event) => {
      onProgress?.({ event: event.event, data: event.data as any });
    });

    await relaunch();
  } catch (e) {
    console.error('[TauriBridge] Update install failed:', e);
    throw e;
  }
}

// ─── URL Resolution ────────────────────────────────────────────────────────────

export async function getBaseUrl(): Promise<string> {
  if (!isTauri) return '';
  const port = await getPhpPort();
  return `http://127.0.0.1:${port}`;
}

export async function isServerReachable(): Promise<boolean> {
  if (!isTauri) return true;
  try {
    const port = await getPhpPort();
    const response = await fetch(`http://127.0.0.1:${port}/api/v1/health`, {
      method: 'GET',
      signal: AbortSignal.timeout(3000),
    });
    return response.ok;
  } catch {
    return false;
  }
}

// ─── Cloudflare Tunnel ─────────────────────────────────────────────────────────

export interface TunnelStatus {
  installed: boolean;
  running: boolean;
  hostname: string | null;
  tunnel_id: string | null;
  error: string | null;
}

export async function getTunnelStatus(): Promise<TunnelStatus> {
  if (!isTauri) return { installed: false, running: false, hostname: null, tunnel_id: null, error: 'Web mode' };
  return invoke<TunnelStatus>('tunnel_status');
}

export async function tunnelGenerateConfig(
  tunnelId: string,
  credentialsPath: string,
  hostname: string,
  localPort: number
): Promise<string> {
  if (!isTauri) return '';
  return invoke<string>('tunnel_generate_config', {
    tunnelId, credentialsPath, hostname, localPort,
  });
}

export async function tunnelInstallService(configPath: string): Promise<void> {
  if (!isTauri) return;
  return invoke<void>('tunnel_install_service', { configPath });
}

export async function tunnelStart(): Promise<void> {
  if (!isTauri) return;
  return invoke<void>('tunnel_start');
}

export async function tunnelStop(): Promise<void> {
  if (!isTauri) return;
  return invoke<void>('tunnel_stop');
}

export async function tunnelUninstall(): Promise<void> {
  if (!isTauri) return;
  return invoke<void>('tunnel_uninstall');
}
