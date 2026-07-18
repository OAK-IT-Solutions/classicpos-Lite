use log::{info, warn, error};

// ─── USB Printer ───────────────────────────────────────────────────────────────

/// Print ESC/POS bytes to a USB receipt printer.
///
/// Searches for USB devices with class 0x07 (Printer), opens the first one found,
/// and writes the bytes in 4096-byte chunks to the bulk OUT endpoint.
pub fn print_to_usb(bytes: &[u8]) -> Result<(), String> {
    let devices = rusb::devices().map_err(|e| format!("Failed to enumerate USB devices: {}", e))?;

    for device in devices.iter() {
        let desc = match device.device_descriptor() {
            Ok(d) => d,
            Err(_) => continue,
        };

        // Check if this is a printer (class 0x07)
        if desc.class_code() != 0x07 {
            continue;
        }

        info!(
            "Found USB printer: {:04x}:{:04x}",
            desc.vendor_id(),
            desc.product_id()
        );

        let handle = match device.open() {
            Ok(h) => h,
            Err(e) => {
                warn!("Failed to open USB printer: {}", e);
                continue;
            }
        };

        // Find the printer interface
        for interface in device.active_config_descriptor().map_err(|e| e.to_string())?.interfaces() {
            for iface_desc in interface.descriptors() {
                if iface_desc.class_code() == 0x07 {
                    let iface_num = iface_desc.interface_number();

                    // Try to claim the interface
                    match handle.claim_interface(iface_num) {
                        Ok(()) => {
                            info!("Claimed USB interface {}", iface_num);
                        }
                        Err(e) => {
                            warn!("Failed to claim interface {}: {}", iface_num, e);
                            continue;
                        }
                    }

                    // Find the bulk OUT endpoint
                    for ep_desc in iface_desc.endpoint_descriptors() {
                        if ep_desc.direction() == rusb::Direction::Out
                            && ep_desc.transfer_type() == rusb::TransferType::Bulk
                        {
                            let endpoint_addr = ep_desc.address();
                            info!("Writing {} bytes to USB endpoint 0x{:02x}", bytes.len(), endpoint_addr);

                            // Write in 4096-byte chunks
                            for chunk in bytes.chunks(4096) {
                                handle
                                    .write_bulk(endpoint_addr, chunk, std::time::Duration::from_secs(5))
                                    .map_err(|e| format!("USB write failed: {}", e))?;
                            }

                            info!("USB print complete");
                            return Ok(());
                        }
                    }

                    warn!("No suitable USB endpoint found on interface {}", iface_num);
                }
            }
        }
    }

    Err("No USB receipt printer found".to_string())
}

// ─── Serial/Network Printer ────────────────────────────────────────────────────

/// Print ESC/POS bytes to a serial port (COM/USB-serial).
pub fn print_to_serial(port_name: &str, bytes: &[u8]) -> Result<(), String> {
    let mut port = serialport::new(port_name, 9600)
        .timeout(std::time::Duration::from_secs(5))
        .open()
        .map_err(|e| format!("Failed to open serial port {}: {}", port_name, e))?;

    port.write_all(bytes)
        .map_err(|e| format!("Serial write failed: {}", e))?;

    // Wait for data to be transmitted
    std::thread::sleep(std::time::Duration::from_millis(100));

    info!("Serial print complete on {}", port_name);
    Ok(())
}

/// Print ESC/POS bytes to a network printer via TCP.
pub fn print_to_network(ip: &str, port: u16, bytes: &[u8]) -> Result<(), String> {
    use std::io::Write;
    use std::net::TcpStream;

    let addr = format!("{}:{}", ip, port);
    let mut stream = TcpStream::connect(&addr)
        .map_err(|e| format!("Failed to connect to network printer at {}: {}", addr, e))?;

    stream
        .set_write_timeout(Some(std::time::Duration::from_secs(5)))
        .map_err(|e| e.to_string())?;

    stream
        .write_all(bytes)
        .map_err(|e| format!("Network write failed: {}", e))?;

    stream.flush().map_err(|e| e.to_string())?;

    info!("Network print complete to {}", addr);
    Ok(())
}

// ─── Printer Enumeration ───────────────────────────────────────────────────────

#[derive(Debug, Clone, serde::Serialize)]
pub struct PrinterInfo {
    pub name: String,
    pub port_type: String, // "usb", "serial", "network"
    pub vendor_id: Option<u16>,
    pub product_id: Option<u16>,
    pub port_name: Option<String>,
    pub connected: bool,
}

/// List all available USB printers.
pub fn list_usb_printers() -> Vec<PrinterInfo> {
    let mut printers = Vec::new();

    if let Ok(devices) = rusb::devices() {
        for device in devices.iter() {
            if let Ok(desc) = device.device_descriptor() {
                if desc.class_code() == 0x07 {
                    let name = device
                        .open()
                        .ok()
                        .and_then(|h| h.read_product_string_ascii().ok())
                        .unwrap_or_else(|| format!("USB Printer {:04x}:{:04x}", desc.vendor_id(), desc.product_id()));

                    printers.push(PrinterInfo {
                        name,
                        port_type: "usb".to_string(),
                        vendor_id: Some(desc.vendor_id()),
                        product_id: Some(desc.product_id()),
                        port_name: None,
                        connected: true,
                    });
                }
            }
        }
    }

    printers
}

/// List all available serial ports.
pub fn list_serial_printers() -> Vec<PrinterInfo> {
    let mut printers = Vec::new();

    if let Ok(ports) = serialport::available_ports() {
        for port_info in ports {
            printers.push(PrinterInfo {
                name: port_info.port_name.clone(),
                port_type: "serial".to_string(),
                vendor_id: port_info.vid_pid.map(|v| v.0),
                product_id: port_info.vid_pid.map(|v| v.1),
                port_name: Some(port_info.port_name),
                connected: true,
            });
        }
    }

    printers
}

/// List all printers (USB + Serial).
pub fn list_all_printers() -> Vec<PrinterInfo> {
    let mut printers = list_usb_printers();
    printers.extend(list_serial_printers());
    printers
}

// ─── Cash Drawer ───────────────────────────────────────────────────────────────

/// Send ESC/POS command to open cash drawer via USB printer.
pub fn open_drawer_usb() -> Result<(), String> {
    // ESC/POS: ESC p 0x00 0x19 0xFA — Pulse Pin 2 for 60ms
    let drawer_cmd = vec![0x1B, 0x70, 0x00, 0x19, 0xFA];
    print_to_usb(&drawer_cmd)
}

/// Send ESC/POS command to open cash drawer via serial port.
pub fn open_drawer_serial(port_name: &str) -> Result<(), String> {
    let drawer_cmd = vec![0x1B, 0x70, 0x00, 0x19, 0xFA];
    print_to_serial(port_name, &drawer_cmd)
}

/// Send ESC/POS command to open cash drawer via network.
pub fn open_drawer_network(ip: &str, port: u16) -> Result<(), String> {
    let drawer_cmd = vec![0x1B, 0x70, 0x00, 0x19, 0xFA];
    print_to_network(ip, port, &drawer_cmd)
}
