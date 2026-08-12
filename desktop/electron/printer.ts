import net from 'node:net';

export interface PrinterInfo {
  name: string;
  port_type: 'usb' | 'serial' | 'network';
  vendor_id: number | null;
  product_id: number | null;
  port_name: string | null;
  connected: boolean;
}

export async function listPrinters(): Promise<PrinterInfo[]> {
  const printers: PrinterInfo[] = [];

  // Enumerate serial ports
  try {
    const { SerialPort } = await import('serialport');
    const ports = await SerialPort.list();
    for (const port of ports) {
      if (port.path && (port.path.startsWith('COM') || port.path.startsWith('/dev/'))) {
        printers.push({
          name: port.path,
          port_type: 'serial',
          vendor_id: port.vendorId ? parseInt(port.vendorId, 16) : null,
          product_id: port.productId ? parseInt(port.productId, 16) : null,
          port_name: port.path,
          connected: true,
        });
      }
    }
  } catch (e) {
    console.warn('[Printer] serialport not available:', (e as Error).message);
  }

  // Enumerate USB printers
  try {
    const usb = await import('usb');
    const devices = usb.getDeviceList();
    for (const device of devices) {
      const desc = device.deviceDescriptor;
      // USB Printer class = 0x07
      if (device.configDescriptors?.[0]?.interfaces) {
        for (const iface of device.configDescriptors[0].interfaces) {
          for (const alt of iface) {
            if (alt.bInterfaceClass === 0x07) {
              printers.push({
                name: `USB Printer (VID:${desc.idVendor.toString(16)} PID:${desc.idProduct.toString(16)})`,
                port_type: 'usb',
                vendor_id: desc.idVendor,
                product_id: desc.idProduct,
                port_name: null,
                connected: true,
              });
            }
          }
        }
      }
    }
  } catch (e) {
    console.warn('[Printer] usb not available:', (e as Error).message);
  }

  return printers;
}

export async function printToPort(
  portType: string,
  portName: string,
  ip: string,
  networkPort: number,
  bytes: number[]
): Promise<void> {
  const buffer = Buffer.from(bytes);

  switch (portType) {
    case 'usb':
      await printUsb(buffer);
      break;
    case 'serial':
      await printSerial(portName, buffer);
      break;
    case 'network':
      await printNetwork(ip, networkPort, buffer);
      break;
    default:
      throw new Error(`Unknown printer type: ${portType}`);
  }
}

export async function openDrawerToPort(
  portType: string,
  portName: string,
  ip: string,
  networkPort: number
): Promise<void> {
  // ESC/POS pulse command for cash drawer pin 2
  const pulse = Buffer.from([0x1b, 0x70, 0x00, 0x19, 0xfa]);
  await printToPort(portType, portName, ip, networkPort, Array.from(pulse));
}

async function printUsb(buffer: Buffer): Promise<void> {
  try {
    const usb = await import('usb');
    const devices = usb.getDeviceList();

    for (const device of devices) {
      const desc = device.deviceDescriptor;
      if (!device.configDescriptors?.[0]?.interfaces) continue;

      for (const iface of device.configDescriptors[0].interfaces) {
        for (const alt of iface) {
          if (alt.bInterfaceClass !== 0x07) continue;

          try {
            device.open();
            device.selectConfiguration(1);
            device.claimInterface(alt.bInterfaceNumber);

            const endpoint = alt.endpoints?.find(
              (ep: any) => ep.bEndpointAddress & 0x80
            );
            if (!endpoint) continue;

            const chunkSize = 4096;
            for (let i = 0; i < buffer.length; i += chunkSize) {
              const chunk = buffer.subarray(i, Math.min(i + chunkSize, buffer.length));
              device.transferOut(endpoint.bEndpointAddress, chunk);
            }

            device.releaseInterface(alt.bInterfaceNumber);
            device.close();
            console.log('[Printer] USB print sent');
            return;
          } catch (e) {
            console.warn('[Printer] USB print attempt failed:', (e as Error).message);
            try { device.close(); } catch {}
          }
        }
      }
    }

    throw new Error('No USB printer found');
  } catch (e) {
    if ((e as Error).message === 'No USB printer found') throw e;
    throw new Error(`USB print failed: ${(e as Error).message}`);
  }
}

async function printSerial(portName: string, buffer: Buffer): Promise<void> {
  const { SerialPort } = await import('serialport');
  const { ReadlineParser } = await import('@serialport/parser-readline');

  return new Promise((resolve, reject) => {
    const port = new SerialPort({
      path: portName,
      baudRate: 9600,
    });

    const parser = port.pipe(new ReadlineParser());

    port.on('open', () => {
      port.write(buffer, (err) => {
        if (err) {
          port.close();
          reject(new Error(`Serial write failed: ${err.message}`));
        } else {
          setTimeout(() => {
            port.close();
            resolve();
          }, 500);
        }
      });
    });

    port.on('error', (err) => {
      reject(new Error(`Serial port error: ${err.message}`));
    });

    setTimeout(() => {
      port.close();
      reject(new Error('Serial print timeout'));
    }, 5000);
  });
}

async function printNetwork(ip: string, port: number, buffer: Buffer): Promise<void> {
  return new Promise((resolve, reject) => {
    const socket = new net.Socket();

    socket.setTimeout(5000);

    socket.connect(port, ip, () => {
      socket.write(buffer, (err) => {
        if (err) {
          socket.destroy();
          reject(new Error(`Network write failed: ${err.message}`));
        } else {
          setTimeout(() => {
            socket.destroy();
            resolve();
          }, 500);
        }
      });
    });

    socket.on('timeout', () => {
      socket.destroy();
      reject(new Error('Network print timeout'));
    });

    socket.on('error', (err) => {
      socket.destroy();
      reject(new Error(`Network print error: ${err.message}`));
    });
  });
}
