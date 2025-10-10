<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_keychain_values', static function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value');
            $table->string('type');
            $table->foreignIdFor(User::class);
            $table->timestamps();
            
            $table->unique(['user_id', 'key', 'type']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('user_keychain_values');
    }
};
