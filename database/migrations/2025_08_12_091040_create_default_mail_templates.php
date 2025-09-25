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
        // Drop existing table if it exists
        Schema::dropIfExists('mail_templates');

        // Create new simplified mail_templates table
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type')->comment('Template type (welcome, otp, invitation, etc.)');
            $table->string('language', 5)->default('de')->comment('Language code (de, en, etc.)');
            $table->text('description')->nullable()->comment('Template description for admin interface');
            $table->string('subject')->comment('Email subject line with placeholder support');
            $table->longText('body')->comment('Email HTML body with placeholder support');
            $table->timestamps();

            // Indexes for performance
            $table->index(['type', 'language'], 'idx_mail_templates_type_lang');

            // Unique constraint for template type per language
            $table->unique(['type', 'language'], 'uk_mail_templates_type_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
