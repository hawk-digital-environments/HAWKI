# 🎯 **Unified Citation Format - Design**

## 🔍 **Problem: Zwei Citation-Paradigmen**

### **Anthropic-Style**:
- **Text**: `"Climate change is real [1] and affects everyone [2,3]."`
- **Citations**: Live während Stream
- **Processing**: Bestehende `[1]` in `<sup><a>` umwandeln

### **Google-Style**:
- **Text**: `"Climate change is real and affects everyone."`
- **textSegments**: `[{"text": "Climate change is real", "citationIds": [0]}, {"text": "affects everyone", "citationIds": [1,2]}]`
- **Processing**: Text-Abschnitte finden und Citations hinzufügen

## 🚀 **Unified HAWKI Citation Format v1**

### **Backend Output** (einheitlich für alle Provider):
```php
[
    'format' => 'hawki_v1',
    'processing_mode' => 'inline', // oder 'segments'
    'citations' => [
        [
            'id' => 1,
            'title' => 'Climate Change Report',
            'url' => 'https://example.com',
            'snippet' => 'Climate change is affecting...'
        ]
    ],
    'text_processing' => [
        // Für Anthropic (inline mode)
        'mode' => 'inline',
        'inline_markers' => true
        
        // Für Google (segments mode)
        'mode' => 'segments',
        'text_segments' => [
            ['text' => 'Climate change is real', 'citation_ids' => [1]],
            ['text' => 'affects everyone', 'citation_ids' => [2,3]]
        ]
    ]
]
```

## 💻 **Frontend Processing** (unified renderer):

### **Unified Citation Processor**:
```javascript
function processCitations(content, citationData) {
    if (citationData.format !== 'hawki_v1') {
        return content; // Fallback für alte Formate
    }
    
    const processingMode = citationData.text_processing.mode;
    
    switch(processingMode) {
        case 'inline':
            return processInlineCitations(content, citationData);
        case 'segments':
            return processSegmentCitations(content, citationData);
        default:
            return content;
    }
}

function processInlineCitations(content, citationData) {
    // Anthropic-Style: [1] → <sup><a>1</a></sup>
    return content.replace(/\[(\d+(?:,\s*\d+)*)\]/g, (match, citationString) => {
        const citationIds = citationString.split(',').map(id => parseInt(id.trim()));
        return createCitationLink(citationIds, citationData.citations);
    });
}

function processSegmentCitations(content, citationData) {
    // Google-Style: Text-Segmente + Citations
    let processedContent = content;
    
    citationData.text_processing.text_segments.forEach(segment => {
        const citationLink = createCitationLink(segment.citation_ids, citationData.citations);
        processedContent = processedContent.replace(
            new RegExp(escapeRegExp(segment.text), 'g'),
            match => match + citationLink
        );
    });
    
    return processedContent;
}

function createCitationLink(citationIds, citationsData) {
    const validCitations = citationIds.filter(id => citationsData[id-1]);
    if (validCitations.length === 0) return '';
    
    return `<sup><a href="#sources" class="citation-link" 
                data-sources="${validCitations.map(id => id-1).join(',')}"
                data-citations='${JSON.stringify(validCitations.map(id => citationsData[id-1]))}'
            >${validCitations.join(', ')}</a></sup>`;
}
```

## 🔄 **Provider Implementation**:

### **GoogleProvider**:
```php
public function formatCitations($content, $rawGroundingMetadata) {
    return [
        'format' => 'hawki_v1',
        'processing_mode' => 'segments',
        'citations' => $this->extractGoogleCitations($rawGroundingMetadata),
        'text_processing' => [
            'mode' => 'segments',
            'text_segments' => $this->extractTextSegments($rawGroundingMetadata)
        ]
    ];
}
```

### **AnthropicProvider**:
```php
public function formatCitations($content, $rawCitations) {
    return [
        'format' => 'hawki_v1',
        'processing_mode' => 'inline',
        'citations' => $this->extractAnthropicCitations($rawCitations),
        'text_processing' => [
            'mode' => 'inline',
            'inline_markers' => true
        ]
    ];
}
```

## ✅ **Vorteile**:

1. **Unified Interface**: Ein Citation-Renderer für alle Provider
2. **Provider Flexibility**: Jeder Provider kann sein optimales Format beibehalten
3. **Backwards Compatible**: Kann alte Formate weiterhin verarbeiten
4. **Extensible**: Neue Provider können eigene Modi hinzufügen
5. **Performance**: Kein Provider-spezifischer Code im Frontend

## 🎯 **Implementation Strategy**:

### **Phase 1**: Backend Unified Format
- CitationService erweitern für hawki_v1 Format
- GoogleCitationFormatter implementieren (segments mode)
- AnthropicCitationFormatter implementieren (inline mode)

### **Phase 2**: Frontend Unified Renderer
- `processCitations()` implementieren
- Alte `formatCitations()` als Fallback beibehalten
- Testing mit beiden Provider-Typen

### **Phase 3**: Cleanup
- Alte provider-spezifische Frontend-Logik entfernen
- Documentation aktualisieren
- Performance optimization

**Das Unified Format löst beide Citation-Paradigmen elegant und performant!** 🚀
