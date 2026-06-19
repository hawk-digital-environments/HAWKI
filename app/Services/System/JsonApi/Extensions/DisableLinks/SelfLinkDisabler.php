<?php
declare(strict_types=1);


namespace App\Services\System\JsonApi\Extensions\DisableLinks;


use LaravelJsonApi\Core\Schema\Schema;

class SelfLinkDisabler
{
    public static function setSelfLinkDisabledIfNotManuallySetToTrue(Schema $schema): void
    {
        $ref = (new \ReflectionObject($schema))->getProperty('selfLink');
        if ($ref->getDeclaringClass()->getName() === Schema::class) {
            $ref->setValue($schema, false);
        }
    }
}
