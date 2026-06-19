<?php
declare(strict_types=1);

namespace Tests\Unit\Utils\DecoratorTraitTestFixtures;

class ExtendedParentModel extends ParentModel
{
    public string $extra = 'extra_value';
}
