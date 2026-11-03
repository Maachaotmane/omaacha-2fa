<?php

namespace Omaacha\TwoFactor\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Omaacha\TwoFactor\Services\TwoFactorService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserDisableTwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        try {
            $userModel = config('two-factor.user_model');
            $targetUser = $userModel::find($id);

            if (!$targetUser instanceof TwoFactorAuthenticatable) {
                return response()->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
            }

            if (!$targetUser->hasTwoFactorEnabled() && empty($targetUser->getTwoFactorSecret())) {
                return response()->json([
                    'message' => __('two-factor::messages.two_factor_not_enabled') ?? 'Two-factor is not enabled.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->twoFactorService->adminDisable($targetUser);

            return response()->json([
                'message' => __('two-factor::messages.two_factor_disabled_successfully') ?? 'Two-factor authentication disabled successfully.',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('two-factor::messages.generic_request_error')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

