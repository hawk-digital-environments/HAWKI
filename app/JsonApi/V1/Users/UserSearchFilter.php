<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Users;

use App\Services\Users\Repositories\UserRepository;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

/**
 * Substring user lookup across name, username and email — backs the
 * assistant share dialog. Matching on email is server-side only; the
 * field itself stays hidden for non-admins (see UserSchema).
 */
class UserSearchFilter implements Filter
{
    use DeserializesValue;
    use IsSingular;

    public static function make(): self
    {
        return new self();
    }

    public function key(): string
    {
        return 'search';
    }

    public function apply($query, $value)
    {
        return app(UserRepository::class)->filterBySearchTerm($query, $value);
    }
}
