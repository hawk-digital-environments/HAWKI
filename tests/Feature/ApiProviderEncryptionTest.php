<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiProviderEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_is_encrypted_in_database(): void
    {
        // Create an API provider with a test key
        $testApiKey = 'sk-test123456789abcdef';
        
        $provider = ApiProvider::create([
            'provider_name' => 'Test Encryption Provider',
            'api_key' => $testApiKey,
            'base_url' => 'https://api.test.com',
            'is_active' => true,
            'display_order' => 1,
        ]);

        // Check that the API key is decrypted when accessed via Eloquent
        $this->assertEquals($testApiKey, $provider->api_key);

        // Check that the API key is encrypted in the database
        $rawProvider = DB::table('api_providers')->where('id', $provider->id)->first();
        $this->assertNotEquals($testApiKey, $rawProvider->api_key);
        $this->assertStringContainsString('eyJ', $rawProvider->api_key); // Base64 encoded encrypted data

        // Check that loading via Eloquent decrypts correctly
        $loadedProvider = ApiProvider::find($provider->id);
        $this->assertEquals($testApiKey, $loadedProvider->api_key);
    }

    public function test_empty_api_key_handling(): void
    {
        // Test with null API key
        $provider = ApiProvider::create([
            'provider_name' => 'Test Empty Key Provider',
            'api_key' => null,
            'base_url' => 'https://api.test.com',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->assertNull($provider->api_key);

        // Test with empty string - Laravel's encrypted cast will encrypt empty strings
        $provider2 = ApiProvider::create([
            'provider_name' => 'Test Empty String Provider',
            'api_key' => '',
            'base_url' => 'https://api.test.com',
            'is_active' => true,
            'display_order' => 2,
        ]);

        // Empty string gets encrypted and decrypted back to empty string
        $this->assertEquals('', $provider2->api_key);
        
        // Verify it's encrypted in database (not empty)
        $rawProvider2 = DB::table('api_providers')->where('id', $provider2->id)->first();
        $this->assertNotEquals('', $rawProvider2->api_key);
    }

    public function test_api_key_can_be_updated(): void
    {
        $originalKey = 'sk-original123';
        $newKey = 'sk-new456';

        // Create provider with original key
        $provider = ApiProvider::create([
            'provider_name' => 'Test Update Provider',
            'api_key' => $originalKey,
            'base_url' => 'https://api.test.com',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->assertEquals($originalKey, $provider->api_key);

        // Update the key
        $provider->update(['api_key' => $newKey]);

        $this->assertEquals($newKey, $provider->api_key);

        // Verify it's still encrypted in database
        $rawProvider = DB::table('api_providers')->where('id', $provider->id)->first();
        $this->assertNotEquals($newKey, $rawProvider->api_key);
        $this->assertStringContainsString('eyJ', $rawProvider->api_key);
    }
}
