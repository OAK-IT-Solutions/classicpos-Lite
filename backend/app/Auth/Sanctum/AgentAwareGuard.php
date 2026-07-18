<?php

namespace App\Auth\Sanctum;

use App\Models\Landlord\AgentUser;
use Laravel\Sanctum\Guard;

class AgentAwareGuard extends Guard
{
    protected function hasValidProvider($tokenable): bool
    {
        if (is_null($this->provider)) {
            return true;
        }

        $model = config("auth.providers.{$this->provider}.model");

        return $tokenable instanceof $model || $tokenable instanceof AgentUser;
    }
}
