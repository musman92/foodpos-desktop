//! Local activated license — stores the vendor token and re-verifies on launch.

use std::fs;
use std::path::{Path, PathBuf};

use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};

use crate::error::{LicensingError, Result};
use crate::fingerprint;
use crate::token::{self, LicenseClaims};

pub const LICENSE_FILENAME: &str = "license.json";

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct LicenseFile {
    /// Original vendor-signed token (`FPOS1.…`).
    pub token: String,
    pub activated_at: DateTime<Utc>,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
#[serde(tag = "status", rename_all = "snake_case")]
pub enum LicenseStatus {
    Valid {
        machine_id: String,
        license_id: String,
        seats: u32,
        customer: Option<String>,
        activated_at: DateTime<Utc>,
    },
    Missing,
    Invalid {
        reason: String,
    },
}

pub fn license_path(app_data_dir: impl AsRef<Path>) -> PathBuf {
    app_data_dir.as_ref().join(LICENSE_FILENAME)
}

/// Activate a vendor-signed token on this machine.
///
/// Same token on the same machine (reinstall) is allowed.
/// Same token on a different machine is rejected.
pub fn activate(app_data_dir: impl AsRef<Path>, token: &str) -> Result<LicenseFile> {
    let app_data_dir = app_data_dir.as_ref();
    fs::create_dir_all(app_data_dir)?;

    let claims = verify_token_for_this_machine(token)?;

    let file = LicenseFile {
        token: token.trim().to_string(),
        activated_at: Utc::now(),
    };

    let path = license_path(app_data_dir);
    let raw = serde_json::to_string_pretty(&file)?;
    fs::write(path, raw)?;

    // Touch claims so unused-var isn't an issue if we expand later.
    let _ = claims;
    Ok(file)
}

/// Read and verify the stored token against the current machine.
pub fn read_and_verify(app_data_dir: impl AsRef<Path>) -> LicenseStatus {
    match verify_inner(app_data_dir.as_ref()) {
        Ok((claims, activated_at)) => LicenseStatus::Valid {
            machine_id: claims.machine_id,
            license_id: claims.license_id,
            seats: claims.seats,
            customer: claims.customer,
            activated_at,
        },
        Err(LicensingError::LicenseMissing) => LicenseStatus::Missing,
        Err(e) => LicenseStatus::Invalid {
            reason: e.to_string(),
        },
    }
}

fn verify_token_for_this_machine(token: &str) -> Result<LicenseClaims> {
    let claims = token::decode_and_verify_token(token)?;
    let current = fingerprint::machine_id()?;
    if claims.machine_id != current {
        return Err(LicensingError::MachineMismatch);
    }
    Ok(claims)
}

fn verify_inner(app_data_dir: &Path) -> Result<(LicenseClaims, DateTime<Utc>)> {
    let path = license_path(app_data_dir);
    if !path.exists() {
        return Err(LicensingError::LicenseMissing);
    }

    let raw = fs::read_to_string(&path)?;
    let file: LicenseFile = serde_json::from_str(&raw)
        .map_err(|e| LicensingError::CorruptLicense(e.to_string()))?;

    let claims = verify_token_for_this_machine(&file.token)?;
    Ok((claims, file.activated_at))
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::token::issue;
    use std::time::{SystemTime, UNIX_EPOCH};

    fn temp_dir() -> PathBuf {
        let nanos = SystemTime::now()
            .duration_since(UNIX_EPOCH)
            .unwrap()
            .as_nanos();
        std::env::temp_dir().join(format!("licensing-act-{nanos}"))
    }

    #[test]
    fn activate_rejects_wrong_machine() {
        let dir = temp_dir();
        fs::create_dir_all(&dir).unwrap();
        let issued = issue("some-other-machine", 1, None, None).unwrap();
        let err = activate(&dir, &issued.token).unwrap_err();
        assert!(matches!(err, LicensingError::MachineMismatch));
        let _ = fs::remove_dir_all(&dir);
    }

    #[test]
    fn activate_and_verify_this_machine() {
        let dir = temp_dir();
        fs::create_dir_all(&dir).unwrap();
        let mid = fingerprint::machine_id().unwrap();
        let issued = issue(&mid, 3, Some("Cafe".into()), None).unwrap();

        activate(&dir, &issued.token).unwrap();
        match read_and_verify(&dir) {
            LicenseStatus::Valid {
                seats, machine_id, ..
            } => {
                assert_eq!(seats, 3);
                assert_eq!(machine_id, mid);
            }
            other => panic!("expected valid: {other:?}"),
        }

        activate(&dir, &issued.token).unwrap();
        let _ = fs::remove_dir_all(&dir);
    }
}
