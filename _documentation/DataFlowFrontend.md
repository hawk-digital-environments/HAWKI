stream_functions.js → `processResponse` bis zur UI-Darstellung:

## 🎨 Frontend Data Flow: Stream → UI Rendering

### **1. Stream Processing Ebene (stream_functions.js)**

#### **🔴 processResponse() - Non-Streaming Entry Point**
```javascript
// stream_functions.js - Zeile 141
async function processResponse(response, onData){
    const responseJson = await response.json();
    onData(responseJson, true); // ← Komplette Antwort als JSON
}
```

#### **🔴 processStream() - Streaming Entry Point**
```javascript
// stream_functions.js - Zeilen 85-136
async function processStream(stream, onData) {
    const reader = stream.getReader();
    const textDecoder = new TextDecoder("utf-8");
    let buffer = "";
    
    while (true) {
        const { done, value } = await reader.read();
        
        if (done) {
            onData(null, true); // ← Stream beendet
            return;
        }

        // Buffer-Management für SSE
        buffer += textDecoder.decode(value, { stream: true });
        const parts = buffer.split("\n");
        buffer = parts.pop(); // Letzter Teil könnte unvollständig sein
        
        for (const part of parts) {
            if (part.trim()) {
                try {
                    const data = JSON.parse(part); // ← Parsed SSE Data
                    if(data.isDone){
                        onData(data, true); // ← Stream fertig
                        return;
                    }
                    onData(data, false); // ← Chunk-weise Daten
                } catch (error) {
                    console.error('Error parsing JSON:', error);
                }
            }
        }
    }
}
```

### **2. Data Processing Ebene (ai_chat_functions.js)**

#### **🟡 buildRequestObjectForAiConv() - Main Callback Handler**
```javascript
// ai_chat_functions.js - Zeilen 140-280
buildRequestObject(msgAttributes, async (data, done) => {
    
    if(data){
        if(!msgAttributes['broadcasting'] && msgAttributes['stream']){
            setSendBtnStatus(SendBtnStatus.STOPPABLE);
        }

        // ← Hier kommen die Backend-Daten an (von processResponse/processStream)
        
        // 1. Content Dekonstruktion
        const {messageText, groundingMetadata} = deconstContent(data.content);
        if(groundingMetadata != ""){
            metadata = groundingMetadata;
        }
        
        const content = messageText;
        msg += content; // ← Akkumulierung für Streaming
        messageObj = data;
        messageObj.message_role = 'assistant';
        messageObj.content = content;
        messageObj.completion = data.isDone;
        messageObj.model = msgAttributes['model'];

        // 2. Message Element Initialisierung
        if (!messageElement) {
            initializeMessageFormating()
            messageElement = addMessageToChatlog(messageObj, false); // ← UI Element erstellen
        }
        messageElement.dataset.rawMsg = msg;

        // 3. Live Content Update
        const msgTxtElement = messageElement.querySelector(".message-text");
        let markdownProcessed = formatMessage(msg, metadata);
        msgTxtElement.innerHTML = markdownProcessed; // ← Live UI Update!
        formatMathFormulas(msgTxtElement);
        
        // 4. Google Search Metadata
        if (groundingMetadata && 
            groundingMetadata != '' && 
            groundingMetadata.searchMetadata && 
            groundingMetadata.searchMetadata.renderedContent) {

            addSearchRenderedContent(messageElement, groundingMetadata);
            if(typeof activateCitations === 'function'){
                activateCitations(messageElement);
            }
        }

        // 5. Auto-Scroll
        scrollToLast(false, messageElement);
    }

    if(done){
        setSendBtnStatus(SendBtnStatus.SENDABLE);
        
        // Final encryption and server submission...
        const cryptoContent = JSON.stringify({
            text: msg,
            groundingMetadata : metadata
        });
        
        const convKey = await keychainGet('aiConvKey');
        const cryptoMsg = await encryptWithSymKey(convKey, cryptoContent, false);
        
        // Server submission...
        activateMessageControls(messageElement);
    }
});
```

### **3. Content Processing Ebene (message_functions.js)**

#### **🟢 deconstContent() - Content Extraction**
```javascript
// message_functions.js - Zeilen 390-420
function deconstContent(inputContent){
    let messageText = "";
    let groundingMetadata = "";

    if(isValidJson(inputContent)){
        const json = JSON.parse(inputContent);
        
        if(json.hasOwnProperty('groundingMetadata')){
            groundingMetadata = json.groundingMetadata;
        }
        if(json.hasOwnProperty('text')){
            messageText = json.text;
        }
        else if(json.hasOwnProperty('messageText')){
            messageText = json.messageText;
        }
        else if(json.hasOwnProperty('content')){
            messageText = json.content;
        }
        else{
            messageText = inputContent;
        }
    }
    else{
        messageText = inputContent;
    }

    return {
        messageText: messageText,    // ← Reiner Text
        groundingMetadata: groundingMetadata  // ← Google Search Daten
    }
}
```

### **4. UI Rendering Ebene (message_functions.js)**

