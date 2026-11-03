# AGENTS.md — ClassicPOS Lite (Electron Edition)

## Project Overview

**Electron fork of ClassicPOS Lite** — same offline desktop POS product (Laravel 12 + SQLite backend, Vue 3 + PrimeVue frontend) but wrapped in an **Electron shell** instead of Tauri v2. Ships as a Windows NSIS installer via `electron-builder`.

- GitHub: `github.com/OAK-IT-Solutions/classicpos-Lite`, branch **`electron-v1`** (local branch here is named `main` and matches `origin/electron-v1` at `ddca38e`; `origin/main` is the shared Tauri history).
- Version: **1.0.7** (`desktop/package.json`); prior tag work at 1.0.6.
- **Sibling of `classicpos-Lite/` (Tauri)** — backend is a near-fork; do not mix changes across the two directories.

---

## Repository Layout

| Path | What |
|---|---|
| `backend/` | Laravel 12 fork (SQLite offline) incl. `resources/js/Pages/Onboarding/DesktopOnboarding.vue`, `OnboardingController`, backup/export endpoints |
| `backend-temp/` | Runtime-extracted backend copy used by the packaged app (gitignored) |
| `desktop/` | Electron app (see below) |
| `desktop/electron/` | Main-process TS: `main.ts`, `php-server.ts`, `preload.ts`, `printer.ts`, `tunnel.ts` |
| `desktop/electron-builder.yml` | NSIS build config; bundles `resources/backend-bundle.tar`, `resources/binaries` (static PHP), router/startup PHP; publishes to GitHub `OAK-IT-Solutions/classicpos-Lite` |
| `desktop/dist/`, `dist-electron/`, `release/` | Build outputs (gitignored) |

### `desktop/` (renderer)

| Path | What |
|---|---|
| `src/main.ts`, `src/App.vue` | Vue 3 + Vite entry; startup → license gate → onboarding → router |
| `src/services/ElectronBridge.ts` | **Replaces `TauriBridge`** — all IPC via `window.electronAPI` (PHP port/status/start-stop, printers, cash drawer, tunnel, version, startup state, `apiRequest`) |
| `src/pages/Settings.vue` | Printer + tunnel + **SQLite backup / CSV export UI** (S6.3/S6.4) |
| `src/components/` | `StartupScreen`, `ActivationWizard`, `AutoUpdater`, `TunnelSetup` |
| `src/shims/`, `wrappers/`, `layouts/` | Inertia shim + page wrappers (same pattern as Tauri edition) |

---

## Commands

```bash
cd desktop
npm install
npm run dev        # concurrently: vite + wait-on dist/index.html + electron .
npm run build      # vite build → dist/
npm run build:win  # vite build + electron-builder --win (NSIS → release/)
npm run preview    # vite preview
```

PHP sidecar: `electron/php-server.ts` starts the bundled static PHP built-in server against the extracted `backend-temp/`; `electron/printer.ts` and `electron/tunnel.ts` mirror the Tauri Rust commands. Builds normally run on Windows; this Linux box can only run `vite build` / type checks.

---

## Electron vs Tauri deltas

| Concern | Tauri (`classicpos-Lite/`) | Electron (this repo) |
|---|---|---|
| Shell | Rust `src-tauri/` | `electron/*.ts` main process |
| IPC bridge | `services/TauriBridge.ts` | `services/ElectronBridge.ts` + `preload.ts` |
| Updates | `tauri-plugin-updater` + `latest.json` | `electron-updater` (publish: GitHub) |
| Installer | NSIS/MSI via `tauri build` | NSIS via `electron-builder.yml` |
| Backup/export | `DbBackup.php` + Settings UI | Same backend endpoints, Settings.vue UI (`67ff11d`) |
| Version | `1.0.0` conf vs `desktop-v1.0.6` tag | `1.0.7` in `package.json` |
| Extra deps | — | PrimeVue, ag-grid, Dexie, `electron-store`, jspdf/jsbarcode |

---

## Recent history (electron-v1)

- `32b2ed9` Sprint 6: `electron-updater` + publish config, v1.0.6
- `67ff11d` S6.3+S6.4: backup/export endpoints + UI, tunnel claims validated
- `ac1d6e0` button-initiated setup flow + first-run detection
- `79cc853`–`1f96ff2` onboarding always shown after activation, dark-theme login/onboarding, skip for returning users
- `ddca38e` 7 critical bugs from full codebase audit (current HEAD)

## Gotchas

- Local branch is `main` but **remote canonical name is `electron-v1`** — push accordingly (`git push origin main:electron-v1` unless tracking is set).
- `backend-temp/` is generated at runtime; never commit it (it is in `.gitignore`, but watch `git status`).
- Do not copy Tauri-only files (`src-tauri/`, `TauriBridge.ts`) here, or Electron-only files there — shells are intentionally different.
- `resources/backend-bundle.tar` and `resources/binaries/` (static PHP) are build inputs for packaging; keep the PHP binary a Windows PE (never Linux ELF — lesson from `c0812b3`).
