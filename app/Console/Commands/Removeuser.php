<?php

namespace App\Console\Commands;

use App\Services\Profile\ProfileService;
use Illuminate\Console\Command;
use App\Http\Controllers\ProfileController;
use App\Models\User;

class Removeuser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:removeuser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes User From Database';

    /**
     * Execute the console command.
     */
    public function handle(ProfileService $profileService)
    {
        // Ask for confirmation
        if ($this->confirm('The user and all the related messages will be permanently removed. Do you want to continue?', true)) {
            // Present options
            $choice = $this->choice(
                'How would you like to identify the user?',
                ['Username', 'Email Address', 'UserID'],
                0
            );

            // Ask for the respective value
            $value = $this->ask("Please enter the $choice");

            switch($choice){
                case('Username'):
                    $user = User::where('username', $value)->first();
                break;
                case('Email Address'):
                    $user = User::where('email', $value)->first();
                break;
                case('UserID'):
                    $user = User::find($value);
                break;
            }

            if(!$user){
                $this->error('User not found!');
                return;
            }
            if($user->isRemoved === 1){
                $this->error('User is already removed!');
                return;
            }

            $profileService->deleteUserData($user);
            $this->info('Profile Reset Successfull!');

        } else {
            $this->info('Command operation cancelled.');
        }
    }
}
