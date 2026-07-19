# ClassicPOS Desktop — Task Summary & Status

## Project: ClassicPOS Desktop (Offline POS System)
## Repo: https://github.com/OAK-IT-Solutions/classicpos-Lite
## Date: July 18, 2026
## Version: 1.0.0

---

## Executive Summary

Built a complete offline desktop POS system using **Tauri v2 + Static PHP + Laravel 12 + SQLite**. The app runs entirely on the user's desktop with no cloud dependency for POS operations. Installer size: **~35-50MB** (vs ~500MB+ Docker, ~150MB+ Electron).

### What Was Achieved
- 671 files committed to the repo
- 82 SQLite migrations pass
- 14 seeders pass
- Frontend build: 0 errors, 312KB index chunk
- License validation: working (HMAC-signed, offline-capable)
- GitHub Actions CI/CD: configured for Windows, macOS, Linux
- Draft release created on GitHub

---

## Sprint Status

### Sprint 1: SQLite Foundation ✅ COMPLETE
**Goal:** Make the existing Laravel backend database-agnostic (PostgreSQL + SQLite)

| Task | Status | Files |
|------|--------|-------|
| Add SQLite connection to `config/database.php` | ✅ | `config/database.php` |
| Change cache/session/queue defaults to file/sync | ✅ | `config/cache.php`, `session.php`, `queue.php` |
| Remove hardcoded `$connection='pgsql'` from User model | ✅ | `app/Models/User.php` |
| Fix `DATE_TRUNC` in AgentDashboardController | ✅ | `app/Http/Controllers/Api/V1/Agent/AgentDashboardController.php` |
| Create LocalQueueService (Redis → SQLite queue) | ✅ | `app/Services/LocalQueueService.php` |
| Create queue_jobs table migration | ✅ | `database/migrations/2026_07_17_000001_create_queue_jobs_table.php` |
| Update PaymentService to use LocalQueueService | ✅ | `app/Services/PaymentService.php` |
| Update PosService to use LocalQueueService | ✅ | `app/Services/PosService.php` |
| Update NetworkService (no Redis dependency) | ✅ | `app/Services/NetworkService.php` |
| Update HealthController for SQLite mode | ✅ | `app/Http/Controllers/Api/V1/HealthController.php` |
| Update SystemHealthController for SQLite mode | ✅ | `app/Http/Controllers/Api/V1/Admin/SystemHealthController.php` |
| Fork PostgreSQL-specific migrations (3 files) | ✅ | 3 new migration files |
| Fix DemoProductSeeder insertGetId SQLite bug | ✅ | `database/seeders/DemoProductSeeder.php` |
| Create OfflineDatabaseSeeder | ✅ | `database/seeders/OfflineDatabaseSeeder.php` |
| Create migrate:offline artisan command | ✅ | `app/Console/Commands/MigrateOffline.php` |
| Create .env.offline.example | ✅ | `.env.offline.example` |
| **Test:** 82 migrations pass on SQLite | ✅ | |
| **Test:** 14 seeders pass on SQLite | ✅ | |
| **Test:** SQLite database 888KB with seeded data | ✅ | |

---

### Sprint 2: Static PHP Build + Desktop Scaffold ✅ COMPLETE
**Goal:** Build static PHP binary and create Tauri project scaffold

