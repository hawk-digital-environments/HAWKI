<?php

namespace Database\Seeders;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantCategory;
use App\Models\Assistants\AssistantTag;
use App\Models\User;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Database\Seeder;

class AssistantSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure we have creators and categories to work with.
        $users = User::query()->take(3)->get();
        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create();
        }

        $categories = AssistantCategory::all();
        if ($categories->isEmpty()) {
            $this->call(AssistantCategorySeeder::class);
            $categories = AssistantCategory::all();
        }

        // A small set of reusable tags.
        $tags = collect(['beginner', 'advanced', 'writing', 'coding', 'research'])
            ->map(fn (string $text) => AssistantTag::firstOrCreate(['text' => $text]));

        // Seed a spread of assistants across every release stage so the
        // visibility, ownership and relationship tests have data to exercise.
        foreach (AssistantReleaseStage::cases() as $stage) {
            Assistant::factory()
                ->count(3)
                ->state(fn () => [
                    'release_stage' => $stage->value,
                    'creator_id' => $users->random()->id,
                    'remixed_creator_id' => null,
                    'category_id' => $categories->random()->id,
                ])
                ->create()
                ->each(function (Assistant $assistant) use ($tags) {
                    $assistant->assistantTags()->syncWithoutDetaching(
                        $tags->random(rand(1, 2))->pluck('id')->all()
                    );

                    $assistant->assistantUserPrompts()->createMany([
                        ['text' => 'Summarize the following text.'],
                        ['text' => 'Explain this concept step by step.'],
                    ]);
                });
        }
    }
}
