<?php

use App\Services\Ai\Values\ModelDemand;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn('tools');
            $table->renameColumn('default_params', 'parameters');
            $table->enum('status', [
                OnlineStatus::ONLINE->value,
                OnlineStatus::OFFLINE->value,
                OnlineStatus::UNKNOWN->value,
            ])
                ->after('active')
                ->default(OnlineStatus::UNKNOWN->value)
                ->comment('Indicates the current online status of the model. This can be used to determine if the model is currently available for use, offline, or if its status is unknown.');
            $table->enum('demand', [
                ModelDemand::LOW->value,
                ModelDemand::MEDIUM->value,
                ModelDemand::HIGH->value,
            ])
                ->after('status')
                ->default(ModelDemand::LOW->value)
                ->comment('Indicates the current demand level for the model. This can be used to prioritize which models to use when multiple options are available, or to inform users about the expected performance of the model based on current demand.');
            $table->json('capabilities')
                ->after('parameters')
                ->comment('JSON field to store the capabilities of the model. (See AiModelCapabilities value object for the expected structure of this field.)');
            $table->json('settings')
                ->after('capabilities')
                ->comment('JSON field to store model-specific settings. This can include things like max_tool_calling_rounds, web_search_capability, etc. The exact structure of this field can vary and is meant to be flexible to accommodate different types of settings without requiring further database migrations.');
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->renameColumn('parameters', 'default_params');
            $table->json('tools')->nullable();
            $table->dropColumn('status');
            $table->dropColumn('demand');
            $table->dropColumn('capabilities');
            $table->dropColumn('settings');
        });
    }
};
