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
 * Protected by a shared-secret ?token= query param instead (see
 * config('services.payment_gateway.azampay.callback_token')) — AzamPay's
 * sandbox callback isn't signed, so without this anyone who found the URL
 * could POST a fake "payment successful" and get an invoice marked paid.
 */
class PaymentGatewayCallbackController extends Controller
{
    public function azampay(Request $request, PaymentGatewayService $gateway)
    {
        $expected = config('services.payment_gateway.azampay.callback_token');

        if (! $expected || $request->query('token') !== $expected) {
            Log::warning('AzamPay callback rejected — missing or wrong token.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Not found.'], 404);
        }

        Log::info('AzamPay callback received', ['payload' => $request->all()]);

        $gateway->handleCallback($request->all());

        // AzamPay only needs the 200 — the actual result lives in our own
        // transaction row, checked by the parent app's status-poll endpoint.
        return response()->json(['received' => true]);
    }
}
