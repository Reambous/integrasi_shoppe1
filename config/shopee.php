<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shopee Open Platform (Sandbox default)
    |--------------------------------------------------------------------------
    | Semua kredensial dibaca dari .env agar aman dan mudah diganti
    | saat pindah Sandbox -> Production maupun deploy cPanel.
    */
    'partner_id' => env('SHOPEE_PARTNER_ID'),
    'partner_key' => env('SHOPEE_PARTNER_KEY'),
    'api_base_url' => env('SHOPEE_API_BASE_URL', 'https://partner.test-stable.shopeemobile.com'),
    'auth_url' => env('SHOPEE_AUTH_URL', 'https://partner.test-stable.shopeemobile.com/api/v2/shop/auth_partner'),
    'redirect_url' => env('SHOPEE_REDIRECT_URL', 'http://localhost:8000/api/shopee/callback'),
    'webhook_secret' => env('SHOPEE_WEBHOOK_SECRET'),
    'is_sandbox' => env('SHOPEE_IS_SANDBOX', true),

    // Path API (tanpa host) — dipakai untuk base string signature
    'paths' => [
        'auth_partner' => '/api/v2/shop/auth_partner',
        'get_token' => '/api/v2/auth/token/get',
        'refresh_token' => '/api/v2/auth/access_token/get',
    ],
];
