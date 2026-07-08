<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Middleware;


use App\Services\System\Container\ServiceLocatorTrait;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Prompts\AgentPrompt;
use Psr\Log\LoggerInterface;

class LoggingMiddleware
{
    use ServiceLocatorTrait;

    public function handle(AgentPrompt $prompt, \Closure $next)
    {
        $logger = $this->getService(LoggerInterface::class);
        $currentUser = $this->getService(Factory::class)->guard()->user();

        $logData = [
            'model' => $prompt->model,
            'provider' => get_class($prompt->provider),
            'agent' => get_class($prompt->agent),
            'invocation_id' => $prompt->invocationId,
            'user_id' => $currentUser?->id,
        ];
        $logger->info('Sending prompt to agent', $logData);

        try {
            $res = $next($prompt);
            $logger->info('Received response from agent', [
                ...$logData,
            ]);
            return $res;
        } catch (RequestException $e) {
            $responseText = (string)($e->response?->body() ?? 'no response body');
            if (strlen($responseText) > 5000) {
                $responseText = substr($responseText, 0, 5000) . '... [truncated]';
            }

            $logger->error('RequestException sending prompt to agent', [
                ...$logData,
                'url' => (string)($e->response?->transferStats->getEffectiveUri() ?? 'unknown'),
                'response' => $responseText,
                'exception' => $e
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $logger->error('Error sending prompt to agent', [
                ...$logData,
                'exception' => $e
            ]);

            throw $e;
        }
    }
}
