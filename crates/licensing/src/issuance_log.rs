//! Vendor-only issuance log (never shipped inside the customer app).

use std::fs;
use std::path::{Path, PathBuf};

use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};

use crate::error::Result;
use crate::token::LicenseClaims;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct IssuanceRecord {
    pub license_id: String,
    pub machine_id: String,
    pub seats: u32,
    pub customer: Option<String>,
    pub issued_at: DateTime<Utc>,
    pub expires_at: Option<DateTime<Utc>>,
    /// The full token (kept so you can re-send to the same customer).
    pub token: String,
}

#[derive(Debug, Clone, Serialize, Deserialize, Default)]
struct LogFile {
    records: Vec<IssuanceRecord>,
}

#[derive(Debug)]
pub struct IssuanceLog {
    path: PathBuf,
    data: LogFile,
}

impl IssuanceLog {
    pub fn open(path: impl AsRef<Path>) -> Result<Self> {
        let path = path.as_ref().to_path_buf();
        let data = if path.exists() {
            let raw = fs::read_to_string(&path)?;
            serde_json::from_str(&raw)?
        } else {
            if let Some(parent) = path.parent() {
                fs::create_dir_all(parent)?;
            }
            LogFile::default()
        };
        Ok(Self { path, data })
    }

    pub fn path(&self) -> &Path {
        &self.path
    }

    pub fn records(&self) -> &[IssuanceRecord] {
        &self.data.records
    }

    pub fn append(&mut self, claims: &LicenseClaims, token: &str) -> Result<()> {
        self.data.records.push(IssuanceRecord {
            license_id: claims.license_id.clone(),
            machine_id: claims.machine_id.clone(),
            seats: claims.seats,
            customer: claims.customer.clone(),
            issued_at: claims.issued_at,
            expires_at: claims.expires_at,
            token: token.to_string(),
        });
        self.save()
    }

    fn save(&self) -> Result<()> {
        if let Some(parent) = self.path.parent() {
            fs::create_dir_all(parent)?;
        }
        let raw = serde_json::to_string_pretty(&self.data)?;
        fs::write(&self.path, raw)?;
        Ok(())
    }
}
