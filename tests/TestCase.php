<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use LaravelJsonApi\Testing\MakesJsonApiRequests;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use MakesJsonApiRequests;

    protected function setUp(): void
    {
        parent::setUp();

        // The array cache store is not reset between test cases unless the
        // suite refreshes the application (RefreshDatabase et al.). Flush it
        // here so every test starts from a clean cache, regardless of trait
        // usage. This keeps production code free of test-aware bypasses.
        Cache::flush();
    }

    protected function jsonApiRaw(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers = array_merge([
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ], $headers);

        return $this->json($method, $uri, $data, $headers);
    }

    protected function actingAsUser(User $user, array $abilities = ['*']): void
    {
        Sanctum::actingAs($user, $abilities);
        $user->withAccessToken(new \Laravel\Sanctum\TransientToken());
    }
}
