# omaacha/2fa

<p align="center">
  <img src="https://raw.githubusercontent.com/omaacha/2fa/main/art/banner.png" alt="omaacha/2fa Banner" width="100%" onerror="this.style.display='none'">
</p>

<p align="center">
  <a href="https://packagist.org/packages/omaacha/2fa"><img src="https://img.shields.io/packagist/v/omaacha/2fa.svg?style=for-the-badge&logo=packagist" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/omaacha/2fa"><img src="https://img.shields.io/packagist/dt/omaacha/2fa.svg?style=for-the-badge&logo=packagist" alt="Total Downloads"></a>
  <a href="https://github.com/omaacha/2fa/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg?style=for-the-badge" alt="License"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.1%20|%208.2%20|%208.3-777BB4.svg?style=for-the-badge&logo=php" alt="PHP Version"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-10.x%20|%2011.x-FF2D20.svg?style=for-the-badge&logo=laravel" alt="Laravel Version"></a>
</p>

<p align="center">
  <strong>Enterprise-grade, zero-dependency Multi-Factor Authentication (MFA / 2FA) engine for Laravel REST APIs and Web Applications.</strong>
</p>

---

## 📖 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
  - [1. User Model Configuration](#1-user-model-configuration)
  - [2. Token Issuer (Decoupled Auth)](#2-token-issuer-decoupled-auth)
- [Route Protection & Sudo Mode](#-route-protection--sudo-mode)
  - [Sudo Mode Middleware (`2fa.sudo`)](#sudo-mode-middleware-2fasudo)
  - [Enforce 2FA Middleware (`2fa.enforce`)](#enforce-2fa-middleware-2faenforce)
- [Multi-Channel OTP Drivers](#-multi-channel-otp-drivers)
- [API Endpoints Reference](#-api-endpoints-reference)
- [Configuration Reference](#-configuration-reference)
- [Localization](#-localization)
- [Testing](#-testing)
- [Security](#-security)
- [License](#-license)

---

## ✨ Features

- **Standard RFC 6238 TOTP Engine:** Pure PHP implementation compatible with Microsoft Authenticator, Google Authenticator, 1Password, Authy, and Bitwarden.
- **Pure Vector SVG QR Code:** Built-in Nayuki-based QR Code generator. **Zero external C-extensions required** — no `ext-gd` or `ext-imagick` needed.
- **Multi-Channel OTP Driver Architecture:** Send one-time codes via **Email**, **Laravel Notifications**, **SMS (Twilio / Vonage)**, or **WhatsApp** using the built-in driver manager.
- **Sudo Mode Middleware (`2fa.sudo`):** Protect sensitive operations (e.g. deleting assets, altering user roles, exporting tenant data) by demanding fresh 2FA re-confirmation within a configurable window (15 mins).
- **Mandatory 2FA Policy Middleware (`2fa.enforce`):** Block access to critical route groups unless 2FA is activated on the user's account.
- **Hashed Single-Use Recovery Codes:** Generates 10 cryptographically random, formatted recovery codes (`XXXX-XXXX`) securely stored with SHA-256 hashes.
- **Trusted Devices ("Remember This Device"):** Secure 14-day persistent HttpOnly cookie tokens bound to client fingerprints, with active device tracking, expiration, and user-initiated revocation.
- **Anti-Enumeration Protection:** Silent failure for invalid recovery/email requests to prevent email harvesting.
- **24-Hour Non-2FA Grace Window:** Seamless fallback email verification with configurable grace period before re-prompting.
- **Pre-Activation Ownership Verification:** Forces verification via email before allowing a user to register an authenticator app.
- **Agnostic Token Issuer:** Decoupled from any specific auth library — works seamlessly with **custom JWTs**, **Laravel Sanctum**, **Laravel Passport**, or session guards.
- **Multilingual Out of the Box:** Shipped with complete translations for **English**, **French**, **Spanish**, and **Arabic**.

---

## 📋 Requirements

| Dependency | Supported Versions |
| :--- | :--- |
| **PHP** | `^8.1`, `^8.2`, `^8.3` |
| **Laravel Framework** | `^10.0`, `^11.0` |

---

## 🚀 Installation

### 1. Require via Composer

```bash
composer require omaacha/2fa
```

### 2. Publish Configuration & Migrations

```bash
# Publish configuration
php artisan vendor:publish --tag=two-factor-config

# Publish migration stubs
php artisan vendor:publish --tag=two-factor-migrations

# Run the database migrations
php artisan migrate
```

---

## 🛠️ Quick Start

### 1. User Model Configuration

Implement `TwoFactorAuthenticatable` and include `HasTwoFactorAuthentication` in your authenticatable model (typically `App\Models\User`):

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Omaacha\TwoFactor\Traits\HasTwoFactorAuthentication;

class User extends Authenticatable implements TwoFactorAuthenticatable
{
    use HasTwoFactorAuthentication;

    // ...
}
```

### 2. Token Issuer (Decoupled Auth)

The package never imposes a token library on your app. When a user completes a 2FA challenge, the package delegates token creation to your `TwoFactorTokenIssuer` implementation.

Register the contract in your `AppServiceProvider.php` (or `AuthServiceProvider.php`):

#### Using Laravel Sanctum:
```php
use Omaacha\TwoFactor\Contracts\TwoFactorTokenIssuer;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Illuminate\Http\Request;

public function register(): void
{
    $this->app->bind(TwoFactorTokenIssuer::class, function () {
        return new class implements TwoFactorTokenIssuer {
            public function issueToken(TwoFactorAuthenticatable $user, Request $request): array {
                $token = $user->createToken('auth_token')->plainTextToken;

                return [
                    'payload' => [
                        'token' => $token,
                        'user'  => $user,
                    ],
                ];
            }
        };
    });
}
```

#### Using Custom JWT (e.g. `php-open-source-saver/jwt-auth` or `tymon/jwt-auth`):
```php
$this->app->bind(TwoFactorTokenIssuer::class, function () {
    return new class implements TwoFactorTokenIssuer {
        public function issueToken(TwoFactorAuthenticatable $user, Request $request): array {
            $token = auth('api')->login($user);

            return [
                'payload' => [
                    'token' => $token,
                    'user'  => $user,
                ],
            ];
        }
    };
});
```

---

## 🛡️ Route Protection & Sudo Mode

### Sudo Mode Middleware (`2fa.sudo`)

Require the user to have verified a 2FA code within the last 15 minutes before executing high-risk operations:

```php
Route::delete('/api/v1/projects/{id}', [ProjectController::class, 'destroy'])
    ->middleware(['auth:api', '2fa.sudo']);
```

If the Sudo confirmation has expired, the request returns `HTTP 423 (Locked)`:
```json
{
    "message": "Two-factor confirmation required for this sensitive action.",
    "two_factor_sudo_required": true
}
```

To unlock Sudo mode, send the 6-digit authenticator code or recovery code to:
```http
POST /api/v1/auth/2fa/sudo/confirm
Content-Type: application/json

{
    "code": "123456"
}
```

### Enforce 2FA Middleware (`2fa.enforce`)

Require users to have 2FA enabled to access entire areas:

```php
Route::group(['prefix' => 'admin', 'middleware' => ['auth:api', '2fa.enforce']], function () {
    // Only accessible if 2FA is active
});
```

---

## 📱 Multi-Channel OTP Drivers

By default, verification OTP codes are sent via **Email**. You can switch or extend delivery channels seamlessly.

### Switch Channel in `.env`:
```env
TWO_FACTOR_OTP_CHANNEL=mail # Options: "mail", "notification", or custom driver
```

### Extending with Custom Drivers (e.g., Twilio SMS):

In `AppServiceProvider.php`:
```php
use Omaacha\TwoFactor\Channels\OtpChannelManager;
use Omaacha\TwoFactor\Channels\Contracts\OtpChannelDriver;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;

public function boot(): void
{
    app(OtpChannelManager::class)->extend('sms', function ($app) {
        return new class implements OtpChannelDriver {
            public function send(TwoFactorAuthenticatable $user, string $code, int $ttlMinutes): bool {
                // Example: Send SMS using Twilio Client
                $twilio = new \Twilio\Rest\Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
                $twilio->messages->create($user->phone, [
                    'from' => env('TWILIO_FROM'),
                    'body' => "Your security verification code is {$code}. Valid for {$ttlMinutes} minutes.",
                ]);
                return true;
            }
        };
    });
}
```

---

## 📡 API Endpoints Reference

All routes are prefixed under `/api/v1/auth/2fa` by default:

### Challenge Resolution (Unauthenticated)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/verify` | Verify 6-digit TOTP code during sign-in |
| `POST` | `/email/request` | Request an email OTP code |
| `POST` | `/email/verify` | Verify email OTP code |
| `POST` | `/recovery/verify` | Consume a single-use recovery code |
| `POST` | `/invitation/skip` | Skip 2FA onboarding (activates 24h grace window) |
| `POST` | `/invitation/confirm` | Confirm 2FA onboarding via TOTP |

### User Management (Authenticated)
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/status` | Get 2FA status, devices count, and recovery codes remaining |
| `POST` | `/ownership/request` | Request an email OTP before enabling 2FA |
| `POST` | `/ownership/verify` | Verify ownership OTP code |
| `POST` | `/enable` | Generate secret key and SVG QR code |
| `POST` | `/confirm` | Confirm setup with authenticator code & get recovery codes |
| `POST` | `/disable` | Disable 2FA (requires password + code) |
| `POST` | `/recovery-codes/regenerate` | Regenerate all 10 recovery codes |
| `GET` | `/devices` | List active trusted devices |
| `DELETE` | `/devices/{id}` | Revoke a single trusted device |
| `DELETE` | `/devices` | Revoke all trusted devices |
| `POST` | `/sudo/confirm` | Confirm 2FA code to unlock Sudo Mode |

---

## ⚙️ Configuration Reference

Inspect `config/two-factor.php` for all customizable settings:

```php
return [
    'user_model'         => env('TWO_FACTOR_USER_MODEL', App\Models\User::class),
    'issuer'             => env('TWO_FACTOR_ISSUER', config('app.name', 'Laravel')),
    'otp_channel'        => env('TWO_FACTOR_OTP_CHANNEL', 'mail'),
    'challenge_ttl'      => env('TWO_FACTOR_CHALLENGE_TTL', 15),       // minutes
    'email_otp_ttl'      => env('TWO_FACTOR_EMAIL_OTP_TTL', 5),         // minutes
    'email_otp_cooldown' => env('TWO_FACTOR_EMAIL_OTP_COOLDOWN', 60),   // seconds
    'grace_period_hours' => env('TWO_FACTOR_GRACE_PERIOD_HOURS', 24),   // hours
    'sudo_mode_ttl'      => env('TWO_FACTOR_SUDO_MODE_TTL', 15),        // minutes

    'trusted_devices' => [
        'enabled'     => env('TWO_FACTOR_TRUSTED_DEVICES_ENABLED', true),
        'ttl_days'    => env('TWO_FACTOR_DEVICE_TTL_DAYS', 14),
        'max_per_user'=> env('TWO_FACTOR_MAX_DEVICES_PER_USER', 3),
        'cookie_name' => env('TWO_FACTOR_COOKIE_NAME', 'omaacha_device_trust'),
    ],

    'routes' => [
        'prefix'          => 'api/v1/auth/2fa',
        'middleware'      => ['api'],
        'auth_middleware' => ['api'],
    ],
];
```

---

## 🌐 Localization

Publish translation files:
```bash
php artisan vendor:publish --tag=two-factor-translations
```

Translations are available in:
- 🇬🇧 English (`en`)
- 🇫🇷 French (`fr`)
- 🇪🇸 Spanish (`es`)
- 🇸🇦 Arabic (`ar`)

---

## 🧪 Testing

```bash
composer test
```

---

## 🔒 Security

If you discover a security vulnerability within **`omaacha/2fa`**, please send an email to [maacha98otmane@gmail.com](mailto:maacha98otmane@gmail.com). All security vulnerabilities will be promptly addressed.

---

## 👤 Credits

- **[Otmane Maacha](https://github.com/omaacha)** - Creator & Maintainer
- Built with standard RFC 6238 / RFC 4226 algorithms and Nayuki vector SVG.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.