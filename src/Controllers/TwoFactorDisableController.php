<?php

namespace Omaacha\TwoFactor\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omaacha\TwoFactor\Services\TwoFactorService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwoFactorDisableController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'code'     => 'required|string',
        ]);

        try {
            $user = $request->user();
            $this->twoFactorService->disable(
                $user,
                $request->input('password'),
                $request->input('code')
            );

            $forgetCookie = $this->twoFactorService->getStatus($user);

            return response()->json([
                'message' => __('two-factor::messages.two_factor_disabled_successfully') ?? 'Two-factor authentication disabled successfully.',
            ], Response::HTTP_OK);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => __('two-factor::messages.generic_request_error')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

