//! Ed25519 sign / verify for machine-bound license tokens.
//!
//! **Vendor CLI** uses [`sign`] (private key — never ship to customers).
//! **Desktop app** only needs [`verify`] (public key embedded below).

use base64::{engine::general_purpose::URL_SAFE_NO_PAD as B64, Engine};
use ed25519_dalek::{Signature, Signer, SigningKey, Verifier, VerifyingKey};

use crate::error::{LicensingError, Result};

/// Deterministic PoC seed — replace with a real keypair file for production.
/// Private half must live only on your license-issuing machine.
const POC_SEED: [u8; 32] = [
    0x74, 0x61, 0x75, 0x72, 0x69, 0x2d, 0x70, 0x6f, 0x73, 0x2d, 0x6c, 0x69, 0x63, 0x65, 0x6e, 0x73,
    0x65, 0x2d, 0x70, 0x6f, 0x63, 0x2d, 0x73, 0x65, 0x65, 0x64, 0x2d, 0x76, 0x31, 0x21, 0x21, 0x21,
];

fn signing_key() -> SigningKey {
    SigningKey::from_bytes(&POC_SEED)
}

fn verifying_key() -> VerifyingKey {
    signing_key().verifying_key()
}

/// Sign arbitrary bytes; returns URL-safe base64 (no pad).
pub fn sign(message: &[u8]) -> String {
    let sig = signing_key().sign(message);
    B64.encode(sig.to_bytes())
}

/// Verify a URL-safe base64 Ed25519 signature over `message`.
pub fn verify(message: &[u8], signature_b64: &str) -> Result<()> {
    let bytes = B64
        .decode(signature_b64.trim())
        .map_err(|e| LicensingError::CorruptLicense(format!("bad signature encoding: {e}")))?;
    let sig_bytes: [u8; 64] = bytes
        .try_into()
        .map_err(|_| LicensingError::CorruptLicense("signature length != 64".into()))?;
    let signature = Signature::from_bytes(&sig_bytes);
    verifying_key()
        .verify(message, &signature)
        .map_err(|_| LicensingError::InvalidSignature)
}

pub fn encode_bytes(data: &[u8]) -> String {
    B64.encode(data)
}

pub fn decode_bytes(data: &str) -> Result<Vec<u8>> {
    B64.decode(data.trim())
        .map_err(|e| LicensingError::CorruptLicense(format!("bad encoding: {e}")))
}
