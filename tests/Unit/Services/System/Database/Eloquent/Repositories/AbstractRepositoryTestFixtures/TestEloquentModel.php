<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\Repositories\AbstractRepositoryTestFixtures;

use Illuminate\Database\Eloquent\Model;

class TestEloquentModel extends Model
{
    protected $table = 'test_table';
    protected $guarded = [];
}
