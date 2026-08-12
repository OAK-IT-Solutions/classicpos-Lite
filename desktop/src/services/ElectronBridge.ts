/**
 * ElectronBridge — TypeScript bridge to Electron main process
 *
 * Provides:
 * - PHP server lifecycle (port, status, stop)
 * - Native USB/Serial/Network printing (ESC/POS)
 * - Cash drawer control
 * - Printer enumeration
 * - Auto-updater
 * - Direct fetch to local PHP server (no proxy needed in Electron)
 */

// ─── Detection ─────────────────────────────────────────────────────────────────

declare global {
  interface Window {
    electronAPI?: {
      getStartupState: () => Promise<any>;
      getPhpPort: () => Promise<number>;
  getPhpStatus: () => Promise<PhpStatus>;
  stopPhpServer: () => Promise<void>;
  getAppVersion: () => Promise<string>;
  getLogFile: () => Promise<string>;
      listPrinters: () => Promise<PrinterInfo[]>;
      printToPort: (portType: string, portName: string, ip: string, networkPort: number, bytes: number[]) => Promise<void>;
      openDrawerToPort: (portType: string, portName: string, ip: string, networkPort: number) => Promise<void>;
      tunnelStatus: () => Promise<TunnelStatus>;
      tunnelGenerateConfig: (tunnelId: string, credentialsPath: string, hostname: string, localPort: number) => Promise<string>;
      tunnelInstallService: (configPath: string) => Promise<void>;
      tunnelStart: () => Promise<void>;
      tunnelStop: () => Promise<void>;
      tunnelUninstall: () => Promise<void>;
      onStartupState: (cb: (state: any) => void) => () => void;
    };
  }
}

export const isElectron = typeof window !== 'undefined' && !!window.electronAPI;

// ─── Types ─────────────────────────────────────────────────────────────────────

export interface PhpStatus {
  running: boolean;
  port: number;
  pid: number | null;
}

export interface PrinterInfo {
  name: string;
  port_type: 'usb' | 'serial' | 'network';
  vendor_id: number | null;
  product_id: number | null;
  port_name: string | null;
  connected: boolean;
}

export interface TunnelStatus {
  installed: boolean;
  running: boolean;
  hostname: string | null;
  tunnel_id: string | null;
  error: string | null;
}

export interface ApiResponse {
  status: number;
  body: string;
  content_type: string;
}

export interface UpdateInfo {
  version: string;
  date: string;
  body: string;
}

// ─── PHP Server ────────────────────────────────────────────────────────────────

let cachedPhpPort: number | null = null;

export async function getPhpPort(): Promise<number> {
  if (cachedPhpPort) return cachedPhpPort;
  if (isElectron) {
    cachedPhpPort = await window.electronAPI!.getPhpPort();
    return cachedPhpPort!;
  }
  return 18900;
}

export async function getPhpStatus(): Promise<PhpStatus> {
  if (isElectron) {
    return window.electronAPI!.getPhpStatus();
  }
  return { running: true, port: 18900, pid: null };
}

export async function stopPhpServer(): Promise<void> {
  if (isElectron) {
    return window.electronAPI!.stopPhpServer();
  }
}

export async function getAppVersion(): Promise<string> {
  if (isElectron) {
    return window.electronAPI!.getAppVersion();
  }
  return 'web';
}

export async function getLogFile(): Promise<string> {
  if (isElectron) {
    return window.electronAPI!.getLogFile();
  }
  return '';
}

// ─── Startup State ─────────────────────────────────────────────────────────────

export async function getStartupState(): Promise<any> {
  if (isElectron) {
    return window.electronAPI!.getStartupState();
  }
  return { stage: 'Running', detail: { port: 18900 } };
}

export function onStartupState(cb: (state: any) => void): () => void {
  if (isElectron) {
    return window.electronAPI!.onStartupState(cb);
  }
  return () => {};
}

// ─── API (Direct Fetch — No Proxy Needed) ─────────────────────────────────────

export async function apiRequest(
  method: 'GET' | 'POST',
  path: string,
  body?: string
): Promise<ApiResponse> {
  const port = await getPhpPort();
  const url = `http://127.0.0.1:${port}${path}`;
  const options: RequestInit = { method };
  if (body) {
    options.headers = { 'Content-Type': 'application/json' };
    options.body = body;
  }
  const resp = await fetch(url, options);
  return {
    status: resp.status,
    body: await resp.text(),
    content_type: resp.headers.get('content-type') || 'application/octet-stream',
  };
}

// ─── Printing ──────────────────────────────────────────────────────────────────

export async function listPrinters(): Promise<PrinterInfo[]> {
  if (isElectron) {
    return window.electronAPI!.listPrinters();
  }
  return [];
}

export async function printReceipt(bytes: number[]): Promise<string> {
  if (isElectron) {
    await window.electronAPI!.printToPort('usb', '', '', 0, bytes);
    return 'usb';
  }
  return 'unavailable';
}

export async function printToPort(
  portType: 'usb' | 'serial' | 'network',
  portName: string,
  ip: string,
  networkPort: number,
  bytes: number[]
): Promise<void> {
  if (isElectron) {
    return window.electronAPI!.printToPort(portType, portName, ip, networkPort, bytes);
  }
}

export async function openDrawerToPort(
  portType: 'usb' | 'serial' | 'network',
  portName: string,
  ip: string,
  networkPort: number
): Promise<void> {
  if (isElectron) {
    return window.electronAPI!.openDrawerToPort(portType, portName, ip, networkPort);
  }
}

export async function openCashDrawer(): Promise<string> {
  if (isElectron) {
    await openDrawerToPort('usb', '', '', 0);
    return 'usb';
  }
  return 'unavailable';
}

// ─── Tunnel ────────────────────────────────────────────────────────────────────

export async function getTunnelStatus(): Promise<TunnelStatus> {
  if (isElectron) {
    return window.electronAPI!.tunnelStatus();
  }
  return { installed: false, running: false, hostname: null, tunnel_id: null, error: 'Web mode' };
}

export async function tunnelGenerateConfig(
  tunnelId: string,
  credentialsPath: string,
  hostname: string,
  localPort: number
): Promise<string> {
  if (isElectron) {
    return window.electronAPI!.tunnelGenerateConfig(tunnelId, credentialsPath, hostname, localPort);
  }
  return '';
}

export async function tunnelInstallService(configPath: string): Promise<void> {
  if (isElectron) {
    return window.electronAPI!.tunnelInstallService(configPath);
  }
}

export async function tunnelStart(): Promise<void> {
  if (isElectron) {
    return window.electronAPI!.tunnelStart();
  }
}

export async function tunnelStop(): Promise<void> {
  if (isElectron) {
    return window.electronAPI!.tunnelStop();
  }
}

export async function tunnelUninstall(): Promise<void> {
  if (isElectron) {
    return window.electronAPI!.tunnelUninstall();
  }
}

// ─── Server Reachability ───────────────────────────────────────────────────────

export async function isServerReachable(): Promise<boolean> {
  try {
    const port = await getPhpPort();
    const resp = await fetch(`http://127.0.0.1:${port}/api/v1/health`, {
      method: 'GET',
      signal: AbortSignal.timeout(3000),
    });
    return resp.ok;
  } catch {
    return false;
  }
}

// ─── URL Resolution ────────────────────────────────────────────────────────────

export async function getBaseUrl(): Promise<string> {
  const port = await getPhpPort();
  return `http://127.0.0.1:${port}`;
}
