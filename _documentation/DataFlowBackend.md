## 🔄 Kompletter Data Flow: cURL → Provider → StreamController

### 1. **cURL-Ebene (BaseAIModelProvider)**
```php
// BaseAIModelProvider.php - Zeilen 280-400
protected function setCommonCurlOptions($ch, array $payload): void
{
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'HAWKI-AI-Client/1.0',
    ]);
}

// Für Streaming Requests:
protected function setStreamingCurlOptions($ch, callable $callback): void
{
    curl_setopt_array($ch, [
        CURLOPT_WRITEFUNCTION => $callback, // ← Hier fließen die Daten durch!
        CURLOPT_HEADER => false,
        CURLOPT_BUFFERSIZE => 1024,
    ]);
}
```

### 2. **Provider-Ebene (GoogleProvider)**
```php
// GoogleProvider.php - makeStreamingRequest()
public function makeStreamingRequest(array $payload, callable $streamCallback)
{
    $ch = curl_init();
    
    // URL aus Datenbank-Konfiguration
    $provider = $this->getProviderFromDatabase();
    $url = rtrim($provider->base_url, '/') . '/models/' . $payload['model'] . ':streamGenerateContent';
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->formatPayload($payload)));
    
    // Callback-Funktion für eingehende Daten
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($streamCallback) {
        $streamCallback($data); // ← Rohdaten vom Provider!
        return strlen($data);
    });
    
    curl_exec($ch);
    curl_close($ch);
}
```

### 3. **Service-Ebene (AIConnectionService)**
```php
// AIConnectionService.php - Zeilen 20-70
public function processRequest(array $payload, bool $isStreaming = false, ?callable $streamCallback = null)
{
    $provider = $this->providerFactory->getProvider($payload['model']);
    
    if ($isStreaming && $streamCallback) {
        // Streaming: Callback wird direkt an Provider weitergegeben
        return $provider->connect($payload, $streamCallback);
    } else {
        // Non-Streaming: Synchrone Antwort
        return $provider->connect($payload);
    }
}
```

### 4. **Controller-Ebene (StreamController)**
```php
// StreamController.php - handleStreamingRequest()
private function handleStreamingRequest(array $payload, User $user, ?string $avatar_url)
{
    $requestBuffer = "";
    
    // Callback-Funktion für Provider-Daten
    $onData = function (string $data) use (&$requestBuffer, $user, $avatar_url, $payload) {
        // ← Hier kommen die RAW Provider-Daten an!
        
        // 1. SSE-Normalisierung (Google → OpenAI Format)
        $normalizedChunk = $this->normalizeSSEStreamChunk($data, $requestBuffer);
        
        if (!empty($normalizedChunk)) {
            // 2. Response Headers für SSE
            echo "data: " . json_encode([
                'author' => [
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $payload['model'],
                'content' => $normalizedChunk,
                'isDone' => false,
            ]) . "\n\n";
            
            // 3. Sofortiges Senden an Frontend
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
    };
    
    // Service aufrufen mit Callback
    $this->aiConnectionService->processRequest($payload, true, $onData);
}
```

## 🌊 Detaillierter Data Flow

### **Phase 1: Request Initiation**
1. **Frontend** → `handleAiConnectionRequest()` (StreamController)
2. **StreamController** → `handleStreamingRequest()` mit Callback-Definition
3. **Callback-Funktion** wird definiert für eingehende Provider-Daten

### **Phase 2: Provider Connection**
4. **StreamController** → `AIConnectionService.processRequest()`
5. **AIConnectionService** → `AIProviderFactory.getProvider()`
6. **Provider** (z.B. GoogleProvider) → `connect()` mit Callback
7. **Provider** → `makeStreamingRequest()` mit cURL Setup

### **Phase 3: cURL Execution**
8. **cURL** wird konfiguriert mit `CURLOPT_WRITEFUNCTION`
9. **HTTP Request** an externen AI Provider (Google API)
10. **Provider Response** fließt in Echtzeit durch cURL Callback

### **Phase 4: Data Processing Pipeline**
11. **cURL Callback** → **Provider Callback** → **Service Callback** → **StreamController Callback**
12. **Raw Provider Data** wird in `$onData` Callback empfangen
13. **SSE Normalization** wandelt Provider-Format in einheitliches Format um
14. **Response Wrapping** mit User-/Model-Metadaten
15. **Immediate Flush** sendet Daten sofort an Frontend

### **Kritische Übergabestellen:**

#### **🔴 cURL → Provider (Rohdaten)**
```php
// GoogleProvider.php
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($streamCallback) {
    $streamCallback($data); // ← Unverarbeitete Provider-Antwort
    return strlen($data);
});
```

#### **🟡 Provider → StreamController (Normalisierung)**
```php
// StreamController.php
$onData = function (string $data) use (&$requestBuffer, ...) {
    // ← $data enthält RAW Provider Response (z.B. Google SSE)
    $normalizedChunk = $this->normalizeSSEStreamChunk($data, $requestBuffer);
    // → Normalisiertes OpenAI-Format
};
```

#### **🟢 StreamController → Frontend (Finales Format)**
```php
// StreamController.php  
echo "data: " . json_encode([
    'author' => [...],
    'model' => $payload['model'],
    'content' => $normalizedChunk, // ← Normalisierte AI-Antwort
    'isDone' => false,
]) . "\n\n";
```

Der **letzte Backend-Punkt** vor der Frontend-Übergabe ist die **`echo`-Anweisung im StreamController**, die die normalisierten Daten als Server-Sent Events an das Frontend streamt. Hier sind die Daten bereits vollständig verarbeitet und in das einheitliche HAWKI-Format gebracht.