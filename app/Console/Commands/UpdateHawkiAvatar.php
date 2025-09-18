<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdateHawkiAvatar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hawki:update-avatar {path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update HAWKI AVATAR.';


    public function handle()
    {
        $path = $this->argument('path');

        $hawki = User::find(1);
        if($hawki->username != config('hawki.migration.username')){
            $this->error('HAWKI user does not exist or is manipulated. Please double check your migration file and hawki config.');
            return;
        }

        $file = file_get_contents($path);
        if(!$file){
            $this->error('Unable to open file.');
        }

        $array = explode('/', $path);
        $filename = end($array);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $avatarStorage = app(AvatarStorageService::class);
        $uuid = Str::uuid();
        $avatarStorage->store($file,
                            $filename,
                            $uuid,
                            'profile_avatars');


        $hawki->update([
            'avatar_id' => $uuid,
        ]);
        $this->info('HAWKI Avatar was successfully updated.');
    }
}
