<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\FileType;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Console\Command;

class UpdateHawkiAvatar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hawki:update-avatar {path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update HAWKI AVATAR.';


    public function handle()
    {
        $path = $this->argument('path');

        $hawki = User::find(1);
        if ($hawki->username != config('hawki.migration.username')) {
            $this->error('HAWKI user does not exist or is manipulated. Please double check your migration file and hawki config.');
            return 1;
        }

        if (!is_readable($path) || !is_file($path)) {
            $this->error('Unable to open file.');
            return 1;
        }

        $ref = FileReference::fromDisk($path);
        if ($ref->getFileType() !== FileType::IMAGE) {
            $this->error('HAWKI avatar file is not a valid image. Detected type: ' . $ref->getFileType()->value);
            return 1;
        }

        $avatarStorage = app(AvatarStorageService::class);
        $avatarStorage->delete(StoredFileIdentifier::tryFromUserAvatar($hawki));

        $stored = $avatarStorage->store(
            file: $ref,
            category: StoredFileCategory::PROFILE_AVATAR
        );

        $hawki->update([
            'avatar_id' => $stored->getUuid(),
        ]);
        $this->info('HAWKI Avatar was successfully updated.');
        return 0;
    }
}
