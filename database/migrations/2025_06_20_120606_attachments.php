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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable'); // Creates 'attachable_id' (BIGINT), 'attachable_type' (VARCHAR)
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Foreign key to ai_convs table
            $table->string('uuid');
            $table->string('name');
            $table->string('category');
            $table->enum('type', ['image', 'document', 'audio', 'video', 'other']);
            $table->string('mime');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
