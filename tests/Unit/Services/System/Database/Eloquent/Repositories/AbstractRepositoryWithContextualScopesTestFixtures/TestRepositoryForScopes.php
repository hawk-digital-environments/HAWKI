<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopesTestFixtures;

use App\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopes;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;

#[UseModel(TestModelWithoutScopes::class)]
class TestRepositoryForScopes extends AbstractRepositoryWithContextualScopes
{
}
