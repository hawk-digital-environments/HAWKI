<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\LocalAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportTestUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:test-users 
                            {--force : Overwrite existing users}
                            {--dry-run : Show what would be imported without actually importing}
                            {--file= : Specify custom JSON file path (relative to storage/app)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import test users from JSON file to database as local users';

    protected $localAuthService;

    public function __construct(LocalAuthService $localAuthService)
    {
        parent::__construct();
        $this->localAuthService = $localAuthService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileName = $this->option('file') ?? 'test_users.json';
        $filePath = storage_path('app/'.$fileName);

        // Check if file exists
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            $this->info("Expected file: storage/app/{$fileName}");

            return Command::FAILURE;
        }

        // Read and parse JSON
        try {
            $jsonContent = file_get_contents($filePath);
            $testUsers = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON format: '.json_last_error_msg());

                return Command::FAILURE;
            }

            if (empty($testUsers) || ! is_array($testUsers)) {
                $this->error('No users found in JSON file or invalid format');

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Error reading file: '.$e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Found '.count($testUsers)." users to import from: {$fileName}");

        if ($this->option('dry-run')) {
            $this->info("\n=== DRY RUN MODE - No actual changes will be made ===\n");
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($testUsers as $index => $userData) {
            $result = $this->processUser($userData, $index + 1);

            switch ($result) {
                case 'imported':
                    $imported++;
                    break;
                case 'skipped':
                    $skipped++;
                    break;
                case 'error':
                    $errors++;
                    break;
            }
        }

        // Summary
        $this->info("\n=== Import Summary ===");
        $this->info("Imported: {$imported}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        if ($this->option('dry-run')) {
            $this->info("\n=== DRY RUN COMPLETED ===");
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Process a single user from the JSON data
     */
    private function processUser($userData, $userNumber)
    {
        // Validate required fields
        if (! isset($userData['username']) || ! isset($userData['password'])) {
            $this->error("User #{$userNumber}: Missing username or password");

            return 'error';
        }

        $username = $userData['username'];

        // Check if user already exists
        $existingUser = User::where('username', $username)->first();

        if ($existingUser) {
            if ($existingUser->isLocalUser()) {
                if (! $this->option('force')) {
                    $this->warn("User #{$userNumber}: Local user '{$username}' already exists (use --force to overwrite)");

                    return 'skipped';
                }

                if (! $this->option('dry-run')) {
                    // Update existing local user
                    $this->updateExistingUser($existingUser, $userData, $userNumber);
                }
                $this->info("User #{$userNumber}: Updated local user '{$username}'");

                return 'imported';

            } else {
                // External user exists - cannot convert
                $this->error("User #{$userNumber}: External user '{$username}' already exists - cannot convert to local user");

                return 'error';
            }
        }

        // Create new local user
        if (! $this->option('dry-run')) {
            $this->createNewUser($userData, $userNumber);
        }

        $this->info("User #{$userNumber}: Created local user '{$username}'");

        return 'imported';
    }

    /**
     * Create a new local user
     */
    private function createNewUser($userData, $userNumber)
    {
        try {
            User::create([
                'username' => $userData['username'],
                'name' => $userData['name'] ?? $userData['username'],
                'email' => $userData['email'] ?? $userData['username'].'@local.hawki',
                'password' => $userData['password'], // Auto-hashed by model
                'employeetype' => $userData['employeetype'] ?? 'local',
                'auth_type' => 'local', // Explicitly set as local user
                'reset_pw' => true, // Local imported users need password reset
                'approval' => config('auth.local_needapproval', true) ? false : true, // Respect local_needapproval config
                'publicKey' => $userData['publicKey'] ?? '',
                'avatar_id' => $userData['avatar_id'] ?? null,
                'bio' => $userData['bio'] ?? null,
                'isRemoved' => false,
                'permissions' => $userData['permissions'] ?? null,
            ]);

        } catch (\Exception $e) {
            $this->error("User #{$userNumber}: Failed to create user - ".$e->getMessage());
            Log::error("ImportTestUsers: Failed to create user {$userData['username']}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing local user
     */
    private function updateExistingUser($user, $userData, $userNumber)
    {
        try {
            $user->update([
                'name' => $userData['name'] ?? $user->name,
                'email' => $userData['email'] ?? $user->email,
                'password' => $userData['password'], // Auto-hashed by model
                'employeetype' => $userData['employeetype'] ?? $user->employeetype,
                'avatar_id' => $userData['avatar_id'] ?? $user->avatar_id,
                'bio' => $userData['bio'] ?? $user->bio,
                'permissions' => $userData['permissions'] ?? $user->permissions,
            ]);

        } catch (\Exception $e) {
            $this->error("User #{$userNumber}: Failed to update user - ".$e->getMessage());
            Log::error("ImportTestUsers: Failed to update user {$userData['username']}: ".$e->getMessage());
            throw $e;
        }
    }
}
