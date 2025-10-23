<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Available Announcement Anchors
    |--------------------------------------------------------------------------
    |
    | Define all available anchors for announcements. Anchors are used to
    | trigger announcements at specific frontend events (e.g., first file upload).
    |
    | Each anchor should have:
    | - key: The anchor identifier used in code
    | - name: Human-readable name shown in admin interface
    | - description: Explanation of when this anchor is triggered
    |
    */

    'anchors' => [
        'firstupload' => [
            'name' => 'First File Upload',
            'description' => 'Triggered when user clicks file upload button for the first time',
        ],
        'firstchat' => [
            'name' => 'First Chat Message',
            'description' => 'Triggered when user sends their first chat message',
        ],
        'firstgroupchat' => [
            'name' => 'First Group Chat',
            'description' => 'Triggered when user joins or creates their first group chat',
        ],
        'firstmodel' => [
            'name' => 'First Model Selection',
            'description' => 'Triggered when user selects an AI model for the first time',
        ],
        // Add more anchors as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Announcement Settings
    |--------------------------------------------------------------------------
    |
    | Default values for new announcements
    |
    */

    'defaults' => [
        'type' => 'info',
        'is_forced' => false,
        'is_global' => true,
    ],

];
