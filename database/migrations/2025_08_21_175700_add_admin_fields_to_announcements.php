<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin panel columns on announcements. They live in their own migration ordered before
 * `create_basic_announcements`, because that data migration seeds through the announcement
 * services, which filter on `is_published`; a fresh database would otherwise fail to migrate.
 *
 * `target_roles` lives here rather than in the later
 * `create_permission_system_tables` migration (which only adds it
 * conditionally, via `Schema::hasColumn`) for the same reason: the seed data
 * in `create_basic_announcements` writes to it too, and that migration runs
 * before the permission-system one on a fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->json('content')->nullable();
            $table->boolean('is_published')->default(true);
            $table->json('target_roles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', fn(Blueprint $table) => $table->dropColumn(['content', 'is_published', 'target_roles']));
    }
};
