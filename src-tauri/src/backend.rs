//! Start / stop the local Laravel FoodPOS backend (`php artisan serve`).

use std::net::TcpStream;
use std::path::{Path, PathBuf};
use std::process::{Child, Command, Stdio};
use std::time::Duration;

use serde::Serialize;

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
        Ok(socket) => {
            TcpStream::connect_timeout(&socket, Duration::from_millis(400)).is_ok()
        }
        Err(_) => false,
    }
}

pub fn resolve_backend_dir() -> Result<PathBuf, AppError> {
    let mut candidates: Vec<PathBuf> = Vec::new();

    if let Ok(cwd) = std::env::current_dir() {
        candidates.push(cwd.join("foodpos-backend"));
        candidates.push(cwd.join("../foodpos-backend"));
    }
    candidates.push(PathBuf::from("foodpos-backend"));
    candidates.push(PathBuf::from("../foodpos-backend"));

    if let Ok(exe) = std::env::current_exe() {
        if let Some(dir) = exe.parent() {
            candidates.push(dir.join("foodpos-backend"));
            candidates.push(dir.join("../foodpos-backend"));
            candidates.push(dir.join("../../foodpos-backend"));
        }
    }

    for path in candidates {
        let artisan = path.join("artisan");
        if artisan.is_file() {
            return path.canonicalize().map_err(AppError::from);
        }
    }

    Err(AppError::Other(
        "foodpos-backend not found (expected artisan next to the project)".into(),
    ))
}

fn php_bin() -> String {
    std::env::var("FOODPOS_PHP").unwrap_or_else(|_| "php".into())
}

/// Ensure Laravel is serving on 127.0.0.1:8000. Spawns `php artisan serve` if needed.
pub fn ensure_running(existing: &mut Option<BackendHandle>) -> Result<BackendInfo, AppError> {
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

    // Stop a stale handle that died.
    if let Some(handle) = existing.as_mut() {
        handle.stop();
    }

    let backend_dir = resolve_backend_dir()?;
    ensure_sqlite(&backend_dir)?;

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
            "failed to start PHP ({php}): {e}. Is PHP on PATH?",
            php = php_bin()
        ))
    })?;

    *existing = Some(BackendHandle {
        child: Some(child),
        url: url.clone(),
    });

    // Wait until the port accepts connections.
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
        "Laravel backend started but did not become ready on :8000".into(),
    ))
}

fn ensure_sqlite(backend_dir: &Path) -> Result<(), AppError> {
    let sqlite = backend_dir.join("database/database.sqlite");
    if !sqlite.exists() {
        if let Some(parent) = sqlite.parent() {
            fs_create_dir(parent)?;
        }
        std::fs::File::create(&sqlite)?;
    }
    Ok(())
}

fn fs_create_dir(path: &Path) -> Result<(), AppError> {
    std::fs::create_dir_all(path).map_err(AppError::from)
}
