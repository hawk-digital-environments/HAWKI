<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Testing extends Model
{
    protected $fillable = ['name', 'status', 'max_count', 'is_active'];
}
