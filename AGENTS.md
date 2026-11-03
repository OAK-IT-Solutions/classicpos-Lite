# AGENTS.md — ClassicPOS Lite (Offline Desktop POS — Tauri v2)

## Project Overview

**ClassicPOS Lite** — a fully offline desktop point-of-sale product shipped to customers as a ~30MB Windows installer. **Tauri v2 (Rust) shell + Vue 3 frontend + embedded static PHP 8.4 sidecar running Laravel 12 + SQLite.** No cloud dependency for POS operations; cloud is used only for license activation and auto-updates.

- Repo: `github.com/OAK-IT-Solutions/classicpos-Lite` (**`main`** branch)
- **Not interchangeable** with `poslavalel/` (cloud SaaS) or `classicpos-Lite-electron/` (Electron fork on `electron-v1`).
- Pricing: **$150 one-time** (Professional plan; license keys `CPPOS-XXXX-XXXX-XXXX-XXXX`, HMAC-SHA256 offline validation).

---

## Repository Layout

| Directory | What |
|---|---|
| `backend/` | Laravel 12 app forked from poslavalel, database-agnostic (SQLite default offline, PostgreSQL optional). 82+ SQLite migrations, 14 seeders. |
| `desktop/` | Tauri v2 app: `src/` (Vue 3 + Vite), `src-tauri/` (Rust), static PHP binary build files (`Dockerfile.php`, `craft.yml`, `build.bat`, `spc`). |
| `landing/` | Vue 3 SPA — Desktop $150 license marketing/purchase page. |
| `.hermes/plans/` | Sprint implementation plans (e.g. `2026-08-12_sprint6-desktop-completeness.md`). |
| `BUILD_STATUS.md` | Build history — latest: **v1.0.6 Windows NSIS/MSI build success** (tag `desktop-v1.0.6`, draft release). |
| `TASKS.md` | Sprint-by-sprint task log (Sprints 1–9 + Sprint 6 completeness). |
| `INSTALL.md`, `README.md` | Install & product docs. |

---

## Commands

```bash
# Frontend (from desktop/)
npm install
npm run dev              # vite dev
npm run build            # vite build → dist/

# Rust / Tauri (from desktop/src-tauri/) — Windows builds run in GitHub Actions
cargo build
cargo tauri build        # produces NSIS/MSI installers

# Backend (from backend/) — SQLite offline mode
php artisan migrate:offline     # 82 migrations on SQLite
php artisan db:seed             # 14 seeders (incl. OfflineDatabaseSeeder)
php artisan serve

# CI/CD
# .github/workflows/build-desktop.yml — builds on Windows (ubuntu shell workaround for CRLF),
# generates icons, runs composer + vite + tauri build, uploads artifacts.
```

**This Linux server cannot produce Windows installers** — code changes are committed → GitHub Actions builds.

---

## Architecture

```
ClassicPOS Desktop (~30MB installer)
├── Tauri v2 shell (Rust) — window, lifecycle, IPC
│   ├── src-tauri/src/lib.rs            plugins + PHP startup + window
│   ├── src-tauri/src/commands/mod.rs   IPC: PHP lifecycle, tunnel, printer
│   ├── src-tauri/src/commands/printer.rs   USB (rusb) / serial (serialport) / network ESC/POS + cash drawer pulse
│   └── src-tauri/src/commands/tunnel.rs    Cloudflare Tunnel service install/start/stop
├── Static PHP 8.4 sidecar (native Windows PE binary — never Linux ELF)
│   ├── php-startup.php   .env generation, auto-migrate on version update
│   └── php-router.php    static file router for PHP built-in server
├── Laravel 12 backend (local) + SQLite (single file, zero config)
├── Vue 3 + Vite frontend with Inertia shim (src/shims/inertia.ts)
│   └── reuses poslavalel backend Vue pages (POS Register, Products, etc.) via wrappers/
└── TauriBridge.ts  TypeScript ↔ Rust IPC service
```

### Key backend adaptations (offline)
- `app/Services/LocalQueueService.php` — SQLite-backed queue replacing Redis (`queue_jobs` table); `PaymentService`, `PosService`, `NetworkService` use it.
- Config defaults: file cache, file session, sync queue (`config/cache.php`, `session.php`, `queue.php`); `User` model no longer hardcodes `pgsql`.
- `php artisan migrate:offline` + `OfflineDatabaseSeeder`; `.env.offline.example`.
- PostgreSQL-specific migrations forked with driver checks (3 files).

### License & update flow
- `DesktopLicenseController` — `POST /license/verify|activate|deactivate`, `GET /license/status|generate-demo` (unauthenticated routes in `routes/api.php`, lines ~75–88).
- `DesktopLicensePurchaseController` — `POST /license/purchase|complete`, `GET /license/plans`, admin `GET /desktop-licenses`.
- `DesktopUpdateController` — GitHub Releases → `latest.json` for Tauri updater.
- `ActivationWizard.vue` (3-step) + license gate in `App.vue` (3-phase lifecycle); stored in localStorage + server JSON.
- Purchase link → `https://dashboard.oakitsolutionsandsupplies.com/dashboard/checkout?plan=...` or landing page (`landing/`).

---

## Frontend Structure (`desktop/src/`)

| Path | Purpose |
|---|---|
| `main.ts`, `App.vue` | Vue entry; App runs StartupScreen → ActivationWizard → license gate → router |
| `router/index.ts` | vue-router (10+ routes incl. Settings) |
| `shims/inertia.ts` | Inertia adapter shim (`usePage`, `router.visit`, `useForm`, `Link`) so backend pages run unchanged |
| `shims/AppLayout.vue` | Pass-through so backend layout imports resolve |
| `layouts/AppLayout.vue` | Desktop sidebar nav (vue-router links) |
| `wrappers/` | Page wrappers (POS, Products, Customers, Sales, Dashboard, Auth/Login…) |
| `pages/Settings.vue` | Printer + tunnel + app info |
| `components/` | `StartupScreen`, `ActivationWizard`, `AutoUpdater`, `TunnelSetup` |
| `services/TauriBridge.ts` | All Rust IPC calls (print, drawer, tunnel, updater, version) |

Backend first-run onboarding page lives at `backend/resources/js/Pages/Onboarding/DesktopOnboarding.vue`.

---

## Gotchas

- **Never ship a Linux ELF PHP binary** — commit `c0812b3` replaced it with a native Windows PE build; check `desktop/` resources before releases.
- CRLF/YAML CI issues: workflow uses bash shell; keep an eye on `build-desktop.yml`.
- `desktop/src-tauri/tauri.conf.json` `version` may lag the release tag (conf says `1.0.0`, releases are `desktop-v1.0.6`) — reconcile before publishing.
- The **working tree on this machine has pre-existing uncommitted WIP** (TenantResolution, DesktopSetup/Startup controllers, printer/tunnel Rust changes, `landing/`, `.hermes/`). Only stage your own doc/code files; never `git add -A` blindly.
- Do not mix with `classicpos-Lite-electron/` — same GitHub repo, different branch (`electron-v1`) and shell.
