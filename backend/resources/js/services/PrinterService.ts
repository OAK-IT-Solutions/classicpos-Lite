import { ref, computed } from 'vue';
import { db, CachedConfig } from '@/services/OfflineDB';

export type PrinterType = 'usb' | 'network' | 'browser' | 'disabled';

export interface PrinterConfig {
    type: PrinterType;
    ip_address?: string;
    port?: number;
    drawer_pin?: 2 | 5;
    printer_name?: string;
    device_id?: string;
}

const STORAGE_KEY = 'classicpos_printer_config';

const defaultConfig: PrinterConfig = {
    type: 'browser',
    ip_address: '192.168.1.100',
    port: 9100,
    drawer_pin: 2,
    printer_name: '',
    device_id: '',
};

const currentConfig = ref<PrinterConfig>(loadConfig());

function loadConfig(): PrinterConfig {
    if (typeof localStorage === 'undefined') return { ...defaultConfig };
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return { ...defaultConfig };
        const parsed = JSON.parse(raw);
        return { ...defaultConfig, ...parsed };
    } catch {
        return { ...defaultConfig };
    }
}

function saveConfigLocal(config: PrinterConfig): void {
    if (typeof localStorage === 'undefined') return;
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(config));
    } catch (err) {
        console.warn('[Printer] Failed to save to localStorage:', err);
    }
}

async function saveConfigToDB(config: PrinterConfig): Promise<void> {
    try {
        await db.config.put({
            key: STORAGE_KEY,
            value: config,
            updated_at: Date.now(),
        });
    } catch (err) {
        console.warn('[Printer] Failed to save to IndexedDB:', err);
    }
}

export async function getPrinterConfig(): Promise<PrinterConfig> {
    try {
        const cached = await db.config.get(STORAGE_KEY);
        if (cached?.value) {
            currentConfig.value = { ...defaultConfig, ...(cached.value as PrinterConfig) };
        }
    } catch {}
    return currentConfig.value;
}

export async function setPrinterConfig(config: PrinterConfig): Promise<void> {
    currentConfig.value = { ...defaultConfig, ...config };
    saveConfigLocal(currentConfig.value);
    await saveConfigToDB(currentConfig.value);
}

export function usePrinterConfig() {
    return {
        config: currentConfig,
        getPrinterConfig,
        setPrinterConfig,
    };
}

export interface ReceiptItem {
    name: string;
    quantity: number;
    price: number;
}

export interface ReceiptData {
    invoiceNumber: string;
    branchName: string;
    items: ReceiptItem[];
    subtotal: number;
    discount: number;
    taxAmount: number;
    total: number;
    paymentMethod: string;
    customerName?: string;
    amountTendered?: number;
    changeDue?: number;
    offline?: boolean;
    date: string;
    efrisFdn?: string;
    efrisVerificationCode?: string;
}

function textToBytes(text: string): Uint8Array {
    return new TextEncoder().encode(text);
}

// ESC/POS command builders
export const ESCPOS = {
    INIT: new Uint8Array([0x1B, 0x40]),
    LF: new Uint8Array([0x0A]),
    CUT: new Uint8Array([0x1D, 0x56, 0x41, 0x03]),
    OPEN_DRAWER_PIN_2: new Uint8Array([0x1B, 0x70, 0x00, 0x19, 0xFA]),
    OPEN_DRAWER_PIN_5: new Uint8Array([0x1B, 0x70, 0x01, 0x19, 0xFA]),
    BOLD_ON: new Uint8Array([0x1B, 0x45, 0x01]),
    BOLD_OFF: new Uint8Array([0x1B, 0x45, 0x00]),
    ALIGN_CENTER: new Uint8Array([0x1B, 0x61, 0x01]),
    ALIGN_LEFT: new Uint8Array([0x1B, 0x61, 0x00]),
    ALIGN_RIGHT: new Uint8Array([0x1B, 0x61, 0x02]),
    DOUBLE_SIZE_ON: new Uint8Array([0x1D, 0x21, 0x11]),
    NORMAL_SIZE: new Uint8Array([0x1D, 0x21, 0x00]),
};

function concatBytes(...arrays: Uint8Array[]): Uint8Array {
    const total = arrays.reduce((sum, a) => sum + a.length, 0);
    const result = new Uint8Array(total);
    let offset = 0;
    for (const a of arrays) {
        result.set(a, offset);
        offset += a.length;
    }
    return result;
}

