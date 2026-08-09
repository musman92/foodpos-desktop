use thiserror::Error;

pub type Result<T> = std::result::Result<T, LicensingError>;

#[derive(Debug, Error)]
pub enum LicensingError {
    #[error("failed to read machine fingerprint: {0}")]
    Fingerprint(String),

    #[error("invalid license token format")]
    InvalidKeyFormat,

    #[error("license is bound to a different machine")]
    MachineMismatch,

    #[error("license signature is invalid")]
    InvalidSignature,

    #[error("license file is missing or unreadable")]
    LicenseMissing,

    #[error("license file is corrupt: {0}")]
    CorruptLicense(String),

    #[error("I/O error: {0}")]
    Io(#[from] std::io::Error),

    #[error("JSON error: {0}")]
    Json(#[from] serde_json::Error),

    #[error("{0}")]
    Other(String),
}
