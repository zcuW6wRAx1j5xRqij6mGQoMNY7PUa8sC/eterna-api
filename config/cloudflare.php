<?php


return [
    'r2'=>[
        'account_id'=>env('CLOUDFLARE_R2_ACCOUNT_ID',''),
        'account_key_id'=>env('CLOUDFLARE_R2_ACCOUNT_KEY_ID',''),
        'account_key_secret'=>env('CLOUDFLARE_R2_ACCOUNT_KEY_SECRET',''),
        'r2_url'=>env('CLOUDFLARE_R2_URL',''),
        'bucket'=>env('CLOUDFLARE_R2_BUCKET',''),
        'secret_key' => env('CLOUDFLARE_TRUSTSITE_SECRET_KEY', ''),
    ],

    'bot'=>[
        'secret'=>env('CLOUDFLARE_BOT_SECRET','0x4AAAAAABJirYDIzaV3Ub9d3KtRN3oXphU'),
    ],
];
