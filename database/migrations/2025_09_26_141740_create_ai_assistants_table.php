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
        Schema::create('ai_assistants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique()->comment('Stabiler Slug (z.B. support_bot)');
            $table->string('name')->comment('Anzeigename');
            $table->text('description')->nullable()->comment('Kurze Beschreibung');
            $table->enum('status', ['draft', 'active', 'archived'])->default('active')->comment('Status des Assistenten');
            $table->enum('visibility', ['private', 'org', 'public'])->default('private')->comment('Sichtbarkeit des Assistenten');
            $table->uuid('org_id')->nullable()->comment('Organisation/Gruppe für RBAC');
            $table->unsignedBigInteger('owner_id')->default(0)->comment('Verantwortliche Person');
            $table->string('ai_model')->nullable()->comment('AI Model System ID');
            $table->string('prompt')->nullable()->comment('Prompt Type Reference');
            $table->json('tools')->nullable()->comment('Available tools configuration');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ai_model')->references('system_id')->on('ai_models')->onDelete('set null');

            // Indexes for performance
            $table->index(['status', 'visibility']);
            $table->index('org_id');
            $table->index('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistants');
    }
};
