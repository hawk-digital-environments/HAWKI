<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Room;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateAvatars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:avatars
                            {--dry-run : Show what would be migrated without actually doing it}
                            {--force : Force migration even if target file exists}
                            {--cleanup : Delete old avatar files after successful migration}
                            {--user= : Migrate only specific user by ID or username}
                            {--room= : Migrate only specific room by ID or slug}
                            {--type= : Migrate only specific type: profile, room, or both (default: both)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate profile and room avatars from old structure to new AvatarStorageService structure';

    protected AvatarStorageService $avatarStorage;
    protected int $profileMigratedCount = 0;
    protected int $profileSkippedCount = 0;
    protected int $profileErrorCount = 0;
    protected int $roomMigratedCount = 0;
    protected int $roomSkippedCount = 0;
    protected int $roomErrorCount = 0;

    public function __construct(AvatarStorageService $avatarStorage)
    {
        parent::__construct();
        $this->avatarStorage = $avatarStorage;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $cleanup = $this->option('cleanup');
        $specificUser = $this->option('user');
        $specificRoom = $this->option('room');
        $type = $this->option('type') ?? 'both';

        // Validate type option
        if (!in_array($type, ['profile', 'room', 'both'])) {
            $this->error('Invalid type option. Must be: profile, room, or both');
            return 1;
        }

        $this->info('Starting avatar migration...');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No files will be actually migrated');
        }

        // Migrate profile avatars
        if ($type === 'profile' || $type === 'both') {
            $this->migrateProfileAvatars($isDryRun, $force, $cleanup, $specificUser);
        }

        // Migrate room avatars
        if ($type === 'room' || $type === 'both') {
            $this->migrateRoomAvatars($isDryRun, $force, $cleanup, $specificRoom);
        }

        // Display summary
        $this->displaySummary($isDryRun);

        return 0;
    }

    protected function migrateProfileAvatars(bool $isDryRun, bool $force, bool $cleanup, ?string $specificUser = null): void
    {
        // Get users based on options
        $query = User::whereNotNull('avatar_id')->where('avatar_id', '!=', '');

        if ($specificUser) {
            // Try to find user by ID or username
            if (is_numeric($specificUser)) {
                $query->where('id', $specificUser);
            } else {
                $query->where('username', $specificUser);
            }
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users found with avatar_id set.');
            return;
        }

        $this->info("Found {$users->count()} users with avatar_id set.");

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->setFormat('Profile Avatars: %current%/%max% [%bar%] %percent:3s%%');
        $progressBar->start();

        foreach ($users as $user) {
            $this->migrateUserAvatar($user, $isDryRun, $force, $cleanup);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function migrateRoomAvatars(bool $isDryRun, bool $force, bool $cleanup, ?string $specificRoom = null): void
    {
        // Get rooms based on options
        $query = Room::whereNotNull('room_icon')->where('room_icon', '!=', '');

        if ($specificRoom) {
            // Try to find room by ID or slug
            if (is_numeric($specificRoom)) {
                $query->where('id', $specificRoom);
            } else {
                $query->where('slug', $specificRoom);
            }
        }

        $rooms = $query->get();

        if ($rooms->isEmpty()) {
            $this->info('No rooms found with room_icon set.');
            return;
        }

        $this->info("Found {$rooms->count()} rooms with room_icon set.");

        $progressBar = $this->output->createProgressBar($rooms->count());
        $progressBar->setFormat('Room Avatars: %current%/%max% [%bar%] %percent:3s%%');
        $progressBar->start();

        foreach ($rooms as $room) {
            $this->migrateRoomAvatar($room, $isDryRun, $force, $cleanup);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function migrateUserAvatar(User $user, bool $isDryRun, bool $force, bool $cleanup = false): void
    {
        try {
            $avatarId = trim($user->avatar_id);
            $username = $user->username ?? $user->email; // Fallback to email if no username

            // Skip if avatar_id is empty after trimming
            if (empty($avatarId)) {
                if ($this->output->isVerbose()) {
                    $this->line("Skipping user {$user->id} ({$username}) - empty avatar_id");
                }
                $this->profileSkippedCount++;
                return;
            }

            // Clean username for use as folder name (remove special characters)
            $cleanUsername = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);

            // Check if old avatar file exists
            $oldAvatarPath = "public/profile_avatars/{$avatarId}";

            if (!Storage::disk('local')->exists($oldAvatarPath)) {
                $this->error("Avatar file not found for user {$user->id} (username: {$username}): {$oldAvatarPath}");
                $this->profileSkippedCount++;
                return;
            }

            // Check if new avatar already exists (unless force is used)
            if (!$force) {
                $existingFile = $this->avatarStorage->retrieveFile('profile_avatars', $cleanUsername, $avatarId);
                if ($existingFile !== false) {
                    if ($this->output->isVerbose()) {
                        $this->line("Avatar already exists for user {$username}, skipping...");
                    }
                    $this->profileSkippedCount++;
                    return;
                }
            }

            if ($isDryRun) {
                $this->line("Would migrate: User {$user->id} ({$username}) - {$oldAvatarPath} -> profile_avatars/{$cleanUsername}/{$avatarId}");
                $this->profileMigratedCount++;
                return;
            }

            // Get the file content
            $fileContent = Storage::disk('local')->get($oldAvatarPath);

            if (!$fileContent) {
                $this->error("Could not read avatar file for user {$username}: {$oldAvatarPath}");
                $this->profileErrorCount++;
                return;
            }

            // Determine file extension from original file or detect from content
            $extension = $this->getFileExtension($oldAvatarPath);
            if (!$extension) {
                // Try to detect from file content
                $extension = $this->detectFileExtension($fileContent) ?? 'jpg';
            }

            // Store using AvatarStorageService
            $stored = $this->avatarStorage->storeFile(
                file: $fileContent,
                category: 'profile_avatars',
                name: $cleanUsername,
                uuid: $avatarId
            );

            if ($stored) {
                if ($this->output->isVerbose()) {
                    $this->line("✓ Migrated avatar for user {$username}");
                }
                $this->profileMigratedCount++;

                // Delete old file after successful migration if cleanup is enabled
                if ($cleanup) {
                    if (Storage::disk('local')->delete($oldAvatarPath)) {
                        if ($this->output->isVerbose()) {
                            $this->line("  ✓ Cleaned up old file: {$oldAvatarPath}");
                        }
                    } else {
                        $this->warn("  ⚠ Failed to delete old file: {$oldAvatarPath}");
                    }
                }
            } else {
                $this->error("Failed to store avatar for user {$username} using AvatarStorageService");
                $this->profileErrorCount++;
            }

        } catch (Throwable $e) {
            $this->error("Error migrating avatar for user {$user->id}: " . $e->getMessage());
            $this->profileErrorCount++;
        }
    }

    protected function migrateRoomAvatar(Room $room, bool $isDryRun, bool $force, bool $cleanup = false): void
    {
        try {
            $roomIcon = trim($room->room_icon);
            $roomName = $room->room_name ?? "Room {$room->id}"; // Fallback to room ID

            // Skip if room_icon is empty after trimming
            if (empty($roomIcon)) {
                if ($this->output->isVerbose()) {
                    $this->line("Skipping room {$room->id} ({$roomName}) - empty room_icon");
                }
                $this->roomSkippedCount++;
                return;
            }

            // Clean room name for use as folder name (remove special characters)
            $cleanRoomName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $roomName);

            // Check if old room avatar file exists
            $oldAvatarPath = "public/room_avatars/{$roomIcon}";

            if (!Storage::disk('local')->exists($oldAvatarPath)) {
                $this->error("Room avatar file not found for room {$room->id} (name: {$roomName}): {$oldAvatarPath}");
                $this->roomSkippedCount++;
                return;
            }

            // Check if new avatar already exists (unless force is used)
            if (!$force) {
                $existingFile = $this->avatarStorage->retrieveFile('room_avatars', $cleanRoomName, $roomIcon);
                if ($existingFile !== false) {
                    if ($this->output->isVerbose()) {
                        $this->line("Avatar already exists for room {$roomName}, skipping...");
                    }
                    $this->roomSkippedCount++;
                    return;
                }
            }

            if ($isDryRun) {
                $this->line("Would migrate: Room {$room->id} ({$roomName}) - {$oldAvatarPath} -> room_avatars/{$cleanRoomName}/{$roomIcon}");
                $this->roomMigratedCount++;
                return;
            }

            // Get the file content
            $fileContent = Storage::disk('local')->get($oldAvatarPath);

            if (!$fileContent) {
                $this->error("Could not read room avatar file for room {$roomName}: {$oldAvatarPath}");
                $this->roomErrorCount++;
                return;
            }

            // Determine file extension from original file or detect from content
            $extension = $this->getFileExtension($oldAvatarPath);
            if (!$extension) {
                // Try to detect from file content
                $extension = $this->detectFileExtension($fileContent) ?? 'jpg';
            }

            // Store using AvatarStorageService
            $stored = $this->avatarStorage->storeFile(
                file: $fileContent,
                category: 'room_avatars',
                name: $cleanRoomName,
                uuid: $roomIcon
            );

            if ($stored) {
                if ($this->output->isVerbose()) {
                    $this->line("✓ Migrated avatar for room {$roomName}");
                }
                $this->roomMigratedCount++;

                // Delete old file after successful migration if cleanup is enabled
                if ($cleanup) {
                    if (Storage::disk('local')->delete($oldAvatarPath)) {
                        if ($this->output->isVerbose()) {
                            $this->line("  ✓ Cleaned up old file: {$oldAvatarPath}");
                        }
                    } else {
                        $this->warn("  ⚠ Failed to delete old file: {$oldAvatarPath}");
                    }
                }
            } else {
                $this->error("Failed to store avatar for room {$roomName} using AvatarStorageService");
                $this->roomErrorCount++;
            }

        } catch (Throwable $e) {
            $this->error("Error migrating avatar for room {$room->id}: " . $e->getMessage());
            $this->roomErrorCount++;
        }
    }

    protected function getFileExtension(string $path): ?string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return $extension ?: null;
    }

    protected function detectFileExtension(string $content): ?string
    {
        // Detect file type from content
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content);

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
        ];

        return $extensions[$mimeType] ?? null;
    }

    protected function displaySummary(bool $isDryRun): void
    {
        $action = $isDryRun ? 'Would be migrated' : 'Migrated';

        $this->info('Migration Summary:');
        
        // Profile avatars summary
        if ($this->profileMigratedCount > 0 || $this->profileSkippedCount > 0 || $this->profileErrorCount > 0) {
            $this->comment('Profile Avatars:');
            $this->table(
                ['Status', 'Count'],
                [
                    [$action, $this->profileMigratedCount],
                    ['Skipped', $this->profileSkippedCount],
                    ['Errors', $this->profileErrorCount],
                    ['Total', $this->profileMigratedCount + $this->profileSkippedCount + $this->profileErrorCount]
                ]
            );
        }

        // Room avatars summary
        if ($this->roomMigratedCount > 0 || $this->roomSkippedCount > 0 || $this->roomErrorCount > 0) {
            $this->comment('Room Avatars:');
            $this->table(
                ['Status', 'Count'],
                [
                    [$action, $this->roomMigratedCount],
                    ['Skipped', $this->roomSkippedCount],
                    ['Errors', $this->roomErrorCount],
                    ['Total', $this->roomMigratedCount + $this->roomSkippedCount + $this->roomErrorCount]
                ]
            );
        }

        // Overall summary
        $totalMigrated = $this->profileMigratedCount + $this->roomMigratedCount;
        $totalSkipped = $this->profileSkippedCount + $this->roomSkippedCount;
        $totalErrors = $this->profileErrorCount + $this->roomErrorCount;

        if ($totalMigrated > 0 || $totalSkipped > 0 || $totalErrors > 0) {
            $this->info('Overall Summary:');
            $this->table(
                ['Status', 'Count'],
                [
                    [$action, $totalMigrated],
                    ['Skipped', $totalSkipped],
                    ['Errors', $totalErrors],
                    ['Total', $totalMigrated + $totalSkipped + $totalErrors]
                ]
            );
        }

        if ($totalErrors > 0) {
            $this->warn("There were {$totalErrors} errors during migration. Check the output above for details.");
        }

        if (!$isDryRun && $totalMigrated > 0) {
            $this->info("Successfully migrated {$totalMigrated} avatars to the new storage structure.");
            $this->comment('Note: Old avatar files were not deleted unless --cleanup was used.');
        }

        if ($isDryRun) {
            $this->info('Run without --dry-run to perform the actual migration.');
        }
    }
}
