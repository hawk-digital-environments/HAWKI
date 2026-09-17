<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('assistant_review_logs', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Reuses AssistantReviewStatus values (approved|denied|blocked) —
            // one row per review decision, unlike assistant_reviews which
            // only keeps the current state.
            $table->string('action');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('assistant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_review_logs');
    }
};
