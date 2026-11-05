<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * BEEM Africa bulk SMS gateway (East Africa, incl. Tanzania — this
     * deployment's initial market). 'driver' defaults to 'log' (writes
     * to storage/logs instead of a real network call) so the whole
     * feature works and is testable without a real Beem account. Set
     * SMS_DRIVER=beem and the credentials below once a real account
     * exists — nothing else about the feature changes.
     */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        // Used to normalize a locally-entered number ("0712345678") to
        // international format ("255712345678") before sending. Tanzania
        // by default since that's this deployment's initial market.
        'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '255'),
        'beem' => [
            // `?:` rather than env()'s own default: the .env template
            // pre-declares these keys blank for the user to fill in
            // later, and env('KEY', 'default') only falls back when the
            // key is entirely ABSENT — a present-but-empty value (the
            // template's starting state) is not, so it silently returns
            // '' instead of the intended default without this.
            'base_url' => env('BEEMS_BASE_URL') ?: 'https://apisms.beem.africa/v1',
            'api_key' => env('BEEMS_API_KEY'),
            'secret_key' => env('BEEMS_SECRET_KEY'),
            // No universal default is possible here — Beem rejects any sender ID
            // that isn't already registered and approved on the account behind
            // these credentials (confirmed live: an unregistered value fails with
            // "Invalid Sender ID", not a warning). 'INFO' is a placeholder guess,
            // not a working default; set BEEMS_SENDER_ID to a real approved one.
            'sender_id' => env('BEEMS_SENDER_ID') ?: 'INFO',
        ],
    ],

    /*
     * AzamPay (https://azampay.co.tz) mobile-money checkout — lets a
     * guardian pay an invoice from the parent app and have the status
     * update itself when the gateway confirms it, via a callback (see
     * routes/api.php's /payments/azampay/callback). 'driver' defaults to
     * 'log' (simulates an instant successful payment, no real account
     * needed) so the whole feature works and is testable without a real
     * AzamPay account. Set PAYMENT_GATEWAY_DRIVER=azampay and the sandbox
     * credentials below once a real AzamPay sandbox account exists —
     * nothing else about the feature changes.
     */
    'payment_gateway' => [
        'driver' => env('PAYMENT_GATEWAY_DRIVER', 'log'),
        'azampay' => [
            'auth_base_url' => env('AZAMPAY_AUTH_BASE_URL') ?: 'https://authenticator-sandbox.azampay.co.tz',
            'checkout_base_url' => env('AZAMPAY_CHECKOUT_BASE_URL') ?: 'https://sandbox.azampay.co.tz',
            'app_name' => env('AZAMPAY_APP_NAME'),
            'client_id' => env('AZAMPAY_CLIENT_ID'),
            'client_secret' => env('AZAMPAY_CLIENT_SECRET'),
            // NOT something we invent — copy the "Token" value shown on
            // AzamPay's own Register App dashboard, next to the Client
            // ID/Secret. Per their UI: "a secure API token for your
            // application to receive callback of the payment... you need
            // to validate this while receiving callback."
            'callback_token' => env('AZAMPAY_CALLBACK_TOKEN'),
        ],
    ],

    /*
     * AzamPay's Bill Pay product (a DIFFERENT product from the Checkout
     * API above) — lets a guardian pay by entering the student's control
     * number directly into any bank's or mobile network's own "pay bill"
     * menu, no app required. Unlike Checkout, there's no self-service
     * dashboard registration for this: per
     * https://developerdocs.azampay.co.tz/tanzania/bill-pay-api, a
     * business must email support@azampay.com to become a "biller", and
     * the shared secret is "shared privately" afterwards — not shown in
     * any dashboard. Fails closed (rejects every request) until that
     * secret is set, same pattern as every other driver here.
     */
    'bill_pay' => [
        'shared_secret' => env('AZAMPAY_BILLPAY_SHARED_SECRET'),
    ],

    /*
     * Jitsi Meet, for Virtual Classroom sessions. Defaults to their free
     * public server (meet.jit.si) — no account, credentials, or
     * self-hosting needed, same "works immediately with a safe default"
     * approach as SMS_DRIVER=log. Set JITSI_DOMAIN to a self-hosted
     * instance later if one exists; nothing else about the feature
     * changes. The public server has no built-in room password, which is
     * exactly why VirtualClass.room_name is a random unguessable token
     * rather than anything a client picks.
     */
    'jitsi' => [
        'domain' => env('JITSI_DOMAIN') ?: 'https://meet.jit.si',
    ],

];