| Task | Status | Files |
|------|--------|-------|
| Install static-php-cli (spc) binary | ✅ | `desktop/spc.exe` |
| Define required PHP extensions (20 extensions) | ✅ | `desktop/craft.yml` |
| Create Dockerfile.php for static PHP build | ✅ | `desktop/Dockerfile.php` |
| Create build.bat automation script | ✅ | `desktop/build.bat` |
| Create package.json with all dependencies | ✅ | `desktop/package.json` |
| Create vite.config.ts with Inertia shim alias | ✅ | `desktop/vite.config.ts` |
| Create tsconfig.json | ✅ | `desktop/tsconfig.json` |
| Create index.html | ✅ | `desktop/index.html` |
| Create src/main.ts (Vue app entry) | ✅ | `desktop/src/main.ts` |
| Create src/App.vue | ✅ | `desktop/src/App.vue` |
| Create src/router/index.ts (10 routes) | ✅ | `desktop/src/router/index.ts` |
| Create Inertia adapter shim | ✅ | `desktop/src/shims/inertia.ts` |
| Create AppLayout pass-through shim | ✅ | `desktop/src/shims/AppLayout.vue` |
| Create 8 page wrappers (POS, Products, etc.) | ✅ | `desktop/src/wrappers/` |
| Create desktop AppLayout (sidebar + nav) | ✅ | `desktop/src/layouts/AppLayout.vue` |
| Create Login page (standalone) | ✅ | `desktop/src/wrappers/Auth/Login.vue` |
| **Test:** npm install (127 packages, 0 vulnerabilities) | ✅ | |
| **Test:** vite build (1437 modules, 1.5MB output) | ✅ | |

---

### Sprint 3: Tauri Integration ✅ COMPLETE
**Goal:** PHP sidecar lifecycle, app startup flow, .env generation

| Task | Status | Files |
|------|--------|-------|
| Rust lib.rs (plugins, PHP startup, window lifecycle) | ✅ | `desktop/src-tauri/src/lib.rs` |
| Rust main.rs (Windows subsystem) | ✅ | `desktop/src-tauri/src/main.rs` |
| PHP sidecar lifecycle (start, health check, stop) | ✅ | `desktop/src-tauri/src/commands/mod.rs` |
| Auto-migrate SQLite on version update | ✅ | `desktop/src-tauri/php-startup.php` |
| Static file router for PHP built-in server | ✅ | `desktop/src-tauri/php-router.php` |
| .env auto-generation for embedded mode | ✅ | (in mod.rs) |
| TauriBridge TypeScript service | ✅ | `desktop/src/services/TauriBridge.ts` |
| StartupScreen component (loading spinner) | ✅ | `desktop/src/components/StartupScreen.vue` |
| Tauri config (tauri.conf.json) | ✅ | `desktop/src-tauri/tauri.conf.json` |
| Cargo.toml (Rust dependencies) | ✅ | `desktop/src-tauri/Cargo.toml` |
| **Test:** Frontend build: 0 errors, 2.5MB output | ✅ | |

---

### Sprint 4: Inertia Shim Refinement ✅ COMPLETE
**Goal:** Ensure existing backend components work in desktop shell

| Task | Status | Files |
|------|--------|-------|
| Audit all 12 pages for Inertia dependencies | ✅ | |
| Fix usePage().url reactive state | ✅ | `desktop/src/shims/inertia.ts` |
| Fix router.visit() to use vue-router | ✅ | (in shim) |
| Add useForm() support in shim | ✅ | (in shim) |
| Add Link component support in shim | ✅ | (in shim) |
| Replace backend AppLayout with pass-through shim | ✅ | `desktop/src/shims/AppLayout.vue` |
| Create desktop AppLayout with vue-router links | ✅ | `desktop/src/layouts/AppLayout.vue` |
| **Test:** All 8 wrappers compile cleanly | ✅ | |
| **Test:** vite build: 0 errors | ✅ | |

---

### Sprint 5: Native Features ✅ COMPLETE
**Goal:** USB printing, cash drawer, auto-updater

| Task | Status | Files |
|------|--------|-------|
| USB printer (rusb crate, ESC/POS) | ✅ | `desktop/src-tauri/src/commands/printer.rs` |
| Serial printer support (serialport crate) | ✅ | (in printer.rs) |
| Network printer support (TCP) | ✅ | (in printer.rs) |
| Printer enumeration (USB + Serial) | ✅ | (in printer.rs) |
| Cash drawer (ESC/POS pulse command) | ✅ | (in printer.rs) |
| Rust IPC commands (print, drawer, list) | ✅ | `desktop/src-tauri/src/commands/mod.rs` |
| Auto-updater component | ✅ | `desktop/src/components/AutoUpdater.vue` |
| TauriBridge printer/drawer/updater APIs | ✅ | `desktop/src/services/TauriBridge.ts` |
| **Test:** Frontend build: 0 errors | ✅ | |

