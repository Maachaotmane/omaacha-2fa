<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Services\TwoFactorService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorStatusController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $status = $this->twoFactorService->getStatus($user);

            return response()->json($status, Response::HTTP_OK);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => __('two-factor::messages.generic_request_error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

