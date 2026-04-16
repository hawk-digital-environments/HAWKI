<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\ServiceLocatorTraitTestFixtures;

class SampleService
{
    public function identify(): string
    {
        return 'real_service';
    }
}
