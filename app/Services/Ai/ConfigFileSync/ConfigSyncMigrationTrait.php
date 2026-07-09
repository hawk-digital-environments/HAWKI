<?php
declare(strict_types=1);


namespace App\Services\Ai\ConfigFileSync;


use App\Services\Ai\StatusCheck\McpServerStatusUpdater;
use App\Services\Ai\StatusCheck\ModelStatusUpdater;
use App\Services\Ai\Tools\FunctionToolSyncer;
use App\Services\Ai\Tools\Mcp\McpToolSyncer;
use App\Services\System\Container\ServiceLocatorTrait;
use Illuminate\Console\OutputStyle;
use Laravel\Prompts\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArgvInput;

// @phpstan-ignore trait.unused
trait ConfigSyncMigrationTrait
{
    use ServiceLocatorTrait;

    public function up(): void
    {
        $input = new ArgvInput();
        $output = new ConsoleOutput();
        $io = new OutputStyle($input, $output);

        $syncers = [
            [
                'run' => function () {
                    return $this->getService(ConfigFileSyncer::class)->sync(true);
                },
                'commandOnFailure' => 'php artisan ai:models:list'
            ],
            [
                'run' => function () {
                    return $this->getService(FunctionToolSyncer::class)->sync();
                },
                'commandOnFailure' => 'php artisan ai:tools:sync'
            ],
            [
                'run' => function () {
                    return $this->getService(McpServerStatusUpdater::class)->run();
                },
                'commandOnFailure' => 'php artisan ai:tools:check-status'
            ],
            [
                'run' => function () {
                    return $this->getService(McpToolSyncer::class)->sync();
                },
                'commandOnFailure' => 'php artisan ai:tools:sync'
            ],
            [
                'run' => function () {
                    return $this->getService(ModelStatusUpdater::class)->run();
                },
                'commandOnFailure' => 'php artisan ai:models:check-status'
            ]
        ];

        foreach ($syncers as $syncer) {
            try {
                $metrics = $syncer['run']();

                if ($metrics->hasErrors()) {
                    throw new \RuntimeException("Errors during sync: " . implode(", ", $metrics->getErrors()));
                }

                $metrics->writeToCli($io);
            } catch (\Throwable $e) {
                $io->warning(sprintf(
                    'Error during sync: %s, please run the command `%s` to sync manually. IMPORTANT: If you are seeing this, but afterwards see output that tells you that the sync was successful, you can ignore this warning.',
                    $e->getMessage(),
                    $syncer['commandOnFailure']
                ));
            }
        }
    }
}
