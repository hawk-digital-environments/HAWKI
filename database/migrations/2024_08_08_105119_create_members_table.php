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
        Schema::create('members', function (Blueprint $table) {
            
            $table->id();
            
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            $table->timestamp('last_read')->useCurrent();
            
            $table->string('role'); // e.g., editor, admin, viewer
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
