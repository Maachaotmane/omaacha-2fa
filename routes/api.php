<?php

use Illuminate\Support\Facades\Route;
use Omaacha\TwoFactor\Controllers\TwoFactorConfirmController;
use Omaacha\TwoFactor\Controllers\TwoFactorConfirmInvitationController;
use Omaacha\TwoFactor\Controllers\TwoFactorDeviceListController;
use Omaacha\TwoFactor\Controllers\TwoFactorDeviceRevokeAllController;
use Omaacha\TwoFactor\Controllers\TwoFactorDeviceRevokeController;
use Omaacha\TwoFactor\Controllers\TwoFactorDisableController;
use Omaacha\TwoFactor\Controllers\TwoFactorEmailRequestController;
use Omaacha\TwoFactor\Controllers\TwoFactorEmailVerifyController;
use Omaacha\TwoFactor\Controllers\TwoFactorEnableController;
use Omaacha\TwoFactor\Controllers\TwoFactorOwnershipRequestController;
use Omaacha\TwoFactor\Controllers\TwoFactorOwnershipVerifyController;
use Omaacha\TwoFactor\Controllers\TwoFactorRecoveryVerifyController;
use Omaacha\TwoFactor\Controllers\TwoFactorRegenerateRecoveryCodesController;
use Omaacha\TwoFactor\Controllers\TwoFactorSkipInvitationController;
use Omaacha\TwoFactor\Controllers\TwoFactorStatusController;
use Omaacha\TwoFactor\Controllers\TwoFactorSudoConfirmController;
use Omaacha\TwoFactor\Controllers\TwoFactorVerifyController;

$prefix = config('two-factor.routes.prefix', 'api/v1/auth/2fa');
$middleware = config('two-factor.routes.middleware', ['api']);
$authMiddleware = config('two-factor.routes.auth_middleware', ['api']);

Route::group(['prefix' => $prefix, 'middleware' => $middleware, 'as' => 'two-factor.'], function () use ($authMiddleware) {
    // Unauthenticated challenge resolution routes
    Route::post('/verify', TwoFactorVerifyController::class)->middleware('throttle:10,1')->name('verify');
    Route::post('/email/request', TwoFactorEmailRequestController::class)->middleware('throttle:5,1')->name('email.request');
    Route::post('/email/verify', TwoFactorEmailVerifyController::class)->middleware('throttle:10,1')->name('email.verify');
    Route::post('/recovery/verify', TwoFactorRecoveryVerifyController::class)->middleware('throttle:10,1')->name('recovery.verify');
    Route::post('/invitation/skip', TwoFactorSkipInvitationController::class)->middleware('throttle:10,1')->name('invitation.skip');
    Route::post('/invitation/confirm', TwoFactorConfirmInvitationController::class)->middleware('throttle:10,1')->name('invitation.confirm');

    // Authenticated management routes
    Route::group(['middleware' => $authMiddleware], function () {
        Route::get('/status', TwoFactorStatusController::class)->name('status');
        Route::post('/enable', TwoFactorEnableController::class)->name('enable');
        Route::post('/confirm', TwoFactorConfirmController::class)->name('confirm');
        Route::post('/disable', TwoFactorDisableController::class)->name('disable');
        Route::post('/recovery-codes/regenerate', TwoFactorRegenerateRecoveryCodesController::class)->name('recovery-codes.regenerate');

        Route::get('/devices', TwoFactorDeviceListController::class)->name('devices.list');
        Route::delete('/devices/{id}', TwoFactorDeviceRevokeController::class)->name('devices.revoke');
        Route::delete('/devices', TwoFactorDeviceRevokeAllController::class)->name('devices.revoke-all');
        Route::post('/devices/revoke-all', TwoFactorDeviceRevokeAllController::class)->name('devices.revoke-all-post');

        Route::post('/ownership/request', TwoFactorOwnershipRequestController::class)->name('ownership.request');
        Route::post('/ownership/verify', TwoFactorOwnershipVerifyController::class)->name('ownership.verify');

        // Sudo Mode Confirmation
        Route::post('/sudo/confirm', TwoFactorSudoConfirmController::class)->middleware('throttle:10,1')->name('sudo.confirm');
    });
});

