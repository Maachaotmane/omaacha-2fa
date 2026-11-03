<?php

namespace Omaacha\TwoFactor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    /**
     * Handle an incoming request.
     * Enforces that the user has Two-Factor Authentication active.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user instanceof TwoFactorAuthenticatable && !$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message'             => __('two-factor::messages.two_factor_required') ?? 'Two-factor authentication must be enabled to access this resource.',
                'two_factor_required' => true,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

