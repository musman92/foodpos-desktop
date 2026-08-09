//! Simple in-memory session + password verification.

use argon2::{
    password_hash::{PasswordHash, PasswordVerifier},
    Argon2,
};
use rusqlite::Connection;
use serde::Serialize;

use crate::db::{self, UserRecord};
use crate::error::{AppError, Result};

#[derive(Debug, Clone, Serialize)]
pub struct SessionUser {
    pub id: i64,
    pub username: String,
    pub role: String,
}

impl From<UserRecord> for SessionUser {
    fn from(u: UserRecord) -> Self {
        Self {
            id: u.id,
            username: u.username,
            role: u.role,
        }
    }
}

pub fn verify_password(password: &str, password_hash: &str) -> Result<bool> {
    let parsed = PasswordHash::new(password_hash)
        .map_err(|e| AppError::Other(format!("bad hash: {e}")))?;
    Ok(Argon2::default()
        .verify_password(password.as_bytes(), &parsed)
        .is_ok())
}

pub fn sign_in(conn: &Connection, username: &str, password: &str) -> Result<SessionUser> {
    let (user, hash) = db::get_user_by_username(conn, username)?;
    if !verify_password(password, &hash)? {
        return Err(AppError::Auth("invalid username or password".into()));
    }
    Ok(user.into())
}
