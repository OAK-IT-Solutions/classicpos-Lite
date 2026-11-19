<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class DesktopStartupController extends Controller
{
    /**
     * GET /api/v1/desktop/startup/status
     *
     * Always returns a valid JSON response — even if the DB isn't migrated yet.
     * Used by the frontend to determine if Laravel is fully initialized.
     */
    public function status(): JsonResponse
    {
        try {
            $hasMigrated = Schema::hasTable('users');
        } catch (\Exception $e) {
            // Tables don't exist yet (migrations haven't run)
            $hasMigrated = false;
        }

        return response()->json([
            'initialized' => $hasMigrated,
        ]);
    }
}
