<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches usage_records with the attribution context from the generic-proxy usage
 * rework: entry-point channel, client user agent, wire format key and assistant
 * handle (reserved). Existing rows keep null — they predate the rework.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_records', static function (Blueprint $table): void {
            $table->string('channel', 32)->nullable()->after('type');
            $table->string('user_agent', 512)->nullable()->after('channel');
            $table->string('format_key', 64)->nullable()->after('user_agent');
            $table->string('assistant_handle', 255)->nullable()->after('format_key');
        });
    }

    public function down(): void
    {
        Schema::table('usage_records', static function (Blueprint $table): void {
            $table->dropColumn(['channel', 'user_agent', 'format_key', 'assistant_handle']);
        });
    }
};
