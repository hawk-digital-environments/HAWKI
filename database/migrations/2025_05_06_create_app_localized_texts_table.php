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
        Schema::create('app_localized_texts', function (Blueprint $table) {
            $table->id();
            $table->string('content_key'); // z.B. 'guidelines', 'data_protection', 'group_welcome'
            $table->string('language', 10); // z.B. 'de_DE', 'en_US'
            $table->longText('content'); // HTML-Inhalt oder langer Text
            $table->timestamps();
            
            // Unique-Constraint für content_key und language
            $table->unique(['content_key', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_localized_texts');
    }
};
