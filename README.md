# FoodPOS Offline (WinUI)

Windows offline POS — **WinUI 3 + C# + .NET 8**.  
Cloud SaaS (Laravel) is a separate product and is not part of this repo.

## Docs

| Doc | Purpose |
|-----|---------|
| [`REQUIREMENTS.md`](REQUIREMENTS.md) | Product analysis, phases, modules / licensing model |
| [`SETUP.md`](SETUP.md) | Visual Studio install + run on Windows |

## Solution layout

```
FoodPos.sln
src/
  FoodPos.App/             WinUI UI (login, settings)
  FoodPos.Core/            Domain, session, interfaces
  FoodPos.Infrastructure/  EF Core + SQL Server Express
```

## MVP (current)

- Admin login: `admin` / `admin123`
- Shop settings (name, currency, receipt footer)
- DB: **SQL Server Express** → `FoodPosOffline` (see [`SETUP.md`](SETUP.md))

## Run (Windows only)

```powershell
dotnet restore FoodPos.sln
dotnet run --project src\FoodPos.App\FoodPos.App.csproj -p:Platform=x64
```

Or open `FoodPos.sln` in Visual Studio 2022 → F5.  
Full steps: [`SETUP.md`](SETUP.md).

## Roadmap (short)

1. License + modules (machine-bound)
2. Single-counter POS
3. Multi-counter (same Express DB over LAN)
4. Offline → cloud sync
