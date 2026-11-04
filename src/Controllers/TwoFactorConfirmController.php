<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Services\TwoFactorService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorConfirmController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'code'         => 'required|string|size:6',
            'trust_device' => 'nullable|boolean',
            'device_name'  => 'nullable|string|max:255',
        ]);

        try {
            $user = $request->user();
            $result = $this->twoFactorService->confirm(
                $user,
                $request->input('code'),
                $request->boolean('trust_device'),
                $request->input('device_name'),
                $request
            );

            $cookie = $result['cookie'] ?? null;
            unset($result['cookie']);

            $response = response()->json($result, Response::HTTP_OK);
            if ($cookie) {
                $response->withCookie($cookie);
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

