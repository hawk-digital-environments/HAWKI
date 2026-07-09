<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->boolean('added_by_file')->default(false)->comment('A temporary column to track tools added via config files, to be removed once we have a proper admin UI for managing tools and MCP servers');
            $table->renameColumn('server_id', 'mcp_server_id');
            $table->string('mcp_name')->after('mcp_server_id')->nullable()->default(null)->comment('The name of the tool as defined on the MCP server (only for type=mcp)');
            $table->json('mcp_config')->after('mcp_name')->nullable()->default(null)->comment('The raw tool configuration as received from the MCP server, stored for reference (only for type=mcp)');
            $table->dropColumn('inputSchema');
            $table->dropColumn('outputSchema');
            $table->string('mapped_capability')->after('capability')->nullable()->default(null)->comment('The user defined capability key that this tool is mapped to. This will override the "capability" field if given.');
        });
    }

    public function down(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->renameColumn('mcp_server_id', 'server_id');
            $table->dropColumn('mcp_name');
            $table->dropColumn('mcp_config');
            $table->dropColumn('added_by_file');
            $table->json('inputSchema')->nullable();
            $table->json('outputSchema')->nullable();
            $table->dropColumn('mapped_capability');
        });
    }
};
