# FoodPOS WinUI — Setup guide (Windows)

Build and run the desktop app on a **Windows** PC.  
(WinUI / Windows App SDK cannot be built on macOS.)

**Database from day 1:** SQL Server Express (same engine for 1 counter and later multi-counter).

---

## 1. Prerequisites

### 1.1 Windows

- Windows 10 (1809+) or Windows 11  
- Legitimate Windows install (Home is fine)

### 1.2 Visual Studio 2022 + SDKs

#### A) Visual Studio 2022 (recommended — installs most things)

- Download: https://visualstudio.microsoft.com/downloads/  
  → **Community 2022** (free)

Or with winget (Admin PowerShell):

```powershell
winget install --id Microsoft.VisualStudio.2022.Community -e --accept-package-agreements --accept-source-agreements
```

Then open **Visual Studio Installer** → **Modify** → enable workloads:

- **.NET desktop development**
- **Windows application development** (WinUI / Windows App SDK)

Apply / install, then restart the PC if asked.

#### B) .NET 8 SDK (install explicitly)

- Download: https://dotnet.microsoft.com/download/dotnet/8.0  
  → under **SDK 8.0.x** choose **Windows x64** installer

Or with winget:

```powershell
winget install --id Microsoft.DotNet.SDK.8 -e --accept-package-agreements --accept-source-agreements
```

Verify (new PowerShell window):

```powershell
dotnet --version
dotnet --list-sdks
# expect an 8.0.x line
```

#### C) Windows SDK (install explicitly if WinUI build fails)

- Download Windows 11 SDK:  
  https://developer.microsoft.com/windows/downloads/windows-sdk/

Or with winget (Windows 11 SDK):

```powershell
winget install --id Microsoft.WindowsSDK.10.0.22621 -e --accept-package-agreements --accept-source-agreements
```

Alternate (Windows 10 SDK 19041), if the above package id is unavailable on your machine:

```powershell
winget search WindowsSDK
# then install a 10.0.19041 or 10.0.22621 package from the list
```

You can also tick these in **Visual Studio Installer → Individual components**:

- `.NET 8.0 SDK`
- `Windows 11 SDK (10.0.22621.0)` or `Windows 10 SDK (10.0.19041.0)`

### 1.3 SQL Server Express (required)

Install **SQL Server 2022 Express** (free):

1. Download: https://www.microsoft.com/en-us/sql-server/sql-server-downloads  
   → choose **Express**  
2. During setup:
   - Feature: **Database Engine Services**
   - Instance: default name **`SQLEXPRESS`** (recommended)
   - Authentication: **Windows authentication** (fine for local/dev)
3. Optional but useful: install **SQL Server Management Studio (SSMS)**  
   https://learn.microsoft.com/en-us/sql/ssms/download-sql-server-management-studio-ssms

#### Verify Express is running

```powershell
# Service should be Running
Get-Service | Where-Object { $_.Name -like "*SQL*" }

# Quick connectivity test (sqlcmd ships with Express tools / VS)
sqlcmd -S .\SQLEXPRESS -E -Q "SELECT @@VERSION"
```

If `sqlcmd` is missing, connect with SSMS to `.\SQLEXPRESS` using Windows auth.

#### Default connection used by the app

```
Server=.\SQLEXPRESS;Database=FoodPosOffline;Trusted_Connection=True;TrustServerCertificate=True;MultipleActiveResultSets=True
```

The app creates the `FoodPosOffline` database automatically on first launch if it does not exist.

#### Override connection string (optional)

**Option A — environment variable** (current user or process):

```powershell
[System.Environment]::SetEnvironmentVariable(
  "FOODPOS_CONNECTION_STRING",
  "Server=.\SQLEXPRESS;Database=FoodPosOffline;Trusted_Connection=True;TrustServerCertificate=True;MultipleActiveResultSets=True",
  "User")
```

**Option B — file** (created by you):

```
%LocalAppData%\FoodPosOffline\connection.txt
```

Put the full connection string on one line. File wins over the default; env var wins over the file.

For a second counter later (same shop DB on the main PC):

```
Server=192.168.1.10\SQLEXPRESS;Database=FoodPosOffline;User Id=...;Password=...;TrustServerCertificate=True;MultipleActiveResultSets=True
```

(You will enable SQL auth / firewall when you reach multi-counter.)

### 1.4 Verify .NET

Open **Developer PowerShell for VS 2022**:

```powershell
dotnet --version
# 8.x or newer
```

---

## 2. Get the project

Copy or clone this repo so you have:

```
FoodPos.sln
SETUP.md
REQUIREMENTS.md
src\
  FoodPos.App\
  FoodPos.Core\
  FoodPos.Infrastructure\
```

---

## 3. Restore and run

```powershell
cd path\to\this-repo
dotnet restore FoodPos.sln
dotnet build FoodPos.sln -c Debug -p:Platform=x64
dotnet run --project src\FoodPos.App\FoodPos.App.csproj -p:Platform=x64
```

Or open `FoodPos.sln` in Visual Studio → set **FoodPos.App** as startup → platform **x64** → **F5**.

### First login

| Field | Value |
|--------|--------|
| Username | `admin` |
| Password | `admin123` |

Then open **Settings**, change shop name, click **Save**. Restart app to confirm it persisted in SQL Server.

---

## 4. Where data lives

| What | Where |
|------|--------|
| **Database** | SQL Server Express → database `FoodPosOffline` |
| **App files / overrides** | `%LocalAppData%\FoodPosOffline\` (e.g. `connection.txt`) |

- Replacing the `.exe` does **not** drop SQL Server data.  
- To reset the MVP database in SSMS / sqlcmd:

```sql
ALTER DATABASE FoodPosOffline SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
DROP DATABASE FoodPosOffline;
```

Next app launch recreates and seeds `admin` / `admin123`.

---

## 5. Common issues

### Cannot connect to `.\SQLEXPRESS`

1. Confirm service **SQL Server (SQLEXPRESS)** is Running.  
2. Confirm instance name (some installs use a custom name — update connection string).  
3. `TrustServerCertificate=True` is already in the default string.  
4. Temporarily test with SSMS using Windows auth.

### “Windows App SDK not found” / WinUI packages fail

- Install VS workload **Windows application development**  
- Restart VS / terminal  
- `dotnet restore` again  

### Wrong platform

Prefer **x64**:

```powershell
dotnet build FoodPos.sln -c Debug -p:Platform=x64
```

### App builds on Mac?

**No.** Use a Windows machine or VM for build/run. Docs can be edited on Mac; compile on Windows.

---

## 6. Project map

| Project | Role |
|---------|------|
| `FoodPos.App` | WinUI UI (Login, Settings) |
| `FoodPos.Core` | Entities, auth/settings interfaces |
| `FoodPos.Infrastructure` | EF Core + **SQL Server** |

Next features (POS, license modules) add screens in App and services in Core/Infrastructure — see `REQUIREMENTS.md`.

---

## 7. Publish a simple folder (optional)

```powershell
dotnet publish src\FoodPos.App\FoodPos.App.csproj -c Release -r win-x64 --self-contained true -p:WindowsPackageType=None -p:Platform=x64
```

Output under `src\FoodPos.App\bin\Release\net8.0-windows10.0.19041.0\win-x64\publish\`.  
Copy that folder to the test PC. That PC also needs **SQL Server Express** (or network access to the shop’s Express instance) and the same connection string setup.
