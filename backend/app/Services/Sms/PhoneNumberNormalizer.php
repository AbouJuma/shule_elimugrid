<?php

namespace App\Services\Sms;

class PhoneNumberNormalizer
{
    /**
     * Normalizes a locally-entered phone number to the plain
     * international format SMS gateways expect (no "+", no leading
     * zero): "0712345678" -> "255712345678". Already-international
     * numbers ("255712345678" or "+255712345678") pass through
     * unchanged (leading "+" stripped).
     */
    public static function normalize(string $phone, string $defaultCountryCode): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, $defaultCountryCode)) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return $defaultCountryCode.substr($digits, 1);
        }

        return $defaultCountryCode.$digits;
    }
}
