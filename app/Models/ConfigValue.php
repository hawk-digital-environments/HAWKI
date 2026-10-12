<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One serialized config row of the app-config system: (namespace, key) → value.
 *
 * The namespace is derived from the owning plugin via
 * {@see \App\Services\Config\AbstractConfig::namespace()} ('hawki-core' for core
 * configs, the plugin slug for plugin configs); serialization, casting and encryption
 * are handled entirely by the config classes via
 * {@see \App\Utils\Casts\AbstractCastableObject} — this model only stores the
 * serialized strings.
 *
 * The table is currently unused groundwork for the planned migration of the file-based
 * config onto the database; rows are managed exclusively through
 * {@see \App\Services\System\Database\SettingsAndConfig\ConfigSchema}.
 */
class ConfigValue extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'namespace',
        'key',
        'value',
    ];
}
