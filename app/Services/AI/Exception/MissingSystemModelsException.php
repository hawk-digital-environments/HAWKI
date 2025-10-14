<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


class MissingSystemModelsException extends AbstractMissingConfiguredModelsException
{
    protected function getListType(): string
    {
        return 'system';
    }
    
}
