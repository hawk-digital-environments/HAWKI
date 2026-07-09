<?php

use App\Models\User;
use App\Services\Encryption\EncryptionUtils;
use App\Services\Frontend\Migrations\Exceptions\NoDownForFrontendMigrationsExceptionException;
use App\Services\Frontend\Migrations\Facades\FrontendMigrator;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        FrontendMigrator::register(__FILE__, static function (User $user, Connection $connection): array|null {
            $userdata = $connection->table('private_user_data')->select()->where('user_id', $user->id)->first();
            if (!$userdata) {
                return null;
            }

            return [
                'blob' => (string)EncryptionUtils::symmetricCryptoValueFromStrings(
                    $userdata->KCIV,
                    $userdata->KCTAG,
                    $userdata->keychain
                )
            ];
        });

        Schema::drop('private_user_data');
    }

    public function down(): void
    {
        // Rolling back a frontend migration is not possible.
        //
        // Here's why: this migration changes data that is encrypted on the client (the user's browser).
        // The server never sees the actual content — it only stores an encrypted blob it can't read.
        // When the migration runs, clients pick it up one by one as they come online and transform
        // their data to the new format. Once a client has done that, the old format is gone — the
        // server has no way to reverse it, because it never had access to the data in the first place.
        //
        // Even if only one user out of thousands has already migrated, rolling back would leave
        // that user's data in the new format while everyone else reverts to the old one.
        // There is no safe way to get back to a consistent state from the server side alone.
        //
        // If you need to undo what this migration does, write a new forward migration that
        // transforms the data back to the previous format. Never try to roll this one back.
        throw NoDownForFrontendMigrationsExceptionException::forMigration(__CLASS__);
    }
};
