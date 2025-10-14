<?php

use App\Services\Storage\AvatarStorageService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $hawki = DB::table('users')->where('id', 1)->first();

        // Always ensure ID = 1 exists
        DB::table('users')->updateOrInsert(
            ['id' => 1],
            [
                'name'        => config('hawki.migration.name'),
                'username'    => config('hawki.migration.username'),
                'email'       => config('hawki.migration.email'),
                'employeetype'=> config('hawki.migration.employeetype'),
                'publicKey'   => '0',
                'avatar_id'   => config('hawki.migration.avatar_id'),
                'updated_at'  => now(),
                'created_at'  => $hawki?->created_at ?? now(),
            ]
        );

        // Handle avatar
        //ADD HAWKI AVATAR TO STORAGE FOLDER:
        if(public_path('img/' . config('hawki.migration.avatar_id'))){
            $file = file_get_contents(public_path('img/' . config('hawki.migration.avatar_id')));
            if(!$file){
                throw new \Exception('File not found');
            }

            $avatarStorage = app(AvatarStorageService::class);
            $avatarStorage->store($file,
                config('hawki.migration.avatar_id'),
                config('hawki.migration.avatar_id'),
                'profile_avatars');
        }
    }

};
