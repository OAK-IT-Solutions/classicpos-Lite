<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * License Service — Offline-capable license validation for ClassicPOS Desktop.
 *
 * License key format: CPPOS-XXXX-XXXX-XXXX-XXXX
 * Payload (base64url-encoded): { business, device, expiry, features, issued }
 * Signature: HMAC-SHA256 of payload using secret key
 */
class LicenseService
{
    private const KEY_PREFIX = 'CPPOS-';
    private const SECRET_KEY = 'classicpos-offline-license-2026';
    private const SEPARATOR = '.';

    /**
     * Generate a new license key.
     */
    public static function generate(
        string $businessName,
        string $deviceId = '*',
        ?string $expiryDate = null,
        array $features = ['full_pos'],
    ): string {
        $payload = base64url_encode(json_encode([
            'business' => hash('sha256', strtolower(trim($businessName))),
            'device' => hash('sha256', $deviceId),
            'expiry' => $expiryDate,
            'features' => $features,
            'issued' => now()->toIso8601String(),
        ]));

        $signature = hash_hmac('sha256', $payload, self::SECRET_KEY);

        $code = strtoupper(substr(bin2hex(random_bytes(10)), 0, 16));
        $formatted = self::formatKey($code);

        return $formatted . self::SEPARATOR . $payload . self::SEPARATOR . substr($signature, 0, 16);
    }

    /**
     * Validate a license key offline.
     * Returns ['valid' => bool, 'data' => array|null, 'error' => string|null]
     */
    public static function validate(string $key, string $businessName = '', string $deviceId = '*'): array
    {
        $key = trim($key);

        // Format check: CPPOS-XXXX-XXXX-XXXX-XXXX.payload.signature
        $parts = explode(self::SEPARATOR, $key, 3);
        if (count($parts) !== 3) {
            return ['valid' => false, 'data' => null, 'error' => 'Invalid license key structure'];
        }

        $codePart = strtoupper($parts[0]);
        if (!preg_match('/^CPPOS-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $codePart)) {
            return ['valid' => false, 'data' => null, 'error' => 'Invalid license key format'];
        }

        $payload = $parts[1];
        $signature = strtolower($parts[2]);

        // Verify HMAC signature (case-insensitive hex comparison)
        $expectedSignature = substr(hash_hmac('sha256', $payload, self::SECRET_KEY), 0, 16);
        if (!hash_equals($expectedSignature, $signature)) {
            return ['valid' => false, 'data' => null, 'error' => 'Invalid license signature'];
        }

        // Decode payload
        $data = json_decode(base64url_decode($payload), true);
        if (!$data) {
            return ['valid' => false, 'data' => null, 'error' => 'Invalid license payload'];
        }

        // Check expiry
        if (!empty($data['expiry']) && strtotime($data['expiry']) < time()) {
            return ['valid' => false, 'data' => $data, 'error' => 'License has expired'];
        }

        // Check business name (if provided)
        if (!empty($businessName)) {
            $expectedBusiness = hash('sha256', strtolower(trim($businessName)));
            if ($data['business'] !== $expectedBusiness) {
                return ['valid' => false, 'data' => $data, 'error' => 'License is for a different business'];
            }
        }

        // Check device (if provided and not wildcard)
        if ($deviceId !== '*' && $data['device'] !== '*') {
            $expectedDevice = hash('sha256', $deviceId);
            if ($data['device'] !== $expectedDevice) {
                return ['valid' => false, 'data' => $data, 'error' => 'License is bound to a different device'];
            }
        }

        return [
            'valid' => true,
            'data' => $data,
            'error' => null,
        ];
    }

    /**
     * Get a device fingerprint (hardware-based identifier).
     */
    public static function getDeviceFingerprint(): string
    {
        $components = [];

        // Machine ID
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('wmic csproduct get UUID 2>nul');
            if ($output) {
                preg_match('/UUID\s+(\S+)/', $output, $matches);
                $components[] = $matches[1] ?? '';
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('ioreg -rd1 -c IOPlatformExpertDevice 2>/dev/null | grep IOPlatformUUID');
            if ($output) {
                preg_match('/"IOPlatformUUID"\s*=\s*"([^"]+)"/', $output, $matches);
                $components[] = $matches[1] ?? '';
            }
        } else {
            $output = shell_exec('cat /etc/machine-id 2>/dev/null || cat /var/lib/dbus/machine-id 2>/dev/null');
            $components[] = trim($output ?? '');
        }

        // MAC address as fallback
        if (empty($components[0])) {
            $interfaces = net_get_interfaces();
            foreach ($interfaces as $name => $info) {
                if ($name !== 'lo' && !empty($info['mac'])) {
                    $components[] = $info['mac'];
                    break;
                }
            }
        }

        $fingerprint = implode('-', array_filter($components));
        return hash('sha256', $fingerprint ?: 'default-device');
    }

    /**
     * Format a 16-char code into XXXX-XXXX-XXXX-XXXX.
     */
    private static function formatKey(string $code): string
    {
        $code = strtoupper(substr($code, 0, 16));
        return self::KEY_PREFIX
            . substr($code, 0, 4) . '-'
            . substr($code, 4, 4) . '-'
            . substr($code, 8, 4) . '-'
            . substr($code, 12, 4);
    }
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}
