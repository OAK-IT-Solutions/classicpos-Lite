pub mod printer;
pub mod tunnel;

use std::path::PathBuf;
use std::sync::Mutex;
use tauri::{AppHandle, Emitter, Manager, State};

// ─── Managed State Types ───────────────────────────────────────────────────────

pub struct PhpPort(pub u16);
pub struct PhpProcess(pub Mutex<Option<std::process::Child>>);
pub struct AppVersion(pub String);
pub struct LaravelRoot(pub Mutex<Option<PathBuf>>);

#[derive(Debug, Clone, serde::Serialize, PartialEq)]
#[serde(tag = "stage", content = "detail")]
pub enum StartupState {
    Idle,
    Extracting,
    StartingPhp { attempt: u8 },
    Running { port: u16 },
    Failed { message: String },
}

pub struct StartupPhase(pub Mutex<StartupState>);

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

#[derive(Debug, Clone, serde::Serialize)]
pub struct PhpStatus {
    pub running: bool,
    pub port: u16,
    pub pid: Option<u32>,
}

#[tauri::command]
pub fn get_startup_state(state: State<'_, StartupPhase>) -> StartupState {
    state.0.lock().map(|s| s.clone()).unwrap_or(StartupState::Idle)
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
    let port = find_port_from_state(&app);

    let app_key = generate_app_key();
    let content = format!(
        "APP_NAME=ClassicPOS\nAPP_ENV=production\nAPP_DEBUG=false\n\
         APP_URL=http://127.0.0.1:{port}\nAPP_KEY={key}\n\
         DB_CONNECTION=sqlite\nDB_DATABASE={db}\n\
         CACHE_STORE=file\nSESSION_DRIVER=file\nQUEUE_CONNECTION=sync\n\
         CLASSICPOS_SELF_HOSTED=true\nSANCTUM_STATEFUL_DOMAINS=localhost\n\
         CORS_ALLOWED_ORIGINS=*\n",
        port = port,
        key = app_key,
        db = app_dir.join("data/classicpos.sqlite").to_string_lossy(),
    );

    std::fs::write(&env_path, &content).map_err(|e| e.to_string())?;
    Ok(env_path.to_string_lossy().to_string())
}

// ─── Printing Commands ─────────────────────────────────────────────────────────

