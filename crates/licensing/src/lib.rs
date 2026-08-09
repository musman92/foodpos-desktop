//! Machine-bound, offline-first licensing.
//!
//! # Commercial flow
//! 1. Customer installs → app shows [`machine_id`]
//! 2. Customer sends Machine ID to you
//! 3. You run `license-gen issue --machine-id … --seats N`
//! 4. Customer pastes the `FPOS1.…` token → [`activate`]
//! 5. App verifies signature + machine match on every launch
//!
//! Same token on another PC fails. Same token on the same PC (reinstall) works.
//! There is no customer-side JSON key pool.

pub mod crypto;
pub mod error;
pub mod fingerprint;
pub mod issuance_log;
pub mod license;
pub mod token;

pub use error::{LicensingError, Result};
pub use fingerprint::machine_id;
pub use issuance_log::{IssuanceLog, IssuanceRecord};
pub use license::{activate, license_path, read_and_verify, LicenseFile, LicenseStatus};
pub use token::{decode_and_verify_token, issue, IssuedLicense, LicenseClaims};
