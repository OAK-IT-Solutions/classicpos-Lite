import { app, BrowserWindow, ipcMain, shell } from 'electron';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { autoUpdater } from 'electron-updater';
import {
  startPhpServer,
  stopPhpServer,
  getPhpPort,
  getPhpStatus,
  ensureBackendExtracted,
  getLogFile,
} from './php-server.js';
import {
  listPrinters,
  printToPort,
  openDrawerToPort,
} from './printer.js';
import {
  getTunnelStatus,
  generateTunnelConfig,
  installTunnel,
  startTunnel,
  stopTunnel,
  uninstallTunnel,
} from './tunnel.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

let mainWindow: BrowserWindow | null = null;
let startupPhase: string = 'Idle';
let phpPort: number = 0;

function getResourceDir(): string {
  if (process.env.VITE_DEV_SERVER_URL) {
    // Dev mode: resources are next to dist-electron
    return path.join(__dirname, '..', 'resources');
  }
  // Production: resources are in process.resourcesPath
  // In unpacked mode, process.resourcesPath = app/resources/
  // In packed (asar) mode, extraResources are copied next to app.asar
  return process.resourcesPath || path.join(__dirname, '..', 'resources');
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 1024,
    minHeight: 600,
    title: 'ClassicPOS',
    show: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
  });

  mainWindow.once('ready-to-show', () => {
    mainWindow?.show();
  });

  if (process.env.VITE_DEV_SERVER_URL) {
    mainWindow.loadURL(process.env.VITE_DEV_SERVER_URL);
  } else {
    mainWindow.loadFile(path.join(__dirname, '..', 'dist', 'index.html'));
  }

  mainWindow.on('closed', () => {
    mainWindow = null;
  });

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });
}

function emitStartupState(state: Record<string, any>) {
  startupPhase = state.stage;
  mainWindow?.webContents.send('startup-state', state);
}

app.whenReady().then(async () => {
  createWindow();

  const resourceDir = getResourceDir();

  // Step 1: Extract backend (may take 60-90s on first run)
  emitStartupState({ stage: 'Extracting' });
  try {
    ensureBackendExtracted(resourceDir);
  } catch (e: any) {
    emitStartupState({ stage: 'Failed', detail: { message: e.message } });
    return;
  }

  // Step 2: Start PHP server
  emitStartupState({ stage: 'StartingPhp', detail: { attempt: 1 } });
  try {
    phpPort = await startPhpServer(resourceDir, (attempt: number) => {
      emitStartupState({ stage: 'StartingPhp', detail: { attempt } });
    });
    emitStartupState({ stage: 'Running', detail: { port: phpPort } });
  } catch (e: any) {
    emitStartupState({ stage: 'Failed', detail: { message: e.message } });
  }

  // Step 3: Check for updates (after app is running)
  if (!process.env.VITE_DEV_SERVER_URL) {
    autoUpdater.checkForUpdatesAndNotify().catch(() => {
      // Silent fail — updates are optional
    });
  }

  registerIpcHandlers();
});

app.on('window-all-closed', () => {
  stopPhpServer();
  app.quit();
});

app.on('before-quit', () => {
  stopPhpServer();
});

function registerIpcHandlers() {
  ipcMain.handle('get-startup-state', () => {
    return { stage: startupPhase, detail: { port: phpPort } };
  });

  ipcMain.handle('get-php-port', () => phpPort);
  ipcMain.handle('get-php-status', () => getPhpStatus());
  ipcMain.handle('stop-php-server', () => stopPhpServer());
  ipcMain.handle('get-app-version', () => app.getVersion());
  ipcMain.handle('get-log-file', () => getLogFile());

  ipcMain.handle('list-printers', () => listPrinters());
  ipcMain.handle(
    'print-to-port',
    (_event, portType: string, portName: string, ip: string, networkPort: number, bytes: number[]) =>
      printToPort(portType, portName, ip, networkPort, bytes)
  );
  ipcMain.handle(
    'open-drawer-to-port',
    (_event, portType: string, portName: string, ip: string, networkPort: number) =>
      openDrawerToPort(portType, portName, ip, networkPort)
  );

  ipcMain.handle('tunnel-status', () => getTunnelStatus());
  ipcMain.handle(
    'tunnel-generate-config',
    (_event, tunnelId: string, credentialsPath: string, hostname: string, localPort: number) =>
      generateTunnelConfig(tunnelId, credentialsPath, hostname, localPort)
  );
  ipcMain.handle('tunnel-install-service', (_event, configPath: string) =>
    installTunnel(configPath)
  );
  ipcMain.handle('tunnel-start', () => startTunnel());
  ipcMain.handle('tunnel-stop', () => stopTunnel());
  ipcMain.handle('tunnel-uninstall', () => uninstallTunnel());
}