#[tauri::command]
pub async fn print_receipt(bytes: Vec<u8>) -> Result<String, String> {
    match printer::print_to_usb(&bytes) {
        Ok(()) => return Ok("usb".to_string()),
        Err(e) => eprintln!("[Printer] USB failed: {}", e),
    }

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

// ─── App Key Generation ────────────────────────────────────────────────────────

fn generate_app_key() -> String {
    use std::io::Read;
    let mut bytes = [0u8; 32];
    let file = std::fs::File::open(r"\\.\PhysicalDrive0").or_else(|_| {
        std::fs::File::open(r"\\.\NUL")
    });
    if let Ok(mut f) = file {
        let _ = f.read(&mut bytes);
    }
    if bytes.iter().all(|b| *b == 0) {
        for (i, b) in bytes.iter_mut().enumerate() {
            *b = ((std::time::SystemTime::now()
                .duration_since(std::time::UNIX_EPOCH)
                .unwrap_or_default()
                .subsec_nanos() as u64)
                .wrapping_mul((i as u64) + 1)
                .wrapping_add(std::process::id() as u64)) as u8;
            std::thread::sleep(std::time::Duration::from_nanos(1));
        }
    }
    format!("base64:{}", base64_encode(&bytes))
}

fn base64_encode(data: &[u8]) -> String {
    const CHARS: &[u8] = b"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
    let mut result = String::with_capacity((data.len() + 2) / 3 * 4);
    for chunk in data.chunks(3) {
        let b0 = chunk[0] as u32;
        let b1 = if chunk.len() > 1 { chunk[1] as u32 } else { 0 };
        let b2 = if chunk.len() > 2 { chunk[2] as u32 } else { 0 };
        let triple = (b0 << 16) | (b1 << 8) | b2;
        result.push(CHARS[((triple >> 18) & 0x3F) as usize] as char);
        result.push(CHARS[((triple >> 12) & 0x3F) as usize] as char);
        if chunk.len() > 1 {
            result.push(CHARS[((triple >> 6) & 0x3F) as usize] as char);
        } else {
            result.push('=');
        }
        if chunk.len() > 2 {
            result.push(CHARS[(triple & 0x3F) as usize] as char);
        } else {
            result.push('=');
        }
    }
    result
}

// ─── .env Generation ───────────────────────────────────────────────────────────

fn generate_env_file(app: &AppHandle, port: u16) -> Result<PathBuf, String> {
    let laravel_root = resolve_laravel_root(app);
    let app_dir = app.path().app_data_dir().map_err(|e| e.to_string())?;
    let env_path = laravel_root.join(".env");

    let needs_regen = if env_path.exists() {
        let content = std::fs::read_to_string(&env_path).unwrap_or_default();
        content.contains("APP_KEY=\n") || content.contains("APP_KEY=\r\n") || !content.contains("APP_KEY=")
    } else {
        true
    };

    if needs_regen {
        let app_key = generate_app_key();
        let db_path = app_dir.join("data/classicpos.sqlite");
        let content = format!(
            "APP_NAME=ClassicPOS\nAPP_ENV=production\nAPP_DEBUG=false\n\
             APP_URL=http://127.0.0.1:{port}\nAPP_KEY={key}\n\
             DB_CONNECTION=sqlite\nDB_DATABASE={db}\n\
             CACHE_STORE=file\nSESSION_DRIVER=file\nQUEUE_CONNECTION=sync\n\
             CLASSICPOS_SELF_HOSTED=true\nSANCTUM_STATEFUL_DOMAINS=localhost\n\
             CORS_ALLOWED_ORIGINS=*\n",
            port = port,
            key = app_key,
            db = db_path.to_string_lossy(),
        );
        std::fs::write(&env_path, content).map_err(|e| format!("Failed to write .env: {}", e))?;
        eprintln!("[ClassicPOS] Generated .env with APP_KEY at {:?}", env_path);
    } else {
        eprintln!("[ClassicPOS] .env already exists with valid APP_KEY");
    }

    Ok(env_path)
}

// ─── PHP Server Lifecycle ──────────────────────────────────────────────────────

fn set_startup_state(app: &AppHandle, state: StartupState) {
    if let Some(s) = app.try_state::<StartupPhase>() {
        if let Ok(mut guard) = s.0.lock() {
            *guard = state.clone();
        }
    }
    let _ = app.emit("classicpos:startup-state", &state);
}

/// Ensure the Laravel backend is extracted and ready. Called from the background task.
pub fn ensure_backend_extracted(app: &AppHandle) -> Result<PathBuf, String> {
    set_startup_state(app, StartupState::Extracting);
    let root = resolve_laravel_root(app);
    let public_dir = root.join("public");
    if !public_dir.exists() {
        return Err(format!("Public directory not found after extraction: {:?}", public_dir));
    }
    Ok(root)
}

pub async fn start_server_background(app: AppHandle, port: u16) {
    // Step 1: Extract backend
    match ensure_backend_extracted(&app) {
        Ok(_root) => {}
        Err(e) => {
            eprintln!("[ClassicPOS] Backend extraction failed: {}", e);
            set_startup_state(&app, StartupState::Failed { message: e });
            return;
        }
    };

    // Step 2: Prepare directories + .env
    let app_dir = match app.path().app_data_dir() {
        Ok(d) => d,
        Err(e) => {
            let msg = format!("Failed to get app data dir: {}", e);
            set_startup_state(&app, StartupState::Failed { message: msg });
            return;
        }
    };
    let db_dir = app_dir.join("data");
    let storage_dir = app_dir.join("storage");
    let _ = std::fs::create_dir_all(&db_dir);
    let _ = std::fs::create_dir_all(&storage_dir);
    for sub in ["app", "framework", "logs", "framework/cache", "framework/sessions", "framework/views"] {
        let _ = std::fs::create_dir_all(storage_dir.join(sub));
    }
    if let Err(e) = generate_env_file(&app, port) {
        eprintln!("[ClassicPOS] .env generation warning: {}", e);
    }

    // Step 3: Start PHP server
    match start_php_server_inner(&app, port).await {
        Ok(_) => {
            eprintln!("[ClassicPOS] PHP server started successfully on port {}", port);
            set_startup_state(&app, StartupState::Running { port });
        }
        Err(e) => {
            eprintln!("[ClassicPOS] Failed to start PHP server: {}", e);
            set_startup_state(&app, StartupState::Failed { message: e });
        }
    }
}

pub async fn start_php_server_inner(app: &AppHandle, port: u16) -> Result<u16, String> {
    use std::process::{Command, Stdio};

    let app_dir = app.path().app_data_dir().map_err(|e| e.to_string())?;
    let storage_dir = app_dir.join("storage");

    let laravel_root = resolve_laravel_root(app);
    let public_dir = laravel_root.join("public");

    eprintln!("[ClassicPOS] Laravel root: {:?}", laravel_root);
    eprintln!("[ClassicPOS] Public dir: {:?}", public_dir);

    if !public_dir.exists() {
        return Err(format!("Public directory not found: {:?}", public_dir));
    }

    let php_binary = find_php_binary(app)?;
    eprintln!("[ClassicPOS] PHP binary: {}", php_binary);

    let router_script = find_resource_file(app, "php-router.php")
        .or_else(|| find_dev_file("php-router.php"))
        .ok_or("php-router.php not found")?;
    eprintln!("[ClassicPOS] Router: {}", router_script);

    for attempt in 1..=3 {
        eprintln!("[ClassicPOS] PHP server start attempt {}/3", attempt);
        set_startup_state(app, StartupState::StartingPhp { attempt });

        let public_dir_normalized = normalize_path_for_env(&public_dir.to_string_lossy());
        let router_normalized = normalize_path_for_env(&router_script);

        let mut cmd = Command::new(&php_binary);
        cmd.args(["-S", &format!("127.0.0.1:{}", port), "-t", &public_dir_normalized, &router_normalized])
            .env("APP_ENV", "production")
            .env("APP_DATA_DIR", normalize_path_for_env(&app_dir.to_string_lossy()))
            .env("PHP_BINARY", normalize_path_for_env(&php_binary))
            .env("DB_CONNECTION", "sqlite")
            .env("DB_DATABASE", normalize_path_for_env(&app_dir.join("data/classicpos.sqlite").to_string_lossy()))
            .env("CACHE_STORE", "file")
            .env("SESSION_DRIVER", "file")
            .env("QUEUE_CONNECTION", "sync")
            .env("LARAVEL_STORAGE_PATH", normalize_path_for_env(&storage_dir.to_string_lossy()))
            .env("LARAVEL_ROOT", normalize_path_for_env(&laravel_root.to_string_lossy()))
            .env("SERVER_NAME", format!("127.0.0.1:{}", port))
            .stdout(Stdio::piped())
            .stderr(Stdio::piped());
        hide_console_window(&mut cmd);

        let mut child = cmd.spawn().map_err(|e| format!("Failed to start PHP: {}", e))?;

        // Drain stdout/stderr in background threads to prevent pipe buffer deadlock
        if let Some(stdout) = child.stdout.take() {
            std::thread::spawn(move || {
                use std::io::BufRead;
                let reader = std::io::BufReader::new(stdout);
                for line in reader.lines() {
                    if let Ok(l) = line {
                        eprintln!("[PHP] {}", l);
                    }
                }
            });
        }
        if let Some(stderr) = child.stderr.take() {
            std::thread::spawn(move || {
                use std::io::BufRead;
                let reader = std::io::BufReader::new(stderr);
                for line in reader.lines() {
                    if let Ok(l) = line {
                        eprintln!("[PHP ERR] {}", l);
                    }
                }
            });
        }

        // Store child IMMEDIATELY so health check loop can reference it
        let child_id = child.id();
        if let Some(process) = app.try_state::<PhpProcess>() {
            if let Ok(mut guard) = process.0.lock() {
                *guard = Some(child);
            }
        }

        // Health check — accept any response (including 5xx = Laravel booted)
        let url = format!("http://127.0.0.1:{}/", port);
        let mut server_ready = false;
        for health_attempt in 0..40 {
            tokio::time::sleep(std::time::Duration::from_millis(500)).await;

            match reqwest::get(&url).await {
                Ok(resp) => {
                    let status = resp.status().as_u16();
                    eprintln!("[ClassicPOS] PHP responded HTTP {} on attempt {}", status, health_attempt + 1);
                    // Accept any non-5xx immediately; accept 5xx after brief wait (Laravel may be initializing)
                    if status < 500 {
                        server_ready = true;
                        break;
                    }
                    if status >= 500 && health_attempt > 3 {
                        eprintln!("[ClassicPOS] Server returning 5xx — accepting (Laravel booting)");
                        server_ready = true;
                        break;
                    }
                }
                Err(_) => {
                    if health_attempt % 5 == 0 {
                        eprintln!("[ClassicPOS] Waiting for PHP... (attempt {})", health_attempt + 1);
                    }
                }
            }
        }

        if server_ready {
            eprintln!("[ClassicPOS] PHP server ready on port {}", port);
            return Ok(port);
        }

        // Kill failed process and retry
        eprintln!("[ClassicPOS] Attempt {} failed, killing PHP process (pid {})", attempt, child_id);
        if let Some(process) = app.try_state::<PhpProcess>() {
            if let Ok(mut guard) = process.0.lock() {
                if let Some(mut dead) = guard.take() {
                    let _ = dead.kill();
                    let _ = dead.wait();
                }
            }
        }

        if attempt < 3 {
            tokio::time::sleep(std::time::Duration::from_secs(1)).await;
        }
    }

    Err("PHP server failed to start after 3 attempts".to_string())
}

// ─── Helpers ───────────────────────────────────────────────────────────────────

fn find_port_from_state(app: &AppHandle) -> u16 {
    app.try_state::<PhpPort>().map(|p| p.0).unwrap_or(8080)
}

pub fn resolve_laravel_root(app: &AppHandle) -> PathBuf {
    // Check cache first
    if let Some(state) = app.try_state::<LaravelRoot>() {
        if let Ok(guard) = state.0.lock() {
            if let Some(ref path) = *guard {
                return path.clone();
            }
        }
    }

    let root = find_laravel_root(app);

    // Cache it
    if let Some(state) = app.try_state::<LaravelRoot>() {
        if let Ok(mut guard) = state.0.lock() {
            *guard = Some(root.clone());
        }
    }

    root
}

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

    if let Ok(exe_path) = std::env::current_exe() {
        if let Some(exe_dir) = exe_path.parent() {
            let path = exe_dir.join("binaries").join(binary_name);
            if path.exists() {
                return Ok(path.to_string_lossy().to_string());
            }
            let path2 = exe_dir.join(binary_name);
            if path2.exists() {
                return Ok(path2.to_string_lossy().to_string());
            }
        }
    }

    let dev_dirs = [
        std::env::current_dir().unwrap_or_default().join("src-tauri").join("binaries"),
        std::env::current_dir().unwrap_or_default().join("desktop").join("src-tauri").join("binaries"),
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
        "PHP binary '{}' not found.",
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

fn find_dev_file(name: &str) -> Option<String> {
    let cwd = std::env::current_dir().unwrap_or_default();
    let path = cwd.join("desktop").join("src-tauri").join(name);
    if path.exists() {
        return Some(path.to_string_lossy().to_string());
    }
    let path2 = cwd.join("src-tauri").join(name);
    if path2.exists() {
        return Some(path2.to_string_lossy().to_string());
    }
    None
}

/// Find the Laravel root directory (contains artisan, public/, app/, vendor/)
fn find_laravel_root(app: &AppHandle) -> PathBuf {
    let app_dir = app.path().app_data_dir().unwrap_or_default();

    // 1. Always try to extract/overwrite from bundled tarball resource
    //    tar xf safely overwrites existing files, ensuring new routes/code take effect
    if let Ok(resource_dir) = app.path().resource_dir() {
        let tarball = resource_dir.join("backend-bundle.tar");
        if tarball.exists() {
            eprintln!("[ClassicPOS] Extracting backend tarball to {:?}", app_dir);
            std::fs::create_dir_all(&app_dir).ok();

            let output = std::process::Command::new("tar")
                .args(["xf", tarball.to_str().unwrap_or(""), "-C", app_dir.to_str().unwrap_or("")])
                .output();

            match output {
                Ok(o) if o.status.success() => {
                    eprintln!("[ClassicPOS] Backend extracted successfully");
                    if app_dir.join("artisan").exists() {
                        return app_dir;
                    }
                }
                Ok(o) => {
                    let stderr = String::from_utf8_lossy(&o.stderr);
                    eprintln!("[ClassicPOS] tar failed: {}", stderr);
                }
                Err(e) => eprintln!("[ClassicPOS] tar command failed: {}", e),
            }
        }
    }

    // 2. Fallback: check if already extracted (tarball missing but backend exists)
    if app_dir.join("artisan").exists() && app_dir.join("vendor").join("autoload.php").exists() {
        eprintln!("[ClassicPOS] Using existing extracted backend at {:?}", app_dir);
        return app_dir;
    }

    // 3. Check exe directory
    if let Ok(exe_path) = std::env::current_exe() {
        if let Some(exe_dir) = exe_path.parent() {
            if exe_dir.join("artisan").exists() {
                eprintln!("[ClassicPOS] Found Laravel root at exe dir");
                return exe_dir.to_path_buf();
            }
            let tarball = exe_dir.join("backend-bundle.tar");
            if tarball.exists() {
                eprintln!("[ClassicPOS] Extracting tarball from exe dir...");
                std::fs::create_dir_all(&app_dir).ok();
                let _ = std::process::Command::new("tar")
                    .args(["xf", tarball.to_str().unwrap_or(""), "-C", app_dir.to_str().unwrap_or("")])
                    .output();
                if app_dir.join("artisan").exists() {
                    return app_dir;
                }
            }
        }
    }

    // 4. Dev mode
    let cwd = std::env::current_dir().unwrap_or_default();
    let candidates = [
        cwd.join("backend"),
        cwd.join("desktop").join("backend"),
        cwd.join("desktop").join("src-tauri").join("backend"),
        cwd.parent().unwrap_or(&cwd).join("backend"),
    ];
    for candidate in &candidates {
        if candidate.join("artisan").exists() {
            eprintln!("[ClassicPOS] Found Laravel root at {:?}", candidate);
            return candidate.clone();
        }
    }

    eprintln!("[ClassicPOS] WARNING: Laravel root not found, using cwd fallback");
    cwd
}

// ─── API Proxy ──────────────────────────────────────────────────────────────────

#[derive(Debug, Clone, serde::Serialize)]
pub struct ApiResponse {
    pub status: u16,
    pub body: String,
    pub content_type: String,
}

#[tauri::command]
pub async fn api_request(
    app: AppHandle,
    method: String,
    path: String,
    body: Option<String>,
) -> Result<ApiResponse, String> {
    let port = app.state::<PhpPort>().0;
    let url = format!("http://127.0.0.1:{}{}", port, path);

    let client = reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(15))
        .build()
        .map_err(|e| format!("Failed to create HTTP client: {}", e))?;

    eprintln!("[API Proxy] Sending {} {} (body present: {})", method, url, body.is_some());

    let request = match method.to_uppercase().as_str() {
        "GET" => client.get(&url),
        "POST" => {
            let mut req = client.post(&url);
            if let Some(ref b) = body {
                req = req
                    .header("Content-Type", "application/json")
                    .body(b.clone());
            }
            req
        }
        _ => return Err(format!("Unsupported HTTP method: {}", method)),
    };

    let response = request.send().await.map_err(|e| {
        let msg = format!("API proxy request failed: {} (url: {})", e, url);
        eprintln!("[API Proxy] ERROR: {}", msg);
        msg
    })?;

    let status = response.status().as_u16();
    let content_type = response
        .headers()
        .get("content-type")
        .and_then(|v| v.to_str().ok())
        .unwrap_or("application/octet-stream")
        .to_string();
    let body = response.text().await.map_err(|e| e.to_string())?;

    eprintln!("[API Proxy] Response {} ({}): {} bytes", status, content_type, body.len());
    if status >= 400 {
        eprintln!("[API Proxy] Error body (first 500 chars): {}", &body[..body.len().min(500)]);
    }

    Ok(ApiResponse {
        status,
        body,
        content_type,
    })
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

/// Hide console window on Windows without affecting other platforms
#[cfg(windows)]
fn hide_console_window(cmd: &mut std::process::Command) {
    use std::os::windows::process::CommandExt;
    cmd.creation_flags(0x08000000); // CREATE_NO_WINDOW
}

#[cfg(not(windows))]
fn hide_console_window(_cmd: &mut std::process::Command) {}

/// Strip verbatim path prefix (\\?\) on Windows — cmd.exe cannot handle it
fn normalize_path_for_env(path: &str) -> String {
    if cfg!(windows) && path.starts_with("\\\\?\\") {
        path[4..].to_string()
    } else {
        path.to_string()
    }
}
