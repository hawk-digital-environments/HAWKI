<?php

namespace App\Models\FrontendMigrations;

use App\Policies\MigrationPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[UsePolicy(MigrationPolicy::class)]
class FrontendMigration extends Model
{
    protected $fillable = [
        'namespace',
        'migration_name',
        'has_userdata',
    ];
}
