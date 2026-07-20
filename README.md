# ClassicPOS Desktop

Offline desktop POS application built with **Tauri v2 + Vue 3 + Laravel 12 + SQLite**.

## What is it?

A complete point-of-sale system that runs entirely on your desktop with no cloud dependency for POS operations. Install once, use forever.

## Architecture

```
Tauri v2 Shell (Rust, ~5MB)
  ├── Static PHP Binary (embedded, ~25MB)
  ├── Laravel 12 (local server)
  ├── SQLite Database (zero config)
  ├── USB/Serial Receipt Printer
  ├── Cash Drawer Control
  ├── Auto-Updater
  ├── License System
  └── Cloudflare Tunnel (remote access)
```

## Features

- **Offline-first POS** — sell without internet
- **USB/Serial thermal receipt printing** (ESC/POS)
- **Cash drawer control** via printer pulse
- **Auto-updates** via GitHub Releases
- **HMAC-signed license system** (offline validation)
- **Cloudflare Tunnel** for remote manager access
- **Barcode scanning** via USB
- **Full inventory, customers, sales, reporting**

## Pricing

**One-time purchase — $150. No monthly fees.**

| Plan | Price | Updates | Best For |
|------|-------|---------|----------|
| **Professional** | $150 | 1 year | Small retail shops, bars, restaurants (1-5 locations) |
| **Enterprise** | $150 | Lifetime | Enterprise chains, multi-location operations |

### What's Included

**Professional ($150)**
- Full offline POS system
- USB & serial receipt printing
- Cash drawer control
- Sales & inventory reports
- Multi-branch (up to 5 locations)
- Barcode scanning
- Auto-updates (1 year)
- Cloudflare Tunnel remote access

**Enterprise ($150)**
- Everything in Professional
- Custom integrations
- Priority support
- SLA guarantee
- Lifetime updates
- Unlimited branches
- Unlimited devices

### How to Purchase

1. Visit [ClassicPOS License Portal](https://oakitsolutionsandsupplies.com/settings/license)
2. Choose Professional or Enterprise ($150 one-time)
3. Pay via PayPal or PesaPal
4. Receive license key via email instantly

## Download

Go to [Releases](https://github.com/OAK-IT-Solutions/classicpos-Lite/releases) to download the latest version.

### Windows
- **NSIS Installer** (.exe) — recommended for most users
- **MSI Installer** (.msi) — for enterprise deployment

### System Requirements
- Windows 10+ (x64)
- 4GB RAM
- 200MB disk space

## Quick Start

### 1. Get a License
Visit [ClassicPOS License Portal](https://oakitsolutionsandsupplies.com/settings/license) to purchase a license key.

| Plan | Price | Features |
|------|-------|----------|
| Professional | $29 (one-time) | Full POS, offline mode, printing, reports, multi-branch |
| Enterprise | $79 (one-time) | Everything + custom integrations, priority support |

### 2. Download & Install
Download the installer from [Releases](https://github.com/OAK-IT-Solutions/classicpos-Lite/releases) and run it.

### 3. Activate
- Launch ClassicPOS Desktop
- Enter your **business name** and **license key** (from your email)
- Click **Activate**
- Start selling!

### License Key Format
```
CPPOS-XXXX-XXXX-XXXX-XXXX
```
You'll receive this key via email after purchase. Keep it safe — you'll need it if you reinstall.

## Development

### Prerequisites
- Node.js 18+
- Rust 1.70+
- PHP 8.4+ (for backend development)

### Setup
```bash
# Clone the repo
git clone https://github.com/OAK-IT-Solutions/classicpos-Lite.git
cd classicpos-Lite

# Install backend dependencies
cd backend
composer install
npm install
cd ..

# Install desktop dependencies
cd desktop
npm install

# Run in dev mode
npm run tauri dev
```

### Build
```bash
cd desktop
npm run tauri build
```

Output: `desktop/src-tauri/target/release/bundle/`

## Project Structure

```
classicpos-Lite/
├── backend/           ← Laravel 12 (SQLite-compatible)
│   ├── app/           ← Controllers, Services, Models
│   ├── config/        ← SQLite + file cache/session/queue
│   ├── database/      ← 85 migrations + 15 seeders
│   ├── routes/        ← API + web routes
│   └── resources/     ← Vue pages, composables, components
├── desktop/           ← Tauri v2 project
│   ├── src/           ← Vue frontend + shims + components
│   ├── src-tauri/     ← Rust (printer, drawer, tunnel, PHP lifecycle)
│   ├── Dockerfile.php ← Static PHP build
│   └── README.md      ← Desktop-specific docs
└── .github/workflows/ ← CI/CD (build-desktop.yml)
```

## How It Works

1. **App Launch** → Tauri starts, shows startup screen
2. **PHP Server** → Starts embedded PHP on random port
3. **Health Check** → Polls until Laravel is ready
4. **License Gate** → Checks for valid license key
5. **Main App** → Vue frontend loads from localhost
6. **POS Operations** → All sales via local Laravel + SQLite
7. **Sync** → When online, syncs to cloud API
8. **Updates** → Auto-checks GitHub Releases

## Tech Stack

| Component | Technology |
|-----------|------------|
| Desktop Shell | Tauri v2 (Rust) |
| Frontend | Vue 3 + TypeScript + Vite |
| Backend | Laravel 12 (PHP) |
| Database | SQLite |
| PHP Runtime | Static PHP CLI (embedded) |
| Printing | Rust rusb/serialport (ESC/POS) |
| Updates | Tauri Updater + GitHub Releases |
| Remote Access | Cloudflare Tunnel |

## License

Proprietary — Oak IT Solutions

## Support

- [Issues](https://github.com/OAK-IT-Solutions/classicpos-Lite/issues)
- [Email](mailto:support@oakitsolutionsandsupplies.com)
