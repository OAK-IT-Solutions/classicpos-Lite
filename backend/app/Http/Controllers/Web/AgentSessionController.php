<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Landlord\AgentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AgentSessionController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $agentUser = AgentUser::where('email', $validated['email'])->first();

        if (!$agentUser || !Hash::check($validated['password'], $agentUser->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.']);
        }

        if (!$agentUser->is_active) {
            return back()->withErrors(['email' => 'Your account is inactive.']);
        }

        Auth::guard('web')->login($agentUser);

        return redirect()->intended('/agent');
    }

    /**
     * Token-based callback from landing page login.
     * Validates a Sanctum token and creates a session.
     */
    public function callback(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return redirect('/agent/login')->withErrors(['error' => 'Missing token.']);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return redirect('/agent/login')->withErrors(['error' => 'Invalid or expired token.']);
        }

        $agentUser = $accessToken->tokenable;
        if (!$agentUser instanceof AgentUser) {
            return redirect('/agent/login')->withErrors(['error' => 'Invalid token.']);
        }

        if (!$agentUser->is_active) {
            return redirect('/agent/login')->withErrors(['error' => 'Account is inactive.']);
        }

        Auth::guard('web')->login($agentUser);

        return redirect('/agent');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/agent/login');
    }
}
