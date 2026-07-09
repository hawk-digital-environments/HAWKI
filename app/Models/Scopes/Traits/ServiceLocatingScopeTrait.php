<?php
declare(strict_types=1);


namespace App\Models\Scopes\Traits;


use App\Services\System\Container\ServiceLocator;

trait ServiceLocatingScopeTrait
{
    protected ServiceLocator $serviceLocator;

    public function initializeServiceLocatingScopeTrait(ServiceLocator $serviceLocator): void
    {
        $this->serviceLocator = $serviceLocator;
    }
}