#### **🟢 addMessageToChatlog() - DOM Element Creation**
```javascript
// message_functions.js - Zeilen 1-160
function addMessageToChatlog(messageObj, isFromServer = false){

    const {messageText, groundingMetadata} = deconstContent(messageObj.content);

    // 1. Message Element Clone
    const messageTemp = document.getElementById('message-template')
    const messageClone = messageTemp.content.cloneNode(true);
    const messageElement = messageClone.querySelector(".message");

    // 2. Dataset & ID Setup
    messageElement.dataset.role = messageObj.message_role;
    messageElement.dataset.rawMsg = messageText;
    if(messageObj.created_at) messageElement.dataset.created_at = messageObj.created_at;
    if(messageObj.message_id) messageElement.id = messageObj.message_id;

    // 3. Avatar & Author Setup
    if(messageObj.message_role === "assistant"){
        messageElement.classList.add('AI');
        messageElement.querySelector('.user-inits').remove();
        messageElement.querySelector('.icon-img').src = hawkiAvatarUrl;
    }
    // ... User/Member avatar logic

    // 4. Author Name mit Model Info
    if(messageObj.model && messageObj.message_role === 'assistant'){
        model = modelsList.find(m => m.id === messageObj.model);
        messageElement.querySelector('.message-author').innerHTML = 
            model ?
            `<span>${messageObj.author.username} </span><span class="message-author-model">(${model.label})</span>`:
            `<span>${messageObj.author.username} </span><span class="message-author-model">(${messageObj.model}) !!! Obsolete !!!</span>`;
    }

    // 5. Content Processing & Rendering
    const msgTxtElement = messageElement.querySelector(".message-text");

    if(!messageElement.classList.contains('AI')){
        // User messages: Hyperlinks + Mentions
        let processedContent = detectMentioning(messageText).modifiedText;
        processedContent = convertHyperlinksToLinks(processedContent);
        msgTxtElement.innerHTML = processedContent;
    }
    else{
        // AI messages: Markdown + Math + Citations
        let markdownProcessed = formatMessage(messageText, groundingMetadata);
        msgTxtElement.innerHTML = markdownProcessed; // ← FINALE UI DARSTELLUNG!
        formatMathFormulas(msgTxtElement);
        
        // Google Search Results
        if (groundingMetadata && 
            groundingMetadata != '' && 
            groundingMetadata.searchMetadata && 
            groundingMetadata.searchMetadata.renderedContent) {

            addSearchRenderedContent(messageElement, groundingMetadata);
            if(typeof activateCitations === 'function'){
                activateCitations(messageElement);
            }
        }
    }

    // 6. Thread Insertion
    let activeThread = findThreadWithID(threadIndex);
    activeThread.appendChild(messageElement);
    
    return messageElement;
}
```

## 🌊 Detaillierter Frontend Data Flow

### **Phase 1: Stream Reception**
1. **Backend SSE Stream** → `processStream()` mit TextDecoder
2. **JSON Parsing** von SSE chunks (`"data: {...}"`)
3. **Buffer Management** für unvollständige Pakete
4. **onData Callback** wird für jeden Chunk aufgerufen

### **Phase 2: Content Processing**
5. **buildRequestObjectForAiConv()** empfängt `data` object
6. **deconstContent()** extrahiert `messageText` und `groundingMetadata`
7. **Message Accumulation** für Streaming (`msg += content`)
8. **Metadaten Processing** (Google Search, Citations)

### **Phase 3: UI Element Management**
9. **addMessageToChatlog()** erstellt DOM Element (nur beim ersten Chunk)
10. **Live Content Updates** via `innerHTML` während Streaming
11. **Markdown Processing** mit `formatMessage()`
12. **Math Formula Rendering** mit `formatMathFormulas()`

### **Phase 4: Final Rendering**
13. **Google Search Results** via `addSearchRenderedContent()`
14. **Citation Activation** mit `activateCitations()`
15. **Auto-Scroll** zu neuester Message
16. **Message Controls** werden aktiviert (Copy, Regenerate, etc.)

### **Kritische UI-Übergabestellen:**

#### **🔴 Stream → Processing**
```javascript
// processStream() → onData callback
const data = JSON.parse(part); // ← Raw SSE data
onData(data, false); // → Weiterleitung an buildRequestObjectForAiConv
```

#### **🟡 Processing → Content Extraction**
```javascript
// buildRequestObjectForAiConv()
const {messageText, groundingMetadata} = deconstContent(data.content);
// ← Backend content → Frontend text + metadata
```

#### **🟢 Content → Live UI Update**
```javascript
// Live streaming updates
const msgTxtElement = messageElement.querySelector(".message-text");
let markdownProcessed = formatMessage(msg, metadata);
msgTxtElement.innerHTML = markdownProcessed; // ← SICHTBARE UI ÄNDERUNG!
```

#### **🔵 Final UI Rendering**
```javascript
// addMessageToChatlog() - Final formatting
let markdownProcessed = formatMessage(messageText, groundingMetadata);
msgTxtElement.innerHTML = markdownProcessed; // ← ENDGÜLTIGE DARSTELLUNG
formatMathFormulas(msgTxtElement);
addSearchRenderedContent(messageElement, groundingMetadata);
```

Der **finale UI-Renderingpunkt** ist die **`innerHTML`-Zuweisung in `addMessageToChatlog()`**, wo der verarbeitete Markdown-Content mit Math-Formeln und Google Search-Ergebnissen in das DOM eingefügt wird. Dies ist der letzte Punkt, bevor die Daten für den Benutzer sichtbar werden.