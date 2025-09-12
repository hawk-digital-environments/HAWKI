## 🔍 **Aktueller DataFlow mit CitationService**:

### **1. Non-Streaming Requests** (`formatResponse` - Zeile 127):
```php
// GoogleProvider.php formatResponse()
if (!empty($rawGroundingMetadata)) {
    $formattedCitations = $this->citationService->formatCitations('google', $rawGroundingMetadata, $content);
    $groundingMetadata = $formattedCitations;
}
```

### **2. Streaming Requests** (`formatStreamChunk` - Zeile 172):
```php
// GoogleProvider.php formatStreamChunk()
if (isset($jsonChunk['candidates'][0]['groundingMetadata'])) {
    $rawGroundingMetadata = $jsonChunk['candidates'][0]['groundingMetadata'];
    
    // Format citations using unified service
    $formattedCitations = $this->citationService->formatCitations('google', $rawGroundingMetadata, $content);
    $groundingMetadata = $formattedCitations;
}
```

## 🎯 **Kompletter Citation DataFlow**:

```
Google API Response (with groundingMetadata)
           ↓
GoogleProvider.formatStreamChunk() / formatResponse()
           ↓
CitationService.formatCitations('google', rawGroundingMetadata, content)
           ↓
GoogleCitationFormatter.format() (provider-spezifisch)
           ↓
Standardisierte HAWKI Citation Format
           ↓
Frontend (als Teil von content.groundingMetadata -> JS)
```

## 📊 **CitationService Architektur**:

1. **Unified Interface**: Einheitliche `formatCitations()` Methode für alle Provider
2. **Provider-Specific Formatters**: `GoogleCitationFormatter`, `AnthropicCitationFormatter`
3. **Automatic Registration**: Default-Formatter werden automatisch registriert
4. **Null-Safe**: Gibt `null` zurück wenn keine Citations vorhanden

## 💡 **Warum das wichtig ist**:

- **Google Search Integration**: Google Gemini kann Web-Suchergebnisse einbeziehen
- **Citation Formatting**: Rohe Google-Metadaten werden in einheitliches HAWKI-Format konvertiert
- **Provider Abstraction**: Andere Provider (Anthropic) können eigene Citation-Formate haben
- **Frontend Consistency**: Einheitliche Citation-Darstellung unabhängig vom Provider

**Der CitationService ist ein aktiver und wichtiger Teil des DataFlows für alle Google-Requests mit Search-Funktionalität!** 🎯

## ✅ **Frontend Citation DataFlow** 🎯

## 🔍 **Kompletter Frontend Citation DataFlow**:

### **1. Content Parsing** (`deconstContent` - Zeile 392):
```javascript
function deconstContent(inputContent){
    let messageText = '';
    let groundingMetadata = '';
    
    if(isValidJson(inputContent)){
        const json = JSON.parse(inputContent);
        
        if(json.hasOwnProperty('groundingMetadata')){
            groundingMetadata = json.groundingMetadata; // ← Hier wird es extrahiert!
        }
        if(json.hasOwnProperty('text')){
            messageText = json.text;
        }
    }
    
    return { messageText, groundingMetadata }
}
```

### **2. Message Processing** (`formatMessage` - Zeile 58):
```javascript
function formatMessage(rawContent, groundingMetadata = '') {
    // Process citations and preserve HTML elements in one step
    let contentToProcess = formatCitations(rawContent, groundingMetadata); // ← Citation Processing
    
    // Apply markdown rendering
    const markdownProcessed = md.render(processedContent);
    
    // Restore preserved HTML elements
    let finalContent = restoreCitations(finalContent);
    return finalContent;
}
```

### **3. Citation Formatting** (`formatCitations` - Zeile 310+):
```javascript
function formatCitations(content, groundingMetadata = '') {
    // Return early if no citation metadata
    if (!groundingMetadata || typeof groundingMetadata !== 'object') {
        return content;
    }
    // [Citation processing logic]
}
```

### **4. Search Metadata Rendering** (`addSearchRenderedContent` - Zeile 273):
```javascript
function addSearchRenderedContent(messageElement, groundingMetadata){
    if (groundingMetadata && typeof groundingMetadata === 'object' &&
        groundingMetadata.searchMetadata &&
        groundingMetadata.searchMetadata.renderedContent) {
                
        const render = groundingMetadata.searchMetadata.renderedContent;
        // Extract the HTML and add it to the message
        const parser = new DOMParser();
        const doc = parser.parseFromString(render, 'text/html');
        const divElement = doc.querySelector('.container');
        
        // Create google-search span and append to message
        let googleSpan = document.createElement('span');
        googleSpan.classList.add('google-search');
        googleSpan.innerHTML = divElement.outerHTML; 
        messageContent.appendChild(googleSpan);
    }
}
```

## 🎯 **Kompletter Citation DataFlow**:

```
Backend: CitationService.formatCitations()
           ↓
JSON: {"text": "...", "groundingMetadata": {...}}
           ↓
Frontend: deconstContent() → extrahiert groundingMetadata
           ↓
formatMessage(messageText, groundingMetadata)
           ↓
formatCitations() → Verarbeitet Citations im Text
           ↓
addSearchRenderedContent() → Fügt Google Search UI hinzu
           ↓
DOM: Citation-Links + Google Search Chips im Message
```

## 📊 **Was passiert konkret**:

1. **Text Citations**: `formatCitations()` ersetzt Citation-Marker im Text durch klickbare Links
2. **Search Metadata**: `addSearchRenderedContent()` fügt Google Search-Chips unterhalb der Nachricht hinzu
3. **HTML Preservation**: Citations werden während Markdown-Processing geschützt
4. **UI Integration**: Search-Results werden als `.google-search` span in `.message-content` eingefügt

## 💡 **Frontend Citation Features**:

- ✅ **Inline Citations**: Links im Text für Quellenverweise
- ✅ **Search Chips**: Interaktive Google Search-Ergebnisse
- ✅ **External Links**: `target="_blank"` für alle Citation-Links  
- ✅ **Markdown Safe**: Citations überleben Markdown-Processing
- ✅ **Dynamic Updates**: Funktioniert mit Streaming und statischen Messages

**Das Frontend hat ein vollständiges Citation-System das sowohl Inline-Citations als auch Google Search-UI unterstützt!** 🚀