# How to Install ClassicPOS Desktop

A complete point-of-sale system that runs entirely on your desktop. No internet required for POS operations.

---

## System Requirements

| Requirement | Minimum |
|-------------|---------|
| Operating System | Windows 10 (x64) or later |
| RAM | 4 GB |
| Disk Space | 200 MB |
| Display | 1024×600 or higher |

---

## Step 1: Purchase a License

1. Visit **[ClassicPOS License Portal](https://oakitsolutionsandsupplies.com/settings/license)**
2. Choose your plan:
   - **Professional — $150** (one-time, 1 year of updates)
   - **Enterprise — $150** (one-time, lifetime updates)
3. Pay via PayPal or PesaPal
4. Receive your license key via email instantly

Your license key looks like this:
```
CPPOS-XXXX-XXXX-XXXX-XXXX
```

Keep this key safe — you'll need it to activate the app and if you ever reinstall.

---

## Step 2: Download the Installer

1. Go to **[GitHub Releases](https://github.com/OAK-IT-Solutions/classicpos-Lite/releases)**
2. Under the latest release, download one of:
   - **ClassicPOS_1.0.0_x64-setup.exe** — NSIS installer (recommended for most users)
   - **ClassicPOS_1.0.0_x64_en-US.msi** — MSI installer (for enterprise/IT deployment)

---

## Step 3: Install

### NSIS Installer (.exe)
1. Double-click `ClassicPOS_1.0.0_x64-setup.exe`
2. Click **Next** through the wizard
3. Choose install location (default: `C:\Program Files\ClassicPOS`)
4. Click **Install**
5. Click **Finish** to launch ClassicPOS

### MSI Installer (.msi)
1. Double-click `ClassicPOS_1.0.0_x64_en-US.msi`
2. Follow the installation prompts
3. ClassicPOS will be available in your Start Menu

---

## Step 4: Activate Your License

1. Launch ClassicPOS from the Start Menu
2. The Activation Wizard will appear on first launch
3. Enter your **Business Name** (the name you used when purchasing)
4. Enter your **License Key** (from your email)
5. Click **Activate**
6. You're ready to sell!

---

## Step 5: Connect Hardware (Optional)

### Receipt Printer (USB)
1. Connect your USB thermal receipt printer
2. ClassicPOS detects it automatically
3. Go to **Settings > Printer** to configure paper size and test print

### Cash Drawer
1. Connect your cash drawer to the receipt printer (RJ11 cable)
2. The drawer opens automatically after cash sales
3. Configure the drawer pin in **Settings > Printer**

### Barcode Scanner
1. Plug in your USB barcode scanner
2. It works like a keyboard — no drivers needed
3. Scan barcodes on the POS screen or Products page

---

## Step 6: Start Selling

1. **Open a Register** — Enter your opening cash balance
2. **Add Products** — Scan barcodes or search by name
3. **Process Payment** — Cash, card, or mobile money
4. **Print Receipt** — Automatic or manual print
5. **End of Day** — Close the register to see your daily summary

---

## Step 7: Remote Access (Optional)

Let managers view reports from any browser:

1. Go to **Settings > Cloudflare Tunnel**
2. Enter your Cloudflare tunnel token
3. Access your POS from: `https://your-pos.your-domain.com`

No software install needed for managers — just visit the URL.

---

## Troubleshooting

### "PHP binary not found" or startup error
- Reinstall ClassicPOS from the latest GitHub release
- Make sure you downloaded the Windows version

### "License invalid"
- Check you entered the full key including `CPPOS-` prefix
- Ensure business name matches your purchase
- Contact support if the issue persists

### Printer not detected
- Check USB cable connection
- Try a different USB port
- Install printer drivers from the manufacturer

### App won't start
- Run as Administrator (right-click > Run as administrator)
- Check Windows Event Viewer for error details
- Ensure Windows 10 is fully updated

### Cash drawer doesn't open
- Verify the RJ11 cable is connected to the printer
- Check the drawer pin setting in Settings > Printer
- Test with the "Test Drawer" button in Settings

---

## Data & Backups

- All data is stored in a local SQLite database
- Database location: `C:\Users\{you}\AppData\Local\ClassicPOS\classicpos.db`
- Back up this file regularly
- When online, data syncs to the cloud automatically

---

## Support

- **Email**: [support@oakitsolutionsandsupplies.com](mailto:support@oakitsolutionsandsupplies.com)
- **Issues**: [GitHub Issues](https://github.com/OAK-IT-Solutions/classicpos-Lite/issues)
- **License Help**: [License Portal](https://oakitsolutionsandsupplies.com/settings/license)

---

## Uninstall

1. Open Windows Settings > Apps
2. Search for "ClassicPOS"
3. Click **Uninstall**
4. Follow the prompts

Your database is preserved after uninstall. To fully remove, delete `C:\Users\{you}\AppData\Local\ClassicPOS\`.
