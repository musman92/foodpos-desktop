# FoodPOS Offline — Windows first-time setup

Use this on a **Windows PC** before you build or run the app.  
(You cannot produce a Windows installer from macOS.)

---

## 1. Install prerequisites

### 1.1 Visual Studio Build Tools (required for Rust)

1. Download **Build Tools for Visual Studio**:  
   https://visualstudio.microsoft.com/visual-cpp-build-tools/
2. Run the installer → select **Desktop development with C++**.
3. Finish install and **reboot** if asked.

### 1.2 Rust (fixes `failed to run cargo metadata`)

That Tauri error almost always means `cargo` is missing or not on PATH.

1. Install Rust from: https://rustup.rs  
   (or run the `rustup-init.exe` installer)
2. When prompted, choose the default (stable).
3. **Close and reopen** PowerShell / Terminal (PATH only updates in a new session).
4. Verify:

```powershell
cargo --version
rustc --version
```

If those fail, add this to your user PATH and open a new terminal:

```
%USERPROFILE%\.cargo\bin
```

### 1.3 Node.js (need 20+)

Tauri 2 / Vite 7 need **Node 20 or 22**. Node 16 is too old.

#### Option A — Installer (simplest)

1. Uninstall old Node 16 from **Settings → Apps** (if present).
2. Install **Node.js 22 LTS**: https://nodejs.org
3. Open a **new** PowerShell:

```powershell
node -v
# expect v22.x.x
```

#### Option B — nvm-windows

Use [nvm-windows](https://github.com/coreybutler/nvm-windows) (not the Mac/Linux `nvm`).

```powershell
nvm version
nvm list
nvm install 22
nvm use 22
node -v
```

If `nvm use 22` still leaves you on `v16.14.0`:

1. **Install 22 first** — `nvm use` does nothing if that version is not installed.
2. Run PowerShell / CMD **as Administrator** (nvm-windows often needs this to switch).
3. Check which `node` wins on PATH:

```powershell
where.exe node
```

- If you see `C:\Program Files\nodejs\...` **above** the nvm path, uninstall the standalone Node 16 from Apps, or remove that folder from PATH.
- nvm’s active path should look like `...\nvm\...\nodejs\node.exe` (symlink folder).

4. Close **all** terminals and open a new one, then:

```powershell
nvm use 22
node -v
where.exe node
```

5. Avoid mixing Git Bash “nvm” with nvm-windows — use **PowerShell or CMD** for builds.

### 1.4 WebView2 Runtime

Most Windows 10/11 machines already have it. If the app window fails to open:

https://developer.microsoft.com/en-us/microsoft-edge/webview2/

### 1.5 PHP (required to run FoodPOS after activate)

The installed app starts Laravel with `php artisan serve`. **PHP must be on PATH** on any PC that runs the app (bundled PHP is still TODO).

Install PHP 8.2+ (e.g. https://windows.php.net/download/ or Chocolatey `choco install php`), then:

```powershell
php --version
```

Also enable extensions commonly needed by Laravel in `php.ini` (`openssl`, `pdo_sqlite`, `mbstring`, `tokenizer`, `fileinfo`, `curl`).

### 1.6 Composer (build machine only)

Needed once to prepare `foodpos-backend` before building the installer:

https://getcomposer.org/download/

```powershell
composer --version
```

---

## 2. Get the project on Windows

Copy the **full** project onto the Windows machine (including `foodpos-backend`).  
If you copy from git without `vendor/`, prepare it in the next section.

Example path:

```
C:\dev\tauri-test
```

---

## 3. Prepare Laravel, then build

The installer now **bundles** `foodpos-backend`. Build will fail if vendor / frontend assets are missing.

```powershell
cd C:\dev\tauri-test

# One-time (or after backend changes)
cd foodpos-backend
copy .env.example .env   # if you have no .env yet
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
composer install --no-dev
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
cd ..

# Build desktop installer (also runs prepare-backend-bundle)
npm install
npm run tauri build
```

If prepare fails with “vendor missing”, you skipped the `foodpos-backend` steps above.

### Dev mode (optional)

```powershell
npm run tauri dev
```

### Build output

After a successful build:

```
src-tauri\target\release\bundle\nsis\
```

or

```
src-tauri\target\release\bundle\msi\
```

---

## 4. Common errors

### `failed to run cargo metadata` / `cargo: command not found`

| Check | Fix |
|--------|-----|
| Rust not installed | Install from https://rustup.rs |
| Old terminal session | Close PowerShell, open a new one |
| PATH missing | Add `%USERPROFILE%\.cargo\bin` to user PATH |
| Wrong folder | Run commands from the project root (where `package.json` and `Cargo.toml` are) |

Verify again:

```powershell
cargo metadata --format-version 1 --no-deps
```

That should print JSON without error. Then retry:

```powershell
npm run tauri build
```

### `tauri: command not found`

Run from project root after `npm install`:

```powershell
npm run tauri build
```

Do not rely on a global `tauri` binary.

### Linker / `link.exe` / MSVC errors

Install **Desktop development with C++** via Visual Studio Build Tools, then reboot and retry.

### `foodpos-backend not found (expected artisan…)`

The **old installer** did not include Laravel. Fix:

1. Pull/copy the latest project (with bundling changes).
2. Prepare backend (`composer install`, `migrate --seed`, `npm run build` in `foodpos-backend`).
3. Rebuild: `npm run tauri build`
4. Uninstall the old app, install the new NSIS/MSI.
5. Activate license again if needed.

On first launch the app copies the bundled backend into:

`%APPDATA%\com.usman.foodpos-offline\foodpos-backend`

### App activates but PHP errors / blank FoodPOS

Install PHP 8.2+ and ensure `php` works in a new PowerShell, then relaunch the app.

Login after backend starts: `admin@local` / `admin123`

---

## 5. License on Windows (same as Mac)

1. Run the app → copy **Machine ID**.
2. On your vendor machine (this Mac is fine):

```bash
cargo run -p license-gen -- issue \
  --machine-id 'PASTE_WINDOWS_MACHINE_ID' \
  --seats 1 \
  --customer 'Test PC'
```

3. Paste the `FPOS1.…` token into the Windows app → Activate.

Details: [`LICENSING.md`](LICENSING.md)

---

## 6. Checklist (first time)

- [ ] Visual Studio Build Tools — Desktop development with C++
- [ ] Rust (`cargo --version` works in a **new** PowerShell)
- [ ] Node.js LTS (`npm --version`)
- [ ] WebView2 (usually already installed)
- [ ] PHP on PATH (for Laravel after activate)
- [ ] `npm install` in project root
- [ ] `npm run tauri build` succeeds
- [ ] Installer found under `src-tauri\target\release\bundle\`

---

## Note

- `foodpos-backend` is bundled in the installer (after a successful prepare step).
- **Portable PHP inside the installer is still TODO** — target PCs need PHP on PATH for now.
