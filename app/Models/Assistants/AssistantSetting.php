<?php

namespace App\Models\Assistants;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('assistant_settings')]
class AssistantSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'ui_type',
        'ui_options',
        'prompt_template',
        'default_value',
    ];

    protected function casts(): array
    {
        return [
            'ui_options' => 'array',
            'default_value' => 'array',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AssistantSettingValue::class, 'setting_id');
    }
}
