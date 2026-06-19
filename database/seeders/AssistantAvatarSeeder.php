<?php

namespace Database\Seeders;

use App\Models\Assistants\AssistantAvatar;
use App\Services\Assistant\Repositories\AssistantAvatarRepository;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssistantAvatarSeeder extends Seeder
{
    private const SOURCE_DIR = 'img/defaults/assistant_avatars';

    public function run(AvatarStorageService $avatarStorage, AssistantAvatarRepository $repository): void
    {
        $sourcePath = public_path(self::SOURCE_DIR);

        if (!is_dir($sourcePath)) {
            $this->command->warn('Assistant avatar source directory not found: ' . $sourcePath);

            return;
        }

        $files = glob($sourcePath . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE);

        if ($files === false || $files === []) {
            $this->command->warn('No assistant avatar source images found in: ' . $sourcePath);

            return;
        }

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $contents = file_get_contents($file);

            if ($contents === false) {
                $this->command->error("Could not read assistant avatar source: {$file}");
                continue;
            }

            $existing = $repository->findByName($name);
            $uuid = $existing?->uuid ?? Str::uuid()->toString();
            $filename = "{$uuid}.{$extension}";

            $stored = $avatarStorage->store(
                $contents,
                $filename,
                $uuid,
                AssistantAvatar::STORAGE_CATEGORY,
            );

            if (!$stored) {
                $this->command->error("Failed to store assistant avatar: {$name}");
                continue;
            }

            $repository->store($name, $uuid);
        }

        $this->command->info('Seeded ' . count($files) . ' assistant avatars.');
    }
}
