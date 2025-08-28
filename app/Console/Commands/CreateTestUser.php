<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Events\GuestAccountCreated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-test 
                            {--count=1 : Number of test users to create}
                            {--type=guest : Employee type for the user (guest, student, staff, admin)}
                            {--auth=Local : Authentication type (Local, LDAP)}
                            {--no-approval : Create users without approval (pending approval)}
                            {--no-notify : Do not send admin notifications for created users}
                            {--admin : Create a default admin user (name=admin, password=password, type=admin)}
                            {--username= : Custom username (only with --admin flag)}
                            {--password= : Custom password (only with --admin flag)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test users with incremental naming (Testuser<ID>) or create a default admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
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
    private function createTestUser(string $employeeType, string $authType, bool $hasApproval = true): User
    {
        // Ensure employeeType is lowercase for Orchid role compatibility
        $employeeType = strtolower($employeeType);
        
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
            'reset_pw' => ($authType === 'Local'), // Local users need password reset
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
     * Create a default admin user
     */
    private function createAdminUser(): int
    {
        $username = $this->option('username') ?: 'admin';
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
