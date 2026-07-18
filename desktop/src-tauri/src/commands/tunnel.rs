use std::path::PathBuf;
use log::{info, warn, error};

// ─── Tunnel Configuration ──────────────────────────────────────────────────────

#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub struct TunnelConfig {
    pub tunnel_id: String,
    pub credentials_file: String,
    pub hostname: String,
    pub local_port: u16,
    pub service_running: bool,
}

#[derive(Debug, Clone, serde::Serialize)]
pub struct TunnelStatus {
    pub installed: bool,
    pub running: bool,
    pub hostname: Option<String>,
    pub tunnel_id: Option<String>,
    pub error: Option<String>,
}

// ─── Config Generation ─────────────────────────────────────────────────────────

/// Generate a cloudflared config.yml for the given tunnel.
pub fn generate_config(
    tunnel_id: &str,
    credentials_path: &str,
    hostname: &str,
    local_port: u16,
) -> Result<String, String> {
    let config = format!(
        r#"tunnel: {tunnel_id}
credentials-file: {credentials}

ingress:
  - hostname: {hostname}
    service: https://localhost:{port}
    originRequest:
      noTLSVerify: true
  - service: http_status:404
"#,
        tunnel_id = tunnel_id,
        credentials = credentials_path,
        hostname = hostname,
        port = local_port,
    );

    Ok(config)
}

/// Save the config to a file.
pub fn save_config(config_dir: &std::path::Path, config: &str) -> Result<PathBuf, String> {
    std::fs::create_dir_all(config_dir).map_err(|e| e.to_string())?;
    let config_path = config_dir.join("config.yml");
    std::fs::write(&config_path, config).map_err(|e| e.to_string())?;
    info!("Cloudflared config saved to {:?}", config_path);
    Ok(config_path)
}

// ─── Service Management ────────────────────────────────────────────────────────

/// Find the cloudflared binary.
pub fn find_cloudflared() -> Result<String, String> {
    // Check common locations
    let candidates = if cfg!(target_os = "windows") {
        vec![
            "C:\\Program Files\\cloudflared\\cloudflared.exe".to_string(),
            "C:\\Program Files (x86)\\cloudflared\\cloudflared.exe".to_string(),
            format!("{}\\cloudflared.exe", std::env::var("LOCALAPPDATA").unwrap_or_default()),
            "cloudflared.exe".to_string(), // PATH lookup
        ]
    } else if cfg!(target_os = "macos") {
        vec![
            "/usr/local/bin/cloudflared".to_string(),
            "/opt/homebrew/bin/cloudflared".to_string(),
            "cloudflared".to_string(),
        ]
    } else {
        vec![
            "/usr/local/bin/cloudflared".to_string(),
            "/usr/bin/cloudflared".to_string(),
            "cloudflared".to_string(),
        ]
    };

    for candidate in &candidates {
        if std::path::Path::new(candidate).exists() {
            return Ok(candidate.clone());
        }
    }

    // Try to find via which/command
    let which_cmd = if cfg!(target_os = "windows") { "where" } else { "which" };
    if let Ok(output) = std::process::Command::new(which_cmd).arg("cloudflared").output() {
        if output.status.success() {
            let path = String::from_utf8_lossy(&output.stdout).trim().to_string();
            if !path.is_empty() {
                return Ok(path);
            }
        }
    }

    Err("cloudflared not found. Download from https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/".to_string())
}

/// Install cloudflared as a Windows service.
pub fn install_service(config_path: &str) -> Result<(), String> {
    let cloudflared = find_cloudflared()?;

    let output = std::process::Command::new(&cloudflared)
        .args(["service", "install", "--config", config_path])
        .output()
        .map_err(|e| format!("Failed to run cloudflared: {}", e))?;

    if output.status.success() {
        info!("Cloudflared service installed");
        Ok(())
    } else {
        let stderr = String::from_utf8_lossy(&output.stderr);
        Err(format!("Service install failed: {}", stderr))
    }
}

/// Uninstall cloudflared service.
pub fn uninstall_service() -> Result<(), String> {
    let cloudflared = find_cloudflared()?;

    let output = std::process::Command::new(&cloudflared)
        .args(["service", "uninstall"])
        .output()
        .map_err(|e| format!("Failed to run cloudflared: {}", e))?;

    if output.status.success() {
        info!("Cloudflared service uninstalled");
        Ok(())
    } else {
        let stderr = String::from_utf8_lossy(&output.stderr);
        Err(format!("Service uninstall failed: {}", stderr))
    }
}

