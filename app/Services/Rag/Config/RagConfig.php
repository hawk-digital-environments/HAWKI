<?php

declare(strict_types=1);

namespace App\Services\Rag\Config;

use App\Services\Config\AbstractConfig;
use App\Services\Config\Contracts\PublicConfigInterface;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;

/**
 * The RAG slice's complete configuration object — the single owner of every
 * `rag.*` value (see `config/rag.php` for the file-backed source and the
 * env variables behind it).
 *
 * Slice-internal consumers inject this class instead of reading the global
 * config repository, so the slice owns its configuration surface: when the
 * DB-backed plugin configuration arrives (plugin system, §4.7), only
 * {@see make()} changes its source — every consumer stays untouched.
 *
 * The public API exposes only the `enabled` flag under the `rag` key: it
 * decides how the assistant builder's knowledge page treats uploaded files
 * (RAG ingestion vs. per-request context injection). Secrets like the API
 * key never reach the frontend.
 */
class RagConfig extends AbstractConfig implements PublicConfigInterface
{
    /**
     * Whether uploaded assistant knowledge files are ingested into the RAG
     * server's per-assistant datasets.
     */
    public readonly bool $enabled;

    /** The ingestion backend driver key ("hawki_rag"; anything else is a no-op ingester). */
    public readonly string $driver;

    /** Base URL of the HAWKI-RAG REST server's ingestion API. */
    public readonly string $apiUrl;

    /** Bearer token for the ingestion API. */
    public readonly string $apiKey;

    /** Request timeout for ingestion calls, in seconds. */
    public readonly int $timeout;

    /**
     * Dataset id prefix; one dataset per assistant, named
     * `{datasetPrefix}{assistant-id}` (mirrored by the query-side agent
     * tool's dataset derivation).
     */
    public readonly string $datasetPrefix;

    /**
     * What is sent to the RAG server for assistant attachments: "text"
     * pushes locally extracted text; "file" uploads the original file and
     * lets the RAG server's own converter pipeline run.
     */
    public readonly string $attachmentIngestion;

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
            'driver' => (string) $repo->get('rag.driver', 'hawki_rag'),
            'apiUrl' => (string) $repo->get('rag.api_url', 'http://localhost:8080/api'),
            'apiKey' => (string) $repo->get('rag.api_key', ''),
            'timeout' => (int) $repo->get('rag.timeout', 30),
            'datasetPrefix' => (string) $repo->get('rag.dataset_prefix', 'assistant_'),
            'attachmentIngestion' => (string) $repo->get('rag.attachment_ingestion', 'text'),
        ]);
    }
}
