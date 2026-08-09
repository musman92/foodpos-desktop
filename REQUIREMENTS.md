# FoodPOS Desktop (WinUI) — Requirements & analysis

**Product:** Offline Windows POS for restaurants (one-time sale + paid modules).  
**UI stack:** WinUI 3 + C# + .NET 8.  
**Cloud SaaS (Laravel)** stays separate; this app does not embed Laravel.

---

## 1. Goals

| Goal | Detail |
|------|--------|
| Offline first | Works days without internet |
| Windows only | All customers are Windows-based |
| Protected IP | Compiled .NET — no PHP source tree on disk |
| One-time sale | Base install; extras paid per customer |
| Per-customer modules | Enable/disable without updating every shop |
| No mass updates | Shop stays on their build until they pay / request update |
| Data safe | License/module changes and app updates must not wipe shop data |

---

## 2. Phased scope

### Phase A — MVP (this project start)

- [x] Spec + setup guide  
- [x] WinUI desktop shell  
- [x] Admin login (local users)  
- [x] Shop settings (name, etc.)  
- [x] Local database (**SQL Server Express** from day 1)  
- [x] App data / connection overrides under LocalAppData  

### Phase B — Single-counter POS

- Floors / tables  
- Menu items & categories  
- Create order, pay, basic receipt (browser or direct print later)  
- Machine-bound license (signed token + modules)  
- Module flags in license  

### Phase C — Multi-counter (same shop)

- One shop DB server (SQL Server Express on main PC)  
- Other counters connect over LAN  
- Seats / counter limit from license  

### Phase D — Sync

- Offline-first local DB  
- One-way then two-way sync to cloud API  
- Conflict rules documented before build  

---

## 3. Architecture (target)

```
┌─────────────────────────────────────────┐
│  WinUI App (FoodPos.App)                │
│  Login · Settings · (later POS screens) │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│  FoodPos.Core                           │
│  Auth · Settings · Modules · Domain     │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│  FoodPos.Infrastructure                 │
│  EF Core · SQL Server Express           │
└─────────────────────────────────────────┘

SQL Server:
  Instance: .\SQLEXPRESS
  Database: FoodPosOffline

App files (never inside Program Files):
  %LocalAppData%\FoodPosOffline\
    connection.txt   (optional override)
    license.json     (later)
    logs\
```

**No local HTTP “backend” for MVP.** UI calls C# services directly.

---

## 4. Modules & customization (business rules)

### 4.1 How modules turn on/off (offline)

1. Feature code ships **in the build** (hidden if not licensed).  
2. Signed **license token** lists allowed `modules[]` + `machine_id`.  
3. Customer pays → vendor issues **new token** → paste/activate on that PC.  
4. **No data loss** — only license file changes.  
5. Disable = new token without that module (applied when they activate it).

Same idea as cloud toggles; the “admin panel” is your license issuer, not their DB.

### 4.2 When a new build is required

| Change | Delivery |
|--------|----------|
| Module already in their installed version | License only |
| New code not in their version | New installer for that customer (+ license) |
| Other shops | Untouched until they pay / request |

### 4.3 Clashing UI requirements

| Type | Mechanism |
|------|-----------|
| Column order, labels, extra form fields | **Per-shop config** in DB |
| Big capabilities (reports, loyalty, KDS) | **License modules** |
| Truly unique logic | Prefer module; avoid long-lived per-customer git branches |

**Do not** maintain `customer/c1`, `customer/c2` forever branches.  
Use short `feature/*` branches → merge to `main` → unlock per license.

### 4.4 “C1 paid for Report 1; others get it when they ask”

- Build Report 1 once on `main`.  
- Unlock for C1 via license.  
- When C2 asks (free or paid), unlock same module for C2.  
- One product, many licenses.

---

## 5. Functional requirements — MVP

### 5.1 Authentication

- Local admin user seeded on first run.  
- Default (change after first login in later iteration):  
  - Username: `admin`  
  - Password: `admin123`  
- Login screen; failed login shows error.  
- Session kept until logout / app exit (simple in-memory for MVP).

### 5.2 Settings

Admin can view/update:

| Setting | Notes |
|---------|--------|
| Shop / company name | Required |
| Currency code | e.g. PKR (display later) |
| Receipt footer text | Optional string |
| (Later) tax %, timezone, language | Out of MVP |

Settings persist in local DB.

### 5.3 Non-goals for MVP

- Full POS ordering  
- Printing  
- Multi-counter  
- Cloud sync  
- License gate (add immediately after MVP login works)  
- ionCube / PHP  

---

## 6. Non-functional requirements

| Area | Requirement |
|------|-------------|
| OS | Windows 10 1809+ / Windows 11 |
| Install | Unpackaged or MSIX; data under LocalAppData |
| Performance | Login + settings &lt; 2s on typical shop PC |
| Security | Password hashed (ASP.NET Identity–style or BCrypt); no plain text |
| IP | Ship compiled binaries only to customers |
| Updates | Manual / per-customer; no forced fleet update |

---

## 7. Database decision

| Phase | Database |
|-------|----------|
| **Day 1 / single counter** | **SQL Server Express** on the same PC (`.\SQLEXPRESS`, DB `FoodPosOffline`) |
| **Multi-counter** | Same Express instance on the shop “server” PC; counters use LAN connection string |
| Access | EF Core (`Microsoft.EntityFrameworkCore.SqlServer`) |

Connection string resolution (see `SETUP.md`):

1. `FOODPOS_CONNECTION_STRING` environment variable  
2. `%LocalAppData%\FoodPosOffline\connection.txt`  
3. Default: `Server=.\SQLEXPRESS;Database=FoodPosOffline;Trusted_Connection=True;...`

---

## 8. Licensing (next after MVP)

Planned approach:

- Machine ID bound token  
- Claims: `license_id`, `machine_id`, `seats`, `modules[]`, optional `customer`  
- Vendor tool issues tokens  
- App verifies on startup  

---

## 9. Open decisions (record later)

- [ ] MSIX vs classic installer (Inno/NSIS) for v1 customers  
- [ ] Direct print stack (Windows spooler vs third-party)  
- [ ] Sync protocol with existing Laravel cloud  
- [ ] Whether admin password must change on first login (recommended before real sales)

---

## 10. Success criteria for this first slice

1. Developer follows `SETUP.md` and runs the app on Windows.  
2. Login as `admin` / `admin123`.  
3. Change shop name → restart app → value still there.  
4. Clear path to add POS modules without rewriting auth/settings.
