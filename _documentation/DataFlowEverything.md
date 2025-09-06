## Hauptkomponenten:

1. **AIConnectionService** - Zentrale Orchestrierung
2. **AIProviderFactory** - Factory Pattern mit Caching
3. **Provider-Hierarchie** - Interface → BaseProvider → Spezifische Provider
4. **UsageAnalyzerService** - Nutzungs-Tracking
5. **Database Layer** - Modell- und Provider-Konfiguration
6. **Cache Layer** - Performance-Optimierung

## Datenfluss:

1. **Request**: Client → Controller → Service → Factory → Provider → API
2. **Response**: API → Provider → Service → Controller → Client
3. **Usage Tracking**: Parallel zur Response-Verarbeitung
4. **Caching**: Aggressive Cache-Strategien für Mappings und Instanzen

## Besondere Merkmale:

- **Multi-Provider Support**: 7 verschiedene AI-Provider
- **Streaming/Non-Streaming**: Beide Modi unterstützt
- **Database-driven**: Konfiguration komplett über Database
- **Caching**: 30min für Mappings, 1h für Provider-Instanzen
- **Error Handling**: Fallbacks und umfassendes Logging

## 1. **Blade Template Data Transfer (Server-Side Rendering)**

### HomeController → Blade Views
```php
// HomeController.php - Zeile 82-99
return view('modules.' . $requestModule, 
    compact('translation', 
            'settingsPanel',
            'slug', 
            'userProfile', 
            'userData',
            'activeModule',
            'activeOverlay',
            'models',
            'systemPrompts',
            'localizedTexts'));
```

### Blade Template JavaScript Injection
```blade
<!-- layouts/home.blade.php - Zeile 71-85 -->
<script>
    const userInfo = @json($userProfile);
    const userAvatarUrl = @json($userData['avatar_url']);
    const hawkiAvatarUrl = @json($userData['hawki_avatar_url']);
    const activeModule = @json($activeModule);
    
    const activeLocale = {!! json_encode(Session::get('language')) !!};
    const translation = @json($translation);
    const systemPrompts = @json($systemPrompts);
    const localizedTexts = @json($localizedTexts);

    const modelsList = @json($models).models;
    const defaultModel = @json($models).defaultModel;
</script>
```

## 2. **AJAX API Endpoints (Asynchrone Datenübertragung)**

### Frontend → Backend AJAX Calls
```javascript
// Beispiele aus verschiedenen JS-Dateien:

// user_profile.js
const response = await fetch(`/req/profile/update`, {
    method: 'POST',
    headers: { /* ... */ },
    body: JSON.stringify(/* ... */)
});

// chatlog_functions.js
const response = await fetch('/req/conv/sendMessage/' + slug, {
    method: 'POST',
    headers: { /* ... */ },
    body: JSON.stringify(/* ... */)
});

// sanctum_functions.js
const response = await fetch(`/req/profile/create-token`, {
    method: 'POST',
    headers: { /* ... */ },
    body: JSON.stringify({ 'name': name })
});
```

### Wichtige API-Endpunkte (routes/web.php)
```php
// AI-Anfragen
Route::post('/req/streamAI', [StreamController::class, 'handleAiConnectionRequest']);
Route::post('/req/room/streamAI/{slug}', [StreamController::class, 'handleAiConnectionRequest']);

// Chat-Verwaltung
Route::post('/req/conv/sendMessage/{slug}', [AiConvController::class, 'sendMessage']);
Route::get('/req/conv/{slug?}', [AiConvController::class, 'loadConv']);

// Raum-Verwaltung
Route::get('/req/room/{slug?}', [RoomController::class, 'loadRoom']);
Route::post('/req/room/sendMessage/{slug}', [RoomController::class, 'sendMessage']);

// Profil-Verwaltung
Route::post('/req/profile/update', [ProfileController::class, 'update']);
Route::post('/req/profile/create-token', [AccessTokenController::class, 'createToken']);
```

## 3. **WebSocket-Übertragung (Laravel Reverb)**

### Real-time Broadcasting
```javascript
// Frontend WebSocket-Verbindung
Echo.private(`Rooms.${roomSlug}`)
    .listen('RoomMessageEvent', handleMessage)
    .listenForWhisper('typing', handleTyping)
```

### Backend Broadcasting Events
```php
// Beispiel aus der Dokumentation
Message::create() → SendMessage::dispatch() → RoomMessageEvent
```

## 4. **Session-basierte Datenübertragung**

### Session Data
```php
// AuthenticationController.php
Session::put('authenticatedUserInfo', json_encode($authenticatedUserInfo));
Session::put('registration_access', true);

// Blade Templates
const activeLocale = {!! json_encode(Session::get('language')) !!};
```

## 5. **Konfigurationsdaten**

### Environment/Config zur Blade
```php
// LoginController.php
$authenticationMethod = config('auth.authentication_method', 'LDAP');
$localUsersActive = config('auth.local_authentication', false);
$localSelfserviceActive = config('auth.local_selfservice', false);
```

## **Zusammenfassung der Datenübertragungspunkte:**

1. **Initial Page Load**: Controller → Blade → JavaScript-Variablen
2. **AJAX Requests**: JavaScript fetch() → Controller Endpoints
3. **Real-time Updates**: WebSocket Broadcasting via Laravel Reverb
4. **Session Data**: PHP Sessions → Blade Templates
5. **Configuration**: Environment/Database Config → Controllers → Views

Die Hauptdatenübertragung erfolgt über:
- **Server-Side Rendering** beim initialen Seitenaufbau
- **REST API Endpoints** für dynamische Interaktionen 
- **WebSocket-Verbindungen** für Real-time Updates
- **Session Storage** für persistente Benutzerdaten