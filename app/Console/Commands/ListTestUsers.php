<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListTestUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:list-test 
                            {--all : Show all test users including non-Testuser pattern}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all test users created with user:manage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $showAll = $this->option('all');
        
        if ($showAll) {
            // Show all users that look like test users
            $testUsers = User::where(function($query) {
                $query->where('username', 'LIKE', 'Testuser%')
                      ->orWhere('username', 'LIKE', 'testuser%')
                      ->orWhere('email', 'LIKE', '%@hawki.test')
                      ->orWhere('email', 'LIKE', '%@example.com');
            })->orderBy('username')->get();
            
            $this->info("All test users (including manual ones):");
        } else {
            // Show only users created by the command (Testuser pattern)
            $testUsers = User::where('username', 'LIKE', 'Testuser%')
                ->orderBy('username')
                ->get();
                
            $this->info("Test users created with 'user:manage' command:");
        }
        
        if ($testUsers->isEmpty()) {
            $this->comment("No test users found.");
            return Command::SUCCESS;
        }
        
        $this->table(
            ['ID', 'Username', 'Name', 'Email', 'Type', 'Auth', 'Created'],
            $testUsers->map(function ($user) {
                return [
                    $user->id,
                    $user->username,
                    $user->name,
                    $user->email,
                    $user->employeetype,
                    $user->auth_type,
                    $user->created_at->format('Y-m-d H:i')
                ];
            })->toArray()
        );
        
        $this->newLine();
        $this->comment("Found " . $testUsers->count() . " test user(s)");
        $this->comment("All test users have the password: 'password'");
        
        // Show next available ID
        $nextId = $this->getNextTestUserId();
        $this->comment("Next available Testuser ID would be: Testuser{$nextId}");
        
        return Command::SUCCESS;
    }
    
    /**
     * Get the next available Testuser ID
     */
    private function getNextTestUserId(): int
    {
        $lastTestUser = User::where('username', 'LIKE', 'Testuser%')
            ->orderByRaw('CAST(SUBSTRING(username, 9) AS UNSIGNED) DESC')
            ->first();
            
        if ($lastTestUser) {
            $lastId = (int) substr($lastTestUser->username, 8);
            return $lastId + 1;
        }
        
        return 1;
    }
}
