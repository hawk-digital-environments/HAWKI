<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assistant_categories', static function (Blueprint $table): void {
            $table->id();
            $table->string('text')->unique();
            $table->timestamps();
        });

        Schema::create('assistants', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();

            $table->string('name');
            $table->string('handle')->unique()->nullable();

            $table->text('system_prompt');

            $table->text('greeting');
            $table->text('description');
            $table->text('detail_description');

            $table->boolean('allow_remix');

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('assistant_categories')
                ->nullOnDelete();

            $table->string('release_stage');

            $table->boolean('allow_model_select');

            $table->text('model');
            $table->integer('max_tokens');
            $table->float('temp');
            $table->float('top_p');

            $table->foreignId('creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('remixed_creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('remixed_assistant_id')
                ->nullable()
                ->constrained('assistants')
                ->nullOnDelete();
        });

        Schema::create('assistant_versions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_id')
                ->constrained('assistants')
                ->cascadeOnDelete();
            $table->text('text');
            $table->decimal('version', 8, 1)->default(1.0);
            $table->json('changed_keys')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_tools', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_tool_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assistant_user_prompts', static function (Blueprint $table): void {
            $table->id();

            $table->text('text');

            $table->foreignId('assistant_id')
                ->constrained('assistants')
                ->cascadeOnDelete();

            $table->timestamps();
        });

        Schema::create('assistant_tags', static function (Blueprint $table): void {
            $table->id();

            $table->string('text')->unique();

            $table->timestamps();
        });

        Schema::create('assistant_tag', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('assistant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('tag_id')
                ->constrained('assistant_tags')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['assistant_id', 'tag_id']);
        });

        Schema::create('assistant_reviews', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_id')
                ->unique()
                ->constrained('assistants')
                ->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_feedback', static function (Blueprint $table): void {
            $table->id();

            $table->text('text');

            $table->foreignId('assistant_id')
                ->constrained('assistants')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();
        });

        Schema::create('assistant_favorite_users', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('assistant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['assistant_id', 'user_id']);
        });

        Schema::create('assistant_settings', static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('ui_type');
            $table->json('ui_options')->nullable();
            $table->text('prompt_template')->nullable();
            $table->json('default_value')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_setting_values', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_id')
                ->constrained('assistants')
                ->cascadeOnDelete();
            $table->foreignId('setting_id')
                ->constrained('assistant_settings')
                ->cascadeOnDelete();
            $table->json('value');
            $table->timestamps();

            $table->unique(['assistant_id', 'setting_id']);
        });

        Schema::create('assistant_avatars', static function (Blueprint $table): void {
            $table->id();

            // An avatar now belongs to exactly one assistant (1:1 owned child).
            $table->foreignId('assistant_id')
                ->nullable()
                ->constrained('assistants')
                ->cascadeOnDelete();

            // Enforces the 1:1 relationship; NULLs (legacy orphan avatars) are
            // allowed since MySQL/SQLite permit multiple NULLs in a unique index.
            $table->unique('assistant_id');

            $table->string('name');
            $table->string('icon_css', 1000)->default('');

            $table->timestamps();
        });

        Schema::create('assistant_shared_users', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('assistant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['assistant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_shared_users');
        Schema::dropIfExists('assistant_avatars');
        Schema::dropIfExists('assistant_setting_values');
        Schema::dropIfExists('assistant_settings');
        Schema::dropIfExists('assistant_favorite_users');
        Schema::dropIfExists('assistant_feedback');
        Schema::dropIfExists('assistant_reviews');
        Schema::dropIfExists('assistant_tag');
        Schema::dropIfExists('assistant_tags');
        Schema::dropIfExists('assistant_user_prompts');
        Schema::dropIfExists('assistant_tools');
        Schema::dropIfExists('assistant_versions');
        Schema::dropIfExists('assistants');
        Schema::dropIfExists('assistant_categories');
    }
};
