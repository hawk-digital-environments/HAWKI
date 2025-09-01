<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\AppSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Define Reverb settings to be added
        $reverbSettings = [
            [
                'key' => 'reverb_servers_reverb_host',
                'value' => env('REVERB_SERVER_HOST', '0.0.0.0'),
                'source' => 'reverb',
                'group' => 'websockets',
                'type' => 'string',
                'description' => 'Reverb server host (usually 0.0.0.0 for all interfaces)',
                'is_private' => false,
            ],
            [
                'key' => 'reverb_servers_reverb_port',
                'value' => env('REVERB_SERVER_PORT', 8080),
                'source' => 'reverb',
                'group' => 'websockets',
                'type' => 'integer',
                'description' => 'Reverb server port (default: 8080)',
                'is_private' => false,
            ],
            [
                'key' => 'reverb_servers_reverb_hostname',
                'value' => env('REVERB_HOST'),
                'source' => 'reverb',
                'group' => 'websockets',
                'type' => 'string',
                'description' => 'Reverb hostname for client connections',
                'is_private' => false,
            ],
            [
                'key' => 'reverb_apps_apps_0_options_host',
                'value' => env('REVERB_HOST'),
                'source' => 'reverb',
                'group' => 'websockets',
                'type' => 'string',
                'description' => 'Reverb client host (for WebSocket connections)',
                'is_private' => false,
            ],
            [
                'key' => 'reverb_apps_apps_0_options_port',
                'value' => env('REVERB_PORT', 443),
                'source' => 'reverb',
                'group' => 'websockets',
                'type' => 'integer',
                'description' => 'Reverb client port (default: 443 for HTTPS)',
                'is_private' => false,
            ],
            [
                'key' => 'reverb_apps_apps_0_options_scheme',
                'value' => env('REVERB_SCHEME', 'https'),
                'source' => 'reverb',
                'group' => 'websockets',
                'type' => 'string',
                'description' => 'Reverb scheme (http or https)',
                'is_private' => false,
            ],
        ];

        // Insert settings if they don't exist
        foreach ($reverbSettings as $setting) {
            AppSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Reverb settings
        $reverbKeys = [
            'reverb_servers_reverb_host',
            'reverb_servers_reverb_port',
            'reverb_servers_reverb_hostname',
            'reverb_apps_apps_0_options_host',
            'reverb_apps_apps_0_options_port',
            'reverb_apps_apps_0_options_scheme',
        ];

        AppSetting::whereIn('key', $reverbKeys)->delete();
    }
};
