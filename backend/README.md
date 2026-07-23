# Mitra Niidhi — Next (React + ASP.NET Core)

## Structure

```
backend/     ASP.NET Core Web API (Clean Architecture)
frontend/    React + Vite + TypeScript
```

## Backend

```bash
cd backend
dotnet test MitraNiidhi.Domain.Tests
dotnet run --project MitraNiidhi.Api
```

Swagger: http://localhost:5027/swagger

Configure MySQL in `MitraNiidhi.Api/appsettings.Development.json`.

## Frontend

```bash
cd frontend
npm install
npm run dev
```

App: http://localhost:5173
API URL: `frontend/.env.development` → `VITE_API_URL=http://localhost:5027`
