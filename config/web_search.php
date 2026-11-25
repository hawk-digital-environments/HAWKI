<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Web Search Service
    |--------------------------------------------------------------------------
    |
    | Service key used when no explicit provider is specified.
    |
    */

    'default' => env('WEB_SEARCH_PROVIDER', ''),

    'services' => [

        /*
        |--------------------------------------------------------------------------
        | Brave Search
        |--------------------------------------------------------------------------
        |
        | Configuration for Brave Search API access.
        |
        */

        'brave' => [
            'api_key' => env('BRAVE_SEARCH_API_KEY', ''),
            'api_url' => env('BRAVE_SEARCH_API_URL', 'https://api.search.brave.com/res/v1/web/search'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Tavily
        |--------------------------------------------------------------------------
        |
        | Configuration for Tavily Search API access.
        |
        */

        'tavily' => [
            'api_key' => env('TAVILY_SEARCH_API_KEY', ''),
            'api_url' => env('TAVILY_SEARCH_API_URL', 'https://api.tavily.com/search'),
        ],
    ],

];
