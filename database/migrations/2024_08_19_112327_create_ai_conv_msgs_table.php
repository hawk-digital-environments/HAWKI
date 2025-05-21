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
        Schema::create('ai_conv_msgs', function (Blueprint $table) {
            
            $table->id();
            
            $table->foreignId('conv_id')->constrained('ai_convs')->onDelete('cascade'); // Foreign key to ai_convs table

            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Foreign key to ai_convs table
            
            $table->string('message_role');
            
            $table->string('message_id');

            $table->string('model')->nullable();
            
            $table->string('iv');
            
            $table->string('tag');
            
            $table->longText('content');
            
            $table->boolean('completion');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conv_msgs');
    }
};
