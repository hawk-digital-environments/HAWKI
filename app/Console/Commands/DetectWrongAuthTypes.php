<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectWrongAuthTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:detect-wrong-auth-types 
                            {--fix : Attempt to automatically fix detected issues}
                            {--dry-run : Preview fixes without applying them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect users with potentially incorrect auth_type values and optionally fix them';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $shouldFix = $this->option('fix');
        $isDryRun = $this->option('dry-run');

        $this->info('🔍 Scanning for users with potentially incorrect auth_type...');
        $this->newLine();

        $issues = [];
        
        // Issue 1: Local users without password
        $localUsersNoPassword = User::where('auth_type', 'local')
            ->where('isRemoved', false)
            ->whereNull('password')
            ->orWhere('password', '')
            ->get();

        if ($localUsersNoPassword->count() > 0) {
            $issues[] = [
                'type' => 'local_no_password',
                'description' => 'Local users without password (cannot authenticate)',
                'users' => $localUsersNoPassword,
                'severity' => 'critical',
            ];
        }

        // Issue 2: Non-local users with password
        $externalUsersWithPassword = User::whereIn('auth_type', ['ldap', 'oidc', 'shibboleth'])
            ->where('isRemoved', false)
            ->whereNotNull('password')
            ->where('password', '!=', '')
            ->get();

        if ($externalUsersWithPassword->count() > 0) {
            $issues[] = [
                'type' => 'external_with_password',
                'description' => 'External auth users with password field (usually wrong)',
                'users' => $externalUsersWithPassword,
                'severity' => 'warning',
            ];
        }

        // Issue 3: Users with NULL auth_type
        $usersNullAuthType = User::whereNull('auth_type')
            ->where('isRemoved', false)
            ->get();

        if ($usersNullAuthType->count() > 0) {
            $issues[] = [
                'type' => 'null_auth_type',
                'description' => 'Users with NULL auth_type',
                'users' => $usersNullAuthType,
                'severity' => 'critical',
            ];
        }

        // Issue 4: Users with invalid auth_type values
        $validAuthTypes = ['local', 'ldap', 'oidc', 'shibboleth'];
        $usersInvalidAuthType = User::whereNotNull('auth_type')
            ->whereNotIn('auth_type', $validAuthTypes)
            ->where('isRemoved', false)
            ->get();

        if ($usersInvalidAuthType->count() > 0) {
            $issues[] = [
                'type' => 'invalid_auth_type',
                'description' => 'Users with invalid auth_type values',
                'users' => $usersInvalidAuthType,
                'severity' => 'critical',
            ];
        }

        // Display results
        if (empty($issues)) {
            $this->info('✅ No issues detected! All users have correct auth_type values.');
            return Command::SUCCESS;
        }

        // Summary
        $totalIssues = collect($issues)->sum(fn($issue) => $issue['users']->count());
        $criticalIssues = collect($issues)
            ->filter(fn($issue) => $issue['severity'] === 'critical')
            ->sum(fn($issue) => $issue['users']->count());

        $this->warn("⚠️  Found {$totalIssues} users with potential auth_type issues");
        if ($criticalIssues > 0) {
            $this->error("   {$criticalIssues} critical issues require immediate attention");
        }
        $this->newLine();

        // Display each issue category
        foreach ($issues as $issue) {
            $this->displayIssue($issue);
        }

        // Fix mode
        if ($shouldFix || $isDryRun) {
            $this->newLine();
            $this->attemptAutoFix($issues, $isDryRun);
        } else {
            $this->newLine();
            $this->info('💡 To attempt automatic fixes, run:');
            $this->line('   php artisan user:detect-wrong-auth-types --fix');
            $this->newLine();
            $this->info('💡 To preview fixes without applying them, run:');
            $this->line('   php artisan user:detect-wrong-auth-types --dry-run');
            $this->newLine();
            $this->info('💡 To fix a specific user, run:');
            $this->line('   php artisan user:fix-auth-type <username> <auth_type>');
        }

        return Command::SUCCESS;
    }

    /**
     * Display an issue category with affected users
     */
    private function displayIssue(array $issue): void
    {
        $severityColor = $issue['severity'] === 'critical' ? 'red' : 'yellow';
        $severityIcon = $issue['severity'] === 'critical' ? '🔴' : '⚠️';
        
        $this->line("<fg={$severityColor}>{$severityIcon} {$issue['description']}</>");
        $this->line("   Found: {$issue['users']->count()} users");
        
        // Display table of affected users
        $this->table(
            ['ID', 'Username', 'Auth Type', 'Has Password', 'Employee Type', 'Approval'],
            $issue['users']->map(function ($user) {
                return [
                    $user->id,
                    $user->username,
                    $user->auth_type ?? 'NULL',
                    $user->password ? 'Yes' : 'No',
                    $user->employeetype,
                    $user->approval ? 'Yes' : 'No',
                ];
            })->toArray()
        );
        
        $this->newLine();
    }

    /**
     * Attempt to automatically fix detected issues
     */
    private function attemptAutoFix(array $issues, bool $isDryRun): void
    {
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - Showing what would be fixed without making changes...');
        } else {
            $this->warn('🔧 Attempting to automatically fix detected issues...');
        }
        
        $this->newLine();

        $fixedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($issues as $issue) {
            $this->line("Processing: {$issue['description']}");
            
            foreach ($issue['users'] as $user) {
                $result = $this->tryFixUser($user, $issue['type'], $isDryRun);
                
                if ($result['status'] === 'fixed') {
                    $fixedCount++;
                    $this->info("  ✅ {$user->username}: {$result['message']}");
                } elseif ($result['status'] === 'skipped') {
                    $skippedCount++;
                    $this->warn("  ⏭  {$user->username}: {$result['message']}");
                } else {
                    $failedCount++;
                    $this->error("  ❌ {$user->username}: {$result['message']}");
                }
            }
            
            $this->newLine();
        }

        // Summary
        $this->newLine();
        if ($isDryRun) {
            $this->info("📊 Dry Run Summary:");
            $this->line("   Would fix: {$fixedCount} users");
        } else {
            $this->info("📊 Fix Summary:");
            $this->line("   Fixed: {$fixedCount} users");
        }
        $this->line("   Skipped: {$skippedCount} users (need manual review)");
        if ($failedCount > 0) {
            $this->line("   Failed: {$failedCount} users");
        }

        if ($isDryRun && $fixedCount > 0) {
            $this->newLine();
            $this->info('To apply these fixes, run:');
            $this->line('   php artisan user:detect-wrong-auth-types --fix');
        }
    }

    /**
     * Try to automatically fix a user's auth_type
     */
    private function tryFixUser(User $user, string $issueType, bool $isDryRun): array
    {
        try {
            switch ($issueType) {
                case 'local_no_password':
                    // Local user without password - likely should be external auth
                    // Check if username suggests external auth (e.g., LDAP format)
                    return [
                        'status' => 'skipped',
                        'message' => 'Cannot auto-fix: Needs manual review (local user without password)',
                    ];

                case 'external_with_password':
                    // External auth user with password - likely should be local
                    if ($isDryRun) {
                        return [
                            'status' => 'fixed',
                            'message' => "Would change auth_type to 'local' (has password)",
                        ];
                    }
                    
                    $oldAuthType = $user->auth_type;
                    $user->auth_type = 'local';
                    $user->save();
                    
                    Log::info('Auto-fixed user auth_type', [
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'old_auth_type' => $oldAuthType,
                        'new_auth_type' => 'local',
                        'reason' => 'Had password but was marked as external auth',
                    ]);
                    
                    return [
                        'status' => 'fixed',
                        'message' => "Changed auth_type from '{$oldAuthType}' to 'local' (has password)",
                    ];

                case 'null_auth_type':
                    // User with NULL auth_type
                    return [
                        'status' => 'skipped',
                        'message' => 'Cannot auto-fix: Needs manual review (NULL auth_type)',
                    ];

                case 'invalid_auth_type':
                    // User with invalid auth_type
                    return [
                        'status' => 'skipped',
                        'message' => "Cannot auto-fix: Invalid auth_type '{$user->auth_type}'",
                    ];

                default:
                    return [
                        'status' => 'skipped',
                        'message' => 'Unknown issue type',
                    ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to auto-fix user auth_type', [
                'user_id' => $user->id,
                'username' => $user->username,
                'issue_type' => $issueType,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }
}
