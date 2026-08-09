mod auth;
mod backend;
mod commands;
mod db;
mod error;
mod state;

use std::fs;
use std::sync::Mutex;

use tauri::{Manager, RunEvent};

use state::{AppConfig, AppState};

fn load_or_create_config(app_data_dir: &std::path::Path) -> AppConfig {
    let path = app_data_dir.join("app_config.json");
    if let Ok(raw) = fs::read_to_string(&path) {
        if let Ok(cfg) = serde_json::from_str(&raw) {
            return cfg;
        }
    }
    let cfg = AppConfig::default();
    if let Ok(raw) = serde_json::to_string_pretty(&cfg) {
        let _ = fs::write(path, raw);
    }
    cfg
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .setup(|app| {
            let app_data_dir = app
                .path()
                .app_data_dir()
                .expect("failed to resolve app data dir");
            fs::create_dir_all(&app_data_dir)?;

            let config = load_or_create_config(&app_data_dir);

            let db_path = app_data_dir.join("app.db");
            let conn = db::open_db(&db_path).expect("failed to open database");
            db::seed_default_admin(&conn).expect("failed to seed admin user");

            app.manage(AppState {
                db: Mutex::new(conn),
                session: Mutex::new(None),
                app_data_dir,
                config: Mutex::new(config),
                backend: Mutex::new(None),
            });

            Ok(())
        })
        .invoke_handler(tauri::generate_handler![
            commands::get_bootstrap,
            commands::activate_license,
            commands::launch_foodpos,
            commands::get_license_status,
            commands::sign_in,
            commands::sign_out,
            commands::get_session,
            commands::list_users,
            commands::add_user,
        ])
        .build(tauri::generate_context!())
        .expect("error while building tauri application")
        .run(|app_handle, event| {
            if let RunEvent::Exit = event {
                if let Some(state) = app_handle.try_state::<AppState>() {
                    if let Ok(mut backend) = state.backend.lock() {
                        if let Some(handle) = backend.as_mut() {
                            handle.stop();
                        }
                    }
                }
            }
        });
}
