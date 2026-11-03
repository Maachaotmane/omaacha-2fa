<?php

namespace Omaacha\TwoFactor\Contracts;

use Illuminate\Http\Request;

interface TwoFactorTokenIssuer
{
    /**
     * Create and return the host app's authentication token and payload upon successful 2FA.
     *
     * @return array{payload: array, cookie?: \Symfony\Component\HttpFoundation\Cookie}
     */
    public function issueToken(TwoFactorAuthenticatable $user, Request $request): array;
}

