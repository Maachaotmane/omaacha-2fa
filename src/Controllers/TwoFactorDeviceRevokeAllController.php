<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Managers\TrustedDeviceManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorDeviceRevokeAllController extends Controller
{
    public function __construct(
        private TrustedDeviceManager $trustedDeviceManager
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $count = $this->trustedDeviceManager->revokeAllDevices($user);

            return response()->json([
                'message'       => 'All trusted devices revoked successfully.',
                'revoked_count' => $count,
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('two-factor::messages.generic_request_error')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

