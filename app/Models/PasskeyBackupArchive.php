<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A passkey backup row that lost the deduplication race in
 * `2026_09_09_120000_archive_duplicate_passkey_backups`.
 *
 * These rows are kept only so an operator can still recover a user's encrypted backup blob if
 * the wrong duplicate survived. They are never read by the application and are deleted by
 * `passkey-backups:cleanup-archive` once `expires_at` has passed.
 */
class PasskeyBackupArchive extends Model
{
    public $timestamps = false;

    protected $table = 'passkey_backup_archives';

    protected $fillable = [
        'old_id',
        'username',
        'ciphertext',
        'iv',
        'tag',
        'archived_at',
        'expires_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
