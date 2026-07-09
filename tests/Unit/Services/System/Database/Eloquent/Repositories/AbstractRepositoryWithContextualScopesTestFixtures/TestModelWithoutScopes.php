<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryWithContextualScopesTestFixtures;

use Illuminate\Database\Eloquent\Model;

class TestModelWithoutScopes extends Model
{
    protected $table = 'test_table';
    protected $guarded = [];
}
