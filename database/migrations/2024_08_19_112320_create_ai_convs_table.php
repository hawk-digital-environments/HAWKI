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
        Schema::create('ai_convs', function (Blueprint $table) {

            $table->id();

            $table->string('conv_name');

            $table->string('slug')->unique();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Foreign key to users table
            
            $table->text('system_prompt')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_convs');
    }
};
