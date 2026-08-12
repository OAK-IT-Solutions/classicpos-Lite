import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('electronAPI', {
  // Startup
  getStartupState: () => ipcRenderer.invoke('get-startup-state'),

  // PHP lifecycle
  getPhpPort: () => ipcRenderer.invoke('get-php-port'),
  getPhpStatus: () => ipcRenderer.invoke('get-php-status'),
  stopPhpServer: () => ipcRenderer.invoke('stop-php-server'),
  getAppVersion: () => ipcRenderer.invoke('get-app-version'),
  getLogFile: () => ipcRenderer.invoke('get-log-file'),

  // Printing
  listPrinters: () => ipcRenderer.invoke('list-printers'),
  printToPort: (
    portType: string,
    portName: string,
    ip: string,
    networkPort: number,
    bytes: number[]
  ) => ipcRenderer.invoke('print-to-port', portType, portName, ip, networkPort, bytes),
  openDrawerToPort: (
    portType: string,
    portName: string,
    ip: string,
    networkPort: number
  ) => ipcRenderer.invoke('open-drawer-to-port', portType, portName, ip, networkPort),

  // Tunnel
  tunnelStatus: () => ipcRenderer.invoke('tunnel-status'),
  tunnelGenerateConfig: (
    tunnelId: string,
    credentialsPath: string,
    hostname: string,
    localPort: number
  ) => ipcRenderer.invoke('tunnel-generate-config', tunnelId, credentialsPath, hostname, localPort),
  tunnelInstallService: (configPath: string) =>
    ipcRenderer.invoke('tunnel-install-service', configPath),
  tunnelStart: () => ipcRenderer.invoke('tunnel-start'),
  tunnelStop: () => ipcRenderer.invoke('tunnel-stop'),
  tunnelUninstall: () => ipcRenderer.invoke('tunnel-uninstall'),

  // Events
  onStartupState: (cb: (state: any) => void) => {
    const handler = (_event: Electron.IpcRendererEvent, state: any) => cb(state);
    ipcRenderer.on('startup-state', handler);
    return () => ipcRenderer.removeListener('startup-state', handler);
  },
});
