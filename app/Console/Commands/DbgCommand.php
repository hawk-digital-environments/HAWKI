<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ai\Agent\Chat\ChatRequestFactory;
use App\Services\Ai\AiService;
use App\Services\Ai\Repositories\AiModelRepository;
use App\Services\Ai\Repositories\AiModelToolRepository;
use App\Services\Ai\Repositories\AiToolRepository;
use App\Services\Users\Events\UserCreatedEvent;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class DbgCommand extends Command
{
    protected $signature = 'dbg {--tools : Assign tools to models}';

    protected $description = 'Command description';

    public function handle(): void
    {
        if ($this->input->getOption('tools')) {
            $modelRepo = app(AiModelRepository::class);
            $toolRepo = app(AiToolRepository::class);
            $assignmentRepo = app(AiModelToolRepository::class);
            foreach ($toolRepo->findAll() as $tool) {
                // Ignore tools that are already assigned
                if ($tool->models->count() > 0) {
                    continue;
                }
                // Find between 1-3 random models and assign the tool to them
                $models = $modelRepo->findAll()->random(rand(1, 3));
                foreach ($models as $model) {
                    $assignmentRepo->assignTool($model, $tool);
                    dbg("Assigned tool {$tool->name} to model {$model->label}");
                }
            }
            dbge("Done assigning tools to models");
        }

        $repo = app(UserRepository::class);
        Event::listen(UserCreatedEvent::class, function (UserCreatedEvent $event) {
            dbge("User created: " . $event->user->id);
        });
        dbge($repo->insert('test-' . uniqid(more_entropy: true), 'Test User', uniqid(more_entropy: true) . '@bar.de', 'employee'));
        dbge();
        dbge(app(AiModelRepository::class), app(AiModelRepository::class));
//        Auth::loginUsingId(1);
        dbge(User::get()->pluck('id'));
        dbge(Http::getSsrfSafe('http://127.0.0.1:5000'));
        dbge(Http::timeout(10)->getSsrfSafe('http://127.0.0.1:5000'));

//        $modelUpdater = app(ModelStatusUpdater::class);
//        $metrics = $modelUpdater->run();
//        dbge($metrics);
//        $serverUpdater = app(McpServerStatusUpdater::class);
//        $count = $serverUpdater->run();
//        dbge("Checked $count servers");
//        $syncer = app(McpToolSyncer::class);
//        $metrics = $syncer->sync();
//        dbge($metrics);

        $service = app(AiService::class);
        $request = app(ChatRequestFactory::class)->fromPayload([
            'model' => 'gpt-4.1'
        ]);
        $service->sendRequestToAgent($request);
        dbge('done');
    }
}
