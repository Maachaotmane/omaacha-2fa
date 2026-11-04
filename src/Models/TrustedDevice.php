<?php

namespace Omaacha\TwoFactor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Omaacha\TwoFactor\Traits\HasTwoFactorConnection;

class TrustedDevice extends Model
{
    use HasTwoFactorConnection;

    protected $table = 'trusted_devices';

    protected $fillable = [
        'user_id',
        'token_hash',
        'device_token_hash',
        'device_name',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        $userModel = config('two-factor.user_model');
        return $this->belongsTo($userModel, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }
}

