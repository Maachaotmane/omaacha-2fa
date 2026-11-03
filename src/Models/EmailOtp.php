<?php

namespace Omaacha\TwoFactor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailOtp extends Model
{
    protected $table = 'email_otps';

    protected $fillable = [
        'user_id',
        'challenge_id',
        'code_hash',
        'expires_at',
        'used_at',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'attempts'   => 'integer',
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
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}

