<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools\Contracts;


interface SettingsAwareToolInterface
{
    public function setSettings(array $settings): void;
}
