<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillPayPayment;
use App\Services\BillPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Public — AzamPay's Bill Pay product calls these, not a guardian or a
 * school admin. Built against
 * https://developerdocs.azampay.co.tz/tanzania/bill-pay-api, which is NOT
 * verified live: it needs a separate "Biller" registration/shared secret
 * from AzamPay that this project doesn't have yet (the Checkout API
 * credentials used elsewhere are a different product and don't apply
 * here). Treat the JWT/Hash verification below as best-effort against the
 * docs, not confirmed working, same caveat the rest of this integration
 * shipped with before each piece got a live sandbox test.
 *
 * Every request is authenticated two ways per the docs: a short-lived
 * (120s) HS256 JWT in the Authorization header, and a `Hash` field in the
 * body (HMAC-SHA256 of the SHA-256 of the minified `Data` object) — both
 * signed with the same shared secret AzamPay gives privately. Fails
 * closed if that secret isn't configured.
 */
class BillPayController extends Controller
{
    public function __construct(protected BillPayService $billPay)
    {
    }

    public function nameLookup(Request $request)
    {
        if (! $this->authenticate($request)) {
            return $this->unauthorized();
        }

        $data = (array) $request->input('Data', []);
        $billIdentifier = $data['BillIdentifier'] ?? null;
        $result = $billIdentifier ? $this->billPay->lookup($billIdentifier) : null;

        if (! $result) {
            return response()->json([
                'Name' => null, 'BillAmount' => 0, 'BillIdentifier' => $billIdentifier,
                'Status' => 'Failed', 'Message' => 'No student found for this control number.', 'StatusCode' => 1,
            ]);
        }

        return response()->json([
            'Name' => $result['student']->fullName(),
            'BillAmount' => (float) $result['balance'],
            'BillIdentifier' => $billIdentifier,
            'Status' => 'Success',
            'Message' => 'Bill found.',
            'StatusCode' => 0,
        ]);
    }

    public function payment(Request $request)
    {
        if (! $this->authenticate($request)) {
            return $this->unauthorized();
        }

        $data = (array) $request->input('Data', []);
        Log::info('AzamPay Bill Pay payment received (raw)', ['data' => $data]);

        try {
            $billPayPayment = $this->billPay->pay(
                (string) ($data['BillIdentifier'] ?? ''),
                (string) ($data['Amount'] ?? '0'),
                (string) ($data['FspReferenceId'] ?? Str::uuid()),
                $data['PgReferenceId'] ?? null,
                $data['FspCode'] ?? null,
                $data['BillType'] ?? null,
                $data,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'MerchantReferenceId' => null, 'Status' => 'Failed', 'StatusCode' => 1,
                'Message' => $e->validator->errors()->first(),
            ]);
        }

        return response()->json([
            'MerchantReferenceId' => $billPayPayment->merchant_reference_id,
            'Status' => 'Success',
            'StatusCode' => 0,
            'Message' => 'Payment received.',
        ]);
    }

    public function statusCheck(Request $request)
    {
        if (! $this->authenticate($request)) {
            return $this->unauthorized();
        }

        $merchantReferenceId = $request->input('MerchantReferenceId') ?? $request->input('Data.MerchantReferenceId');
        $billPayPayment = BillPayPayment::allTenants()->where('merchant_reference_id', $merchantReferenceId)->first();

        if (! $billPayPayment) {
            return response()->json(['MerchantReferenceId' => $merchantReferenceId, 'Status' => 'Failed', 'StatusCode' => 1, 'Message' => 'Unknown reference.']);
        }

        return response()->json([
            'MerchantReferenceId' => $billPayPayment->merchant_reference_id,
            'Status' => $billPayPayment->status === 'completed' ? 'Success' : 'Failed',
            'StatusCode' => $billPayPayment->status === 'completed' ? 0 : 1,
            'Message' => $billPayPayment->message ?? 'OK',
        ]);
    }

    protected function authenticate(Request $request): bool
    {
        $secret = config('services.bill_pay.shared_secret');

        if (! $secret) {
            Log::warning('Bill Pay request rejected — AZAMPAY_BILLPAY_SHARED_SECRET not configured.', ['ip' => $request->ip()]);

            return false;
        }

        $token = $request->bearerToken();

        if (! $token || ! $this->verifyJwt($token, $secret)) {
            return false;
        }

        $data = $request->input('Data');
        $hash = $request->input('Hash');

        return $data !== null && $hash && $this->verifyHash($data, (string) $hash, $secret);
    }

    protected function verifyJwt(string $jwt, string $secret): bool
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');

        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        if (! is_array($claims) || ! isset($claims['exp'], $claims['iat'])) {
            return false;
        }

        // Docs: tokens are valid a maximum of 120 seconds.
        return time() <= $claims['exp'] && ($claims['exp'] - $claims['iat']) <= 125;
    }

    /** @param  array<string, mixed>  $data */
    protected function verifyHash(array $data, string $hash, string $secret): bool
    {
        $minified = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected = hash_hmac('sha256', hash('sha256', (string) $minified), $secret);

        return hash_equals(strtolower($expected), strtolower($hash));
    }

    protected function unauthorized()
    {
        return response()->json(['message' => 'Not found.'], 404);
    }
}
