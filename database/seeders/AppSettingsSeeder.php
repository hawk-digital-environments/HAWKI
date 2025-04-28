<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Basic Settings
        $this->createOrUpdateSetting('APP_NAME', 'HAWKI2', 'basic', 'string', 'Application name');
        $this->createOrUpdateSetting('APP_DEBUG', 'false', 'basic', 'boolean', 'Enable debug mode');
        $this->createOrUpdateSetting('APP_LOCALE', 'de_DE', 'basic', 'string', 'Application locale');
        
        // Authentication Settings
        $this->createOrUpdateSetting('AUTHENTICATION_METHOD', 'LDAP', 'authentication', 'string', 'Authentication method (LDAP/OIDC/Shibboleth)');
        $this->createOrUpdateSetting('TEST_USER_LOGIN', 'false', 'authentication', 'boolean', 'Enable test user login');
        $this->createOrUpdateSetting('SESSION_LIFETIME', '120', 'authentication', 'integer', 'Session lifetime in minutes');
        
        // API Settings
        $this->createOrUpdateSetting('ALLOW_EXTERNAL_COMMUNICATION', 'true', 'api', 'boolean', 'Allow external API communication');
        $this->createOrUpdateSetting('ALLOW_USER_TOKEN_CREATION', 'false', 'api', 'boolean', 'Allow users to create API tokens');
    }

    /**
     * Create or update a setting.
     *
     * @param string $key
     * @param string $value
     * @param string $group
     * @param string $type
     * @param string|null $description
     * @param bool $isPrivate
     * @return void
     */
    private function createOrUpdateSetting($key, $value, $group, $type, $description = null, $isPrivate = false)
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'type' => $type,
                'description' => $description,
                'is_private' => $isPrivate,
            ]
        );
    }
}
