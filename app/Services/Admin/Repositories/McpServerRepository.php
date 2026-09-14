<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Mcp\McpClientFactory;
use App\Services\Ai\Tools\Repositories\AiToolRepository;
use App\Services\Ai\Values\OnlineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @extends ConfigurationRepository<McpServer>
 */
class McpServerRepository extends ConfigurationRepository
{
    public const RESOURCE = 'mcp';

    public function mcp(?string $id, bool $discover): array
    {
        $server = McpServer::findOrFail($id);

        try {
            $client = app(McpClientFactory::class)->createForServer($server);
            $online = $client->ping();
            $server->setAttribute('status', $online ? OnlineStatus::ONLINE : OnlineStatus::OFFLINE);
            $server->save();
            abort_unless($online, 502);

            if (!$discover) {
                return ['online' => true];
            }

            $definitions = $client->listToolDefinitions();

            return DB::transaction(static function () use ($server, $definitions) {
                $repository = app(AiToolRepository::class);
                $ids = [];
                $names = [];

                foreach ($definitions as $definition) {
                    $tool = $repository->upsertMcp($definition, $server);
                    $ids[] = $tool->id;
                    $names[] = $definition->name;
                }

                $repository->removeAllMcpToolsOf($server, $ids);

                return ['tools' => $names];
            });
        } catch (\Throwable $exception) {
            report($exception);
            abort(502, __('admin.errors.connection'));
        }
    }

    protected function definition(): array
    {
        $fields = new \App\Services\Admin\ResourceFields();

        return ['model' => McpServer::class, 'columns' => ['server_label', 'type', 'url', 'status', 'api_key_set'], 'fields' => [
            $fields->text('server_label', true), $fields->select('type', ['http', 'sse', 'stdio']), $fields->text('url', true),
            $fields->field('description', 'textarea', 'nullable|string|max:10000'),
            $fields->select('require_approval', ['never', 'always']), ...$fields->secrets(), $fields->json('timeouts'),
        ]];
    }

    protected function rules(?int $id, array $values): array
    {
        $rules = parent::rules($id, $values);

        if ('stdio' !== ($values['type'] ?? null)) {
            $rules['url'] = 'required|url:http,https|max:2000';
        }

        foreach (['read', 'connect', 'sse_idle'] as $key) {
            $rules['timeouts.' . $key] = 'nullable|numeric|min:0.1|max:120';
        }

        return $rules;
    }

    protected function prepare(Model $model, array &$data): void
    {
        parent::prepare($model, $data);
        $data['timeouts'] ??= [];
        $model->setAttribute('added_by_file', false);
    }

    protected function deleting(Model $model): void
    {
        AiTool::withoutGlobalScopes()->where('mcp_server_id', $model->getKey())->delete();
    }
}