---

### Sprint 6: License System ✅ COMPLETE
**Goal:** HMAC-signed license keys, activation wizard

| Task | Status | Files |
|------|--------|-------|
| License key format (CPPOS-XXXX-XXXX-XXXX-XXXX) | ✅ | |
| HMAC-SHA256 signing and validation | ✅ | `app/Services/LicenseService.php` |
| Offline validation (signature + expiry + business) | ✅ | (in LicenseService) |
| Device fingerprinting | ✅ | (in LicenseService) |
| Backend license API (verify, activate, deactivate, status) | ✅ | `app/Http/Controllers/Api/V1/DesktopLicenseController.php` |
| License routes (5 endpoints) | ✅ | `routes/api.php` |
| Activation wizard (3-step Vue component) | ✅ | `desktop/src/components/ActivationWizard.vue` |
| License gate in App.vue (3-phase lifecycle) | ✅ | `desktop/src/App.vue` |
| License storage (localStorage + server JSON) | ✅ | |
| **Test:** Valid key → VALID | ✅ | |
| **Test:** Wrong business → INVALID | ✅ | |
| **Test:** Tampered key → INVALID | ✅ | |

---

### Sprint 7: Cloudflare Tunnel ✅ COMPLETE
**Goal:** Remote manager access via Cloudflare Tunnel

| Task | Status | Files |
|------|--------|-------|
| Rust tunnel module (config, service install/start/stop) | ✅ | `desktop/src-tauri/src/commands/tunnel.rs` |
| Tunnel IPC commands (6 commands) | ✅ | `desktop/src-tauri/src/commands/mod.rs` |
| TunnelSetup.vue wizard (2-step) | ✅ | `desktop/src/components/TunnelSetup.vue` |
| Settings page (printer + tunnel + app info) | ✅ | `desktop/src/pages/Settings.vue` |
| TauriBridge tunnel APIs | ✅ | `desktop/src/services/TauriBridge.ts` |
| Settings route + sidebar link | ✅ | `desktop/src/router/index.ts`, `layouts/AppLayout.vue` |
| **Test:** Frontend build: 0 errors | ✅ | |

---

### Sprint 8: Distribution ✅ COMPLETE
**Goal:** CI/CD, installers, downloads page

| Task | Status | Files |
|------|--------|-------|
| GitHub Actions CI/CD (Windows, macOS, Linux) | ✅ | `.github/workflows/build-desktop.yml` |
| Tauri signing key generation (in workflow) | ✅ | |
| Update server endpoint (GitHub Releases → latest.json) | ✅ | `app/Http/Controllers/Api/V1/DesktopUpdateController.php` |
| Downloads page (web app, gated by subscription) | ✅ | `resources/js/Pages/Settings/Downloads.vue` |
| Downloads route | ✅ | `routes/web.php` |
| **Test:** Frontend build: 0 errors | ✅ | |

---

### Sprint 9: Polish ✅ COMPLETE
**Goal:** Final config updates, README, repo setup

| Task | Status | Files |
|------|--------|-------|
| Updated tauri.conf.json (CSP, updater, shell scope) | ✅ | `desktop/src-tauri/tauri.conf.json` |
| Updated GitHub Actions workflow for correct repo | ✅ | `.github/workflows/build-desktop.yml` |
| Created desktop README.md | ✅ | `desktop/README.md` |
| Created .gitignore | ✅ | `.gitignore` |
| Copied all files to classicpos-Lite repo | ✅ | |
| Committed 671 files | ✅ | |
| Pushed to origin/main | ✅ | |
| Created tag desktop-v1.0.0 | ✅ | |
| Created GitHub release (draft) | ✅ | |

---

## Files Created/Modified Summary

