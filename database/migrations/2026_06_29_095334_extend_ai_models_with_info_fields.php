<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->string('model_type')->nullable()->after('id');
            $table->dateTime('deprecation_date')->nullable()->after('model_type');
            $table->text('documentation_url')->nullable()->after('label')
                ->comment('URL to the documentation of the model. This is a free-form field that can be used to link to any documentation, but it is recommended to link to the provider\'s official documentation for the model.');
            $table->dropColumn('capabilities');
            $table->json('native_capabilities')
                ->after('output')
                ->comment('JSON field to store which native capabilities the model has. (WellKnownCapabilities for what the values mean.)');
            $table->json('limits')
                ->after('settings')
                ->comment('JSON field to store the limits (tokens or pixels) of the model. (See implementing classes of AiModelLimitsInterface for the expected structure of this field.)');
            $table->json('pricing')
                ->after('limits')
                ->comment('JSON field to store the pricing of the model. (See implementing classes of AiModelPricingInterface for the expected structure of this field.)');
            $table->json('flags')
                ->after('pricing')
                ->comment('JSON field to store the flags (meta-information describing what the model is able to do) of the model. (See implementing classes of ModelFlagsInterface for the expected structure of this field.)');
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn('model_type');
            $table->dropColumn('deprecation_date');
            $table->dropColumn('documentation_url');
            $table->json('capabilities')
                ->after('parameters')
                ->comment('JSON field to store the capabilities of the model. (See AiModelCapabilities value object for the expected structure of this field.)');
            $table->dropColumn('native_capabilities');
            $table->dropColumn('limits');
            $table->dropColumn('pricing');
            $table->dropColumn('flags');
        });
    }
};
