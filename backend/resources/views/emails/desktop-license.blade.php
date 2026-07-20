<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2563eb; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
        .license-box { background: white; border: 2px solid #2563eb; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .license-key { font-family: 'Courier New', monospace; font-size: 18px; font-weight: bold; color: #1e40af; letter-spacing: 2px; word-break: break-all; }
        .plan-badge { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 4px 12px; border-radius: 4px; font-weight: 600; margin-bottom: 1rem; }
        .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ClassicPOS Desktop</h1>
        <p>Your License Key</p>
    </div>

    <div class="content">
        <p>Hello {{ $businessName }},</p>

        <p>Thank you for purchasing <strong>ClassicPOS Desktop</strong>! Your license key is below.</p>

        <div style="text-align: center;">
            <span class="plan-badge">{{ $plan }} Plan — $150 One-Time</span>
        </div>

        <div class="license-box">
            <p style="margin: 0 0 10px; color: #64748b; font-size: 14px;">Your License Key</p>
            <div class="license-key">{{ $licenseKey }}</div>
        </div>

        <p><strong>Plan:</strong> {{ $plan }}</p>
        <p><strong>Valid Until:</strong> {{ $expiresAt }}</p>

        <h3>How to Activate</h3>
        <ol>
            <li>Download ClassicPOS Desktop from <a href="https://github.com/OAK-IT-Solutions/classicpos-Lite/releases">GitHub Releases</a></li>
            <li>Run the installer for your platform (Windows, macOS, or Linux)</li>
            <li>Launch the application</li>
            <li>Enter your <strong>business name</strong> and the <strong>license key</strong> above</li>
            <li>Click <strong>Activate</strong> — you're ready to sell!</li>
        </ol>

        <p style="text-align: center; margin: 30px 0;">
            <a href="https://github.com/OAK-IT-Solutions/classicpos-Lite/releases" class="btn">Download ClassicPOS Desktop</a>
        </p>

        <p><strong>Important:</strong> Keep this email safe. You'll need the license key if you reinstall the app on a new computer.</p>

        <p>If you have any questions, contact us at support@oakitsolutionsandsupplies.com</p>
    </div>

    <div class="footer">
        <p>ClassicPOS Desktop &mdash; Oak IT Solutions</p>
        <p>This is a one-time purchase. No recurring fees.</p>
    </div>
</body>
</html>
