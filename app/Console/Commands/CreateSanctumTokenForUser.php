<?php

namespace App\Console\Commands;

use App\Services\Profile\ProfileService;
use Exception;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Http\Request;

class CreateSanctumTokenForUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:token {--revoke : Revoke a token instead of creating one}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or revoke Sanctum API tokens for a user';



    /**
     * Execute the console command.
     */
    public function handle(ProfileService $profileService)
    {
        $isRevoking = $this->option('revoke');
        $actionText = $isRevoking ? 'revoke' : 'create';

        $this->info("You are about to $actionText an API token for a user.");

        // Present options to identify the user
        $choice = $this->choice(
            'How would you like to identify the user?',
            ['Username', 'Email Address', 'UserID'],
            0
        );

        // Ask for the respective value
        $value = $this->ask("Please enter the $choice");

        // Find the user
        $user = null;
        switch($choice) {
            case 'Username':
                $user = User::where('username', $value)->first();
                break;
            case 'Email Address':
                $user = User::where('email', $value)->first();
                break;
            case 'UserID':
                $user = User::find($value);
                break;
        }

        if (!$user) {
            $this->error('User not found!');
            return;
        }

        if ($user->isRemoved === 1) {
            $this->error('User account is suspended!');
            return;
        }

        // Simulate authentication for the user
        auth()->setUser($user);

        if ($isRevoking) {
            // List existing tokens
            $this->listUserTokens($profileService);

            $tokenId = $this->ask('Enter the token ID to revoke');
            try{
                // Call the revoke method
                $profileService->revokeToken($tokenId);
                $this->info('Token successfully revoked.');
            }
            catch(Exception $e){
                $this->error('Failed to revoke token.' . $e);
            }

        } else {
            // Create a token
            $tokenName = $this->ask('Enter a name for the token (max 16 characters)');

            // Call the create method
            $token = $profileService->createApiToken($tokenName);

            // Check the response status
            if ($token) {
                $this->info('Token created successfully:');
                $this->line('');
                $this->line('Token ID: ' . $token->accessToken->id);
                $this->line('Token Name: ' . $token->accessToken->name);
                $this->line('');
                $this->warn('IMPORTANT: Copy this token now - it will not be shown again!');
                $this->line('');
                $this->info($token->plainTextToken);
                $this->line('');
            } else {
                $this->error('Failed to create token.');
            }
        }
    }

    /**
     * List tokens for the user
     */
    private function listUserTokens(ProfileService $profileService)
    {
        $tokens = $profileService->fetchTokenList();

        if (!$tokens || empty($tokens)) {
            $this->warn('No tokens found for this user.');
            return;
        }

        $this->info('Available tokens:');
        $headers = ['ID', 'Name'];
        $rows = [];

        foreach ($tokens as $token) {
            $rows[] = [$token['id'], $token['name']];
        }

        $this->table($headers, $rows);
    }
}
