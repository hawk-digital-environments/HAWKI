<?php

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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 50)->index(); // debug, info, warning, error, critical, etc.
            $table->string('channel', 50)->nullable()->index(); // Log channel name
            $table->longText('message'); // Log message (longText for full error messages)
            $table->json('context')->nullable(); // Additional context data
            $table->longText('stack_trace')->nullable(); // Stack trace for errors
            $table->string('remote_addr', 45)->nullable(); // IP address
            $table->string('user_agent')->nullable(); // User agent
            $table->unsignedBigInteger('user_id')->nullable()->index(); // Associated user
            $table->timestamp('logged_at')->useCurrent()->index(); // When the log was created
            $table->timestamps();
            
            // Foreign key constraint for user_id if needed
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
