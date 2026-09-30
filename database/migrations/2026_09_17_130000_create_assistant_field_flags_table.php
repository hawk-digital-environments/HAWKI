<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('assistant_field_flags', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Which Assistant field this flag is about, e.g. 'system_prompt', 'greeting'.
            $table->string('field');
            // The selected passage, when the admin selected one; null for a whole-field flag.
            $table->text('excerpt')->nullable();
            $table->text('comment');
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->index('assistant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_field_flags');
    }
};
