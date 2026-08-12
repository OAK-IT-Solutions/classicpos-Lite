<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'landlord';

    protected $casts = [
        'abilities' => 'json',
        'tokenable_id' => 'string',
    ];

    public static function findToken($token)
    {
        if (strpos($token, '|') === false) {
            $hash = hash('sha256', $token);

            $instance = (new static)->on('landlord')->where('token', $hash)->first();
            if ($instance) return $instance;

            return (new static)->on(config('database.default'))->where('token', $hash)->first();
        }

        [$id, $token] = explode('|', $token, 2);
        $hash = hash('sha256', $token);

        $instance = (new static)->on('landlord')->find($id);
        if ($instance && hash_equals($instance->token, $hash)) return $instance;

        $instance = (new static)->on(config('database.default'))->find($id);
        if ($instance && hash_equals($instance->token, $hash)) return $instance;

        return null;
    }
}
