<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Http\Controllers\AccessTokenController;
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
    public function handle()
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

        // Create/revoke token functionality
        $tokenController = new AccessTokenController();
        
        // Simulate authentication for the user
        auth()->setUser($user);
        
        if ($isRevoking) {
            // List existing tokens
            $this->listUserTokens($user, $tokenController);

            $tokenId = $this->ask('Enter the token ID to revoke');
            
            // Create a request with token ID
            $request = new Request(['tokenId' => $tokenId]);
            
            // Call the revoke method
            $response = $tokenController->revokeToken($request);
            
            // Check the response status
            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getContent(), true);
                if ($responseData['success']) {
                    $this->info('Token successfully revoked.');
                } else {
                    $this->error($responseData['message']);
                }
            } else {
                $this->error('Failed to revoke token.');
            }
        } else {
            // Create a token
            $tokenName = $this->ask('Enter a name for the token (max 16 characters)');
            
            // Create a request with token name
            $request = new Request(['name' => $tokenName]);
            
            // Call the create method
            $response = $tokenController->createToken($request);
            
            // Check the response status
            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getContent(), true);
                if ($responseData['success']) {
                    $this->info('Token created successfully:');
                    $this->line('');
                    $this->line('Token ID: ' . $responseData['id']);
                    $this->line('Token Name: ' . $responseData['name']);
                    $this->line('');
                    $this->warn('IMPORTANT: Copy this token now - it will not be shown again!');
                    $this->line('');
                    $this->info($responseData['token']);
                    $this->line('');
                } else {
                    $this->error('Failed to create token.');
                }
            } else {
                $this->error('Failed to create token.');
            }
        }
    }

    /**
     * List tokens for the user
     */
    private function listUserTokens(User $user, AccessTokenController $tokenController)
    {
        $response = $tokenController->fetchTokenList(new Request());
        $responseData = json_decode($response->getContent(), true);
        
        if (!$responseData['success'] || empty($responseData['tokens'])) {
            $this->warn('No tokens found for this user.');
            return;
        }
        
        $this->info('Available tokens:');
        $headers = ['ID', 'Name'];
        $rows = [];
        
        foreach ($responseData['tokens'] as $token) {
            $rows[] = [$token['id'], $token['name']];
        }
        
        $this->table($headers, $rows);
    }
}
