<?php

use App\Models\ExtApp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ext_app_user_requests', static function (Blueprint $table) {
            $table->id();
            $table->text('user_public_key');
            $table->text('user_private_key');
            $table->string('ext_user_id');
            $table->string('request_id');
            $table->dateTime('valid_until');
            $table->foreignIdFor(ExtApp::class);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('app_user_requests');
    }
};
