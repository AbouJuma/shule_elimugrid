<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public — AzamPay calls this, a guardian never does. No Sanctum/tenant
 * context exists here (AzamPay doesn't know what a "tenant" is), so this
 * stays outside the auth:sanctum/tenant route group and resolves the
 * school itself, from whatever transaction the payload matches.
 *
 * Protected by the per-app "Token" shown on AzamPay's own Register App
 * dashboard (config('services.payment_gateway.azampay.callback_token'))
 * — per AzamPay's own UI, this is "a secure API token for your
 * application to receive callback of the payment... you need to
 * validate this while receiving callback." Their docs don't say exactly
 * where they put it on the request, so this checks every plausible slot
 * (query, a couple of header names, a couple of body field names) and
 * — regardless of outcome — always logs the full raw headers/body first,
 * so the first real sandbox callback tells us exactly where it lives and
 * this can be tightened to that one spot.
 */
class PaymentGatewayCallbackController extends Controller
{
    public function azampay(Request $request, PaymentGatewayService $gateway)
    {
        Log::info('AzamPay callback received (raw, pre-validation)', [
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'query' => $request->query(),
            'body' => $request->all(),
        ]);

        $expected = config('services.payment_gateway.azampay.callback_token');

        $candidates = [
            $request->query('token'),
            $request->bearerToken(),
            $request->header('Token'),
            $request->header('X-Token'),
            $request->input('token'),
            $request->input('Token'),
        ];

        if (! $expected || ! in_array($expected, $candidates, true)) {
            Log::warning('AzamPay callback rejected — token not found in any known slot.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Not found.'], 404);
        }

        $gateway->handleCallback($request->all());

        // AzamPay only needs the 200 — the actual result lives in our own
        // transaction row, checked by the parent app's status-poll endpoint.
        return response()->json(['received' => true]);
    }
}