export function buildReceiptBytes(receipt: ReceiptData, drawerPin: 2 | 5 = 2): Uint8Array {
    const parts: Uint8Array[] = [];

    parts.push(ESCPOS.INIT);
    parts.push(ESCPOS.ALIGN_CENTER);
    parts.push(ESCPOS.BOLD_ON);
    parts.push(textToBytes(receipt.branchName + '\n'));
    parts.push(ESCPOS.BOLD_OFF);
    parts.push(textToBytes(new Date(receipt.date).toLocaleString() + '\n'));
    parts.push(textToBytes(`Invoice: ${receipt.invoiceNumber}\n`));
    if (receipt.offline) {
        parts.push(textToBytes('*** OFFLINE SALE - PENDING SYNC ***\n'));
    }
    parts.push(textToBytes('--------------------------------\n'));

    parts.push(ESCPOS.ALIGN_LEFT);
    for (const item of receipt.items) {
        const line = `${item.name} x${item.quantity}  ${item.price.toFixed(2)}\n`;
        parts.push(textToBytes(line));
    }

    parts.push(textToBytes('--------------------------------\n'));
    parts.push(textToBytes(`Subtotal:    ${receipt.subtotal.toFixed(2)}\n`));
    if (receipt.discount > 0) {
        parts.push(textToBytes(`Discount:   -${receipt.discount.toFixed(2)}\n`));
    }
    parts.push(textToBytes(`Tax:         ${receipt.taxAmount.toFixed(2)}\n`));
    parts.push(ESCPOS.BOLD_ON);
    parts.push(textToBytes(`TOTAL:       ${receipt.total.toFixed(2)}\n`));
    parts.push(ESCPOS.BOLD_OFF);

    if (receipt.amountTendered !== undefined && receipt.changeDue !== undefined) {
        parts.push(textToBytes(`Paid:        ${receipt.amountTendered.toFixed(2)}\n`));
        parts.push(textToBytes(`Change:      ${receipt.changeDue.toFixed(2)}\n`));
    }

    parts.push(textToBytes('--------------------------------\n'));
    parts.push(ESCPOS.ALIGN_CENTER);
    parts.push(textToBytes(`Payment: ${receipt.paymentMethod}\n`));
    if (receipt.customerName) {
        parts.push(textToBytes(`Customer: ${receipt.customerName}\n`));
    }
    parts.push(textToBytes('\nThank you for your purchase!\n'));
    if (receipt.efrisFdn) {
        parts.push(textToBytes(`EFRIS FDN: ${receipt.efrisFdn}\n`));
        if (receipt.efrisVerificationCode) {
            parts.push(textToBytes(`Verify: ${receipt.efrisVerificationCode}\n`));
        }
    }
    parts.push(textToBytes('\n'));
    parts.push(ESCPOS.CUT);

    // Append the cash drawer pulse - this must come AFTER the print job
    const drawerCmd = drawerPin === 5 ? ESCPOS.OPEN_DRAWER_PIN_5 : ESCPOS.OPEN_DRAWER_PIN_2;
    parts.push(drawerCmd);

    return concatBytes(...parts);
}

async function sendViaUSB(payload: Uint8Array): Promise<boolean> {
    if (!navigator.usb) {
        throw new Error('WebUSB API not available in this browser');
    }

    const device = await navigator.usb.requestDevice({
        filters: [
            { classCode: 0x07 }, // Printer class
        ],
    });

    if (!device) {
        throw new Error('No USB device selected');
    }

    await device.open();
    if (device.configuration === null) {
        await device.selectConfiguration(1);
    }

    const iface = device.configuration?.interfaces[0];
    if (!iface) {
        throw new Error('No USB interface found');
    }
    await device.claimInterface(iface.interfaceNumber);

    // Find the OUT endpoint
    const endpoint = iface.alternates[0]?.endpoints.find(
        (ep: any) => ep.direction === 'out' && ep.type === 'bulk'
    );

    if (!endpoint) {
        throw new Error('No bulk OUT endpoint found on printer');
    }

    // Send in chunks (max 4096 bytes per transfer typically)
    const CHUNK_SIZE = 4096;
    for (let offset = 0; offset < payload.length; offset += CHUNK_SIZE) {
        const chunk = payload.slice(offset, offset + CHUNK_SIZE);
        await device.transferOut(endpoint.endpointNumber, chunk);
    }

    await device.close();
    return true;
}

async function sendViaNetwork(payload: Uint8Array, config: PrinterConfig): Promise<boolean> {
    if (!config.ip_address) {
        throw new Error('Printer IP address not configured');
    }

    const port = config.port || 9100;
    const url = `http://${config.ip_address}:${port}/print`;

    // Try to POST raw bytes. Most network printers support HTTP POST or have a print relay.
    // The browser cannot open raw TCP sockets, so we use HTTP. For printers that only support
    // raw TCP on port 9100, a small local print service (Node.js/Python helper) is required.
    const response = await fetch(url, {
        method: 'POST',
        body: payload as BodyInit,
        headers: {
            'Content-Type': 'application/octet-stream',
        },
    });

    if (!response.ok && response.status !== 0) {
        throw new Error(`Network printer returned status ${response.status}`);
    }

    return true;
}

