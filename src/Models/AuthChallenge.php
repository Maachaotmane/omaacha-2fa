<?php

namespace Omaacha\TwoFactor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Omaacha\TwoFactor\Traits\HasTwoFactorConnection;

class AuthChallenge extends Model
{
    use HasTwoFactorConnection;

    protected $table = 'auth_challenges';

    protected $fillable = [
        'user_id',
        'challenge_hash',
        'purpose',
        'failed_attempts',
        'is_consumed',
        'expires_at',
    ];

    protected $casts = [
        'failed_attempts' => 'integer',
        'is_consumed'     => 'boolean',
        'expires_at'      => 'datetime',
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

    public function getRequiresEmailVerificationAttribute(): bool
    {
        return $this->purpose === 'email_otp';
    }

    public function getIsSetupInvitationAttribute(): bool
    {
        return $this->purpose === 'invitation';
    }

    public function getInvitationSecretAttribute(): ?string
    {
        return Cache::get("2fa_inv_secret_{$this->id}");
    }

    public function getInvitationQrCodeAttribute(): ?string
    {
        return Cache::get("2fa_inv_qr_{$this->id}");
    }
}

