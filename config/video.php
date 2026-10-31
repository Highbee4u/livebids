<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Video Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "livekit", "null"
    |
    */
    'default' => env('VIDEO_DRIVER', 'livekit'),

    'drivers' => [

        'livekit' => [
            'api_url' => env('LIVEKIT_API_URL', 'http://localhost:7880'),
            'api_key' => env('LIVEKIT_API_KEY', ''),
            'api_secret' => env('LIVEKIT_API_SECRET', ''),
            'timeout' => (int) env('LIVEKIT_TIMEOUT', 10),
            'webhook_secret' => env('LIVEKIT_WEBHOOK_SECRET', ''),
        ],

        'null' => [
            // Returns mock tokens/sessions for local development and tests.
            'simulate_success' => env('VIDEO_SIMULATE_SUCCESS', true),
        ],

    ],

];
