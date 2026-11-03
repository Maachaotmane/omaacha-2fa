<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Services\TwoFactorService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorEmailRequestController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'email'           => 'nullable|string',
        ]);

        try {
            $result = $this->twoFactorService->requestEmailOtp(
                $request->input('challenge_token'),
                $request->input('email')
            );

            return response()->json($result, Response::HTTP_OK);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_TOO_MANY_REQUESTS);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('two-factor::messages.generic_request_error')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

