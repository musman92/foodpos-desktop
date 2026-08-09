# FoodPOS Offline — Licensing (vendor)

Customer sends a **Machine ID** → you issue a signed token → they paste it in the app.  
The token only works on that machine. Reinstall on the same PC is fine; another PC needs a new license.

Vendor tools live in this repo. **Do not ship** `keys/` or the private signing key to customers.

## Flow

1. Customer opens the app and copies **Machine ID** from the activation screen.
2. You issue a token with `license-gen` (commands below).
3. You send them the `FPOS1.…` token.
4. They paste it into **License token** → **Activate**.

Issuance history is stored locally at `keys/issuance_log.json` (gitignored, vendor machine only).

## Issue a license

From the project root:

```bash
cd /Users/usman/try/tauri-test

cargo run -p license-gen -- issue \
  --machine-id 'PASTE_MACHINE_ID_HERE' \
  --seats 1 \
  --customer 'My Restaurant'
```

The CLI prints a token starting with `FPOS1.…` — send that full string to the customer.

### Flags

| Flag | Required | Meaning |
|------|----------|---------|
| `--machine-id` | Yes | Hex Machine ID copied from the customer’s app. Binds the license to that PC. |
| `--seats` | No (default `1`) | Max floor counters / seats allowed for this license. |
| `--customer` | No | Optional label (e.g. restaurant name). Stored in the token and issuance log; shown in the app after activation. Does **not** affect validity or Machine ID binding. |

Omit customer if you want:

```bash
cargo run -p license-gen -- issue \
  --machine-id 'PASTE_MACHINE_ID_HERE' \
  --seats 1
  --customer 'My Restaurant'
```

## List issued licenses

```bash
cargo run -p license-gen -- list
```

## Reset local app license data (macOS, testing)

Clears saved license / app data so you can re-activate:

```bash
rm -rf ~/Library/Application\ Support/com.usman.foodpos-offline
```

## Notes

- Token format: `FPOS1.<claims_b64>.<sig_b64>` (Ed25519-signed claims).
- Same token fails on a different Machine ID.
- Run `license-gen` only on your vendor machine; keep `keys/` private.
