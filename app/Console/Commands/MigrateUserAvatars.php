<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateUserAvatars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'avatars:migrate
                            {--dry-run : Show what would be migrated without actually migrating}
                            {--force : Force migration even if some files are missing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate old avatar files (filenames) to new UUID-based avatar storage system';

    private AvatarStorageService $avatarStorage;

    public function __construct(AvatarStorageService $avatarStorage)
    {
        parent::__construct();
        $this->avatarStorage = $avatarStorage;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🔍 Searching for users with old avatar IDs (non-UUID filenames)...');

        // Find all users with avatar_id that are NOT UUIDs
        $usersWithOldAvatars = User::whereNotNull('avatar_id')
            ->where('avatar_id', '!=', '')
            ->get()
            ->filter(function ($user) {
                // Check if avatar_id is NOT a UUID
                return !$this->isUuid($user->avatar_id);
            });

        if ($usersWithOldAvatars->isEmpty()) {
            $this->info('✅ No users with old avatar IDs found. All avatars are already using UUID format.');
            return self::SUCCESS;
        }

        $this->info("Found {$usersWithOldAvatars->count()} users with old avatar IDs:");
        $this->newLine();

        $migratedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($usersWithOldAvatars as $user) {
            $oldAvatarId = $user->avatar_id;
            
            $this->line("👤 User: {$user->username} (ID: {$user->id})");
            $this->line("   Old avatar_id: {$oldAvatarId}");

            // Try to find the old avatar file in various locations
            $oldFilePath = $this->findOldAvatarFile($oldAvatarId);

            if (!$oldFilePath) {
                $this->warn("   ⚠️  Old avatar file not found in expected locations");
                
                if (!$force) {
                    $this->error("   ❌ Skipping (use --force to skip missing files)");
                    $skippedCount++;
                    $this->newLine();
                    continue;
                }
                
                $this->warn("   ⏭️  Forcing skip due to --force flag");
                $skippedCount++;
                $this->newLine();
                continue;
            }

            $this->info("   📁 Found file at: {$oldFilePath}");

            if ($isDryRun) {
                $this->comment("   🔄 [DRY RUN] Would migrate to new UUID-based storage");
                $migratedCount++;
                $this->newLine();
                continue;
            }

            // Perform the actual migration
            try {
                $newUuid = $this->migrateAvatar($user, $oldFilePath);
                
                if ($newUuid) {
                    $this->info("   ✅ Migrated successfully!");
                    $this->line("   New avatar_id: {$newUuid}");
                    $migratedCount++;
                } else {
                    $this->error("   ❌ Migration failed");
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error: " . $e->getMessage());
                $errorCount++;
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('=== Migration Summary ===');
        $this->line("Total users found: {$usersWithOldAvatars->count()}");
        
        if ($isDryRun) {
            $this->comment("Dry run - no changes made");
            $this->line("Would migrate: {$migratedCount}");
            $this->line("Would skip: {$skippedCount}");
        } else {
            $this->line("✅ Successfully migrated: {$migratedCount}");
            $this->line("⚠️  Skipped: {$skippedCount}");
            $this->line("❌ Errors: {$errorCount}");
        }

        return self::SUCCESS;
    }

    /**
     * Check if a string is a valid UUID
     */
    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * Try to find the old avatar file in various locations
     */
    private function findOldAvatarFile(string $filename): ?string
    {
        // Possible locations for old avatar files
        $possiblePaths = [
            public_path('img/' . $filename),                           // /public/img/
            storage_path('app/public/' . $filename),                   // /storage/app/public/
            storage_path('app/public/avatars/' . $filename),          // /storage/app/public/avatars/
            public_path('storage/' . $filename),                       // /public/storage/ (symlink)
            public_path('storage/avatars/' . $filename),              // /public/storage/avatars/
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Migrate an old avatar file to the new UUID-based storage
     */
    private function migrateAvatar(User $user, string $oldFilePath): ?string
    {
        // Generate new UUID
        $uuid = Str::uuid()->toString();
        
        // Get file extension
        $extension = pathinfo($oldFilePath, PATHINFO_EXTENSION);
        if (!$extension) {
            $extension = 'jpg'; // Default fallback
        }
        
        $filename = $uuid . '.' . $extension;
        
        // Read the old file
        $fileContent = file_get_contents($oldFilePath);
        if ($fileContent === false) {
            throw new \Exception("Failed to read old avatar file");
        }

        // Store in new avatar storage system
        $stored = $this->avatarStorage->store(
            file: $fileContent,
            filename: $filename,
            uuid: $uuid,
            category: 'profile_avatars',
            temp: false
        );

        if (!$stored) {
            throw new \Exception("Failed to store avatar in new storage system");
        }

        // Update user's avatar_id
        $user->update(['avatar_id' => $uuid]);

        return $uuid;
    }
}
