pub mod printer;
pub mod tunnel;

use std::path::PathBuf;
use std::sync::Mutex;
use tauri::{AppHandle, Manager, State};

// ─── Managed State Types ───────────────────────────────────────────────────────

pub struct PhpPort(pub u16);
pub struct PhpProcess(pub Mutex<Option<std::process::Child>>);
pub struct AppVersion(pub String);

#[derive(Debug, Clone, serde::Serialize)]
pub struct PhpStatus {
    pub running: bool,
    pub port: u16,
    pub pid: Option<u32>,
}

// ─── IPC Commands ──────────────────────────────────────────────────────────────

#[tauri::command]
pub fn get_php_port(port: State<'_, PhpPort>) -> u16 {
    port.0
}

#[tauri::command]
pub fn get_php_status(port: State<'_, PhpPort>, process: State<'_, PhpProcess>) -> PhpStatus {
    let running = process.0.lock().map(|p| p.is_some()).unwrap_or(false);
    PhpStatus {
        running,
        port: port.0,
        pid: process.0.lock().ok().and_then(|p| p.as_ref().map(|c| c.id())),
    }
}

#[tauri::command]
pub async fn start_php_server(
    app: AppHandle,
    port: State<'_, PhpPort>,
) -> Result<u16, String> {
    start_php_server_inner(&app, port.0).await
}

#[tauri::command]
pub async fn stop_php_server(process: State<'_, PhpProcess>) -> Result<(), String> {
    if let Ok(mut guard) = process.0.lock() {
        if let Some(mut child) = guard.take() {
            child.kill().map_err(|e| e.to_string())?;
            eprintln!("[ClassicPOS] PHP server stopped");
        }
    }
    Ok(())
}

#[tauri::command]
pub async fn get_app_version(version: State<'_, AppVersion>) -> Result<String, String> {
    Ok(version.0.clone())
}

#[tauri::command]
pub async fn generate_env(app: AppHandle) -> Result<String, String> {
    let app_dir = app.path().app_data_dir().map_err(|e| e.to_string())?;
    let env_path = app_dir.join(".env");

    let content = format!(
        r#"APP_NAME=ClassicPOS
APP_ENV=production
APP_DEBUG=false
APP_URL=http://127.0.0.1:{{PORT}}
APP_KEY=
DB_CONNECTION=sqlite
DB_DATABASE={db_path}
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
CLASSICPOS_SELF_HOSTED=true
SANCTUM_STATEFUL_DOMAINS=localhost
CORS_ALLOWED_ORIGINS=*
"#,
        db_path = app_dir.join("data/classicpos.sqlite").to_string_lossy(),
    );

    std::fs::write(&env_path, &content).map_err(|e| e.to_string())?;
    Ok(env_path.to_string_lossy().to_string())
}

// ─── Printing Commands ─────────────────────────────────────────────────────────

#[tauri::command]
pub async fn print_receipt(bytes: Vec<u8>) -> Result<String, String> {
    // Try USB first, then serial, then network
    match printer::print_to_usb(&bytes) {
        Ok(()) => return Ok("usb".to_string()),
        Err(e) => eprintln!("[Printer] USB failed: {}", e),
    }

    // Fallback: write to temp file for manual printing
    let temp_path = std::env::temp_dir().join("classicpos_receipt.bin");
    std::fs::write(&temp_path, &bytes).map_err(|e| format!("Failed to write receipt file: {}", e))?;

    Ok("file".to_string())
}

#[tauri::command]
pub async fn open_drawer() -> Result<String, String> {
    match printer::open_drawer_usb() {
        Ok(()) => return Ok("usb".to_string()),
        Err(e) => eprintln!("[Drawer] USB failed: {}", e),
    }

    Ok("none".to_string())
}

#[tauri::command]
pub async fn list_printers() -> Result<Vec<printer::PrinterInfo>, String> {
    Ok(printer::list_all_printers())
}

#[tauri::command]
pub async fn print_to_port(port_type: String, port_name: String, ip: String, network_port: u16, bytes: Vec<u8>) -> Result<(), String> {
    match port_type.as_str() {
        "usb" => printer::print_to_usb(&bytes),
        "serial" => printer::print_to_serial(&port_name, &bytes),
        "network" => printer::print_to_network(&ip, network_port, &bytes),
        _ => Err(format!("Unknown printer type: {}", port_type)),
    }
}

#[tauri::command]
pub async fn open_drawer_to_port(port_type: String, port_name: String, ip: String, network_port: u16) -> Result<(), String> {
    match port_type.as_str() {
        "usb" => printer::open_drawer_usb(),
        "serial" => printer::open_drawer_serial(&port_name),
        "network" => printer::open_drawer_network(&ip, network_port),
        _ => Err(format!("Unknown printer type: {}", port_type)),
    }
}

