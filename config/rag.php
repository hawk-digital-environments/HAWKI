<?php

declare(strict_types=1);

/**
 * Configuration for the RAG module — the ingestion side (REST client for
 * the external HAWKI-RAG server; one dataset per assistant, named
 * `{prefix}{assistant-id}`, default `assistant_42`) and the retrieval side
 * (the ambient knowledge tool; the MCP query server itself is configured
 * separately in config/tools.php under `hawki-rag`).
 *
 * The module is on or off: one `enabled` switch gates both sides. It is
 * install-time system setup (later: installer/admin section) — changing it
 * mid operation is not supported, because attachments already ingested
 * carry RAG state and server-side datasets, so switching between RAG
 * operation and plain file-upload behaviour requires a data migration.
 */
return [
    // filter_var normalizes boolean-ish env strings ("false" exported by
    // container runtimes is a truthy PHP string otherwise).
    'enabled' => filter_var(env('HAWKI_RAG_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Which backend implements the ingestion contract
    // (App\Services\Rag\Contracts\RagIngesterInterface):
    // "hawki_rag" — the external HAWKI-RAG REST server; anything else
    // resolves to a no-op ingester.
    'driver' => env('HAWKI_RAG_DRIVER', 'hawki_rag'),

    // HawkiRagIngester settings.
    'api_url' => env('HAWKI_RAG_API_URL', 'http://localhost:8080/api'),

    'api_key' => env('HAWKI_RAG_API_KEY', ''),

    'timeout' => (int) env('HAWKI_RAG_API_TIMEOUT', 60),

    'dataset_prefix' => 'assistant_',

    // What is sent to the RAG server for assistant attachments:
    // "text" — HAWKI uses extractions from the file converter (ExtractTextCollector)
    // "file" — the original file is uploaded to POST /documents and the RAG
    // server's own converter pipeline runs.
    'attachment_ingestion' => env('HAWKI_RAG_ATTACHMENT_INGESTION', 'text'),

    // Local tool identities (ai_tools.name) of the MCP tools the
    // hawki-rag server exposes — wire contracts the seeder, the assistant
    // knowledge base and the public config all read. 
    'query_tool' => 'hawki-rag-query-search',
    'web_search_tool' => 'hawki-rag-web-search-tool',
];
