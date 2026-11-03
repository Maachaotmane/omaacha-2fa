<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    | The model representing your authenticatable users. Must implement
    | Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable.
    */
    'user_model' => env('TWO_FACTOR_USER_MODEL', App\Core\Models\User\User::class),

    /*
    |--------------------------------------------------------------------------
    | Issuer Name
    |--------------------------------------------------------------------------
    | The title displayed in authenticator apps (e.g. "I-SENSE", "Acme").
    */
    'issuer' => env('TWO_FACTOR_ISSUER', config('app.name', 'I-SENSE')),

    /*
    |--------------------------------------------------------------------------
    | OTP Channel Driver
    |--------------------------------------------------------------------------
    | Supported: "mail", "notification", or any custom driver registered via
    | app(Omaacha\TwoFactor\Channels\OtpChannelManager::class)->extend('sms', ...)
    */
    'otp_channel' => env('TWO_FACTOR_OTP_CHANNEL', 'mail'),

    /*
    |--------------------------------------------------------------------------
    | Optional Notification Class
    |--------------------------------------------------------------------------
    | Used when otp_channel is set to 'notification'.
    */
    'notification_class' => env('TWO_FACTOR_NOTIFICATION_CLASS'),

    /*
    |--------------------------------------------------------------------------
    | Lifetimes & TTLs
    |--------------------------------------------------------------------------
    */
    'challenge_ttl'      => env('TWO_FACTOR_CHALLENGE_TTL', 15),       // minutes
    'email_otp_ttl'      => env('TWO_FACTOR_EMAIL_OTP_TTL', 5),         // minutes
    'email_otp_cooldown' => env('TWO_FACTOR_EMAIL_OTP_COOLDOWN', 60),   // seconds
    'grace_period_hours' => env('TWO_FACTOR_GRACE_PERIOD_HOURS', 24),   // hours before re-prompting non-2FA user
    'sudo_mode_ttl'      => env('TWO_FACTOR_SUDO_MODE_TTL', 15),        // minutes sudo authorization remains valid

    /*
    |--------------------------------------------------------------------------
    | Trusted Devices (Remember This Device)
    |--------------------------------------------------------------------------
    */
    'trusted_devices' => [
        'enabled'      => env('TWO_FACTOR_TRUSTED_DEVICES_ENABLED', true),
        'ttl_days'     => env('TWO_FACTOR_DEVICE_TTL_DAYS', 14),
        'max_per_user' => env('TWO_FACTOR_MAX_DEVICES_PER_USER', 3),
        'cookie_name'  => env('TWO_FACTOR_COOKIE_NAME', 'omaacha_device_trust'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'prefix'          => 'api/v1/auth/2fa',
        'middleware'      => ['api'],
        'auth_middleware' => ['api'],
    ],
];

