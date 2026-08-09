# FoodPOS Offline (Tauri)

Windows desktop shell for offline FoodPOS. Cloud SaaS at `/Users/usman/Sites/foodpos` is **not** modified.

## Layout

```
crates/licensing/       Machine-ID → signed license tokens
tools/license-gen/      Vendor CLI (issue / list)
src-tauri/ + src/       Tauri license gate + local auth PoC UI
foodpos-backend/        Laravel FoodPOS copy (offline edition)
```

## Run (dev)

Terminal 1 — optional (Tauri will also auto-start PHP):

```bash
cd foodpos-backend && php artisan serve
```

Terminal 2:

```bash
npm run tauri dev
```

Flow: activate license (or reuse existing) → Tauri starts Laravel on `http://127.0.0.1:8000` → window opens FoodPOS login (`admin@local` / `admin123`).

## Licensing (vendor)

```bash
npm run tauri dev
# copy Machine ID from activation screen

cargo run -p license-gen -- issue \
  --machine-id '<paste>' \
  --seats 2 \
  --customer 'Hill Station Cafe'
```

Paste the `FPOS1.…` token into the app. History: `keys/issuance_log.json` (your machine only).

## FoodPOS backend (offline copy)

```bash
cd foodpos-backend
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
composer install && npm install && npm run build
php artisan migrate --seed
php artisan serve
```

Login: `admin@local` / `admin123`  
Details: [`foodpos-backend/README.md`](foodpos-backend/README.md)

## Reset Tauri app data (macOS)

```bash
rm -rf ~/Library/Application\ Support/com.usman.foodpos-offline
```

## Roadmap

- [x] Machine-bound licensing (no JSON key pool)
- [x] Copy FoodPOS + strip SaaS / branches UI
- [x] Tauri launches Laravel after license activation
- [ ] Direct local printing
- [ ] Counter mode (multi-floor LAN)
- [ ] One-way sync to cloud later



npm run tauri icon ./app-icon.png