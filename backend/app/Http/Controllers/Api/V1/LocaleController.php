<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/settings/locale")]
class LocaleController extends Controller
{
    #[OA\Get(path: "/settings/locale", tags: ["Settings"], summary: "Get locale settings", responses: [new OA\Response(response: 200, description: "Locale settings")])]
    public function show(Request $request)
    {
        $profile = $request->user()->branch?->businessProfile;

        if (!$profile) {
            return response()->json([
                'error' => ['code' => 'ERR_NO_PROFILE', 'message' => 'Complete onboarding first.', 'details' => [], 'timestamp' => now()->toIso8601String()],
            ], 400);
        }

        $settings = $profile->settings ?? [];

        return response()->json([
            'locale' => [
                'currency' => $profile->currency,
                'timezone' => $profile->timezone,
                'date_format' => $settings['date_format'] ?? 'DD/MM/YYYY',
                'time_format' => $settings['time_format'] ?? '12h',
                'language' => $settings['language'] ?? 'en',
                'first_day_of_week' => $settings['first_day_of_week'] ?? 'monday',
                'decimal_separator' => $settings['decimal_separator'] ?? '.',
                'thousands_separator' => $settings['thousands_separator'] ?? 'comma',
                'currency_position' => $settings['currency_position'] ?? 'before',
                'decimal_places' => $settings['decimal_places'] ?? 2,
            ],
        ]);
    }

    #[OA\Put(path: "/settings/locale", tags: ["Settings"], summary: "Update locale settings", responses: [new OA\Response(response: 200, description: "Locale updated")])]
    public function update(Request $request)
    {
        $validated = $request->validate([
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:50',
            'date_format' => 'required|string|in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD',
            'time_format' => 'required|string|in:12h,24h',
            'language' => 'required|string|in:en,sw,fr',
            'first_day_of_week' => 'required|string|in:monday,sunday',
            'decimal_separator' => ['required', 'string', Rule::in(['.', 'comma'])],
            'thousands_separator' => ['required', 'string', Rule::in(['comma', 'dot', 'space'])],
            'currency_position' => 'required|string|in:before,after',
            'decimal_places' => 'required|integer|min:0|max:4',
        ]);

        $branch = $request->user()->branch;
        if (!$branch) {
            return response()->json([
                'error' => ['code' => 'ERR_NO_BRANCH', 'message' => 'No branch assigned.', 'details' => [], 'timestamp' => now()->toIso8601String()],
            ], 400);
        }

        $profile = $branch->businessProfile;
        if (!$profile) {
            return response()->json([
                'error' => ['code' => 'ERR_NO_PROFILE', 'message' => 'Complete onboarding first.', 'details' => [], 'timestamp' => now()->toIso8601String()],
            ], 400);
        }

        $profile->update([
            'currency' => $validated['currency'],
            'timezone' => $validated['timezone'],
            'settings' => [
                'date_format' => $validated['date_format'],
                'time_format' => $validated['time_format'],
                'language' => $validated['language'],
                'first_day_of_week' => $validated['first_day_of_week'],
                'decimal_separator' => $validated['decimal_separator'],
                'thousands_separator' => $validated['thousands_separator'],
                'currency_position' => $validated['currency_position'],
                'decimal_places' => $validated['decimal_places'],
            ],
        ]);

        $branch->update([
            'timezone' => $validated['timezone'],
        ]);

        $fresh = $profile->fresh();

        return response()->json([
            'message' => 'Locale settings updated successfully.',
            'locale' => [
                'currency' => $fresh->currency,
                'timezone' => $fresh->timezone,
                ...$validated,
            ],
        ]);
    }
}
