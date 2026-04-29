<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('categories')]
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
    ];

    public function assistants(): HasMany
    {
        return $this->hasMany(Assistant::class);
    }
}
