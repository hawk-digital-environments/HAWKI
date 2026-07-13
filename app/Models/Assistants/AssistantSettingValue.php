<?php

declare(strict_types=1);

namespace App\Models\Assistants;

use App\Policies\AssistantSettingValuePolicy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Assistant             $assistant
 * @property int                   $assistant_id
 * @property int                   $id
 * @property null|AssistantSetting $setting
 * @property int                   $setting_id
 * @property null|array            $value
 */
#[Table('assistant_setting_values')]
#[UsePolicy(AssistantSettingValuePolicy::class)]
class AssistantSettingValue extends Model
{
    use HasFactory;
    protected $fillable = [
        'assistant_id',
        'setting_id',
        'value',
    ];

    /**
     * @return BelongsTo<Assistant, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    /**
     * @return BelongsTo<AssistantSetting, $this>
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(AssistantSetting::class, 'setting_id');
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
