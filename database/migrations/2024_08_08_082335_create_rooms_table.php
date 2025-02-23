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
        Schema::create('rooms', function (Blueprint $table) {
            
            $table->id();
            
            $table->string('room_name');
            
            $table->string('room_icon')->nullable();

            $table->string('room_description')->nullable();
            
            $table->text('system_prompt')->nullable();
            
            $table->string('slug')->unique();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