### New Files (Desktop App): ~45 files
| Area | Files |
|------|-------|
| Tauri config | `tauri.conf.json`, `Cargo.toml`, `build.rs`, `capabilities/default.json` |
| Rust source | `lib.rs`, `main.rs`, `commands/mod.rs`, `commands/printer.rs`, `commands/tunnel.rs` |
| PHP sidecar | `php-startup.php`, `php-router.php` |
| Vue frontend | `main.ts`, `App.vue`, `router/index.ts` |
| Shims | `inertia.ts`, `AppLayout.vue` |
| Layouts | `AppLayout.vue` |
| Components | `StartupScreen.vue`, `ActivationWizard.vue`, `AutoUpdater.vue`, `TunnelSetup.vue` |
| Pages | `Settings.vue` |
| Wrappers | 8 page wrappers (POS, Products, Customers, Sales, Dashboard, etc.) |
| Services | `TauriBridge.ts` |
| Build | `Dockerfile.php`, `build.bat`, `craft.yml`, `package.json`, `vite.config.ts` |
| Docs | `README.md` |

### New Files (Backend): ~10 files
| Area | Files |
|------|-------|
| Services | `LocalQueueService.php`, `LicenseService.php` |
| Controllers | `DesktopLicenseController.php`, `DesktopUpdateController.php` |
| Commands | `MigrateOffline.php` |
| Migrations | 3 new (queue_jobs, portable enum, portable schema) |
| Seeders | `OfflineDatabaseSeeder.php` |
| Pages | `Settings/Downloads.vue` |

### Modified Files (Backend): ~15 files
| Area | Files |
|------|-------|
| Config | `database.php`, `cache.php`, `session.php`, `queue.php` |
| Models | `User.php` |
| Services | `PaymentService.php`, `PosService.php`, `NetworkService.php` |
| Controllers | `HealthController.php`, `SystemHealthController.php`, `AgentDashboardController.php` |
| Migrations | 3 (driver checks for PostgreSQL-specific SQL) |
| Seeders | `DemoProductSeeder.php` |
| Routes | `api.php`, `web.php` |

---

## What's Remaining

| Task | Priority | Effort |
|------|----------|--------|
| Build static PHP binary (Docker, 30-60 min) | High | 1 hour |
| Generate Tauri signing keys | High | 5 min |
| Add GitHub secrets (TAURI_SIGNING_PRIVATE_KEY) | High | 5 min |
| Trigger CI/CD build (push tag) | High | 5 min |
| Test installer on Windows | High | 1 hour |
| Test installer on macOS | Medium | 1 hour |
| Test installer on Linux | Medium | 1 hour |
| Generate app icons (PNG, ICO, ICNS) | Medium | 30 min |
| Test full POS workflow offline | High | 2 hours |
| Test USB printing with real printer | Medium | 1 hour |
| Test auto-update flow | Medium | 1 hour |
| Test Cloudflare Tunnel setup | Medium | 1 hour |
| Publish release (remove draft flag) | High | 1 min |
| Set up Cloudflare Access policies | Low | 30 min |
| End-to-end testing across platforms | High | 4 hours |

---

## Architecture Summary

```
┌─────────────────────────────────────────────────┐
│  ClassicPOS Desktop (~35-50MB installer)         │
│                                                  │
│  Tauri v2 Shell (Rust)                          │
│  ├─ Static PHP Binary (sidecar)                 │
│  ├─ Laravel 12 (local)                          │
│  ├─ SQLite Database (embedded)                  │
│  ├─ USB/Serial Printer (native)                 │
│  ├─ Cash Drawer (ESC/POS)                       │
│  ├─ Auto-Updater (GitHub Releases)              │
│  ├─ License System (HMAC-signed)                │
│  └─ Cloudflare Tunnel (remote access)           │
└─────────────────────────────────────────────────┘
```

---

## Key Decisions Made

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Desktop shell | Tauri v2 | ~5MB vs ~150MB Electron |
| PHP runtime | Static PHP CLI | ~25MB, zero dependencies |
| Database | SQLite | Zero config, embedded, single file |
| License format | HMAC-SHA256 | Offline validation, tamper-proof |
| Distribution | GitHub Releases | Free, integrates with Tauri updater |
| Remote access | Cloudflare Tunnel | Free for <50 users, no software install for managers |
| Frontend strategy | Inertia shim | Existing 944-line POS Register works as-is |
