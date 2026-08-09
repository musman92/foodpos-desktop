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

### 1.5 PHP (needed after license activation)

The shell starts Laravel with `php artisan serve`. Install PHP 8.2+ and ensure `php` is on PATH:

```powershell
php --version
```

Until PHP + `foodpos-backend` are bundled in the installer, you need PHP installed for a full POS test.

---

## 2. Get the project on Windows

Copy or clone the project folder onto the Windows machine, e.g.:

```
C:\dev\tauri-test
```

Open PowerShell in that folder.

---

## 3. Install JS deps and build

```powershell
cd C:\dev\tauri-test
npm install
npm run tauri build
```

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

### App starts but FoodPOS does not load

After license activation the app looks for `foodpos-backend` and runs `php`. For now:

1. Keep `foodpos-backend` next to the project / app as in the repo.
2. Ensure `php` is on PATH.
3. Backend should already be migrated/seeded (`admin@local` / `admin123`).

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

Bundling portable PHP + `foodpos-backend` inside the Windows installer is still TODO.  
This guide gets you a Windows **shell build** and local testing with PHP installed separately.
