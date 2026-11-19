mod commands;

use tauri::Manager;

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    env_logger::init();

    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_store::Builder::new().build())
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_notification::init())
        .plugin(tauri_plugin_process::init())
        .setup(|app| {
            #[cfg(desktop)]
            {
                app.handle().plugin(tauri_plugin_single_instance::init(|app, _args, _cwd| {
                    if let Some(window) = app.get_webview_window("main") {
                        let _ = window.set_focus();
                    }
                }))?;
            }

            // Allocate port and managed state
            let port = find_available_port();
            app.manage(commands::PhpPort(port));
            app.manage(commands::PhpProcess(std::sync::Mutex::new(None)));
            app.manage(commands::AppVersion(env!("CARGO_PKG_VERSION").to_string()));
            app.manage(commands::LaravelRoot(std::sync::Mutex::new(None)));
            app.manage(commands::StartupPhase(std::sync::Mutex::new(commands::StartupState::Idle)));

            // Single background task: extract backend → start PHP → emit progress
            let handle = app.handle().clone();
            tauri::async_runtime::spawn(async move {
                commands::start_server_background(handle, port).await;
            });

            // Clean up PHP on window close
            let handle_clone = app.handle().clone();
            if let Some(window) = app.get_webview_window("main") {
                let h = handle_clone.clone();
                window.on_window_event(move |event| {
                    if let tauri::WindowEvent::Destroyed = event {
                        eprintln!("[ClassicPOS] Window closed, stopping PHP...");
                        if let Some(process) = h.try_state::<commands::PhpProcess>() {
                            if let Ok(mut guard) = process.0.lock() {
                                if let Some(mut child) = guard.take() {
                                    let _ = child.kill();
                                }
                            }
                        }
                    }
                });
            }

            Ok(())
        })
        .invoke_handler(tauri::generate_handler![
            // Startup
            commands::get_startup_state,
            // PHP lifecycle
            commands::stop_php_server,
            commands::get_php_port,
            commands::get_php_status,
            // App info
            commands::get_app_version,
            commands::generate_env,
            // Printing
            commands::print_receipt,
            commands::open_drawer,
            commands::list_printers,
            commands::print_to_port,
            commands::open_drawer_to_port,
            // API Proxy
            commands::api_request,
            // Tunnel
            commands::tunnel_status,
            commands::tunnel_generate_config,
            commands::tunnel_install_service,
            commands::tunnel_start,
            commands::tunnel_stop,
            commands::tunnel_uninstall,
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}

fn find_available_port() -> u16 {
    std::net::TcpListener::bind("127.0.0.1:0")
        .unwrap()
        .local_addr()
        .unwrap()
        .port()
}
