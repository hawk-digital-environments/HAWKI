<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tools', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('mcp');    // 'mcp' or 'function'
            $table->string('name')->unique();
            $table->string('class_name')->nullable();
            $table->foreignId('server_id')->nullable()->constrained('mcp_servers')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('capability')->nullable();
            $table->json('inputSchema')->nullable();
            $table->json('outputSchema')->nullable();
            $table->string('status')->default('active'); // 'active', 'inactive'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tools');
    }
};
