<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('assistant_setting_values')]
class AssistantSettingValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'assistant_id',
        'setting_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(AssistantSetting::class, 'setting_id');
    }
}
