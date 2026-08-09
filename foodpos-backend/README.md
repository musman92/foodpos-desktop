# FoodPOS Offline Backend

Laravel copy of the cloud FoodPOS tenant app for the Windows desktop product.

**Do not edit** `/Users/usman/Sites/foodpos` for offline work — that remains the SaaS product.

## What’s different from SaaS

| SaaS | This copy |
|------|-----------|
| Multi-company / super-admin | Disabled (`OFFLINE_EDITION=true`) |
| Multi-branch | Hidden; one default branch seeded |
| Secret login / platform billing / media | Routes off |
| Electron + Reverb print | Removed; Tauri will print locally later |
| Floors / tables / POS / inventory / reports | Kept |

## Setup (dev)

```bash
cd foodpos-backend
cp .env.example .env
# generate key + sqlite file
php artisan key:generate
touch database/database.sqlite

composer install
npm install
npm run build   # or npm run dev

php artisan migrate --seed
php artisan serve
```

Default admin (from seeder): `admin@local` / `admin123`

Config: [`config/offline.php`](config/offline.php)

## Relation to Tauri shell

```
tauri-test/                 ← this repo (license + desktop shell)
  crates/licensing/
  src-tauri/                ← license gate, will embed/open backend later
  foodpos-backend/          ← this Laravel app
```

Next packaging step: Tauri starts PHP + opens this app after license activation.
