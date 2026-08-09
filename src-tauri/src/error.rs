use thiserror::Error;

pub type Result<T> = std::result::Result<T, AppError>;

#[derive(Debug, Error)]
pub enum AppError {
    #[error("{0}")]
    Auth(String),

    #[error("not signed in")]
    NotSignedIn,

    #[error("license not activated")]
    LicenseRequired,

    #[error(transparent)]
    Licensing(#[from] licensing::LicensingError),

    #[error(transparent)]
    Db(#[from] rusqlite::Error),

    #[error(transparent)]
    Io(#[from] std::io::Error),

    #[error("{0}")]
    Other(String),
}

impl From<AppError> for String {
    fn from(value: AppError) -> Self {
        value.to_string()
    }
}
