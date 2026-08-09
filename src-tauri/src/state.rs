use std::path::PathBuf;
use std::sync::Mutex;

use rusqlite::Connection;

use crate::auth::SessionUser;
use crate::backend::BackendHandle;

/// Runtime config for offline FoodPOS (server now; counter later).
#[derive(Debug, Clone, serde::Serialize, serde::Deserialize)]
pub struct AppConfig {
    /// `server` = this PC hosts DB/UI; `counter` = connect to LAN server (future).
    pub mode: String,
    /// Used when mode == counter.
    pub server_url: Option<String>,
    pub station_name: Option<String>,
    pub floor_id: Option<i64>,
}

impl Default for AppConfig {
    fn default() -> Self {
        Self {
            mode: "server".into(),
            server_url: None,
            station_name: Some("Counter 1".into()),
            floor_id: None,
        }
    }
}

pub struct AppState {
    pub db: Mutex<Connection>,
    pub session: Mutex<Option<SessionUser>>,
    pub app_data_dir: PathBuf,
    pub config: Mutex<AppConfig>,
    pub backend: Mutex<Option<BackendHandle>>,
}
