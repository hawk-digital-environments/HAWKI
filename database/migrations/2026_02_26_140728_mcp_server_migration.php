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
        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('server_label');
            $table->string('version')->nullable();
            $table->string('protocolVersion')->nullable();
            $table->text('description')->nullable();
            $table->string('require_approval')->default('never');
            $table->string('timeout')->default('10');
            $table->string('discovery_timeout')->default('10');
            $table->string('api_key');
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
