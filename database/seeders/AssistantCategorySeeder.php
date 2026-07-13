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
            'Academic Writing',
            'Computer Science',
            'Study Tools',
            'Natural Sciences',
            'Campus Life',
            'Research',
            'Mathematics',
            'Languages',
        ];

        foreach ($categories as $text) {
            AssistantCategory::firstOrCreate(['text' => $text]);
        }
    }
}