// ─── Startup Script ────────────────────────────────────────────────────────────

pub async fn run_startup_script(app: &AppHandle) -> Result<(), String> {
    use std::process::{Command, Stdio};

    let app_dir = app.path().app_data_dir().map_err(|e| e.to_string())?;
    let php_binary = find_php_binary(app)?;

    let startup_script = find_resource_file(app, "php-startup.php")
        .unwrap_or_else(|| {
            let cwd = std::env::current_dir().unwrap_or_default();
            cwd.join("src-tauri").join("php-startup.php").to_string_lossy().to_string()
        });

    if !std::path::Path::new(&startup_script).exists() {
        return Ok(());
    }

    let output = Command::new(&php_binary)
        .arg(&startup_script)
        .env("APP_DATA_DIR", app_dir.to_str().unwrap_or(""))
        .env("APP_VERSION", env!("CARGO_PKG_VERSION"))
        .env("DB_CONNECTION", "sqlite")
        .env("DB_DATABASE", app_dir.join("data/classicpos.sqlite").to_str().unwrap_or(""))
        .stdout(Stdio::piped())
        .stderr(Stdio::piped())
        .output()
        .map_err(|e| format!("Failed to run startup script: {}", e))?;

    let stdout = String::from_utf8_lossy(&output.stdout);
    let stderr = String::from_utf8_lossy(&output.stderr);

    for line in stdout.lines() {
        eprintln!("[ClassicPOS Startup] {}", line);
    }
    for line in stderr.lines() {
        eprintln!("[ClassicPOS Startup STDERR] {}", line);
    }

    Ok(())
}

// ─── PHP Server Lifecycle ──────────────────────────────────────────────────────

pub async fn start_php_server_inner(app: &AppHandle, port: u16) -> Result<u16, String> {
    use std::process::{Command, Stdio};

    let app_dir = app.path().app_data_dir().map_err(|e| e.to_string())?;
    let db_dir = app_dir.join("data");
    let storage_dir = app_dir.join("storage");
    let public_dir = app_dir.join("public");
    let bootstrap_dir = app_dir.join("bootstrap");

    // Ensure directories exist
    for dir in [&db_dir, &storage_dir, &public_dir, &bootstrap_dir] {
        std::fs::create_dir_all(dir).map_err(|e| e.to_string())?;
    }

    // Ensure storage subdirectories
    for sub in ["app", "framework", "logs", "framework/cache", "framework/sessions", "framework/views"] {
        std::fs::create_dir_all(storage_dir.join(sub)).map_err(|e| e.to_string())?;
    }

    // Generate .env if not present
    let env_path = app_dir.join(".env");
    if !env_path.exists() {
        let db_path = app_dir.join("data/classicpos.sqlite");
        let content = format!(
            "APP_NAME=ClassicPOS\nAPP_ENV=production\nAPP_DEBUG=false\n\
             APP_URL=http://127.0.0.1:{port}\nAPP_KEY=\n\
             DB_CONNECTION=sqlite\nDB_DATABASE={db}\n\
             CACHE_STORE=file\nSESSION_DRIVER=file\nQUEUE_CONNECTION=sync\n\
             CLASSICPOS_SELF_HOSTED=true\nSANCTUM_STATEFUL_DOMAINS=localhost\n\
             CORS_ALLOWED_ORIGINS=*\n",
            port = port,
            db = db_path.to_string_lossy(),
        );
        std::fs::write(&env_path, content).map_err(|e| format!("Failed to write .env: {}", e))?;
        eprintln!("[ClassicPOS] Generated .env at {:?}", env_path);
    }

    let php_binary = find_php_binary(app)?;
    eprintln!("[ClassicPOS] PHP binary: {}", php_binary);

    let mut cmd = Command::new(&php_binary);
    cmd.args(["-S", &format!("127.0.0.1:{}", port), "-t", public_dir.to_str().unwrap_or(".")])
        .env("APP_ENV", "production")
        .env("APP_DATA_DIR", app_dir.to_str().unwrap_or(""))
        .env("DB_CONNECTION", "sqlite")
        .env("DB_DATABASE", app_dir.join("data/classicpos.sqlite").to_str().unwrap_or(""))
        .env("CACHE_STORE", "file")
        .env("SESSION_DRIVER", "file")
        .env("QUEUE_CONNECTION", "sync")
        .env("LARAVEL_STORAGE_PATH", storage_dir.to_str().unwrap_or(""))
        .env("SERVER_NAME", format!("127.0.0.1:{}", port))
        .stdout(Stdio::piped())
        .stderr(Stdio::piped());

    let child = cmd.spawn().map_err(|e| format!("Failed to start PHP: {}", e))?;

    if let Some(process) = app.try_state::<PhpProcess>() {
        if let Ok(mut guard) = process.0.lock() {
            *guard = Some(child);
        }
    }

    // Health check — wait for PHP to be ready
    let url = format!("http://127.0.0.1:{}/", port);
    for attempt in 0..30 {
        tokio::time::sleep(std::time::Duration::from_millis(200)).await;
        match reqwest::get(&url).await {
            Ok(resp) if resp.status().is_success() => {
                eprintln!("[ClassicPOS] PHP server ready on port {} (attempt {})", port, attempt + 1);
                return Ok(port);
            }
            Ok(resp) => {
                eprintln!("[ClassicPOS] PHP responded status {} on attempt {}", resp.status(), attempt + 1);
            }
            Err(_) => {
                if attempt % 10 == 0 {
                    eprintln!("[ClassicPOS] Waiting for PHP... (attempt {})", attempt + 1);
                }
            }
        }
    }

    Err("PHP server failed to start within 6 seconds".to_string())
}

