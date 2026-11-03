<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Managers\TrustedDeviceManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorDeviceRevokeController extends Controller
{
    public function __construct(
        private TrustedDeviceManager $trustedDeviceManager
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $revoked = $this->trustedDeviceManager->revokeDevice($user, $id);

            if (!$revoked) {
                return response()->json(['message' => 'Device not found or already revoked.'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['message' => 'Device revoked successfully.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('two-factor::messages.generic_request_error')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

