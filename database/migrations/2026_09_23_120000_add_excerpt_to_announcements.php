<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional, hand-written teaser for list previews.
 *
 * The announcement body lives in per-locale markdown files, so the excerpt is stored per locale
 * as well: a JSON object keyed by locale code, e.g. `{"de_DE": "…", "en_US": "…"}`. When it is
 * missing for the requested and the default locale, the frontend derives a teaser from the body.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('announcements', static function (Blueprint $table) {
            $table->json('excerpt')->nullable()->after('view');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', static function (Blueprint $table) {
            $table->dropColumn('excerpt');
        });
    }
};
