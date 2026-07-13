<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use LaravelJsonApi\Testing\MakesJsonApiRequests;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use MakesJsonApiRequests;

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
