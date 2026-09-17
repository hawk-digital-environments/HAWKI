<?php

declare(strict_types=1);

use App\Models\Ai\AiModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_model_roles', function (Blueprint $table) {
            $table->foreignIdFor(AiModel::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['ai_model_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_roles');
    }
};
