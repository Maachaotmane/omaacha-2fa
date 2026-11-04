<?php

namespace Omaacha\TwoFactor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Omaacha\TwoFactor\Traits\HasTwoFactorConnection;

class EmailOtp extends Model
{
    use HasTwoFactorConnection;

    protected $table = 'email_otps';

    protected $fillable = [
        'user_id',
        'challenge_id',
        'otp_hash',
        'expires_at',
        'consumed_at',
        'failed_attempts',
    ];

    protected $casts = [
        'expires_at'      => 'datetime',
        'consumed_at'     => 'datetime',
        'failed_attempts' => 'integer',
    ];

    public function user(): BelongsTo
    {
        $userModel = config('two-factor.user_model');
        return $this->belongsTo($userModel, 'user_id');
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(AuthChallenge::class, 'challenge_id');
    }

    public function isValid(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}

