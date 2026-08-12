<?php

namespace App\Auth;

use App\Models\Landlord\AgentUser;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class AgentAwareUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $user = parent::retrieveById($identifier);
        if ($user) return $user;
        return AgentUser::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $user = parent::retrieveByToken($identifier, $token);
        if ($user) return $user;
        $agentUser = AgentUser::find($identifier);
        return $agentUser && $agentUser->getRememberToken() === $token ? $agentUser : null;
    }

    public function updateRememberToken($user, $token): void
    {
        if ($user instanceof AgentUser) {
            $user->setRememberToken($token);
            $user->save();
            return;
        }
        parent::updateRememberToken($user, $token);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if ($user instanceof AgentUser) {
            return $this->hasher->check($credentials['password'], $user->getAuthPassword());
        }
        return parent::validateCredentials($user, $credentials);
    }
}
