<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
                'tls' => [
                    'local_cert' => env('SSL_CERTIFICATE'),
                    'local_pk' => env('SSL_CERTIFICATE_KEY'),
                    'verify_peer' => false,
                ],
            ],
        ],

        // 'pusher' => [
        //     'driver' => 'pusher',
        //     'key' => env('REVERB_APP_KEY'),
        //     'secret' => env('REVERB_APP_SECRET'),
        //     'app_id' => env('REVERB_APP_ID'),
        //     'options' => [
        //         'cluster' => env('REVERB_APP_CLUSTER'),
        //         'useTLS' => (env('REVERB_SCHEME', 'http') === 'https'),
        //         'host' => env('REVERB_SERVER_HOST', '127.0.0.1'),
        //         'port' => env('REVERB_SERVER_PORT', 6001),
        //         'scheme' => env('REVERB_SCHEME', 'http'),
        //     ],
        // ],

        // 'ably' => [
        //     'driver' => 'ably',
        //     'key' => env('ABLY_KEY'),
        // ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
