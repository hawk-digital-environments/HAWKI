<?php

declare(strict_types=1);

/**
 * REST ingestion client for the external HAWKI-RAG server. One dataset
 * (vector store) per assistant, named `{prefix}{assistant-id}` (default
 * `assistant_42`). The MCP query-side server is configured separately in
 * config/tools.php (`hawki-rag`).
 */
return [
    'enabled' => env('HAWKI_RAG_INGESTION_ENABLED', false),

    // Which backend implements the ingestion contract
    // (App\Services\Rag\Contracts\RagIngesterInterface):
    // "hawki_rag" — the external HAWKI-RAG REST server; anything else
    // resolves to a no-op ingester.
    'driver' => env('HAWKI_RAG_DRIVER', 'hawki_rag'),

    // HawkiRagIngester settings.
    'api_url' => env('HAWKI_RAG_API_URL', 'http://localhost:8080/api'),

    'api_key' => env('HAWKI_RAG_API_KEY', ''),

    'timeout' => (int) env('HAWKI_RAG_API_TIMEOUT', 30),

    'dataset_prefix' => 'assistant_',

    // What is sent to the RAG server for assistant attachments:
    // "text" — HAWKI extracts the text locally (ExtractTextCollector)
    // and pushes it to the text-ingestion endpoint; "file" — the
    // original file is uploaded to POST /documents and the RAG
    // server's own converter pipeline runs. Anything other than
    // "file" resolves to "text".
    'attachment_ingestion' => env('HAWKI_RAG_ATTACHMENT_INGESTION', 'text'),
];
