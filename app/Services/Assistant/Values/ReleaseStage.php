<?php

namespace App\Services\Assistant\Values;

enum ReleaseStage: string
{
    case DRAFT = 'draft';
    case PRIVATE = 'private';
    case ORGANIZATIONAL = 'organizational';
    case FEDERATED = 'federated';

    /**
     * Stages that are broadly visible to every authenticated user.
     *
     * @return list<string>
     */
    public static function publiclyVisibleValues(): array
    {
        return [
            self::ORGANIZATIONAL->value,
            self::FEDERATED->value,
        ];
    }
}
