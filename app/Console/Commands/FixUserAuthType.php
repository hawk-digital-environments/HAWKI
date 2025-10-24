<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixUserAuthType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:fix-auth-type 
                            {user : Username or User ID to fix}
                            {auth_type : The correct auth type (local, ldap, oidc, shibboleth)}
                            {--dry-run : Preview the change without applying it}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix the auth_type of a user whose auth_type was incorrectly changed by a bug';

    /**
     * Valid auth types
     */
    private const VALID_AUTH_TYPES = ['local', 'ldap', 'oidc', 'shibboleth'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userIdentifier = $this->argument('user');
        $newAuthType = strtolower($this->argument('auth_type'));
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        // Validate auth type
        if (!in_array($newAuthType, self::VALID_AUTH_TYPES, true)) {
            $this->error("Invalid auth_type: {$newAuthType}");
            $this->error('Valid auth types are: ' . implode(', ', self::VALID_AUTH_TYPES));
            return Command::FAILURE;
        }

        // Find the user
        $user = $this->findUser($userIdentifier);

        if (!$user) {
            $this->error("❌ User not found: {$userIdentifier}");
            return Command::FAILURE;
        }

        // Display current state
        $this->info('User Information:');
        $this->table(
            ['Field', 'Current Value'],
            [
                ['ID', $user->id],
                ['Username', $user->username],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Employee Type', $user->employeetype],
                ['Current Auth Type', $user->auth_type ?? 'NULL'],
                ['Has Password', $user->password ? 'Yes' : 'No'],
                ['Approval', $user->approval ? 'Approved' : 'Pending'],
                ['Is Removed', $user->isRemoved ? 'Yes' : 'No'],
            ]
        );

        // Check if change is needed
        if ($user->auth_type === $newAuthType) {
            $this->info("✅ User already has auth_type '{$newAuthType}' - no change needed.");
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->warn("⚠️  You are about to change the auth_type:");
        $this->line("   From: <fg=red>{$user->auth_type}</>");
        $this->line("   To:   <fg=green>{$newAuthType}</>");
        $this->newLine();

        // Warn about potential issues
        $this->displayWarnings($user, $newAuthType);

        // Dry run mode
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made.');
            $this->info("Would change auth_type from '{$user->auth_type}' to '{$newAuthType}'");
            return Command::SUCCESS;
        }

        // Confirmation (unless forced)
        if (!$isForced && !$this->confirm('Do you want to proceed with this change?', false)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Perform the change
        try {
            $oldAuthType = $user->auth_type;
            
            $user->auth_type = $newAuthType;
            $user->save();

            $this->info('✅ Successfully updated auth_type!');
            $this->info("   Changed from '{$oldAuthType}' to '{$newAuthType}'");

            // Log the change
            Log::info('User auth_type fixed via artisan command', [
                'user_id' => $user->id,
                'username' => $user->username,
                'old_auth_type' => $oldAuthType,
                'new_auth_type' => $newAuthType,
                'fixed_by' => 'artisan command',
            ]);

            // Display recommendations
            $this->newLine();
            $this->displayRecommendations($user, $newAuthType);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to update auth_type: ' . $e->getMessage());
            Log::error('Failed to fix user auth_type', [
                'user_id' => $user->id,
                'username' => $user->username,
                'target_auth_type' => $newAuthType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Find user by username or ID
     */
    private function findUser(string $identifier): ?User
    {
        // Try to find by ID first (if numeric)
        if (is_numeric($identifier)) {
            $user = User::find($identifier);
            if ($user) {
                return $user;
            }
        }

        // Try to find by username
        return User::where('username', $identifier)->first();
    }

    /**
     * Display warnings about potential issues with the auth type change
     */
    private function displayWarnings(User $user, string $newAuthType): void
    {
        $warnings = [];

        // Check password requirements
        if ($newAuthType === 'local' && !$user->password) {
            $warnings[] = "⚠️  User will be set to 'local' auth but has NO PASSWORD set.";
            $warnings[] = "   → User will not be able to log in until a password is set.";
            $warnings[] = "   → Use: php artisan user:manage --upgrade-to-admin={$user->username} --set-password=<password>";
        }

        if ($newAuthType !== 'local' && $user->password) {
            $warnings[] = "ℹ️  User has a password set but will be changed to '{$newAuthType}' auth.";
            $warnings[] = "   → The password will remain in the database but won't be used for authentication.";
        }

        // Check if user can authenticate
        if ($newAuthType === 'ldap' && config('auth.authentication_method') !== 'LDAP') {
            $warnings[] = "⚠️  System is NOT configured for LDAP authentication.";
            $warnings[] = "   → User may not be able to log in.";
        }

        if ($newAuthType === 'oidc' && config('auth.authentication_method') !== 'OIDC') {
            $warnings[] = "⚠️  System is NOT configured for OIDC authentication.";
            $warnings[] = "   → User may not be able to log in.";
        }

        if ($newAuthType === 'shibboleth' && config('auth.authentication_method') !== 'Shibboleth') {
            $warnings[] = "⚠️  System is NOT configured for Shibboleth authentication.";
            $warnings[] = "   → User may not be able to log in.";
        }

        if (!empty($warnings)) {
            foreach ($warnings as $warning) {
                $this->warn($warning);
            }
            $this->newLine();
        }
    }

    /**
     * Display recommendations after successful change
     */
    private function displayRecommendations(User $user, string $newAuthType): void
    {
        $this->info('📋 Recommendations:');

        if ($newAuthType === 'local') {
            if (!$user->password) {
                $this->line('1. Set a password for the user:');
                $this->line("   php artisan user:manage --upgrade-to-admin={$user->username} --set-password=<password>");
            }
            $this->line('2. Ensure user has completed registration (publicKey must be set)');
            $this->line('3. User can now log in via the local login form');
        } else {
            $this->line("1. Ensure user can authenticate via {$newAuthType}");
            $this->line("2. User should log in via {$newAuthType} authentication");
            if ($user->password) {
                $this->line('3. Consider removing the password field (optional)');
            }
        }

        $this->newLine();
        $this->info('Verify the user can log in successfully after this change.');
    }
}