/// Start the cloudflared tunnel service.
pub fn start_service() -> Result<(), String> {
    #[cfg(target_os = "windows")]
    {
        let output = std::process::Command::new("sc")
            .args(["start", "cloudflared"])
            .output()
            .map_err(|e| e.to_string())?;

        if output.status.success() {
            info!("Cloudflared service started");
            Ok(())
        } else {
            let stderr = String::from_utf8_lossy(&output.stderr);
            Err(format!("Failed to start service: {}", stderr))
        }
    }

    #[cfg(not(target_os = "windows"))]
    {
        let output = std::process::Command::new("sudo")
            .args(["systemctl", "start", "cloudflared"])
            .output()
            .map_err(|e| e.to_string())?;

        if output.status.success() {
            info!("Cloudflared service started");
            Ok(())
        } else {
            let stderr = String::from_utf8_lossy(&output.stderr);
            Err(format!("Failed to start service: {}", stderr))
        }
    }
}

/// Stop the cloudflared tunnel service.
pub fn stop_service() -> Result<(), String> {
    #[cfg(target_os = "windows")]
    {
        let output = std::process::Command::new("sc")
            .args(["stop", "cloudflared"])
            .output()
            .map_err(|e| e.to_string())?;

        if output.status.success() {
            info!("Cloudflared service stopped");
            Ok(())
        } else {
            let stderr = String::from_utf8_lossy(&output.stderr);
            Err(format!("Failed to stop service: {}", stderr))
        }
    }

    #[cfg(not(target_os = "windows"))]
    {
        let output = std::process::Command::new("sudo")
            .args(["systemctl", "stop", "cloudflared"])
            .output()
            .map_err(|e| e.to_string())?;

        if output.status.success() {
            info!("Cloudflared service stopped");
            Ok(())
        } else {
            let stderr = String::from_utf8_lossy(&output.stderr);
            Err(format!("Failed to stop service: {}", stderr))
        }
    }
}

/// Check the status of the cloudflared service.
pub fn check_status() -> TunnelStatus {
    // Check if cloudflared is installed
    let cloudflared = match find_cloudflared() {
        Ok(path) => path,
        Err(_) => {
            return TunnelStatus {
                installed: false,
                running: false,
                hostname: None,
                tunnel_id: None,
                error: Some("cloudflared not found".to_string()),
            };
        }
    };

    // Check if service is running
    let running = check_service_running();

    // Try to read config for hostname/tunnel_id
    let config_dir = get_config_dir();
    let config_path = config_dir.join("config.yml");
    let (hostname, tunnel_id) = if config_path.exists() {
        let content = std::fs::read_to_string(&config_path).unwrap_or_default();
        let hostname = content.lines()
            .find(|l| l.starts_with("  - hostname:"))
            .and_then(|l| l.split(':').nth(1))
            .map(|s| s.trim().to_string());
        let tunnel_id = content.lines()
            .find(|l| l.starts_with("tunnel:"))
            .and_then(|l| l.split(':').nth(1))
            .map(|s| s.trim().to_string());
        (hostname, tunnel_id)
    } else {
        (None, None)
    };

    TunnelStatus {
        installed: true,
        running,
        hostname,
        tunnel_id,
        error: None,
    }
}

fn check_service_running() -> bool {
    #[cfg(target_os = "windows")]
    {
        std::process::Command::new("sc")
            .args(["query", "cloudflared"])
            .output()
            .map(|o| {
                let out = String::from_utf8_lossy(&o.stdout);
                out.contains("RUNNING")
            })
            .unwrap_or(false)
    }

    #[cfg(not(target_os = "windows"))]
    {
        std::process::Command::new("systemctl")
            .args(["is-active", "cloudflared"])
            .output()
            .map(|o| o.status.success())
            .unwrap_or(false)
    }
}

fn get_config_dir() -> PathBuf {
    if cfg!(target_os = "windows") {
        PathBuf::from(std::env::var("PROGRAMDATA").unwrap_or_default())
            .join("classicpos").join("cloudflared")
    } else {
        PathBuf::from("/etc/classicpos/cloudflared")
    }
}
