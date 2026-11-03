<?php

namespace Omaacha\TwoFactor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthChallenge extends Model
{
    protected $table = 'auth_challenges';

    protected $fillable = [
        'challenge_token',
        'user_id',
        'type',
        'requires_email_verification',
        'is_setup_invitation',
        'invitation_secret',
        'invitation_qr_code',
        'payload',
        'expires_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'requires_email_verification' => 'boolean',
        'is_setup_invitation'         => 'boolean',
        'payload'                     => 'array',
        'expires_at'                  => 'datetime',
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

