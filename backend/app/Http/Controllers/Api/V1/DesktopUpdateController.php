<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Update Server — serves latest.json for Tauri Updater.
 *
 * GET /api/v1/desktop/updater/{target}/{arch}/{current_version}
 *
 * Returns 200 with update info if newer version available,
 * or 204 No Content if current version is latest.
 */
class DesktopUpdateController extends Controller
{
    private const GITHUB_REPO = 'anomalyco/poslavalel';
    private const UPDATE_CHANNEL = 'desktop';

    /**
     * Check for updates.
     * Tauri updater sends the current version and platform info.
     */
    public function check(
        string $target,
        string $arch,
        string $currentVersion,
        Request $request,
    ): JsonResponse {
        // Get latest release from GitHub API
        $latest = $this->getLatestRelease();

        if (!$latest) {
            return response()->json(['error' => 'Unable to check for updates'], 503);
        }

        $latestVersion = ltrim($latest['tag_name'] ?? '', 'v');
        $latestVersion = ltrim($latestVersion, self::UPDATE_CHANNEL . '-');

        // Compare versions
        if (version_compare($currentVersion, $latestVersion, '>=')) {
            return response()->json(null, 204); // No update available
        }

        // Find the asset for this platform
        $asset = $this->findAsset($latest, $target, $arch);

        if (!$asset) {
            return response()->json(null, 204);
        }

        return response()->json([
            'version' => $latestVersion,
            'notes' => $latest['body'] ?? 'Update available',
            'pub_date' => $latest['published_at'] ?? now()->toIso8601String(),
            'platforms' => [
                "{$target}-{$arch}" => [
                    'url' => $asset['browser_download_url'],
                    'signature' => $this->getSignature($asset),
                ],
            ],
        ]);
    }

    private function getLatestRelease(): ?array
    {
        $token = env('GITHUB_TOKEN', '');
        $url = "https://api.github.com/repos/" . self::GITHUB_REPO . "/releases/latest";

        $headers = ['Accept: application/vnd.github.v3+json'];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 200 && $response) {
            return json_decode($response, true);
        }

        return null;
    }

    private function findAsset(array $release, string $target, string $arch): ?array
    {
        $assets = $release['assets'] ?? [];

        foreach ($assets as $asset) {
            $name = strtolower($asset['name'] ?? '');

            // Windows
            if ($target === 'windows' && str_ends_with($name, '.nsis.zip')) {
                return $asset;
            }
            if ($target === 'windows' && str_ends_with($name, '-setup.exe')) {
                return $asset;
            }

            // macOS
            if ($target === 'darwin' && str_ends_with($name, '.app.tar.gz')) {
                if ($arch === 'aarch64' && str_contains($name, 'aarch64')) return $asset;
                if ($arch === 'x86_64' && (str_contains($name, 'x64') || str_contains($name, 'x86_64'))) return $asset;
            }

            // Linux
            if ($target === 'linux' && str_ends_with($name, '.appimage')) {
                return $asset;
            }
            if ($target === 'linux' && str_ends_with($name, '.AppImage.tar.gz')) {
                return $asset;
            }
        }

        return null;
    }

    private function getSignature(array $asset): string
    {
        // Try to download the .sig file
        $sigUrl = $asset['browser_download_url'] . '.sig';

        $response = @file_get_contents($sigUrl);
        if ($response !== false) {
            return trim($response);
        }

        return '';
    }
}
