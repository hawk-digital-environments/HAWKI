<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsUser(User $user, array $abilities = ['*']): void
    {
        Sanctum::actingAs($user, $abilities);
        $user->withAccessToken(new \Laravel\Sanctum\TransientToken());
    }
}
