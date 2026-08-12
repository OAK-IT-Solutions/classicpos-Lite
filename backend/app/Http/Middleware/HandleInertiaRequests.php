<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'paypal_client_id' => config('paypal.client_id'),
            'auth.user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'branch' => $user->relationLoaded('branch') && $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'business_type' => $user->branch->business_type,
                    'location' => $user->branch->location,
                    'timezone' => $user->branch->timezone,
                ] : null,
            ] : null,
        ];
    }
}
