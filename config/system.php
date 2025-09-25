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

];
