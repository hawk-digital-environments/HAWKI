<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories\Attributes;

use Illuminate\Database\Eloquent\Model;

#[\Attribute(\Attribute::TARGET_CLASS)]
class UseModel
{
    /**
     * @param class-string<Model> $class
     */
    public function __construct(public string $class)
    {
    }
}
