<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories\Attributes;

use Illuminate\Database\Eloquent\Model;

/**
 * Explicitly binds a repository class to a specific Eloquent model.
 *
 * Applied to repository classes that extend {@see AbstractRepository} when the automatic
 * model-name resolution (based on the repository class name or DocBlock) cannot find the
 * correct model class. This attribute takes priority over all other resolution strategies.
 *
 * Usage:
 * ```php
 * #[UseModel(User::class)]
 * class AccountRepository extends AbstractRepository
 * {
 *     // ...
 * }
 * ```
 *
 * @see \App\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTrait
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UseModel
{
    public function __construct(public string $class)
    {
    }
}
