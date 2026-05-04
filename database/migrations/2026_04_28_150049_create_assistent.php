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

            $table->string('language');
            $table->string('category');

            $table->string('review_stage');

            $table->string('formality');

            $table->boolean('allow_model_select');

            $table->text('model');
            $table->integer('model_length');
            $table->float('model_temp');
            $table->float('model_top_p');

            $table->foreignId('creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('original_creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('original_assistant_id')
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
    }
};
