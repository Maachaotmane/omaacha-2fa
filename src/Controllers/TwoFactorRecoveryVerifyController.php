<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Services\TwoFactorService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorRecoveryVerifyController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'recovery_code'   => 'required|string',
            'trust_device'    => 'sometimes|boolean',
            'device_name'     => 'nullable|string|max:100',
        ]);

        try {
            $result = $this->twoFactorService->verifyLoginRecoveryCode(
                $request->input('challenge_token'),
                $request->input('recovery_code'),
                $request->boolean('trust_device', false),
                $request->input('device_name'),
                $request
            );

            $response = response()->json($result['payload'], Response::HTTP_OK);
            if (!empty($result['cookie'])) {
                $response->withCookie($result['cookie']);
            }

            return $response;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('two-factor::messages.generic_request_error')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

