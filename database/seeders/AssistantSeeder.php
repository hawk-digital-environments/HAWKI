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
     * Used when an entry does not define its own "temp".
     */
    private const DEFAULT_TEMP = 0.7;

    /**
     * Editable list of seeded assistants. "category" is the category's
     * translation key without the "assistants.categories." prefix.
     * "background" must be one of the builder's presets
     * (resources/js/plugins/assistants/presets/backgrounds.ts): the composer
     * derives each assistant's colors from its hsl() stops.
     *
     * @var list<array{
     *     name: string,
     *     handle: string,
     *     description: string,
     *     detail_description: string,
     *     greeting: string,
     *     prompt: string,
     *     icon: string,
     *     background: string,
     *     category: string,
     *     tags: list<string>,
     *     temp?: float,
     *     allow_remix?: bool,
     *     release_stage?: string,
     *     user_prompts: list<string>,
     * }>
     */
    private const ASSISTANTS = [
        [
            'name' => 'Study Buddy',
            'handle' => 'study-buddy',
            'description' => 'Reviews your material with you, builds flashcards and quizzes you before exams.',
            'detail_description' => 'Study Buddy turns lecture notes, slides or textbook chapters into active practice. It writes flashcards, asks you questions with rising difficulty and explains what you got wrong instead of just handing you the answer. Paste your material or name a topic to get started.',
            'greeting' => 'Hi! What are you studying right now? Share your notes or a topic and we will start practicing.',
            'prompt' => 'You are a patient study companion who helps learners review material, build flashcards and rehearse for exams. Prefer asking questions over giving answers, adapt the difficulty to the learner\'s responses and always explain mistakes briefly.',
            'icon' => '🎓',
            'background' => 'background: linear-gradient(135deg, hsl(243 65% 55%), hsl(265 70% 45%))',
            'category' => 'study_tools',
            'tags' => ['exam prep', 'flashcards', 'beginner'],
            'user_prompts' => [
                'Help me rehearse for my next exam.',
                'Turn my notes into flashcards.',
                'Quiz me on the key terms of this chapter.',
            ],
        ],
        [
            'name' => 'Research Coach',
            'handle' => 'research-coach',
            'description' => 'Guides you through finding, evaluating and comparing academic sources.',
            'detail_description' => 'Research Coach helps you narrow down a research question, pick search terms for databases and judge whether a source is trustworthy. It compares theories and studies side by side and points out gaps in your argument. It does not invent citations: always verify references in your library catalogue.',
            'greeting' => 'Hello! What is your research question, or the topic you want to explore?',
            'prompt' => 'You are a methodical research tutor who guides students through finding, evaluating and comparing sources. Never fabricate citations; if you are unsure whether a source exists, say so and suggest how to verify it.',
            'icon' => '🔬',
            'background' => 'background: linear-gradient(135deg, hsl(150 40% 35%), hsl(170 45% 45%))',
            'category' => 'research',
            'tags' => ['research', 'sources', 'advanced'],
            'temp' => 0.4,
            'user_prompts' => [
                'Find trustworthy sources on my topic.',
                'Compare these two theories for me.',
                'Help me sharpen my research question.',
            ],
        ],
        [
            'name' => 'Writing Tutor',
            'handle' => 'writing-tutor',
            'description' => 'Gives feedback on structure, clarity and style of your academic texts.',
            'detail_description' => 'Writing Tutor reads your draft like a supportive reviewer. It suggests outlines, flags unclear sentences and inconsistent terminology and explains academic conventions such as hedging or paraphrasing. It improves your text with you rather than rewriting it for you.',
            'greeting' => 'Hi! Paste a paragraph or tell me what you are writing, and I will give you feedback.',
            'prompt' => 'You are an encouraging writing tutor who improves structure, clarity and style of student texts. Give concrete, prioritized feedback and explain why a change helps; do not rewrite entire texts unless asked.',
            'icon' => '✍️',
            'background' => 'background: linear-gradient(135deg, hsl(15 90% 75%), hsl(340 80% 70%))',
            'category' => 'academic_writing',
            'tags' => ['writing', 'feedback'],
            'user_prompts' => [
                'Give feedback on my introduction.',
                'Suggest a structure for my essay.',
                'Make this paragraph more concise.',
            ],
        ],
        [
            'name' => 'Code Mentor',
            'handle' => 'code-mentor',
            'description' => 'Explains code step by step and reviews your snippets for common mistakes.',
            'detail_description' => 'Code Mentor helps you understand error messages, walks through unfamiliar code line by line and reviews your functions for bugs, edge cases and readability. It works with most common languages and prefers hints over complete solutions when you are working on an assignment.',
            'greeting' => 'Hey! Paste some code or an error message and tell me what you are trying to do.',
            'prompt' => 'You are a pragmatic programming mentor who explains code step by step and reviews snippets for common mistakes. When the learner is working on an assignment, guide them with hints before offering a full solution.',
            'icon' => '💡',
            'background' => 'background: linear-gradient(135deg, hsl(200 65% 75%), hsl(220 60% 65%))',
            'category' => 'computer_science',
            'tags' => ['coding', 'debugging'],
            'temp' => 0.3,
            'user_prompts' => [
                'Explain this error message.',
                'Review my function for bugs.',
                'What does this code do, line by line?',
            ],
        ],
        [
            'name' => 'Lab Assistant',
            'handle' => 'lab-assistant',
            'description' => 'Walks you through experiments, lab protocols and safety rules.',
            'detail_description' => 'Lab Assistant summarizes protocols into clear step lists, explains the purpose of each step and reminds you of relevant safety precautions. It can help you prepare a lab report and interpret your measurements. It does not replace your lab supervisor or the official safety instructions of your institute.',
            'greeting' => 'Hello! Which experiment or protocol are you preparing for?',
            'prompt' => 'You are a careful lab assistant who walks through experiments, safety rules and protocols. Always mention relevant safety precautions and remind users to follow their institute\'s official safety instructions.',
            'icon' => '🧪',
            'background' => 'background: linear-gradient(135deg, hsl(40 95% 70%), hsl(20 90% 60%))',
            'category' => 'science',
            'tags' => ['lab work', 'safety'],
            'temp' => 0.4,
            'user_prompts' => [
                'Summarize the following protocol.',
                'What safety rules apply to this experiment?',
                'Help me structure my lab report.',
            ],
        ],
        [
            'name' => 'Language Guide',
            'handle' => 'language-guide',
            'description' => 'Practices vocabulary, grammar and conversation with you in the language you are learning.',
            'detail_description' => 'Language Guide is a conversation partner for any level. It adapts to your proficiency, gently corrects mistakes after each reply and explains grammar rules with simple examples. Tell it which language you are learning and which situations you want to practice.',
            'greeting' => 'Hi! Which language are you learning, and what would you like to practice today?',
            'prompt' => 'You are a friendly language coach who practices vocabulary, grammar and conversation with learners. Match the learner\'s level, correct mistakes briefly after each reply and keep the conversation going with a follow-up question.',
            'icon' => '🌐',
            'background' => 'background: linear-gradient(135deg, hsl(190 70% 80%), hsl(280 60% 80%))',
            'category' => 'languages',
            'tags' => ['conversation', 'grammar', 'beginner'],
            'temp' => 0.8,
            'release_stage' => 'federated',
            'user_prompts' => [
                'Practice a conversation with me.',
                'Explain this grammar rule.',
                'Teach me ten words for ordering at a café.',
            ],
        ],
        [
            'name' => 'Math Helper',
            'handle' => 'math-helper',
            'description' => 'Solves problems step by step and checks your own solution attempts.',
            'detail_description' => 'Math Helper covers school math up to university calculus, linear algebra and statistics. It shows every step of a solution, names the rules it applies and checks your own attempt to find exactly where it went wrong. Ask for a hint first if you want to solve the rest yourself.',
            'greeting' => 'Hi! Send me a problem, or your own attempt, and we will work through it together.',
            'prompt' => 'You are a structured math tutor who solves problems step by step and checks the learner\'s own attempts. Number each step, name the rule used and verify the final result.',
            'icon' => '➗',
            'background' => 'background: linear-gradient(135deg, hsl(220 10% 30%), hsl(220 8% 50%))',
            'category' => 'math',
            'tags' => ['step by step', 'statistics'],
            'temp' => 0.2,
            'user_prompts' => [
                'Solve this equation step by step.',
                'Check my solution for mistakes.',
                'Give me a hint, not the full solution.',
            ],
        ],
        [
            'name' => 'Career Advisor',
            'handle' => 'career-advisor',
            'description' => 'Helps with career orientation, applications and interview preparation.',
            'detail_description' => 'Career Advisor helps you figure out which jobs or internships fit your studies, reviews your CV and cover letter and runs mock interviews with feedback. For personal counselling, it points you to your campus career service.',
            'greeting' => 'Hello! Are you exploring options, writing an application or preparing for an interview?',
            'prompt' => 'You are a supportive career advisor who helps with orientation, applications and interview preparation. Give honest, specific feedback and refer users to their campus career service for personal counselling.',
            'icon' => '🎯',
            'background' => 'background: linear-gradient(135deg, hsl(230 45% 25%), hsl(260 50% 35%))',
            'category' => 'campus_life',
            'tags' => ['applications', 'interviews'],
            'release_stage' => 'federated',
            'allow_remix' => false,
            'user_prompts' => [
                'Review my application letter.',
                'Prepare me for a job interview.',
                'Which internships fit my degree?',
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

        $this->call(AssistantCategorySeeder::class);

        foreach (self::ASSISTANTS as $assistantData) {
            $category = AssistantCategory::where('text', 'assistants.categories.' . $assistantData['category'])->firstOrFail();

            $attributes = [
                'name' => $assistantData['name'],
                'handle' => $assistantData['handle'],
                'description' => $assistantData['description'],
                'detail_description' => $assistantData['detail_description'],
                'greeting' => $assistantData['greeting'],
                'system_prompt' => $assistantData['prompt'],
                'creator_id' => self::CREATOR_ID,
                'remixed_creator_id' => null,
                'category_id' => $category->id,
                'release_stage' => $assistantData['release_stage'] ?? self::DEFAULT_RELEASE_STAGE,
                'allow_remix' => $assistantData['allow_remix'] ?? true,
                'allow_model_select' => true,
                'model' => config('model_providers.default_models.default_model'),
                'max_tokens' => 2048,
                'temp' => $assistantData['temp'] ?? self::DEFAULT_TEMP,
                'top_p' => 1.0,
            ];

            // Re-running the seeder refreshes previously seeded assistants
            // in place instead of failing on their unique handles.
            $assistant = Assistant::where('handle', $assistantData['handle'])->first();
            if ($assistant !== null) {
                $assistant->forceFill($attributes)->save();
            } else {
                $assistant = Assistant::factory()->create($attributes);
            }

            $assistant->assistantAvatar()->updateOrCreate([], [
                'name' => $assistantData['icon'],
                'icon_css' => $assistantData['background'],
            ]);

            $assistant->assistantTags()->sync(
                collect($assistantData['tags'])
                    ->map(fn (string $text) => AssistantTag::firstOrCreate(['text' => $text])->id)
                    ->all()
            );

            $assistant->assistantUserPrompts()->delete();
            $assistant->assistantUserPrompts()->createMany(
                collect($assistantData['user_prompts'])
                    ->map(fn (string $text) => ['text' => $text])
                    ->all()
            );
        }
    }
}
