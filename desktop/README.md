# ClassicPOS Desktop

Offline desktop POS application built with Tauri v2 + Vue 3 + Laravel + SQLite.

## Architecture

```
Tauri v2 Shell (Rust)
  ├── Static PHP Binary (sidecar) — serves Laravel on localhost
  ├── Vue 3 Frontend — POS UI, Inertia shim
  ├── SQLite Database — zero-config, embedded
  ├── USB/Serial Printer — native ESC/POS printing
  ├── Auto-Updater — GitHub Releases
  └── Cloudflare Tunnel — remote manager access
```

## Requirements

- Node.js 18+ (for building frontend)
- Rust 1.70+ (for building Tauri)
- Docker (for building static PHP binary)
- Tauri CLI: `npm install -g @tauri-apps/cli`

## Quick Start

```bash
# 1. Install dependencies
cd desktop
npm install

# 2. Build static PHP binary (takes 30-60 min first time)
docker build -t classicpos-php-builder -f Dockerfile.php .
docker create --name php-extract classicpos-php-builder
docker cp php-extract:/php src-tauri/binaries/php-x86_64-pc-windows-msvc.exe
docker rm php-extract

# 3. Run in dev mode
npm run tauri dev

# 4. Build for production
npm run tauri build
```

## Project Structure

```
desktop/
├── src/                          # Vue frontend
│   ├── main.ts                   # App entry
│   ├── App.vue                   # Root (startup → activation → app)
│   ├── router/index.ts           # Vue Router
│   ├── shims/
│   │   ├── inertia.ts            # @inertiajs/vue3 adapter
│   │   └── AppLayout.vue         # Backend AppLayout pass-through
│   ├── layouts/
│   │   └── AppLayout.vue         # Desktop sidebar layout
│   ├── components/
│   │   ├── StartupScreen.vue     # Loading while PHP boots
│   │   ├── ActivationWizard.vue  # License activation
│   │   ├── AutoUpdater.vue       # Update notifications
│   │   └── TunnelSetup.vue       # Cloudflare Tunnel wizard
│   ├── pages/
│   │   └── Settings.vue          # Printer + tunnel settings
│   ├── wrappers/                 # Thin wrappers for existing pages
│   │   ├── POS/Register.vue
│   │   ├── Products/Index.vue
│   │   ├── Customers/Index.vue
│   │   ├── Sales/Index.vue
│   │   └── ...
│   └── services/
│       └── TauriBridge.ts        # TypeScript → Rust IPC bridge
├── src-tauri/                    # Rust core
│   ├── Cargo.toml
│   ├── tauri.conf.json
│   ├── src/
│   │   ├── lib.rs                # App entry, plugin init
│   │   ├── main.rs               # Windows subsystem
│   │   └── commands/
│   │       ├── mod.rs            # PHP lifecycle, IPC commands
│   │       ├── printer.rs        # USB/Serial/Network printing
│   │       └── tunnel.rs         # Cloudflare Tunnel management
│   ├── binaries/                 # Static PHP binaries (platform-specific)
│   ├── php-startup.php           # Auto-migration on version update
│   └── php-router.php            # Static file serving
├── Dockerfile.php                # Docker build for static PHP
├── build.bat                     # Windows build script
├── craft.yml                     # Static PHP extensions config
├── package.json
├── vite.config.ts
└── tsconfig.json
```

## How It Works

1. **App Launch** → Tauri starts, shows StartupScreen
2. **Startup Script** → Runs `php-startup.php` (auto-migrate, seed, cache clear)
3. **PHP Server** → Starts PHP built-in server on random port
4. **Health Check** → Polls `GET /` every 200ms until ready
5. **License Gate** → Checks for valid license key
6. **Activation** → First-run wizard if no license
7. **Main App** → Vue frontend loads from localhost:PORT
8. **POS Operations** → All sales, inventory, payments via local Laravel + SQLite
9. **Sync** → When online, syncs to cloud API
10. **Updates** → Auto-checks GitHub Releases on launch

## Key Features

- **Offline-first**: Works without internet. All operations local.
- **SQLite database**: Zero config, single file, WAL mode
- **Native printing**: USB thermal receipt printers via Rust rusb crate
- **Cash drawer**: ESC/POS pulse command via USB printer
- **Auto-updater**: Checks GitHub Releases, downloads, verifies signature
- **Remote access**: Cloudflare Tunnel for manager report viewing
- **License system**: HMAC-signed keys, offline validation

## Building for Production

### Prerequisites
1. Static PHP binary (build with Dockerfile.php)
2. Node.js 18+
3. Rust 1.70+
4. Tauri CLI

### Build Commands
```bash
# Windows
npm run tauri build  # Creates NSIS installer (.exe)

# macOS
npm run tauri build  # Creates .dmg

# Linux
npm run tauri build  # Creates .AppImage + .deb
```

### Output
- `src-tauri/target/release/bundle/nsis/` — Windows installer
- `src-tauri/target/release/bundle/dmg/` — macOS DMG
- `src-tauri/target/release/bundle/appimage/` — Linux AppImage

## CI/CD

Push a tag to trigger the build:
```bash
git tag desktop-v1.0.0
git push origin desktop-v1.0.0
```

GitHub Actions builds for all platforms and creates a draft release.

## License

Proprietary — Oak IT Solutions
