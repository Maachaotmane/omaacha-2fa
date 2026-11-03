<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Managers\TrustedDeviceManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorDeviceListController extends Controller
{
    public function __construct(
        private TrustedDeviceManager $trustedDeviceManager
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $devices = $this->trustedDeviceManager->getActiveDevices($user);

            return response()->json([
                'devices' => $devices->map(function ($device) {
                    return [
                        'id'           => $device->id,
                        'device_name'  => $device->device_name,
                        'ip_address'   => $device->ip_address,
                        'last_used_at' => $device->last_used_at?->toIso8601String(),
                        'expires_at'   => $device->expires_at?->toIso8601String(),
                    ];
                }),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('two-factor::messages.generic_request_error')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

