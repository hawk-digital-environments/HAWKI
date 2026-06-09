<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('text')->unique();
            $table->timestamps();
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('text')->unique();
            $table->timestamps();
        });

        Schema::create('assistants', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('name');
            $table->string('handle')->unique()->nullable();

            $table->text('system_prompt');

            $table->text('greeting');
            $table->text('description');
            $table->text('detail_description');

            $table->boolean('allow_remix');

            $table->foreignId('language_id')
                ->nullable()
                ->constrained('languages')
                ->nullOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('release_stage');

            $table->string('formality');

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

        Schema::create('versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')
                ->constrained('assistants')
                ->cascadeOnDelete();
            $table->text('text');
            $table->decimal('version', 8, 1)->default(1.0);
            $table->json('changed_keys')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_tool_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_prompts', function (Blueprint $table) {
            $table->id();

            $table->text('text');

            $table->foreignId('assistant_id')
                ->constrained('assistants')
                ->cascadeOnDelete();

            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();

            $table->string('text');

            $table->timestamps();
        });

        Schema::create('assistant_tag', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assistant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('tag_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['assistant_id', 'tag_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')
                ->unique()
                ->constrained('assistants')
                ->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('feedback', function (Blueprint $table) {
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

        Schema::create('assistant_favorite_users', function (Blueprint $table) {
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
        Schema::dropIfExists('versions');
        Schema::dropIfExists('assistant_tag');
        Schema::dropIfExists('assistant_tools');
        Schema::dropIfExists('user_prompts');
        Schema::dropIfExists('assistants');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('assistant_favorite_users');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('reviews');
    }
};
