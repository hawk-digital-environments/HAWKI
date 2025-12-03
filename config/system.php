<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Group Chat Configuration
    |--------------------------------------------------------------------------
    |
    | This option controls whether group chat functionality is enabled
    | in the application. When enabled, users can create and participate
    | in group chat rooms.
    |
    */

    'groupchat_active' => env('GROUPCHAT_ACTIVE', true),

    /*
    |--------------------------------------------------------------------------
    | AI Assistant Handle
    |--------------------------------------------------------------------------
    |
    | This setting defines the handle/name used for the AI assistant
    | in group chat conversations. This is how the AI will appear
    | to users in group chat rooms.
    |
    */

    'ai_handle' => env('AI_HANDLE', 'HAWKI Assistant'),

    /*
    |--------------------------------------------------------------------------
    | Stream Output Buffering
    |--------------------------------------------------------------------------
    |
    | This option controls whether output buffering is disabled for SSE streams.
    | Disabling buffering reduces latency for real-time streaming responses
    | but may have slight performance implications. Recommended: true for
    | production to ensure optimal user experience.
    |
    | When enabled:
    | - Output buffers are cleared before streaming
    | - Nginx buffering is disabled via X-Accel-Buffering header
    | - PHP output buffering and compression are disabled
    | - Reduces stream latency from ~3 seconds to < 0.5 seconds
    |
    | Can be changed via Orchid Admin Interface (System Settings)
    |
    */

    'disable_stream_buffering' => true,

    /*
    |--------------------------------------------------------------------------
    | Stream Performance Optimizations
    |--------------------------------------------------------------------------
    |
    | These settings control individual performance optimization techniques
    | for AI streaming responses. Each can be toggled independently to find
    | the optimal configuration for your environment.
    |
    | All optimizations together reduce response time from ~15s to ~0.011s
    |
    */

    /*
    | X-Accel-Buffering Header
    |--------------------------------------------------------------------------
    | Disables Nginx proxy buffering for this response via X-Accel-Buffering header.
    | This is the most critical optimization for reducing latency when using Nginx.
    | Impact: High (significant latency reduction with Nginx/reverse proxies)
    |
    */
    'stream_disable_nginx_buffering' => true,

    /*
    | Apache No-Gzip Environment Variable
    |--------------------------------------------------------------------------
    | Disables Apache's mod_deflate compression for streaming responses.
    | Only effective when running behind Apache web server.
    | Impact: Medium (reduces buffering on Apache servers)
    |
    */
    'stream_disable_apache_gzip' => true,

    /*
    | PHP Output Buffering
    |--------------------------------------------------------------------------
    | Disables PHP's internal output buffering via ini_set('output_buffering', 'off').
    | WARNING: May cause ~4 seconds lag in some configurations.
    | Only enable if you've tested and confirmed it improves performance.
    | Impact: Variable (can help or hurt, depending on environment)
    |
    */
    'stream_disable_php_output_buffering' => false,

    /*
    | PHP Zlib Compression
    |--------------------------------------------------------------------------
    | Disables PHP's zlib.output_compression for streaming responses.
    | Prevents PHP from compressing output before sending to client.
    | Impact: Medium (reduces compression overhead during streaming)
    |
    */
    'stream_disable_zlib_compression' => true,

];

