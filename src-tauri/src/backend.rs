//! Start / stop the local Laravel FoodPOS backend (`php artisan serve`).

use std::fs;
use std::net::TcpStream;
use std::path::{Path, PathBuf};
use std::process::{Child, Command, Stdio};
use std::time::Duration;

use serde::Serialize;
use tauri::{AppHandle, Manager};

use crate::error::AppError;

pub const DEFAULT_HOST: &str = "127.0.0.1";
pub const DEFAULT_PORT: u16 = 8000;

#[derive(Debug, Clone, Serialize)]
pub struct BackendInfo {
    pub url: String,
    pub already_running: bool,
}

pub struct BackendHandle {
    child: Option<Child>,
    #[allow(dead_code)]
    pub url: String,
}

impl BackendHandle {
    pub fn idle(url: String) -> Self {
        Self { child: None, url }
    }

    pub fn stop(&mut self) {
        if let Some(mut child) = self.child.take() {
            let _ = child.kill();
            let _ = child.wait();
        }
    }
}

impl Drop for BackendHandle {
    fn drop(&mut self) {
        self.stop();
    }
}

pub fn default_url() -> String {
    format!("http://{DEFAULT_HOST}:{DEFAULT_PORT}")
}

pub fn is_listening(host: &str, port: u16) -> bool {
    let addr = format!("{host}:{port}");
    match addr.parse() {
        Ok(socket) => TcpStream::connect_timeout(&socket, Duration::from_millis(400)).is_ok(),
        Err(_) => false,
    }
}

fn has_artisan(dir: &Path) -> bool {
    dir.join("artisan").is_file()
}

/// Prefer a writable runtime copy under app data; seed it from the installer resources.
pub fn resolve_backend_dir(app: &AppHandle) -> Result<PathBuf, AppError> {
    let app_data = app
        .path()
        .app_data_dir()
        .map_err(|e| AppError::Other(format!("app data dir: {e}")))?;
    let runtime = app_data.join("foodpos-backend");

    if has_artisan(&runtime) {
        return Ok(runtime);
    }

    let mut sources: Vec<PathBuf> = Vec::new();

    if let Ok(resource_dir) = app.path().resource_dir() {
        sources.push(resource_dir.join("foodpos-backend"));
        sources.push(resource_dir.join("resources").join("foodpos-backend"));
    }

    if let Ok(cwd) = std::env::current_dir() {
        sources.push(cwd.join("foodpos-backend"));
        sources.push(cwd.join("../foodpos-backend"));
    }

    if let Ok(exe) = std::env::current_exe() {
        if let Some(dir) = exe.parent() {
            sources.push(dir.join("foodpos-backend"));
            sources.push(dir.join("../foodpos-backend"));
            sources.push(dir.join("../../foodpos-backend"));
            // Dev: repo root from src-tauri/target/.../debug|release
            sources.push(dir.join("../../../foodpos-backend"));
            sources.push(dir.join("../../../../foodpos-backend"));
        }
    }

    // Workspace root when developing from the repo
    sources.push(PathBuf::from("foodpos-backend"));
    sources.push(PathBuf::from("../foodpos-backend"));

    let mut tried = Vec::new();
    for src in sources {
        tried.push(src.display().to_string());
        if !has_artisan(&src) {
            continue;
        }
        fs::create_dir_all(&app_data)?;
        if runtime.exists() {
            let _ = fs::remove_dir_all(&runtime);
        }
        copy_dir_recursive(&src, &runtime)?;
        ensure_runtime_layout(&runtime)?;
        return Ok(runtime);
    }

    Err(AppError::Other(format!(
        "foodpos-backend not found (expected artisan in installer resources). Tried:\n  - {}",
        tried.join("\n  - ")
    )))
}

fn ensure_runtime_layout(backend_dir: &Path) -> Result<(), AppError> {
    for sub in [
        "storage/logs",
        "storage/framework/cache",
        "storage/framework/sessions",
        "storage/framework/views",
        "bootstrap/cache",
        "database",
    ] {
        fs::create_dir_all(backend_dir.join(sub))?;
    }
    ensure_sqlite(backend_dir)?;
    Ok(())
}

fn copy_dir_recursive(from: &Path, to: &Path) -> Result<(), AppError> {
    fs::create_dir_all(to)?;
    for entry in fs::read_dir(from)? {
        let entry = entry?;
        let src = entry.path();
        let dest = to.join(entry.file_name());
        if entry.file_type()?.is_dir() {
            copy_dir_recursive(&src, &dest)?;
        } else {
            if let Some(parent) = dest.parent() {
                fs::create_dir_all(parent)?;
            }
            fs::copy(&src, &dest)?;
        }
    }
    Ok(())
}

fn php_bin() -> String {
    std::env::var("FOODPOS_PHP").unwrap_or_else(|_| "php".into())
}

/// Ensure Laravel is serving on 127.0.0.1:8000. Spawns `php artisan serve` if needed.
pub fn ensure_running(
    app: &AppHandle,
    existing: &mut Option<BackendHandle>,
) -> Result<BackendInfo, AppError> {
    let url = default_url();

    if is_listening(DEFAULT_HOST, DEFAULT_PORT) {
        if existing.is_none() {
            *existing = Some(BackendHandle::idle(url.clone()));
        }
        return Ok(BackendInfo {
            url,
            already_running: true,
        });
    }

    if let Some(handle) = existing.as_mut() {
        handle.stop();
    }

    let backend_dir = resolve_backend_dir(app)?;
    ensure_runtime_layout(&backend_dir)?;

    let mut cmd = Command::new(php_bin());
    cmd.arg("artisan")
        .arg("serve")
        .arg(format!("--host={DEFAULT_HOST}"))
        .arg(format!("--port={DEFAULT_PORT}"))
        .current_dir(&backend_dir)
        .stdin(Stdio::null())
        .stdout(Stdio::null())
        .stderr(Stdio::null());

    let child = cmd.spawn().map_err(|e| {
        AppError::Other(format!(
            "failed to start PHP ({php}): {e}. Install PHP 8.2+ and ensure it is on PATH (or set FOODPOS_PHP).",
            php = php_bin()
        ))
    })?;

    *existing = Some(BackendHandle {
        child: Some(child),
        url: url.clone(),
    });

    for _ in 0..40 {
        if is_listening(DEFAULT_HOST, DEFAULT_PORT) {
            return Ok(BackendInfo {
                url,
                already_running: false,
            });
        }
        std::thread::sleep(Duration::from_millis(250));
    }

    Err(AppError::Other(
        "Laravel backend started but did not become ready on :8000. Check PHP and foodpos-backend/.env."
            .into(),
    ))
}

fn ensure_sqlite(backend_dir: &Path) -> Result<(), AppError> {
    let sqlite = backend_dir.join("database/database.sqlite");
    if !sqlite.exists() {
        if let Some(parent) = sqlite.parent() {
            fs::create_dir_all(parent)?;
        }
        fs::File::create(&sqlite)?;
    }
    Ok(())
}
