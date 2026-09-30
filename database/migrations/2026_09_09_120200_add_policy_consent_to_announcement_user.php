<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the `announcement_user` pivot into a usable consent record.
 *
 * Two things were missing to be able to answer "has this user accepted the policy that is
 * currently live, in a language they could read?":
 *  - `locale`       — which language the user was shown when they accepted.
 *  - `content_hash` — the hash of the normalized policy text they accepted. Announcement content
 *                     lives in markdown files, so the text can change without the announcement
 *                     row changing; without the hash an old consent looks indistinguishable from
 *                     a current one.
 *
 * The unique index on (announcement_id, user_id) is what makes the consent write an upsert
 * instead of an append. `syncWithoutDetaching()` never guaranteed uniqueness, so duplicate pivot
 * rows can exist today; they are merged before the index is created.
 *
 * Merge rule: the highest `id` survives and inherits the *earliest* non-null `seen_at` and
 * `accepted_at` of its group. Consent is a fact about the past — keeping the earliest timestamp
 * cannot invent a consent that never happened, and cannot lose one that did.
 */
return new class extends Migration {
    private const string UNIQUE_INDEX_NAME = 'announcement_user_announcement_id_user_id_unique';

    public function up(): void
    {
        Schema::table('announcement_user', static function (Blueprint $table) {
            $table->string('locale', 32)->nullable();
            $table->string('content_hash', 64)->nullable();
        });

        $this->mergeDuplicatePivots();

        if (!Schema::hasIndex('announcement_user', self::UNIQUE_INDEX_NAME)) {
            Schema::table('announcement_user', static function (Blueprint $table) {
                $table->unique(['announcement_id', 'user_id'], self::UNIQUE_INDEX_NAME);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('announcement_user', self::UNIQUE_INDEX_NAME)) {
            Schema::table('announcement_user', static function (Blueprint $table) {
                $table->dropUnique(self::UNIQUE_INDEX_NAME);
            });
        }

        Schema::table('announcement_user', static function (Blueprint $table) {
            $table->dropColumn(['locale', 'content_hash']);
        });
    }

    private function mergeDuplicatePivots(): void
    {
        $duplicateGroups = DB::table('announcement_user')
            ->select('announcement_id', 'user_id')
            ->groupBy('announcement_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('announcement_user')
                ->where('announcement_id', $group->announcement_id)
                ->where('user_id', $group->user_id)
                ->orderBy('id')
                ->get();

            $survivor = $rows->last();

            DB::table('announcement_user')
                ->where('id', $survivor->id)
                ->update([
                    'seen_at' => $this->earliest($rows->pluck('seen_at')->all()),
                    'accepted_at' => $this->earliest($rows->pluck('accepted_at')->all()),
                ]);

            DB::table('announcement_user')
                ->whereIn('id', $rows->pluck('id')->reject(fn($id) => $id === $survivor->id)->all())
                ->delete();
        }
    }

    /**
     * @param array<int, string|null> $timestamps
     */
    private function earliest(array $timestamps): ?string
    {
        $known = array_filter($timestamps, static fn($value) => $value !== null && $value !== '');
        if ($known === []) {
            return null;
        }

        sort($known);

        return (string)reset($known);
    }
};
