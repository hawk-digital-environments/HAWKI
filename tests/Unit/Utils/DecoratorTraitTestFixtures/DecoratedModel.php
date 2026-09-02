<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\DecoratorTraitTestFixtures;

use App\Utils\DecoratorTrait;

class DecoratedModel extends ParentModel
{
    use DecoratorTrait;

    public static string $tag = 'decorated_original';

    public static string $extraTag = 'untouched';

    private string $label;

    private readonly string $note;

    public function identify(): string
    {
        return 'decorated';
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