function printViaBrowser(receipt: ReceiptData): boolean {
    const date = new Date(receipt.date).toLocaleString();
    const itemsHtml = receipt.items
        .map(item => `<tr><td>${escapeHtml(item.name)}</td><td class="r">${item.quantity}</td><td class="r">${item.price.toFixed(2)}</td><td class="r">${(item.price * item.quantity).toFixed(2)}</td></tr>`)
        .join('');

    const html = `<!DOCTYPE html>
<html><head><title>Receipt</title>
<style>
    body { font-family: 'Courier New', monospace; font-size: 12px; width: 280px; margin: 0 auto; padding: 16px; }
    h2 { text-align: center; margin: 0 0 4px; font-size: 14px; }
    .center { text-align: center; }
    .line { border-top: 1px dashed #999; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 2px 0; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .total-row td { border-top: 1px solid #999; padding-top: 4px; }
    .offline-badge { background: #fef3c7; border: 1px solid #fbbf24; padding: 4px; margin: 4px 0; text-align: center; font-weight: bold; font-size: 10px; }
</style></head><body>
    <h2>${escapeHtml(receipt.branchName)}</h2>
    <p class="center">${date}</p>
    <p class="center">${escapeHtml(receipt.invoiceNumber)}</p>
    ${receipt.offline ? '<div class="offline-badge">OFFLINE SALE - PENDING SYNC</div>' : ''}
    <div class="line"></div>
    <table>${itemsHtml}</table>
    <div class="line"></div>
    <table>
        <tr><td>Subtotal</td><td class="r">${receipt.subtotal.toFixed(2)}</td></tr>
        ${receipt.discount > 0 ? `<tr><td>Discount</td><td class="r">-${receipt.discount.toFixed(2)}</td></tr>` : ''}
        <tr><td>Tax</td><td class="r">${receipt.taxAmount.toFixed(2)}</td></tr>
        <tr class="total-row"><td class="b">Total</td><td class="r b">${receipt.total.toFixed(2)}</td></tr>
    </table>
    ${receipt.amountTendered !== undefined && receipt.changeDue !== undefined ? `
    <div class="line"></div>
    <table>
        <tr><td>Paid</td><td class="r">${receipt.amountTendered.toFixed(2)}</td></tr>
        <tr><td>Change</td><td class="r">${receipt.changeDue.toFixed(2)}</td></tr>
    </table>` : ''}
    <div class="line"></div>
    <p class="center">${escapeHtml(receipt.paymentMethod)}</p>
    ${receipt.customerName ? `<p class="center">Customer: ${escapeHtml(receipt.customerName)}</p>` : ''}
    <p class="center" style="margin-top:12px;font-size:10px;color:#999;">Thank you for your purchase!</p>
    ${receipt.efrisFdn ? `<div class="line"></div><p class="center" style="font-size:10px;">EFRIS FDN: ${escapeHtml(receipt.efrisFdn)}</p>${receipt.efrisVerificationCode ? `<p class="center" style="font-size:10px;">Verify: ${escapeHtml(receipt.efrisVerificationCode)}</p>` : ''}` : ''}
</body></html>`;

    const win = window.open('', '_blank', 'width=400,height=600');
    if (!win) {
        console.warn('[Printer] Popup blocked; cannot print via browser');
        return false;
    }
    win.document.write(html);
    win.document.close();
    setTimeout(() => {
        win.focus();
        win.print();
    }, 300);
    return true;
}

