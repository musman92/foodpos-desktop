//! Tauri commands — thin wrappers over auth + licensing.

use licensing::{self, LicenseStatus};
use serde::Serialize;
use tauri::{AppHandle, Manager, State};

use crate::auth::{self, SessionUser};
use crate::backend::{self, BackendInfo};
use crate::db::{self, UserRecord};
use crate::error::AppError;
use crate::state::{AppConfig, AppState};

fn require_license(state: &AppState) -> Result<(), String> {
    match licensing::read_and_verify(&state.app_data_dir) {
        LicenseStatus::Valid { .. } => Ok(()),
        LicenseStatus::Missing => Err(AppError::LicenseRequired.into()),
        LicenseStatus::Invalid { reason } => Err(format!("License invalid: {reason}")),
    }
}

fn require_session(state: &AppState) -> Result<SessionUser, String> {
    require_license(state)?;
    state
        .session
        .lock()
        .map_err(|_| "session lock poisoned".to_string())?
        .clone()
        .ok_or_else(|| AppError::NotSignedIn.into())
}

#[derive(Debug, Serialize)]
pub struct BootstrapInfo {
    pub license: LicenseStatus,
    pub machine_id: String,
    pub session: Option<SessionUser>,
    pub config: AppConfig,
}

#[tauri::command]
pub fn get_bootstrap(state: State<'_, AppState>) -> Result<BootstrapInfo, String> {
    let machine_id = licensing::machine_id().map_err(|e| e.to_string())?;
    let license = licensing::read_and_verify(&state.app_data_dir);
    let config = state
        .config
        .lock()
        .map_err(|_| "config lock poisoned".to_string())?
        .clone();
    let session = state
        .session
        .lock()
        .map_err(|_| "session lock poisoned".to_string())?
        .clone();
    let session = match &license {
        LicenseStatus::Valid { .. } => session,
        _ => {
            *state
                .session
                .lock()
                .map_err(|_| "session lock poisoned".to_string())? = None;
            None
        }
    };
    Ok(BootstrapInfo {
        license,
        machine_id,
        session,
        config,
    })
}

#[tauri::command]
pub fn activate_license(state: State<'_, AppState>, key: String) -> Result<LicenseStatus, String> {
    licensing::activate(&state.app_data_dir, &key).map_err(|e| e.to_string())?;
    Ok(licensing::read_and_verify(&state.app_data_dir))
}

/// Start Laravel (if needed) and navigate the main window to FoodPOS.
#[tauri::command]
pub fn launch_foodpos(app: AppHandle, state: State<'_, AppState>) -> Result<BackendInfo, String> {
    require_license(&state)?;

    let mut backend = state
        .backend
        .lock()
        .map_err(|_| "backend lock poisoned".to_string())?;

    let info = backend::ensure_running(&mut backend).map_err(String::from)?;

    let url = info.url.parse().map_err(|e| format!("bad backend url: {e}"))?;
    if let Some(window) = app.get_webview_window("main") {
        window.navigate(url).map_err(|e| e.to_string())?;
    }

    Ok(info)
}

#[tauri::command]
pub fn get_license_status(state: State<'_, AppState>) -> Result<LicenseStatus, String> {
    Ok(licensing::read_and_verify(&state.app_data_dir))
}

#[tauri::command]
pub fn sign_in(
    state: State<'_, AppState>,
    username: String,
    password: String,
) -> Result<SessionUser, String> {
    require_license(&state)?;
    let conn = state
        .db
        .lock()
        .map_err(|_| "db lock poisoned".to_string())?;
    let user = auth::sign_in(&conn, &username, &password).map_err(String::from)?;
    *state
        .session
        .lock()
        .map_err(|_| "session lock poisoned".to_string())? = Some(user.clone());
    Ok(user)
}

#[tauri::command]
pub fn sign_out(state: State<'_, AppState>) -> Result<(), String> {
    *state
        .session
        .lock()
        .map_err(|_| "session lock poisoned".to_string())? = None;
    Ok(())
}

#[tauri::command]
pub fn get_session(state: State<'_, AppState>) -> Result<Option<SessionUser>, String> {
    if require_license(&state).is_err() {
        return Ok(None);
    }
    Ok(state
        .session
        .lock()
        .map_err(|_| "session lock poisoned".to_string())?
        .clone())
}

#[tauri::command]
pub fn list_users(state: State<'_, AppState>) -> Result<Vec<UserRecord>, String> {
    let _ = require_session(&state)?;
    let conn = state
        .db
        .lock()
        .map_err(|_| "db lock poisoned".to_string())?;
    db::list_users(&conn).map_err(String::from)
}

#[tauri::command]
pub fn add_user(
    state: State<'_, AppState>,
    username: String,
    password: String,
    role: String,
) -> Result<UserRecord, String> {
    let _ = require_session(&state)?;
    let conn = state
        .db
        .lock()
        .map_err(|_| "db lock poisoned".to_string())?;
    db::create_user(&conn, &username, &password, &role).map_err(String::from)
}
