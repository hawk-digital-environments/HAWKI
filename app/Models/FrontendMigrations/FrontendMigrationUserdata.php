<?php

namespace App\Models\FrontendMigrations;

use App\Models\Scopes\Generic\BelongsToUserScope;
use App\Models\User;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrontendMigrationUserdata extends Model
{
    use HasContextualScopesTrait;

    protected $table = 'frontend_migration_userdata';

    protected $fillable = [
        'user_id',
        'migration_id',
        'data',
    ];

    protected $casts = [
        'data' => 'encrypted:json'
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('access', new BelongsToUserScope());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function migration(): BelongsTo
    {
        return $this->belongsTo(FrontendMigration::class, 'migration_id');
    }
}
