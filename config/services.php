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
    |--------------------------------------------------------------------------
    | RajaOngkir (Tipe PRO / District) — PRD §7.1
    |--------------------------------------------------------------------------
    | Base URL diabstraksikan agar mudah dialihkan ke endpoint Komerce yang
    | menggantikan pro.rajaongkir.com. ID kecamatan asal gudang TIDAK di sini —
    | dikelola via setting `origin_district_id` (tabel `settings`, halaman admin).
    */
    'rajaongkir' => [
        'key' => env('RAJAONGKIR_API_KEY'),
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://pro.rajaongkir.com/api'),
        // Aktifkan driver mock saat API key kosong agar checkout dapat diuji lokal.
        'mock' => env('RAJAONGKIR_MOCK', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Brevo (Transactional Email via Queue) — PRD §7.2
    |--------------------------------------------------------------------------
    */
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'base_url' => env('BREVO_BASE_URL', 'https://api.brevo.com/v3'),
        'sender_name' => env('BREVO_SENDER_NAME', 'CV. Jajar Wayang'),
        'sender_email' => env('BREVO_SENDER_EMAIL', 'noreply@jajarwayang.com'),
        'admin_email' => env('BREVO_ADMIN_EMAIL', 'gudang@jajarwayang.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Midtrans Snap (Payment Gateway)
    |--------------------------------------------------------------------------
    | Snap token dibuat dari backend memakai Server Key. Frontend hanya
    | menerima snap_token/redirect_url. URL sandbox/production dipilih otomatis
    | berdasarkan flag is_production.
    */
    'midtrans' => [
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'sanitize' => env('MIDTRANS_SANITIZE', true),
        'three_ds' => env('MIDTRANS_3DS', true),

        'snap_sandbox_url' => env('MIDTRANS_SNAP_SANDBOX_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions'),
        'snap_production_url' => env('MIDTRANS_SNAP_PRODUCTION_URL', 'https://app.midtrans.com/snap/v1/transactions'),

        'api_sandbox_url' => env('MIDTRANS_API_SANDBOX_URL', 'https://api.sandbox.midtrans.com'),
        'api_production_url' => env('MIDTRANS_API_PRODUCTION_URL', 'https://api.midtrans.com'),

        'snap_js_sandbox' => 'https://app.sandbox.midtrans.com/snap/snap.js',
        'snap_js_production' => 'https://app.midtrans.com/snap/snap.js',
    ],

];
