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
        Schema::create('app_css', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('CSS file name or identifier');
            $table->string('description')->comment('CSS file description');
            $table->text('content')->comment('CSS content');
            $table->boolean('active')->default(true)->comment('Whether this CSS is active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_css');
    }
};
