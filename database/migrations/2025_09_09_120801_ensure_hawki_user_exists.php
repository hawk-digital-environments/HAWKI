<?php

use App\Services\Storage\AvatarStorageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
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
        $avatarUuid = Str::uuid();
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
        $file = file_get_contents($avatarPath);
        if (!$file) {
            throw new \RuntimeException('Unable to open HAWKI avatar file at ' . $avatarPath);
        }

        $this->avatarStorage->store(
            $file,
            $avatarPath,
            $avatarUuid,
            'profile_avatars'
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
                'avatar_id' => $avatarUuid,
                'updated_at'  => now(),
                'created_at'  => $hawki?->created_at ?? now(),
            ]
        );
    }
};
