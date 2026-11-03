<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Services\TwoFactorService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorOwnershipVerifyController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        try {
            $user = $request->user();
            $this->twoFactorService->verifyOwnershipOtp($user, $request->input('code'));

            return response()->json([
                'message' => __('two-factor::messages.email_verified_successfully') ?? 'Email ownership verified successfully.',
            ], Response::HTTP_OK);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('two-factor::messages.generic_request_error')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

