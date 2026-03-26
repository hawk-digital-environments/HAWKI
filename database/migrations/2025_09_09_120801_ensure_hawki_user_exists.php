<?php

use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Value\FileReference;
use App\Services\Storage\Value\FileType;
use App\Services\Storage\Value\StoredFileCategory;
use Illuminate\Database\Migrations\Migration;
use Psr\Log\LoggerInterface;

return new class extends Migration
{
    private readonly LoggerInterface $logger;
    private readonly AvatarStorageService $avatarStorage;

    public function __construct()
    {
        $this->logger = app(LoggerInterface::class);
        $this->avatarStorage = app(AvatarStorageService::class);
    }

    public function up(): void
    {
        $avatarPath = public_path('img/' . config('hawki.migration.avatar_id'));
        if (!is_file($avatarPath)) {
            $this->logger->warning('HAWKI avatar file not found at ' . $avatarPath);
            $defaultAvatarPath = public_path('img/hawkiAvatar.jpg');
            if (is_file($defaultAvatarPath)) {
                $avatarPath = $defaultAvatarPath;
                $this->logger->info('Using default HAWKI avatar from ' . $defaultAvatarPath);
            } else {
                throw new \RuntimeException('HAWKI avatar file not found and default avatar also missing.');
            }
        }

        if (!is_readable($avatarPath) || !is_file($avatarPath)) {
            throw new \RuntimeException('Unable to open HAWKI avatar file at ' . $avatarPath);
        }

        $ref = FileReference::fromDisk($avatarPath);
        if ($ref->getFileType() !== FileType::IMAGE) {
            throw new \RuntimeException('HAWKI avatar file is not a valid image. Detected type: ' . $ref->getFileType()->value);
        }

        $stored = $this->avatarStorage->store(
            file: $ref,
            category: StoredFileCategory::PROFILE_AVATAR
        );

        $hawki = DB::table('users')->where('id', 1)->first();

        // Always ensure ID = 1 exists
        DB::table('users')->updateOrInsert(
            ['id' => 1],
            [
                'name'        => config('hawki.migration.name'),
                'username'    => config('hawki.migration.username'),
                'email'       => config('hawki.migration.email'),
                'employeetype'=> config('hawki.migration.employeetype'),
                'publicKey'   => '0',
                'avatar_id' => $stored->getUuid(),
                'updated_at'  => now(),
                'created_at'  => $hawki?->created_at ?? now(),
            ]
        );
    }
};
