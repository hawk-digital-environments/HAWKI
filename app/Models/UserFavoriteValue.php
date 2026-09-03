<?php

namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;

    class UserFavoriteValue extends Model {
        /**
         * The attributes that are mass assignable.
         */
        protected $fillable = [
        'namespace',
        'identifier',
        'user_id',
        ];

        public function user(): BelongsTo
        {
        return $this->belongsTo(User::class);
        }
    }
