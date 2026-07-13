<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Policies\AssistantSettingPolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property null|array  $default_value
 * @property null|string $description
 * @property int         $id
 * @property string      $key
 * @property string      $label
 * @property null|string $prompt_template
 * @property null|array  $ui_options
 * @property string      $ui_type
 */
#[Table('assistant_settings')]
#[UsePolicy(AssistantSettingPolicy::class)]
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

    /**
     * @return HasMany<AssistantSettingValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AssistantSettingValue::class, 'setting_id');
    }

    protected function casts(): array
    {
        return [
            'ui_options' => 'array',
            'default_value' => 'array',
        ];
    }
}
