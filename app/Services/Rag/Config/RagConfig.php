<?php

declare(strict_types=1);

namespace App\Services\Rag\Config;

use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * Exposes whether RAG ingestion is enabled to the frontend under the `rag`
 * public-config key.
 *
 * The flag decides how the assistant builder's knowledge page treats
 * uploaded files: with RAG enabled, files are ingested into the assistant's
 * preassembled knowledge-base dataset (so uploads require a model whose
 * knowledge-base tool the assistant can use); with RAG disabled, files are
 * injected into the conversation context per request instead (no ingestion
 * workflow, so uploads are always allowed).
 */
class RagConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * Whether uploaded assistant knowledge files are ingested into the RAG
     * server's per-assistant datasets (see `config/rag.php`).
     */
    public readonly bool $enabled;

    public static function publicKey(): string
    {
        return 'rag';
    }

    public function toPublicArray(Request $request): array|null
    {
        if ($request->user()) {
            return [
                'enabled' => $this->enabled,
            ];
        }

        return null;
    }

    public static function make(Repository $repo): static
    {
        return self::fromArray([
            'enabled' => (bool) $repo->get('rag.enabled', false),
        ]);
    }
}
