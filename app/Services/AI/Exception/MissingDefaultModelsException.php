<?php
declare(strict_types=1);


namespace App\Services\AI\Exception;


class MissingDefaultModelsException extends AbstractMissingConfiguredModelsException
{
    protected function getListType(): string
    {
        return 'default';
    }
}
