<?php
declare(strict_types=1);


namespace App\Services\ExtApp;

use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class ExtAppContext
{
    private bool $isExternal = false;
    
    /**
     * Mark the current request as being made by an external app
     */
    public function markAsExternal(): void
    {
        $this->isExternal = true;
    }
    
    /**
     * Check if the current request has been made by an external app
     * @return bool
     */
    public function isExternal(): bool
    {
        return $this->isExternal;
    }
}
