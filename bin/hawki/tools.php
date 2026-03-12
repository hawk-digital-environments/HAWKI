<?php

/**
 * AI Tools and Models management commands
 *
 * All logic lives in the Laravel application (php artisan tools:* / models:*).
 * These are thin passthrough wrappers so operators can use `php hawki` instead of
 * `php artisan` without duplicating any logic.
 */

// ── Tools dispatcher ──────────────────────────────────────────────────────────

function handleToolsCommand(array $flags): void
{
    $subCommand = $flags[0] ?? 'help';
    $remaining  = array_slice($flags, 1);

    $map = [
        'sync'              => 'ai:tools:sync',
        'list'              => 'ai:tools:list',
        'configure'         => 'ai:tools:configure',
        'configure-server'  => 'ai:tools:configure-server',
        'add-mcp-server'    => 'ai:tools:add-mcp-server',
        'remove-mcp-server' => 'ai:tools:remove-mcp-server',
        'list-mcp-servers'  => 'ai:tools:list-mcp-servers',
        'assign'            => 'ai:tools:assign',
        'check-status'      => 'ai:tools:check-status',
    ];

    if ($subCommand === 'help') {
        showToolsHelp();
        return;
    }

    if (!isset($map[$subCommand])) {
        echo RED . "Unknown tools subcommand: {$subCommand}" . RESET . PHP_EOL;
        echo PHP_EOL;
        showToolsHelp();
        return;
    }

    artisanPassthrough($map[$subCommand], $remaining);
}

// ── Models dispatcher ─────────────────────────────────────────────────────────

function handleModelsCommand(array $flags): void
{
    $subCommand = $flags[0] ?? 'help';
    $remaining  = array_slice($flags, 1);

    $map = [
        'sync'         => 'ai:models:sync',
        'list'         => 'ai:models:list',
        'check-status' => 'check:model-status',
    ];

    if ($subCommand === 'help') {
        showModelsHelp();
        return;
    }

    if (!isset($map[$subCommand])) {
        echo RED . "Unknown models subcommand: {$subCommand}" . RESET . PHP_EOL;
        echo PHP_EOL;
        showModelsHelp();
        return;
    }

    artisanPassthrough($map[$subCommand], $remaining);
}

// ── Passthrough helper ────────────────────────────────────────────────────────

/**
 * Forward a command to `php artisan` with all flags passed through verbatim.
 * Uses passthru() so interactive prompts and colour output work correctly.
 */
function artisanPassthrough(string $artisanCommand, array $flags): void
{
    $parts = ['php artisan', escapeshellarg($artisanCommand)];

    foreach ($flags as $flag) {
        $parts[] = escapeshellarg($flag);
    }

    $parts[] = '--ansi';

    passthru(implode(' ', $parts));
}

// ── Help screens ──────────────────────────────────────────────────────────────

function showToolsHelp(): void
{
    echo BOLD . "Usage: php hawki tools [subcommand] [options]" . RESET . PHP_EOL . PHP_EOL;
    echo BOLD . "Subcommands:" . RESET . PHP_EOL;
    echo "  sync                            - Sync tools from config into database" . PHP_EOL;
    echo "    --force                       - Re-sync even if tools already exist" . PHP_EOL;
    echo "    --function-only               - Only sync function-calling tools" . PHP_EOL;
    echo "    --mcp-only                    - Only sync MCP servers" . PHP_EOL;
    echo "  list                            - List all registered tools" . PHP_EOL;
    echo "    --json                        - Output as JSON" . PHP_EOL;
    echo "  configure                       - Configure a tool's attributes" . PHP_EOL;
    echo "    --tool={name}                 - Tool to configure (skips selection)" . PHP_EOL;
    echo "  configure-server                - Configure an MCP server's attributes" . PHP_EOL;
    echo "    --server={label|id}           - Server to configure (skips selection)" . PHP_EOL;
    echo "  add-mcp-server {url}            - Add an MCP server and discover its tools" . PHP_EOL;
    echo "    --label={label}               - Server label" . PHP_EOL;
    echo "    --description={desc}          - Description" . PHP_EOL;
    echo "    --require_approval={value}    - never | always | auto" . PHP_EOL;
    echo "    --timeout={seconds}           - Execution timeout" . PHP_EOL;
    echo "    --discovery_timeout={seconds} - Discovery timeout" . PHP_EOL;
    echo "    --api_key={key}               - API key (stored encrypted)" . PHP_EOL;
    echo "  remove-mcp-server [id]          - Remove an MCP server and its tools" . PHP_EOL;
    echo "    --force                       - Skip confirmation prompt" . PHP_EOL;
    echo "  list-mcp-servers                - List all registered MCP servers" . PHP_EOL;
    echo "    --json                        - Output as JSON" . PHP_EOL;
    echo "  assign                          - Manage tool-model assignments" . PHP_EOL;
    echo "    --tool={name}                 - Tool name to assign" . PHP_EOL;
    echo "    --model={id}                  - Model ID to assign to" . PHP_EOL;
    echo "    --provider={id}               - Provider ID (assigns to all eligible models)" . PHP_EOL;
    echo "    --detach                      - Remove assignment instead of adding" . PHP_EOL;
    echo "    --list                        - Show current assignments" . PHP_EOL;
    echo "  check-status                    - Ping MCP servers, update tool status" . PHP_EOL;
    echo PHP_EOL;
}

function showModelsHelp(): void
{
    echo BOLD . "Usage: php hawki models [subcommand] [options]" . RESET . PHP_EOL . PHP_EOL;
    echo BOLD . "Subcommands:" . RESET . PHP_EOL;
    echo "  sync                            - Sync AI models from config into database" . PHP_EOL;
    echo "    --force                       - Re-sync even if models already exist" . PHP_EOL;
    echo "  list                            - List AI models" . PHP_EOL;
    echo "    --provider={id}               - Filter by provider" . PHP_EOL;
    echo "    --active                      - Only show active models" . PHP_EOL;
    echo "    --json                        - Output as JSON" . PHP_EOL;
    echo "  check-status                    - Check and update live status of all models" . PHP_EOL;
    echo PHP_EOL;
}
