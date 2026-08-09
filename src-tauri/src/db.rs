//! SQLite persistence for users (app data — separate from the licensing crate).

use argon2::{
    password_hash::{PasswordHasher, SaltString},
    Argon2,
};
use rand_core::OsRng;
use rusqlite::{params, Connection};
use serde::Serialize;

use crate::error::{AppError, Result};

#[derive(Debug, Clone, Serialize)]
pub struct UserRecord {
    pub id: i64,
    pub username: String,
    pub role: String,
    pub created_at: String,
}

pub fn open_db(path: &std::path::Path) -> Result<Connection> {
    if let Some(parent) = path.parent() {
        std::fs::create_dir_all(parent)?;
    }
    let conn = Connection::open(path)?;
    conn.execute_batch(
        "
        PRAGMA foreign_keys = ON;
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        ",
    )?;
    Ok(conn)
}

pub fn seed_default_admin(conn: &Connection) -> Result<()> {
    let count: i64 = conn.query_row("SELECT COUNT(*) FROM users", [], |r| r.get(0))?;
    if count == 0 {
        create_user(conn, "admin", "admin123", "admin")?;
    }
    Ok(())
}

pub fn hash_password(password: &str) -> Result<String> {
    let salt = SaltString::generate(&mut OsRng);
    let hash = Argon2::default()
        .hash_password(password.as_bytes(), &salt)
        .map_err(|e| AppError::Other(format!("hash failed: {e}")))?
        .to_string();
    Ok(hash)
}

pub fn create_user(conn: &Connection, username: &str, password: &str, role: &str) -> Result<UserRecord> {
    let username = username.trim();
    if username.is_empty() {
        return Err(AppError::Other("username is required".into()));
    }
    if password.len() < 4 {
        return Err(AppError::Other("password must be at least 4 characters".into()));
    }
    let role = role.trim().to_lowercase();
    if role != "admin" && role != "staff" {
        return Err(AppError::Other("role must be 'admin' or 'staff'".into()));
    }

    let password_hash = hash_password(password)?;
    conn.execute(
        "INSERT INTO users (username, password_hash, role) VALUES (?1, ?2, ?3)",
        params![username, password_hash, role],
    )
    .map_err(|e| match e {
        rusqlite::Error::SqliteFailure(err, _)
            if err.code == rusqlite::ErrorCode::ConstraintViolation =>
        {
            AppError::Other(format!("username '{username}' already exists"))
        }
        other => AppError::Db(other),
    })?;

    let id = conn.last_insert_rowid();
    get_user_by_id(conn, id)
}

pub fn get_user_by_id(conn: &Connection, id: i64) -> Result<UserRecord> {
    conn.query_row(
        "SELECT id, username, role, created_at FROM users WHERE id = ?1",
        params![id],
        |row| {
            Ok(UserRecord {
                id: row.get(0)?,
                username: row.get(1)?,
                role: row.get(2)?,
                created_at: row.get(3)?,
            })
        },
    )
    .map_err(AppError::from)
}

pub fn get_user_by_username(conn: &Connection, username: &str) -> Result<(UserRecord, String)> {
    conn.query_row(
        "SELECT id, username, role, created_at, password_hash FROM users WHERE username = ?1 COLLATE NOCASE",
        params![username.trim()],
        |row| {
            Ok((
                UserRecord {
                    id: row.get(0)?,
                    username: row.get(1)?,
                    role: row.get(2)?,
                    created_at: row.get(3)?,
                },
                row.get::<_, String>(4)?,
            ))
        },
    )
    .map_err(|e| match e {
        rusqlite::Error::QueryReturnedNoRows => AppError::Auth("invalid username or password".into()),
        other => AppError::Db(other),
    })
}

pub fn list_users(conn: &Connection) -> Result<Vec<UserRecord>> {
    let mut stmt =
        conn.prepare("SELECT id, username, role, created_at FROM users ORDER BY username")?;
    let rows = stmt.query_map([], |row| {
        Ok(UserRecord {
            id: row.get(0)?,
            username: row.get(1)?,
            role: row.get(2)?,
            created_at: row.get(3)?,
        })
    })?;
    let mut users = Vec::new();
    for row in rows {
        users.push(row?);
    }
    Ok(users)
}
