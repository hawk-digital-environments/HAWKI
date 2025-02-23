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
        Schema::create('invitations', function (Blueprint $table) {
            
            $table->id();

            $table->foreignId('room_id')->constrained()->onDelete('cascade');

            $table->string('username');

            $table->string('role');

            $table->string('iv');

            $table->string('tag');

            $table->text('invitation');

            $table->timestamps();

            $table->foreign('username')->references('username')->on('users')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_member');
    }
};
