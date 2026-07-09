<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\DecoratorTraitTestFixtures;

class ParentModel
{
    public static string $tag = 'original';
    public static string $uninitializedStatic; // intentionally never assigned a default
    public string $name;
    protected string $role;
    private string $secret;
    public string $uninitializedProp; // intentionally not initialized

    public function __construct(string $name = 'alice', string $role = 'user', string $secret = 'password')
    {
        $this->name = $name;
        $this->role = $role;
        $this->secret = $secret;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function identify(): string
    {
        return 'parent';
    }
}
