<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Events\GuestAccountCreated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:manage 
                            {--count=1 : Number of test users to create}
                            {--type=guest : Employee type for the user (guest, student, staff, admin)}
                            {--auth=Local : Authentication type (Local, LDAP)}
                            {--no-approval : Create users without approval (pending approval)}
                            {--no-notify : Do not send admin notifications for created users}
                            {--admin : Create a default admin user (name=admin, password=password, type=admin)}
                            {--username= : Custom username (only with --admin flag)}
                            {--password= : Custom password (only with --admin flag)}
                            {--upgrade-to-admin= : Upgrade existing user to admin (provide username or ID)}
                            {--set-password= : Set password for upgraded user (default: password)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage users: create test users, create admin users, or upgrade existing users to admin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Handle admin user upgrade
        if ($this->option('upgrade-to-admin')) {
            return $this->upgradeUserToAdmin();
        }

        // Handle admin user creation
        if ($this->option('admin')) {
            return $this->createAdminUser();
        }

        $count = (int) $this->option('count');
        $employeeType = $this->option('type');
        $authType = $this->option('auth');
        $shouldNotify = !$this->option('no-notify'); // Notify by default, unless --no-notify
        $hasApproval = !$this->option('no-approval'); // Approved by default, unless --no-approval
        
        $approvalStatus = $hasApproval ? 'approved' : 'pending approval';
        $this->info("Creating {$count} test user(s) with type '{$employeeType}', auth '{$authType}', and status '{$approvalStatus}'...");
        
        if (!$shouldNotify) {
            $this->comment("Admin notifications are disabled (--no-notify flag used).");
        } else {
            $this->comment("Admin notifications will be sent for each created user.");
        }
        
        $createdUsers = [];
        
        for ($i = 0; $i < $count; $i++) {
            try {
                $user = $this->createTestUser($employeeType, $authType, $hasApproval);
                $createdUsers[] = $user;
                
                $this->line("✅ Created user: {$user->username} (ID: {$user->id})");
                
                // Send notification if requested
                if ($shouldNotify) {
                    \App\Events\GuestAccountCreated::dispatch($user);
                    $this->comment("   📧 Admin notification sent");
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Failed to create test user: " . $e->getMessage());
                Log::error('Failed to create test user', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        if (count($createdUsers) > 0) {
            $this->newLine();
            $this->info("Summary:");
            $this->table(
                ['ID', 'Username', 'Name', 'Email', 'Type', 'Auth', 'Approval'],
                collect($createdUsers)->map(function ($user) {
                    return [
                        $user->id,
                        $user->username,
                        $user->name,
                        $user->email,
                        $user->employeetype,
                        $user->auth_type,
                        $user->approval ? 'Approved' : 'Pending'
                    ];
                })->toArray()
            );
            
            $this->newLine();
            $this->comment("All test users have the password: 'password'");
            $this->comment("Local users with auth_type 'Local' will need to reset their password on first login.");
            
            if ($shouldNotify) {
                $this->comment("Admin notifications were sent for all created users.");
            } else {
                $this->comment("No admin notifications were sent (--no-notify flag used).");
            }
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Create a single test user with incremental naming
     */
    private function createTestUser(string $employeeType, string $authType, bool $hasApproval = null): User
    {
        // Ensure employeeType is lowercase for Orchid role compatibility
        $employeeType = strtolower($employeeType);
        
        // If approval not explicitly set, use local_needapproval config for local users
        if ($hasApproval === null) {
            $hasApproval = true; // Test users created by admin command are always approved
        }
        
        // Create user with temporary data first
        $userData = [
            'username' => 'temp_user_' . time(), // Temporary username
            'name' => 'Temporary User', // Temporary name
            'email' => 'temp_' . time() . '@hawki.test', // Temporary email
            'employeetype' => $employeeType,
            'auth_type' => $authType,
            'password' => 'password', // Will be auto-hashed by User model
            'publicKey' => '', // Always empty string
            'avatar_id' => null,
            'reset_pw' => ($authType === 'local'), // Local users need password reset
            'approval' => $hasApproval, // Set approval status
            'isRemoved' => false,
            'permissions' => null, // Always NULL
        ];
        
        $user = User::create($userData);
        
        // Now update with the correct ID-based values
        $user->username = "Testuser{$user->id}";
        $user->name = "Test User {$user->id}";
        $user->email = "testuser{$user->id}@hawki.test";
        
        // Check if username already exists (very unlikely, but safety first)
        if (User::where('username', $user->username)->where('id', '!=', $user->id)->exists()) {
            // If conflict, delete the user and throw exception
            $user->delete();
            throw new \Exception("Username 'Testuser{$user->id}' already exists");
        }
        
        $user->save();
        
        Log::info('Test user created via artisan command', [
            'username' => $user->username,
            'id' => $user->id,
            'type' => $employeeType,
            'auth' => $authType
        ]);
        
        return $user;
    }

    /**
     * Upgrade an existing user to admin
     */
    private function upgradeUserToAdmin(): int
    {
        $userIdentifier = $this->option('upgrade-to-admin');
        $newPassword = $this->option('set-password') ?: 'password';

        // Find the user by ID or username
        $user = $this->findUser($userIdentifier);
        
        if (!$user) {
            $this->error("❌ User not found: {$userIdentifier}");
            return Command::FAILURE;
        }

        try {
            $this->info("Upgrading user to admin...");
            $this->info("User: {$user->username} (ID: {$user->id})");
            
            // Show current state
            $this->table(
                ['Field', 'Current Value'],
                [
                    ['Username', $user->username],
                    ['Name', $user->name],
                    ['Email', $user->email],
                    ['Employee Type', $user->employeetype],
                    ['Auth Type', $user->auth_type],
                    ['Has Password', $user->password ? 'Yes' : 'No'],
                    ['Approval', $user->approval ? 'Approved' : 'Pending'],
                    ['Current Roles', $user->roles->pluck('name')->join(', ') ?: 'None'],
                ]
            );

            if (!$this->confirm('Do you want to upgrade this user to admin?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            // Store original values for comparison
            $originalEmployeeType = $user->employeetype;
            $originalApproval = $user->approval;
            $originalPassword = $user->password;

            // Update user to admin
            $updateData = [
                'employeetype' => 'admin',
                'auth_type' => 'Local', // Ensure local auth
                'approval' => true, // Always approve admins
                'isRemoved' => false, // Ensure not removed
            ];

            // Set password if not set or if explicitly provided
            if (!$user->password || $this->option('set-password')) {
                $updateData['password'] = $newPassword; // Will be auto-hashed
                $updateData['reset_pw'] = false; // Admin doesn't need to reset
            }

            $user->update($updateData);

            // Manually trigger UserObserver to ensure role sync
            // This is important because update() might not trigger if no fields actually changed
            $observer = new \App\Observers\UserObserver();
            $reflection = new \ReflectionClass($observer);
            $method = $reflection->getMethod('syncOrchidRole');
            $method->setAccessible(true);
            $method->invoke($observer, $user);

            // Refresh user to get updated data
            $user->refresh();

            $this->info("✅ User successfully upgraded to admin!");
            
            // Show what changed
            $changes = [];
            if ($originalEmployeeType !== 'admin') {
                $changes[] = "Employee Type: {$originalEmployeeType} → admin";
            }
            if (!$originalApproval) {
                $changes[] = "Approval: false → true";
            }
            if (!$originalPassword || $this->option('set-password')) {
                $changes[] = "Password: " . ($originalPassword ? 'updated' : 'set');
            }

            if (!empty($changes)) {
                $this->info("Changes made:");
                foreach ($changes as $change) {
                    $this->line("  • {$change}");
                }
            }

            // Show final state
            $this->newLine();
            $this->table(
                ['Field', 'New Value'],
                [
                    ['Username', $user->username],
                    ['Employee Type', $user->employeetype],
                    ['Auth Type', $user->auth_type],
                    ['Approval', $user->approval ? 'Approved' : 'Pending'],
                    ['Has Password', $user->password ? 'Yes' : 'No'],
                    ['Orchid Roles', $user->roles->pluck('name')->join(', ') ?: 'None'],
                ]
            );

            if (!$originalPassword || $this->option('set-password')) {
                $this->comment("New password: {$newPassword}");
            }

            Log::info('User upgraded to admin via artisan command', [
                'user_id' => $user->id,
                'username' => $user->username,
                'original_type' => $originalEmployeeType,
                'new_type' => 'admin'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to upgrade user to admin: " . $e->getMessage());
            Log::error('Failed to upgrade user to admin', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
     * Create a default admin user
     */
    private function createAdminUser(): int
    {
        $username = $this->option('username') ?: 'admin-local';
        $password = $this->option('password') ?: 'password';

        // Check if user already exists
        if (User::where('username', $username)->exists()) {
            $this->error("❌ User with username '{$username}' already exists!");
            return Command::FAILURE;
        }

        try {
            $this->info("Creating admin user...");
            $this->info("Username: {$username}");
            $this->info("Password: {$password}");
            $this->info("Type: admin");
            $this->info("Approval: true");

            $userData = [
                'username' => $username,
                'name' => ucfirst($username), // 'admin' -> 'Admin'
                'email' => $username . '@hawki.local',
                'employeetype' => 'admin',
                'auth_type' => 'Local',
                'password' => $password, // Will be auto-hashed by User model
                'publicKey' => '',
                'avatar_id' => null,
                'reset_pw' => false, // Admin doesn't need to reset password immediately
                'approval' => true, // Admin is always approved
                'isRemoved' => false,
                'permissions' => null,
            ];

            $user = User::create($userData);

            $this->info("✅ Admin user created successfully!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $user->id],
                    ['Username', $user->username],
                    ['Name', $user->name],
                    ['Email', $user->email],
                    ['Employee Type', $user->employeetype],
                    ['Auth Type', $user->auth_type],
                    ['Approval', $user->approval ? 'Approved' : 'Pending'],
                    ['Reset Password', $user->reset_pw ? 'Required' : 'Not Required'],
                ]
            );

            $this->newLine();
            $this->comment("The admin user has been created with approval=true.");
            $this->comment("The UserObserver will automatically assign the 'Administrator' role if it exists.");
            $this->comment("You can now log in with username '{$username}' and password '{$password}'.");

            Log::info('Admin user created via artisan command', [
                'username' => $user->username,
                'id' => $user->id,
                'type' => 'admin'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create admin user: " . $e->getMessage());
            Log::error('Failed to create admin user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
