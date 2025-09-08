# OpenAI Responses Provider für HAWKI

## Übersicht

Der `OpenAIResponsesProvider` ist ein spezialisierter Provider für die OpenAI Responses API, die für fortgeschrittene Reasoning-Modelle wie o1-preview und o1-mini entwickelt wurde. Dieser Provider wurde nach der offiziellen OpenAI Responses API Migrationsanleitung implementiert.

## Konfiguration

### API Format
- **Unique Name**: `openai-responses-api`
- **Display Name**: `OpenAI Responses API`
- **Base URL**: `https://api.openai.com/v1`
- **Provider Class**: `App\Services\AI\Providers\OpenAIResponsesProvider`

### Endpunkte
- **Chat**: `/v1/responses` (POST)
- **Models**: `/v1/models` (GET)

## Kernfeatures

### 1. Flexible Input-Formate
Der Provider unterstützt verschiedene Input-Arten entsprechend der Responses API:

#### Instructions + Input (bevorzugt)
```json
{
  "model": "o1-preview",
  "instructions": "You are a helpful assistant.",
  "input": "Hello!"
}
```

#### Array von Messages
```json
{
  "model": "o1-preview", 
  "input": [
    {"role": "user", "content": "Hello!"}
  ]
}
```

#### Single String Input
```json
{
  "model": "o1-preview",
  "input": "Hello!"
}
```

### 2. Erweiterte Conversation Features

#### Previous Response ID
Multi-turn Conversations durch Referenzierung vorheriger Antworten:
```php
$payload = [
    'model' => 'o1-preview',
    'input' => 'And its population?',
    'previous_response_id' => 'resp_previous123'
];
```

#### Stateless Mode mit Encrypted Reasoning
Für ZDR (Zero Data Retention) Compliance:
```php
$payload = [
    'model' => 'o1-preview',
    'input' => 'Analyze this problem...',
    'store' => false,
    'include' => ['reasoning.encrypted_content']
];
```

### 3. Reasoning Support
- **Reasoning Effort**: Konfigurierbar über Model-Settings (`reasoning_effort: "high"`)
- **Encrypted Reasoning Tokens**: Optional über Provider-Settings
- **Reasoning Items**: Vollständige Unterstützung für reasoning content und summaries

### 4. Native Tools Integration
Unterstützung für OpenAI's eingebaute Tools:
```php
$payload = [
    'model' => 'o1-preview',
    'input' => 'Search for recent news about AI',
    'tools' => [['type' => 'web_search']]
];
```

### 5. Structured Outputs (text.format)
Neue API-Struktur für Structured Outputs:
```php
$payload = [
    'model' => 'o1-preview',
    'input' => 'Extract person data',
    'text' => [
        'format' => [
            'type' => 'json_schema',
            'name' => 'person',
            'schema' => [...]
        ]
    ]
];
```

### 6. Event-Based Streaming
Unterstützt spezifische Responses API Event-Types:
- `response.output_text.delta`: Text-Deltas
- `response.message.delta`: Message-Deltas mit Content
- `response.completed`: Completion-Signal mit Reasoning
- `response.refreshed`: Refresh-Signal
- `error`: Fehler-Events

## Besonderheiten vs. Chat Completions

### 1. Role Mapping
- `system` → `developer` (Responses API Requirement)
- Automatische Extraktion als `instructions`

### 2. Privacy by Default
- `store: false` standardmäßig (für Datenschutz)
- Optional encrypted reasoning für ZDR-Compliance

### 3. Intelligente Input-Verarbeitung
- Single user message → String input
- Multiple messages → Array input
- System messages → Instructions

### 4. Output Structure
```json
{
  "output": [
    {
      "type": "reasoning",
      "content": [...],
      "summary": [...]
    },
    {
      "type": "message", 
      "content": [
        {"type": "output_text", "text": "Response text"}
      ]
    }
  ]
}
```

## Verwendung

### Model hinzufügen
```php
$model = new LanguageModel();
$model->model_id = 'o1-preview';
$model->label = 'OpenAI o1-preview (Responses API)';
$model->provider_id = 6;
$model->information = [
    'reasoning_effort' => 'high',
    'supports_reasoning' => true
];
```

### Provider-Settings
```php
$provider = new ProviderSetting();
$provider->provider_name = 'OpenAI Responses';
$provider->api_format_id = 10;
$provider->api_key = 'sk-proj-...';
$provider->additional_settings = [
    'keep_reasoning_tokens' => true
];
```

## Migration von Chat Completions

Der Provider ist vollständig kompatibel mit HAWKI's bestehender Architektur:

```php
// Standard HAWKI Payload (wie Chat Completions)
$payload = [
    'model' => 'o1-preview',
    'messages' => [
        ['role' => 'system', 'content' => ['text' => 'You are helpful.']],
        ['role' => 'user', 'content' => ['text' => 'Hello!']]
    ],
    'stream' => true
];

// Automatische Transformation zu Responses API Format
$response = $provider->connect($payload, $streamCallback);
```

## Status

✅ **Vollständig implementiert**: Nach OpenAI Responses API Spezifikation  
✅ **Event-based Streaming**: Alle wichtigen Event-Types unterstützt  
✅ **Privacy-focused**: Store=false, encrypted reasoning  
✅ **Tools-ready**: Native OpenAI Tools Integration  
✅ **Flexible Input**: String, Array und Instructions Support  
✅ **Multi-turn**: Previous Response ID und Conversation Chains  
✅ **ZDR-compliant**: Stateless mode für compliance

Der Provider ist production-ready und bietet alle Vorteile der modernen OpenAI Responses API.
