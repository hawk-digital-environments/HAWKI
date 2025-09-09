<?php

use App\Services\Storage\FileStorageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->text('publicKey');
            $table->string('employeetype');
            $table->string('avatar_id')->nullable();
            $table->string('bio')->nullable();
            $table->timestamps();
        });

        // Ensure HAWKI user exists and has ID = 1
        DB::table('users')->updateOrInsert(
            ['id' => 1], // Force ID 1
            [
                'name'        => config('hawki.migration.name'),
                'username'    => config('hawki.migration.username'),
                'email'       => config('hawki.migration.email'),
                'employeetype'=> config('hawki.migration.employeetype'),
                'publicKey'   => '0',
                'avatar_id'   => config('hawki.migration.avatar_id'),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        //ADD HAWKI AVATAR TO STORAGE FOLDER:
        if(public_path(config('hawki.migration.avatar_id'))){
            $file = file_get_contents(public_path(config('hawki.migration.avatar_id')));
            $fileStorage = app(FileStorageService::class);
            $fileStorage->store($file,
                                config('hawki.migration.avatar_id'),
                                config('hawki.migration.avatar_id'),
                                'profile_avatars');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
