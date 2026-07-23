<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTraitTestFixtures;

use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Model;

class TestModelForHasContextualScopes extends Model
{
    use HasContextualScopesTrait;

    protected $table = 'test_table';
    protected $guarded = [];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('test-scope', TestScopeForModel::class);
    }
}
