<?php

use App\Services\Ai\Values\McpServerType;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropColumn('timeout');
            $table->dropColumn('discovery_timeout');
            $table->enum('status', [
                OnlineStatus::ONLINE->value,
                OnlineStatus::OFFLINE->value,
                OnlineStatus::UNKNOWN->value,
            ])->default(OnlineStatus::UNKNOWN->value)->comment('The current online status of the server, determined by the last ping');
            $table->json('timeouts')->comment('JSON object containing various timeouts for when communicating with the server');
            $table->string('type')->default(McpServerType::SSE->value);
            $table->json('additional_config')->nullable()->comment('Additional encrypted JSON config for the server, e.g. for custom headers or other MCP options');
            $table->boolean('added_by_file')->default(false)->comment('Temporary marker to indicate that this server was added via config file (as opposed to manually by the user). We need this to properly clean up old entries that are no longer in the config file, but it can be removed once we get rid of config-file based server definitions entirely.');
            $table->renameColumn('protocolVersion', 'protocol_version');
        });
    }

    public function down(): void
    {
        Schema::table('mcp_servers', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('type');
            $table->dropColumn('additional_config');
            $table->dropColumn('added_by_file');
            $table->dropColumn('timeouts');
            $table->string('timeout')->default('10');
            $table->string('discovery_timeout')->default('10');
            $table->renameColumn('protocol_version', 'protocolVersion');
        });
    }
};