function escapeHtml(text: string): string {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

export async function openDrawer(config: PrinterConfig): Promise<boolean> {
    try {
        const drawerCmd = (config.drawer_pin === 5)
            ? ESCPOS.OPEN_DRAWER_PIN_5
            : ESCPOS.OPEN_DRAWER_PIN_2;

        if (config.type === 'usb') {
            return await sendViaUSB(drawerCmd);
        } else if (config.type === 'network') {
            return await sendViaNetwork(drawerCmd, config);
        } else {
            console.warn('[Printer] Cash drawer requires USB or Network printer (browser mode is receipt-only)');
            return false;
        }
    } catch (err) {
        console.error('[Printer] Failed to open cash drawer:', err);
        return false;
    }
}

export async function printReceiptOnly(receipt: ReceiptData, config: PrinterConfig): Promise<boolean> {
    try {
        if (config.type === 'usb') {
            const payload = buildReceiptBytes(receipt, config.drawer_pin);
            return await sendViaUSB(payload);
        } else if (config.type === 'network') {
            const payload = buildReceiptBytes(receipt, config.drawer_pin);
            return await sendViaNetwork(payload, config);
        } else if (config.type === 'browser') {
            return printViaBrowser(receipt);
        } else {
            console.log('[Printer] Printer disabled; skipping print');
            return false;
        }
    } catch (err) {
        console.error('[Printer] Print failed:', err);
        // Fall back to browser print
        try {
            return printViaBrowser(receipt);
        } catch (fallbackErr) {
            console.error('[Printer] Browser fallback also failed:', fallbackErr);
            return false;
        }
    }
}

export async function openDrawerAndPrintReceipt(receipt: ReceiptData): Promise<{ drawer: boolean; printed: boolean }> {
    const config = await getPrinterConfig();

    if (config.type === 'disabled') {
        return { drawer: false, printed: false };
    }

    // For USB/Network printers, the single payload includes both the print job and the drawer pulse.
    // This is because the cash drawer is connected via the printer's RJ11 port and triggered
    // by ESC/POS commands. We don't open the drawer separately for USB/Network.
    if (config.type === 'usb' || config.type === 'network') {
        const payload = buildReceiptBytes(receipt, config.drawer_pin);
        let sent = false;
        if (config.type === 'usb') {
            sent = await sendViaUSB(payload).catch((e) => {
                console.error('[Printer] USB send failed:', e);
                return false;
            });
        } else {
            sent = await sendViaNetwork(payload, config).catch((e) => {
                console.error('[Printer] Network send failed:', e);
                return false;
            });
        }
        if (sent) return { drawer: true, printed: true };

        // Fallback to browser print
        const browserPrinted = printViaBrowser(receipt);
        return { drawer: false, printed: browserPrinted };
    }

    // For browser mode, drawer must be triggered separately if user has hardware
    const printed = printViaBrowser(receipt);
    return { drawer: false, printed };
}

export function buildReceiptHTML(receipt: ReceiptData): string {
    const date = new Date(receipt.date).toLocaleString();
    const itemsHtml = receipt.items
        .map(item => `<tr><td>${escapeHtml(item.name)}</td><td class="r">${item.quantity}</td><td class="r">${item.price.toFixed(2)}</td><td class="r">${(item.price * item.quantity).toFixed(2)}</td></tr>`)
        .join('');

    return `<!DOCTYPE html>
<html><head><title>Receipt</title>
<style>
    body { font-family: 'Courier New', monospace; font-size: 12px; width: 280px; margin: 0 auto; padding: 16px; }
    h2 { text-align: center; margin: 0 0 4px; font-size: 14px; }
    .center { text-align: center; }
    .line { border-top: 1px dashed #999; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 2px 0; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .total-row td { border-top: 1px solid #999; padding-top: 4px; }
    .offline-badge { background: #fef3c7; border: 1px solid #fbbf24; padding: 4px; margin: 4px 0; text-align: center; font-weight: bold; font-size: 10px; }
</style></head><body>
    <h2>${escapeHtml(receipt.branchName)}</h2>
    <p class="center">${date}</p>
    <p class="center">${escapeHtml(receipt.invoiceNumber)}</p>
    ${receipt.offline ? '<div class="offline-badge">OFFLINE SALE - PENDING SYNC</div>' : ''}
    <div class="line"></div>
    <table>${itemsHtml}</table>
    <div class="line"></div>
    <table>
        <tr><td>Subtotal</td><td class="r">${receipt.subtotal.toFixed(2)}</td></tr>
        ${receipt.discount > 0 ? `<tr><td>Discount</td><td class="r">-${receipt.discount.toFixed(2)}</td></tr>` : ''}
        <tr><td>Tax</td><td class="r">${receipt.taxAmount.toFixed(2)}</td></tr>
        <tr class="total-row"><td class="b">Total</td><td class="r b">${receipt.total.toFixed(2)}</td></tr>
    </table>
    ${receipt.amountTendered !== undefined && receipt.changeDue !== undefined ? `
    <div class="line"></div>
    <table>
        <tr><td>Paid</td><td class="r">${receipt.amountTendered.toFixed(2)}</td></tr>
        <tr><td>Change</td><td class="r">${receipt.changeDue.toFixed(2)}</td></tr>
    </table>` : ''}
    <div class="line"></div>
    <p class="center">${escapeHtml(receipt.paymentMethod)}</p>
    ${receipt.customerName ? `<p class="center">Customer: ${escapeHtml(receipt.customerName)}</p>` : ''}
    <p class="center" style="margin-top:12px;font-size:10px;color:#999;">Thank you for your purchase!</p>
    ${receipt.efrisFdn ? `<div class="line"></div><p class="center" style="font-size:10px;">EFRIS FDN: ${escapeHtml(receipt.efrisFdn)}</p>${receipt.efrisVerificationCode ? `<p class="center" style="font-size:10px;">Verify: ${escapeHtml(receipt.efrisVerificationCode)}</p>` : ''}` : ''}
</body></html>`;
}
