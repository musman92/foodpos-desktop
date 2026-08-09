//! Vendor-signed license tokens bound to a Machine ID at issuance time.
//!
//! Token format: `FPOS1.<claims_b64>.<signature_b64>`
//!
//! No customer-side JSON key pool. The vendor CLI signs claims for a specific
//! machine; the app verifies with the embedded public key.

use chrono::{DateTime, Utc};
use rand::RngCore;
use serde::{Deserialize, Serialize};

use crate::crypto;
use crate::error::{LicensingError, Result};

pub const TOKEN_PREFIX: &str = "FPOS1";

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq, Eq)]
pub struct LicenseClaims {
    /// Schema version.
    pub v: u32,
    /// Unique id for vendor issuance log.
    pub license_id: String,
    /// Hardware fingerprint this license is valid for.
    pub machine_id: String,
    /// Max counter seats (floor stations) allowed to connect to this server.
    pub seats: u32,
    pub issued_at: DateTime<Utc>,
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub expires_at: Option<DateTime<Utc>>,
    #[serde(default, skip_serializing_if = "Option::is_none")]
    pub customer: Option<String>,
}

#[derive(Debug, Clone)]
pub struct IssuedLicense {
    pub claims: LicenseClaims,
    /// Pasteable token for the customer.
    pub token: String,
}

fn new_license_id() -> String {
    let mut bytes = [0u8; 8];
    rand::thread_rng().fill_bytes(&mut bytes);
    bytes.iter().map(|b| format!("{b:02x}")).collect()
}

/// Vendor: create a signed token for a customer's Machine ID.
pub fn issue(
    machine_id: &str,
    seats: u32,
    customer: Option<String>,
    expires_at: Option<DateTime<Utc>>,
) -> Result<IssuedLicense> {
    let machine_id = machine_id.trim();
    if machine_id.is_empty() {
        return Err(LicensingError::Other("machine_id is required".into()));
    }
    if seats == 0 {
        return Err(LicensingError::Other("seats must be >= 1".into()));
    }

    let claims = LicenseClaims {
        v: 1,
        license_id: new_license_id(),
        machine_id: machine_id.to_string(),
        seats,
        issued_at: Utc::now(),
        expires_at,
        customer,
    };

    let token = encode_token(&claims)?;
    Ok(IssuedLicense { claims, token })
}

pub fn encode_token(claims: &LicenseClaims) -> Result<String> {
    let payload = serde_json::to_vec(claims)?;
    let payload_b64 = crypto::encode_bytes(&payload);
    let signature = crypto::sign(&payload);
    Ok(format!("{TOKEN_PREFIX}.{payload_b64}.{signature}"))
}

/// Parse and cryptographically verify a pasteable token (does not check machine).
pub fn decode_and_verify_token(token: &str) -> Result<LicenseClaims> {
    let token = token.trim();
    let parts: Vec<&str> = token.split('.').collect();
    if parts.len() != 3 || parts[0] != TOKEN_PREFIX {
        return Err(LicensingError::InvalidKeyFormat);
    }

    let payload = crypto::decode_bytes(parts[1])?;
    crypto::verify(&payload, parts[2])?;

    let claims: LicenseClaims = serde_json::from_slice(&payload)
        .map_err(|e| LicensingError::CorruptLicense(e.to_string()))?;

    if claims.v != 1 {
        return Err(LicensingError::CorruptLicense(format!(
            "unsupported license version {}",
            claims.v
        )));
    }
    if let Some(exp) = claims.expires_at {
        if Utc::now() > exp {
            return Err(LicensingError::Other("license has expired".into()));
        }
    }

    Ok(claims)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn issue_roundtrip() {
        let issued = issue("abc123machine", 2, Some("Test Cafe".into()), None).unwrap();
        assert!(issued.token.starts_with("FPOS1."));
        let claims = decode_and_verify_token(&issued.token).unwrap();
        assert_eq!(claims.machine_id, "abc123machine");
        assert_eq!(claims.seats, 2);
        assert_eq!(claims.customer.as_deref(), Some("Test Cafe"));
    }

    #[test]
    fn rejects_tampered_token() {
        let issued = issue("abc123machine", 1, None, None).unwrap();
        let mut bad = issued.token;
        bad.push('x');
        assert!(decode_and_verify_token(&bad).is_err());
    }
}
