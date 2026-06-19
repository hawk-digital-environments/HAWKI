<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Values;

use App\Policies\MigrationPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

#[UsePolicy(MigrationPolicy::class)]
class MigrationToApply
{
    public function __construct(
        public string $name,
        public ?array $data
    )
    {
    }
}
