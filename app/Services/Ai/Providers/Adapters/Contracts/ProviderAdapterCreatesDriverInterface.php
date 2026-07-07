<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Contracts;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use Laravel\Ai\Providers\Provider as Driver;

interface ProviderAdapterCreatesDriverInterface
{
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver;
}
