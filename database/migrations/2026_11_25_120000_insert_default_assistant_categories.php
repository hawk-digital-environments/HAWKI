<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    private const CATEGORIES = [
        'assistants.categories.academic_writing',
        'assistants.categories.computer_science',
        'assistants.categories.study_tools',
        'assistants.categories.science',
        'assistants.categories.campus_life',
        'assistants.categories.research',
        'assistants.categories.math',
        'assistants.categories.languages',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('assistant_categories')->insertOrIgnore(array_map(
            static fn (string $text): array => ['text' => $text, 'created_at' => $now, 'updated_at' => $now],
            self::CATEGORIES,
        ));
    }

    public function down(): void
    {
        DB::table('assistant_categories')->whereIn('text', self::CATEGORIES)->delete();
    }
};
