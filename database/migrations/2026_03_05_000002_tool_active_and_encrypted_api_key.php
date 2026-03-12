<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add user-controlled active flag to ai_tools ─────────────────────
        Schema::table('ai_tools', function (Blueprint $table) {
            // 'status' is system-managed (set by tools:check-status based on reachability).
            // 'active' is user-managed — lets operators disable a tool without deleting it.
            $table->boolean('active')->default(true)->after('status');
        });

        // ── 2. Expand api_key column to TEXT (encrypted values exceed 255 chars) ─
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->text('api_key')->nullable()->change();
        });

        // ── 3. Re-encrypt existing plaintext api_key values ────────────────────
        DB::table('mcp_servers')
            ->whereNotNull('api_key')
            ->where('api_key', '!=', '')
            ->get()
            ->each(function ($server) {
                DB::table('mcp_servers')
                    ->where('id', $server->id)
                    ->update(['api_key' => Crypt::encryptString($server->api_key)]);
            });
    }

    public function down(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn('active');
        });

        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->string('api_key')->default('')->change();
        });
    }
};
