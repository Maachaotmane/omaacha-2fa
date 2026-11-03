<?php

namespace Omaacha\TwoFactor\Facades;

use Illuminate\Support\Facades\Facade;
use Omaacha\TwoFactor\Services\TwoFactorService;

/**
 * @see \Omaacha\TwoFactor\Services\TwoFactorService
 */
class TwoFactor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TwoFactorService::class;
    }
}

