<?php
return [
    'base_url'        => env('CANVI_BASE_URL', 'https://api.canvi.com.br'), // ajuste conforme doc
    'api_key'         => env('CANVI_API_KEY'),
    'api_secret'      => env('CANVI_API_SECRET'), // se houver
    'timeout'         => env('CANVI_TIMEOUT', 10),
    'webhook' => [
        'signature_header' => env('CANVI_WEBHOOK_HEADER', 'X-Canvi-Signature'),
        'algorithm'        => env('CANVI_WEBHOOK_ALGO', 'sha256'),
        'secret'           => env('CANVI_WEBHOOK_SECRET'), // para HMAC
        // Caso a Canvi use assinatura assimétrica (RSA/ECDSA), troque para public key:
        'public_key'       => env('CANVI_WEBHOOK_PUBLIC_KEY'),
        'tolerance_secs'   => env('CANVI_WEBHOOK_TOLERANCE', 300), // proteção contra replays
    ],
];
