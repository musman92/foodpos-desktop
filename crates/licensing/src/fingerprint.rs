//! Hardware machine fingerprinting via the `mid` crate.
//!
//! `mid` hashes stable platform identifiers (hardware UUID / serial / etc.)
//! into a SHA-256 hex string suitable for license binding.

use crate::error::{LicensingError, Result};

/// App-specific salt mixed into the machine ID hash so fingerprints are not
/// interchangeable with other products using `mid`.
const FINGERPRINT_SALT: &str = "tauri-inventory-pos-poc-v1";

/// Returns a stable hex machine fingerprint for the current host.
pub fn machine_id() -> Result<String> {
    mid::get(FINGERPRINT_SALT).map_err(|e| LicensingError::Fingerprint(e.to_string()))
}
