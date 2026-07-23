# Mitra Niidhi Samooh

Community fund (BC / Niidhi) management — **React + ASP.NET Core** rewrite of the original PHP app.

## Stack

| Layer | Tech |
|-------|------|
| Frontend | React (Vite + TypeScript), Tailwind, i18n EN/HI |
| Backend | ASP.NET Core 9, Clean Architecture, MediatR, JWT |
| Database | MySQL (`ayodhya5_bc`) via Pomelo EF Core |

## Portals

1. **Admin** — groups, ledger, bidding, payments, members, reports, settings, UPI/QR  
2. **Member** — dashboard, bidding, payments + QR, notifications, profile, invoices, random pick  
3. **Super Admin** — clients, plans, subscriptions, platform payments, audit log, platform settings  

## Run locally

### API
```bash
cd backend
dotnet run --project MitraNiidhi.Api --launch-profile http
```
API: http://localhost:5027 (Swagger in Development)

### UI
```bash
cd frontend
npm install
npm run dev
```
UI: http://localhost:5173  

Set `VITE_API_URL=http://localhost:5027` if needed (default matches).

### Default logins
| Portal | Username | Password |
|--------|----------|----------|
| Admin | `admin` | `admin123` |
| Super Admin | `superadmin` | `superadmin123` |
| Member | depends on DB | (legacy member hashes) |

## Tests
```bash
cd backend
dotnet test MitraNiidhi.Domain.Tests
dotnet test MitraNiidhi.Application.Tests
```

## Publish (production build)

```bash
# API → publish/api
dotnet publish backend/MitraNiidhi.Api/MitraNiidhi.Api.csproj -c Release -o publish/api

# UI → publish/web  (set your live API URL)
cd frontend
set VITE_API_URL=http://localhost:5027
npm run build -- --outDir ../publish/web --emptyOutDir
```

See `publish/README.md` for how to run the packages.

## PHP legacy

Completed PHP portal pages live under **`PHP Backup/`** (admin, member, auth, superadmin, root-debug).  
They are **not** used by the React app.

Still on disk for reference / DB helpers only:
- `common/`, `config/` — shared PHP helpers & DB config  
- `tests/` — old PHP test scripts  
- `index.php` — legacy entry (prefer React UI)

## Project layout
```
frontend/          React app
backend/           MitraNiidhi.* Clean Architecture
PHP Backup/        Archived PHP portals
config/            Legacy MySQL config (used by old PHP / reference)
```

## Notes
- Startup API auto-migrates missing tables/columns (including `audit_log`).  
- Subscription expiry notifications run on a background job (every 6 hours) and when admins open notifications.  
- Theme: fintech teal `#0F766E` / navy `#0B1F33`.
