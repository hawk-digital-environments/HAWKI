<?php

use App\Services\AI\Value\ModelOnlineStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_model_statuses', function (Blueprint $table) {
            $table->string('model_id')->primary();
            $table->enum('status', [
                ModelOnlineStatus::ONLINE->value,
                ModelOnlineStatus::OFFLINE->value,
                ModelOnlineStatus::UNKNOWN->value,
            ]);
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('ai_model_statuses');
    }
};
