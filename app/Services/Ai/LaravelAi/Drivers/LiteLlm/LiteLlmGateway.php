<?php
declare(strict_types=1);


namespace App\Services\Ai\LaravelAi\Drivers\LiteLlm;


use Illuminate\Support\Str;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;

/**
 * OpenAI Responses API gateway for LiteLLM proxies that forward to strict backends.
 *
 * The stock {@see OpenAiGateway} replays earlier assistant turns as a bare
 * `{"role": "assistant", "content": [{"type": "output_text", ...}]}` item. api.openai.com
 * accepts that shorthand, but LiteLLM passes Responses API requests through to backends
 * such as vLLM, which validate every input item against the OpenAI type definitions.
 * There an assistant message with `output_text` content is only valid as a complete
 * output message item: it must carry `type: "message"`, an `id`, a `status`, and an
 * `annotations` array on each text block. Anything less is rejected with HTTP 400 and a
 * wall of pydantic validation errors, so a conversation fails as soon as it has history.
 *
 * This gateway rewrites those bare items into the full output message shape. Tool
 * calls, reasoning blocks and tool results are already emitted as typed items by the
 * parent and are left untouched.
 */
class LiteLlmGateway extends OpenAiGateway
{
    /**
     * @inheritDoc
     *
     * Delegates to the parent mapping and then upgrades any bare assistant message
     * item it produced to a fully typed output message item.
     */
    protected function mapAssistantMessage(AssistantMessage|Message $message, array &$input): void
    {
        $mapped = [];
        parent::mapAssistantMessage($message, $mapped);

        foreach ($mapped as $item) {
            $input[] = $this->isBareAssistantMessage($item) ? $this->toOutputMessageItem($item) : $item;
        }
    }

    /**
     * True for the shorthand assistant item the parent emits for plain text content.
     */
    private function isBareAssistantMessage(array $item): bool
    {
        return ($item['role'] ?? null) === 'assistant' && !isset($item['type']);
    }

    /**
     * Converts a bare assistant item into the strict Responses API output message shape.
     */
    private function toOutputMessageItem(array $item): array
    {
        $content = [];
        foreach ($item['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'output_text' && !isset($block['annotations'])) {
                $block['annotations'] = [];
            }
            $content[] = $block;
        }

        return [
            'type' => 'message',
            'id' => 'msg_' . str_replace('-', '', (string)Str::uuid()),
            'status' => 'completed',
            'role' => 'assistant',
            'content' => $content,
        ];
    }
}
