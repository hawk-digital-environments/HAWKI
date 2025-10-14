<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('view');
            $table->enum('type', ['policy', 'news', 'system', 'event', 'info'])->default('info');
            $table->boolean('is_forced')->default(false);
            $table->boolean('is_global')->default(true);
            $table->json('target_users')->nullable();
            $table->string('anchor')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
