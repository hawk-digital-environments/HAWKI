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
        Schema::create('app_system_images', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Image identifier (e.g., logo, favicon)');
            $table->string('file_path')->comment('Path to the stored image');
            $table->string('original_name')->nullable()->comment('Original uploaded filename');
            $table->string('mime_type')->nullable()->comment('Image MIME type');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_system_images');
    }
};
