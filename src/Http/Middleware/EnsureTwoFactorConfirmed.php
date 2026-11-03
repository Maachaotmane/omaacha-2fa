<?php

namespace Omaacha\TwoFactor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorConfirmed
{
    /**
     * Handle an incoming request.
     * Enforces Sudo Mode: the user must have verified 2FA recently (e.g. last 15 minutes).
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user instanceof TwoFactorAuthenticatable && $user->hasTwoFactorEnabled()) {
            $sudoKey = "2fa_sudo_confirmed_{$user->getAuthIdentifier()}";

            if (!Cache::has($sudoKey)) {
                return response()->json([
                    'message'                  => __('two-factor::messages.sudo_confirmation_required') ?? 'Two-factor confirmation required for this sensitive action.',
                    'two_factor_sudo_required' => true,
                ], Response::HTTP_LOCKED); // 423 Locked
            }
        }

        return $next($request);
    }
}

