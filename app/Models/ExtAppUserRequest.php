<?php

namespace App\Models;

use App\Casts\AsAsymmetricPublicKeyCast;
use App\Casts\AsHybridCryptoValueCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtAppUserRequest extends Model
{
    protected $fillable = [
        'user_public_key',
        'user_private_key',
        'ext_user_id',
        'request_id',
        'valid_until',
        'ext_app_id',
    ];

    protected $casts = [
        'user_public_key' => AsAsymmetricPublicKeyCast::class,
        'user_private_key' => AsHybridCryptoValueCast::class,
        'valid_until' => 'datetime',
    ];

    /**
     * @return BelongsTo<ExtApp, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(ExtApp::class);
    }
}
