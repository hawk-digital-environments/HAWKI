<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Policies\AssistantCategoryPolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property string $text
 */
#[Table('assistant_categories')]
#[UsePolicy(AssistantCategoryPolicy::class)]
class AssistantCategory extends Model
{
    use HasFactory;
    protected $fillable = [
        'text',
    ];

    /**
     * @return HasMany<Assistant, $this>
     */
    public function assistants(): HasMany
    {
        return $this->hasMany(Assistant::class, 'category_id');
    }
}
