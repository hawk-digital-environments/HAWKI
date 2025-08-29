<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ext_apps', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->text('app_public_key');
            $table->string('redirect_url', 2048);
            $table->foreignIdFor(User::class, 'app_user_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
