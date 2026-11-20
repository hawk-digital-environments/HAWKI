<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Assistants\AssistantCategory;
use Illuminate\Database\Seeder;

class AssistantCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'assistants.categories.academic_writing',
            'assistants.categories.computer_science',
            'assistants.categories.study_tools',
            'assistants.categories.science',
            'assistants.categories.campus_life',
            'assistants.categories.research',
            'assistants.categories.math',
            'assistants.categories.languages',
        ];

        foreach ($categories as $text) {
            AssistantCategory::firstOrCreate(['text' => $text]);
        }
    }
}
