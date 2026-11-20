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
    /**
     * Every seeded assistant is attributed to this user. If no user with
     * this id exists yet, one is created with this exact id.
     */
    private const CREATOR_ID = 1;

    /**
     * Used when an entry does not define its own "release_stage".
     */
    private const DEFAULT_RELEASE_STAGE = AssistantReleaseStage::ORGANIZATIONAL->value;

    /**
     * Editable list of seeded assistants.
     *
     * @var list<array{
     *     name: string,
     *     handle: string,
     *     prompt: string,
     *     icon: string,
     *     background: string,
     *     release_stage?: string,
     *     user_prompts?: list<string>,
     * }>
     */
    private const ASSISTANTS = [
        [
            'name' => 'Study Buddy',
            'handle' => 'study-buddy',
            'prompt' => 'You are a patient study companion who helps learners review material, build flashcards and rehearse for exams.',
            'icon' => '🎓',
            'background' => 'background: hsl(228 80% 52%); color: hsl(205 100% 88%)',
            'user_prompts' => [
                'Help me rehearse for my next exam.',
                'Turn my notes into flashcards.',
            ],
        ],
        [
            'name' => 'Research Coach',
            'handle' => 'research-coach',
            'prompt' => 'You are a methodical research tutor who guides students through finding, evaluating and comparing sources.',
            'icon' => '🔬',
            'background' => 'background: hsl(155 100% 19%); color: hsl(72 90% 62%)',
            'user_prompts' => [
                'Find trustworthy sources on my topic.',
                'Compare these two theories for me.',
            ],
        ],
        [
            'name' => 'Writing Tutor',
            'handle' => 'writing-tutor',
            'prompt' => 'You are an encouraging writing tutor who improves structure, clarity and style of student texts.',
            'icon' => '✍️',
            'background' => 'background: hsl(345 75% 66%); color: hsl(340 65% 18%)',
            'user_prompts' => [
                'Give feedback on my introduction.',
                'Suggest a structure for my essay.',
            ],
        ],
        [
            'name' => 'Code Mentor',
            'handle' => 'code-mentor',
            'prompt' => 'You are a pragmatic programming mentor who explains code step by step and reviews snippets for common mistakes.',
            'icon' => '💡',
            'background' => 'background: hsl(260 80% 87%); color: hsl(265 55% 28%)',
            'user_prompts' => [
                'Explain this error message.',
                'Review my function for bugs.',
            ],
        ],
        [
            'name' => 'Lab Assistant',
            'handle' => 'lab-assistant',
            'prompt' => 'You are a careful lab assistant who walks through experiments, safety rules and protocols.',
            'icon' => '🧪',
            'background' => 'background: hsl(72 90% 60%); color: hsl(155 100% 18%)',
            'user_prompts' => [
                'Summarize the following protocol.',
                'What safety rules apply to this experiment?',
            ],
        ],
        [
            'name' => 'Language Guide',
            'handle' => 'language-guide',
            'prompt' => 'You are a friendly language coach who practices vocabulary, grammar and conversation with learners.',
            'icon' => '🌐',
            'background' => 'background: hsl(205 90% 84%); color: hsl(225 70% 25%)',
            'release_stage' => 'federated',
            'user_prompts' => [
                'Practice a conversation with me.',
                'Explain this grammar rule.',
            ],
        ],
        [
            'name' => 'Math Helper',
            'handle' => 'math-helper',
            'prompt' => 'You are a structured math tutor who solves problems step by step and checks the learner\'s own attempts.',
            'icon' => '➗',
            'background' => 'background: hsl(45 98% 60%); color: hsl(355 75% 18%)',
            'user_prompts' => [
                'Solve this equation step by step.',
                'Check my solution for mistakes.',
            ],
        ],
        [
            'name' => 'Career Advisor',
            'handle' => 'career-advisor',
            'prompt' => 'You are a supportive career advisor who helps with orientation, applications and interview preparation.',
            'icon' => '🎯',
            'background' => 'background: hsl(22 95% 82%); color: hsl(15 70% 24%)',
            'release_stage' => 'federated',
            'user_prompts' => [
                'Review my application letter.',
                'Prepare me for a job interview.',
            ],
        ],
    ];

    public function run(): void
    {
        // Ensure the configured creator exists, with exactly the configured id.
        if (User::query()->whereKey(self::CREATOR_ID)->doesntExist()) {
            User::unguarded(static fn () => User::factory()->create([
                'id' => self::CREATOR_ID,
            ]));
        }

        $categories = AssistantCategory::all();
        if ($categories->isEmpty()) {
            $this->call(AssistantCategorySeeder::class);
            $categories = AssistantCategory::all();
        }

        // A small set of reusable tags.
        $tags = collect(['beginner', 'advanced', 'writing', 'coding', 'research'])
            ->map(fn (string $text) => AssistantTag::firstOrCreate(['text' => $text]));

        foreach (self::ASSISTANTS as $assistantData) {
            $assistant = Assistant::factory()->create([
                'name' => $assistantData['name'],
                'handle' => $assistantData['handle'],
                'system_prompt' => $assistantData['prompt'],
                'creator_id' => self::CREATOR_ID,
                'remixed_creator_id' => null,
                'category_id' => $categories->random()->id,
                'release_stage' => $assistantData['release_stage'] ?? self::DEFAULT_RELEASE_STAGE,
            ]);

            $assistant->assistantAvatar()->create([
                'name' => $assistantData['icon'],
                'icon_css' => $assistantData['background'],
            ]);

            $assistant->assistantTags()->syncWithoutDetaching(
                $tags->random(rand(1, 2))->pluck('id')->all()
            );

            $assistant->assistantUserPrompts()->createMany(
                collect($assistantData['user_prompts'] ?? [])
                    ->map(fn (string $text) => ['text' => $text])
                    ->all()
            );
        }
    }
}
