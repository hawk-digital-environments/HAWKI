<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->renameColumn('ping_url', 'model_status_url');
            $table->string('adapter_key')
                ->after('provider_id')
                ->nullable(false)
                ->comment('The key used to determine which adapter to use for this provider"');
            $table->string('api_key', 4096)
                ->nullable()
                ->comment('The API key for the provider, stored encrypted. Nullable for providers that do not require an API key');
            $table->text('settings')
                ->nullable()
                ->comment('Any additional settings for the provider, stored as JSON. Helpful for providers with complex adapters');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->renameColumn('model_status_url', 'ping_url');
            $table->dropColumn('adapter_key');
            $table->dropColumn('api_key');
            $table->dropcolumn('settings');
        });
    }
};