// ─── Helpers ───────────────────────────────────────────────────────────────────

fn find_php_binary(app: &AppHandle) -> Result<String, String> {
    let binary_name = if cfg!(target_os = "windows") {
        "php-x86_64-pc-windows-msvc.exe"
    } else if cfg!(target_os = "macos") {
        if cfg!(target_arch = "aarch64") {
            "php-aarch64-apple-darwin"
        } else {
            "php-x86_64-apple-darwin"
        }
    } else {
        "php-x86_64-unknown-linux-gnu"
    };

    if let Some(path) = find_resource_file(app, &format!("binaries/{}", binary_name)) {
        return Ok(path);
    }

    // Try exe directory (production — binary next to exe)
    if let Ok(exe_path) = std::env::current_exe() {
        if let Some(exe_dir) = exe_path.parent() {
            let path = exe_dir.join("binaries").join(binary_name);
            if path.exists() {
                return Ok(path.to_string_lossy().to_string());
            }
            // Also try same directory as exe
            let path2 = exe_dir.join(binary_name);
            if path2.exists() {
                return Ok(path2.to_string_lossy().to_string());
            }
        }
    }

    let dev_dirs = [
        std::env::current_dir().unwrap_or_default().join("src-tauri").join("binaries"),
        PathBuf::from(std::env::var("USERPROFILE").or_else(|_| std::env::var("HOME")).unwrap_or_default())
            .join("classicpos-php"),
    ];

    for dir in &dev_dirs {
        let path = dir.join(binary_name);
        if path.exists() {
            return Ok(path.to_string_lossy().to_string());
        }
    }

    Err(format!(
        "PHP binary '{}' not found. Build with: docker build -t classicpos-php-builder -f Dockerfile.php .",
        binary_name
    ))
}

fn find_resource_file(app: &AppHandle, name: &str) -> Option<String> {
    if let Ok(resource_dir) = app.path().resource_dir() {
        let path = resource_dir.join(name);
        if path.exists() {
            return Some(path.to_string_lossy().to_string());
        }
    }
    None
}

// ─── Tunnel Commands ───────────────────────────────────────────────────────────

#[tauri::command]
pub async fn tunnel_status() -> Result<tunnel::TunnelStatus, String> {
    Ok(tunnel::check_status())
}

#[tauri::command]
pub async fn tunnel_generate_config(
    tunnel_id: String,
    credentials_path: String,
    hostname: String,
    local_port: u16,
) -> Result<String, String> {
    let config = tunnel::generate_config(&tunnel_id, &credentials_path, &hostname, local_port)?;
    let config_dir = get_tunnel_config_dir();
    let path = tunnel::save_config(&config_dir, &config)?;
    Ok(path.to_string_lossy().to_string())
}

#[tauri::command]
pub async fn tunnel_install_service(config_path: String) -> Result<(), String> {
    tunnel::install_service(&config_path)
}

#[tauri::command]
pub async fn tunnel_start() -> Result<(), String> {
    tunnel::start_service()
}

#[tauri::command]
pub async fn tunnel_stop() -> Result<(), String> {
    tunnel::stop_service()
}

#[tauri::command]
pub async fn tunnel_uninstall() -> Result<(), String> {
    tunnel::uninstall_service()
}

fn get_tunnel_config_dir() -> PathBuf {
    if cfg!(target_os = "windows") {
        PathBuf::from(std::env::var("PROGRAMDATA").unwrap_or_default())
            .join("classicpos").join("cloudflared")
    } else {
        PathBuf::from("/etc/classicpos/cloudflared")
    }
}
