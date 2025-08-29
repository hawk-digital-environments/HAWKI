<?php

use App\Models\ExtApp;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ext_app_users', static function (Blueprint $table) {
            $table->id();
            $table->text('user_public_key');
            $table->text('user_private_key');
            $table->string('ext_user_id');
            $table->text('passkey');
            $table->text('api_token');
            $table->foreignIdFor(ExtApp::class);
            $table->foreignIdFor(User::class);
            $table->foreignIdFor(PersonalAccessToken::class);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('app_users');
    }
};
