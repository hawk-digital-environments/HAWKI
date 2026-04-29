<?php

namespace Database\Seeders;

use App\Models\Assistants\Category;
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
            Category::firstOrCreate(['text' => $text]);
        }
    }
}
