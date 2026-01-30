let convMessageTemplate;
let chatItemTemplate;
let activeConv;
let defaultPrompt;
let chatlogElement;
let chats = []; // Store chats globally for re-rendering
let hasMoreChats = true; // Track if more chats are available
let isLoadingChats = false; // Prevent duplicate requests

function groupChatsByDate(chats) {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    const groups = {
        today: [],
        yesterday: [],
        dates: {} // Will store chats grouped by specific date string
    };
    
    chats.forEach(chat => {
        const chatDate = new Date(chat.updated_at);
        const chatDateOnly = new Date(chatDate.getFullYear(), chatDate.getMonth(), chatDate.getDate());
        
        if (chatDateOnly.getTime() === today.getTime()) {
            groups.today.push(chat);
        } else if (chatDateOnly.getTime() === yesterday.getTime()) {
            groups.yesterday.push(chat);
        } else {
            // Create a date key for grouping (YYYY-MM-DD format for sorting)
            const dateKey = `${chatDate.getFullYear()}-${String(chatDate.getMonth() + 1).padStart(2, '0')}-${String(chatDate.getDate()).padStart(2, '0')}`;
            if (!groups.dates[dateKey]) {
                groups.dates[dateKey] = {
                    date: chatDate,
                    chats: []
                };
            }
            groups.dates[dateKey].chats.push(chat);
        }
    });
    
    return groups;
}

function formatDateLabel(date) {
    const day = date.getDate();
    const month = date.getMonth();
    const year = date.getFullYear();
    
    const monthName = getMonthName(month);
    
    // Format: "31. Oktober 2025" (German) or "October 31, 2025" (English)
    // Check current language from translation object
    if (translation.language === 'de_DE' || !translation.language) {
        return `${day}. ${monthName} ${year}`;
    } else {
        return `${monthName} ${day}, ${year}`;
    }
}

function getMonthName(monthIndex) {
    const months = [
        translation.January,
        translation.February,
        translation.March,
        translation.April,
        translation.May,
        translation.June,
        translation.July,
        translation.August,
        translation.September,
        translation.October,
        translation.November,
        translation.December
    ];
    return months[monthIndex];
}

function initializeAiChatModule(chatsObject, hasMore = true){

    convMessageTemplate = document.getElementById('message-template');
    chatItemTemplate = document.getElementById('selection-item-template');
    chatlogElement = document.querySelector('.chatlog');

    defaultPrompt = translation.Default_Prompt;

    const systemPromptFields = document.querySelectorAll('.system_prompt_field');
    systemPromptFields.forEach(field => {
        field.textContent = defaultPrompt;
    });

    chats = chatsObject;
    hasMoreChats = hasMore; // Set from backend
    renderChatsList();

    if(document.querySelector('.trunk').childElementCount == 0){
        chatlogElement.classList.add('start-state');
    }


    const input = document.getElementById('input-container');
    initFileUploader(input);

    initializeChatlogFunctions();
}

function renderChatsList() {
    const chatsList = document.getElementById('chats-list');
    chatsList.innerHTML = ''; // Clear existing content
    
    // Sort chats by updated_at (newest first) before grouping
    const sortedChats = [...chats].sort((a, b) => {
        return new Date(b.updated_at) - new Date(a.updated_at);
    });
    
    const groups = groupChatsByDate(sortedChats);
    
    // Render Today
    if (groups.today.length > 0) {
        const separator = createDateSeparator(translation.Today);
        chatsList.appendChild(separator);
        groups.today.forEach(conv => {
            const item = createChatItem(conv);
            chatsList.appendChild(item);
        });
    }
    
    // Render Yesterday
    if (groups.yesterday.length > 0) {
        const separator = createDateSeparator(translation.Yesterday);
        chatsList.appendChild(separator);
        groups.yesterday.forEach(conv => {
            const item = createChatItem(conv);
            chatsList.appendChild(item);
        });
    }
    
    // Render specific dates (sorted descending - newest first)
    const dateKeys = Object.keys(groups.dates).sort().reverse();
    dateKeys.forEach(dateKey => {
        const dateGroup = groups.dates[dateKey];
        const label = formatDateLabel(dateGroup.date);
        
        const separator = createDateSeparator(label);
        chatsList.appendChild(separator);
        
        dateGroup.chats.forEach(conv => {
            const item = createChatItem(conv);
            chatsList.appendChild(item);
        });
    });
    
    // Add "Load More" button if there are more chats to load
    if (hasMoreChats) {
        const existingBtn = document.getElementById('load-more-chats-btn');
        if (!existingBtn) {
            const loadMoreBtn = document.createElement('button');
            loadMoreBtn.id = 'load-more-chats-btn';
            loadMoreBtn.className = 'btn-md-stroke load-more-btn';
            loadMoreBtn.textContent = translation.LoadMore || 'Load More';
            loadMoreBtn.onclick = loadMoreChats;
            chatsList.appendChild(loadMoreBtn);
        }
    }
}

function createDateSeparator(label) {
    const separator = document.createElement('div');
    separator.className = 'date-separator';
    separator.innerHTML = `<span>${label}</span>`;
    return separator;
}

function updateChatTimestamp(slug) {
    // Update the chat's updated_at timestamp in our local array
    const chat = chats.find(c => c.slug === slug);
    if (chat) {
        chat.updated_at = new Date().toISOString();
        
        // Re-render the list to reflect the new order
        const activeSlug = activeConv ? activeConv.slug : null;
        renderChatsList();
        
        // Restore active state
        if (activeSlug) {
            const activeItem = document.querySelector(`.selection-item[slug="${activeSlug}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
    }
}

function updateChatTimestampFromServer(timestamp) {
    // Update the chat's updated_at timestamp with server value
    if (!activeConv) return;
    
    const chat = chats.find(c => c.slug === activeConv.slug);
    if (chat) {
        // Check if chat is already at the top BEFORE updating timestamp
        const sortedChatsBeforeUpdate = [...chats].sort((a, b) => {
            return new Date(b.updated_at) - new Date(a.updated_at);
        });
        const wasAlreadyFirst = sortedChatsBeforeUpdate[0].slug === activeConv.slug;
        
        // Update the timestamp
        chat.updated_at = timestamp;
        
        // Only re-render if chat was NOT already first
        if (!wasAlreadyFirst) {
            // Re-render the list to reflect the new order
            const activeSlug = activeConv.slug;
            renderChatsList();
            
            // Restore active state and add animation
            const activeItem = document.querySelector(`.selection-item[slug="${activeSlug}"]`);
            if (activeItem) {
                activeItem.classList.add('active', 'just-updated');
                
                // Remove animation class after it completes
                setTimeout(() => {
                    activeItem.classList.remove('just-updated');
                }, 400);
            }
        } else {
            // Chat was already first, just make sure it stays active
            const activeItem = document.querySelector(`.selection-item[slug="${activeConv.slug}"]`);
            if (activeItem && !activeItem.classList.contains('active')) {
                activeItem.classList.add('active');
            }
        }
    }
}


function onHandleKeydownConv(event){

    if(getSendBtnStat() === SendBtnStatus.SENDABLE){
        if(event.key == "Enter" && !event.shiftKey){
            event.preventDefault();
            selectActiveThread(event.target);
            sendMessageConv(event.target);
        }
    }
}

function onSendClickConv(btn){

    if(getSendBtnStat() === SendBtnStatus.SENDABLE){

        selectActiveThread(btn);
        //get inputfield relative to the button for multiple inputfields
        const input = btn.closest('.input');
        const inputField = input.querySelector('.input-field');
        sendMessageConv(inputField);
    }
    else if(getSendBtnStat() === SendBtnStatus.STOPPABLE){
        abortCtrl.abort();
    }
}

// SEND MESSAGE FUNCTION
async function sendMessageConv(inputField) {
    // block empty input field.
    if (inputField.value.trim() == "") {
        return;
    }
    const input = inputField.closest('.input');
    inputText = String(escapeHTML(inputField.value.trim()));

    setSendBtnStatus(SendBtnStatus.LOADING);

    // if the chat is empty we need to initialize a new chatlog.
    if (document.querySelector('.trunk').childElementCount === 0) {
        await initNewConv(inputText);
    }

    /// UPLOAD ATTACHMENTS
    const attachments = await uploadAttachmentQueue(input.id, 'conv');

    /// Encrypt message
    const convKey = await keychainGet('aiConvKey');
    const cryptoMsg = await encryptWithSymKey(convKey, inputText, false);
    const ciphertext = cryptoMsg.ciphertext;
    const iv = cryptoMsg.iv;
    const tag = cryptoMsg.tag;

    /// Submit Message to server.
    const messageObj = {
        'isAi': false,
        'threadId': activeThreadIndex,
        'completion': true,

        'content': {
            "text": {
                'ciphertext': ciphertext,
                "iv": iv,
                "tag": tag,
            },
            "attachments": attachments
        },
    }

    const submissionData = await submitMessageToServer(messageObj, `/req/conv/sendMessage/${activeConv.slug}`);

    // Replace the original text
    submissionData.content.text = inputText;

    // empty input field
    inputField.value = "";
    resizeInputField(inputField);
    const thumbnails = input.querySelectorAll('.attachment');
    thumbnails.forEach(thumbnail => {
        removeAtchFromList(thumbnail.dataset.fileId, input.id);
    });

    const messageElement = addMessageToChatlog(submissionData);

    // create and add message element to chatlog.
    messageElement.dataset.rawMsg = submissionData.content.text;
    scrollToLast(true, messageElement);
    
    // Update chat timestamp and re-render list to move chat to top (using server timestamp)
    if (submissionData.conv_updated_at) {
        updateChatTimestampFromServer(submissionData.conv_updated_at);
    } else {
        updateChatTimestamp(activeConv.slug);
    }

    const inputContainer = inputField.closest('.input-container');
    const webSearchBtn = inputContainer ? inputContainer.querySelector('#websearch-btn') : null;
    const webSearchActive = webSearchBtn ? webSearchBtn.classList.contains('active') : false;
    
    const reasoningBtn = inputContainer ? inputContainer.querySelector('#reasoning-btn') : null;
    const reasoningActive = reasoningBtn ? reasoningBtn.classList.contains('active') : false;
    
    // Check if activeModel is set
    if(!activeModel){
        console.error('No active model selected. Cannot send message.');
        alert('Bitte wählen Sie ein Modell aus, bevor Sie eine Nachricht senden.');
        return;
    }
    
    const tools = {
        'web_search': webSearchActive
    }
    
    // Add reasoning_effort parameter if reasoning is active
    // Get the selected effort level from the button's data attribute (default to 'medium')
    let reasoningEffort = null;
    if (reasoningActive) {
        reasoningEffort = reasoningBtn.dataset.effort || 'medium';
    }

    const msgAttributes = {
        'threadIndex': activeThreadIndex,
        'broadcasting': false,
        'slug': '',
        'stream': true,
        'model': activeModel.id,
        'tools': tools
    };
    
    // Only add reasoning_effort if it's set (reasoning is active)
    if (reasoningEffort !== null) {
        msgAttributes['reasoning_effort'] = reasoningEffort;
    }

    buildRequestObjectForAiConv(msgAttributes);
}


async function buildRequestObjectForAiConv(msgAttributes, messageElement = null, isUpdate = false, isDone = null){
    // let messageElement;
    let msg = "";
    let messageObj;
    let metadata;
    let auxiliaries = [];

    // Start buildRequestObject processing
    buildRequestObject(msgAttributes, async (data, done) => {

        if(data){
            
            // Handle error and cancellation status from stream
            if (data.status === 'error' || data.status === 'cancelled') {
                // Ensure message element exists
                if (!messageElement) {
                    initializeMessageFormating();
                    messageElement = addMessageToChatlog({
                        message_role: 'assistant',
                        content: '',
                        model: msgAttributes['model']
                    }, false);
                }
                
                if (messageElement) {
                    // First, mark all in_progress steps as failed/incomplete
                    const statusLog = JSON.parse(messageElement.dataset.statusLog || '{"steps":[],"currentStep":0}');
                    let modified = false;
                    
                    statusLog.steps.forEach(step => {
                        if (step.status === 'in_progress') {
                            step.status = 'incomplete';
                            step.label = step.label.replace('...', ' (incomplete)');
                            modified = true;
                        }
                    });
                    
                    if (modified) {
                        messageElement.dataset.statusLog = JSON.stringify(statusLog);
                        // Re-render to remove spinners from incomplete steps
                        renderStatusIndicator(messageElement);
                    }
                    
                    // Then add the error/cancelled status WITHOUT message (Frontend derives label)
                    updateStatusLog(messageElement, {
                        output_index: null,
                        status: data.status,
                        type: 'processing',
                        label: getStatusLabel(data.status, 'processing', null, null), // Derive label from status
                        icon: 'error',
                        timestamp: Date.now()
                    });
                    
                    // Update status indicator with isDone=true to stop spinner
                    updateAiStatusIndicator(messageElement, [], true);
                }
                
                // Mark as done to persist the error state
                done = true;
            }

            if(!msgAttributes['broadcasting'] && msgAttributes['stream']){
                setSendBtnStatus(SendBtnStatus.STOPPABLE);
            }
            
            // Skip deconstContent for error/cancel status
            if (data.status === 'error' || data.status === 'cancelled') {
                // Don't process content, just handle done state below
            } else {
                // CRITICAL: Reset auxiliaries for each chunk to prevent carryover from previous chunks
                auxiliaries = [];
                
                const {messageText, groundingMetadata, auxiliaries: aux} = deconstContent(data.content);
                
                if(groundingMetadata != ""){
                    metadata = groundingMetadata;
                }
                if(aux && aux.length > 0){
                    auxiliaries = aux;
                    
                    // NOTE: We do NOT store auxiliaries in dataset.rawContent
                    // Auxiliaries are processed once per chunk and should not be re-processed
                    // Only text and metadata are persisted for multi-turn conversations
                }

                // Safety check: ensure messageText is a string, not an object
                const content = typeof messageText === 'string' ? messageText : '';
                
                // Log warning if content is not a string
                if (typeof messageText !== 'string' && messageText !== undefined && messageText !== null) {
                    console.error('[STREAM ERROR] messageText is not a string:', typeof messageText, messageText);
                }
                
                // Update dataset.rawContent with accumulated text and metadata
                // Auxiliaries are NOT stored here - they're processed once per chunk
                if (messageElement && content) {
                    const tempContent = JSON.stringify({
                        text: msg + content,
                        groundingMetadata: metadata
                    });
                    messageElement.dataset.rawContent = tempContent;
                }
                
                msg += content;
                messageObj = data;
                messageObj.message_role = 'assistant';
                messageObj.content = content;
                messageObj.completion = data.isDone;
                messageObj.model = msgAttributes['model'];

                // Create message element early if we have status updates (even without text content)
                if (!messageElement && (auxiliaries.length > 0 || content)) {
                    initializeMessageFormating()
                    messageElement = addMessageToChatlog(messageObj, false);
                }
                
                // Update message element if it exists
                if (messageElement) {
                    messageElement.dataset.rawMsg = msg;

                    const msgTxtElement = messageElement.querySelector(".message-text");

                    msgTxtElement.innerHTML = formatChunk(content, groundingMetadata);
                    formatMathFormulas(msgTxtElement);
                    formatHljs(messageElement);

                    if (groundingMetadata &&
                        groundingMetadata != '' &&
                        groundingMetadata.searchEntryPoint &&
                        groundingMetadata.searchEntryPoint.renderedContent) {

                        addGoogleRenderedContent(messageElement, groundingMetadata);
                    }
                    else{
                        if(messageElement.querySelector('.google-search')){
                            messageElement.querySelector('.google-search').remove();
                        }
                    }

                    // Add Anthropic citations and status updates during streaming
                    if (auxiliaries && Array.isArray(auxiliaries) && auxiliaries.length > 0) {
                        addAnthropicCitations(messageElement, auxiliaries);
                        addResponsesCitations(messageElement, auxiliaries); // OpenAI Responses API citations
                        
                        // Update AI status indicator (thinking, reasoning, web search)
                        // Pass data.isDone to ensure status_log is processed in final chunk
                        updateAiStatusIndicator(messageElement, auxiliaries, data.isDone || false);
                    }

                    if(messageElement.querySelector('.think')){
                        scrollPanelToLast(messageElement.querySelector('.think').querySelector('.content-container'));
                    }

                    scrollToLast(false, messageElement);
                }
            } // End of else block for normal content processing
        }

        if(done){
            setSendBtnStatus(SendBtnStatus.SENDABLE);
            
            // NOTE: We don't call updateAiStatusIndicator(..., true) here anymore
            // The final "processing completed" status is automatically added by the frontend
            // when it receives isDone=true from the backend (in the finish_reason chunk)
            // This happens in updateAiStatusIndicator() → if (isDone) block
            
            // Add status_log from dataset to auxiliaries for persistence
            if (messageElement && messageElement.dataset.statusLog) {
                try {
                    const statusLog = JSON.parse(messageElement.dataset.statusLog);
                    
                    // Convert status log steps to backend format
                    if (statusLog.steps && statusLog.steps.length > 0) {
                        const backendLog = statusLog.steps.map(step => {
                            const entry = {
                                type: step.type,
                                status: step.status,
                                message: step.label,
                                output_index: step.output_index,
                                timestamp: step.timestamp
                            };
                            
                            // Include reasoning summary details if available
                            if (step.details && step.details.content) {
                                entry.summary = step.details.content;
                            }
                            
                            return entry;
                        });
                        
                        // Add or update status_log auxiliary
                        const statusLogAuxIndex = auxiliaries.findIndex(aux => aux.type === 'status_log');
                        if (statusLogAuxIndex >= 0) {
                            auxiliaries[statusLogAuxIndex] = {
                                type: 'status_log',
                                content: JSON.stringify({ log: backendLog })
                            };
                        } else {
                            auxiliaries.push({
                                type: 'status_log',
                                content: JSON.stringify({ log: backendLog })
                            });
                        }
                    }
                } catch (error) {
                    console.error('[STATUS LOG] Error converting status log for persistence:', error);
                }
            }

            const cryptoContent = JSON.stringify({
                text: msg,
                groundingMetadata : metadata,
                auxiliaries: auxiliaries
            });

            const convKey = await keychainGet('aiConvKey');
            const cryptoMsg = await encryptWithSymKey(convKey, cryptoContent, false);

            messageObj.ciphertext = cryptoMsg.ciphertext;
            messageObj.iv = cryptoMsg.iv;
            messageObj.tag = cryptoMsg.tag;

            // Store raw content for multi-turn (needed for createMsgObject)
            messageElement.dataset.rawContent = cryptoContent;

            activateMessageControls(messageElement);

            const requestObj = {
                'isAi': true,
                'threadId': activeThreadIndex,
                'content':{
                    'text': {
                        'ciphertext': messageObj.ciphertext,
                        'iv': messageObj.iv,
                        'tag': messageObj.tag,
                    }
                },
                'model': messageObj.model,
                'completion': messageObj.completion,
            }
            if(isUpdate){
                requestObj.message_id = messageElement.id;
                await requestMsgUpdate(requestObj, messageElement, `/req/conv/updateMessage/${activeConv.slug}`)
            }
            else{
                requestObj.isAi = true;
                const submittedObj = await submitMessageToServer(requestObj, `/req/conv/sendMessage/${activeConv.slug}`);

                submittedObj.content = cryptoContent;
                messageElement.dataset.rawMsg = msg;
                // messageElement.dataset.groundingMetadata = metadata;
                addGoogleRenderedContent(messageElement, metadata);
                updateMessageElement(messageElement, submittedObj);
                activateMessageControls(messageElement);
            }

            if(isDone){
                isDone(true);
            }
        }
    });
}


//#region CONVERSATION FUNCTIONS

/// Initializing a new conversation.
async function initNewConv(firstMessage){

    // if start State panel is there remove it.
    chatlogElement.classList.remove('start-state');

    // empty chatlog
    clearChatlog();
    //
    history.replaceState(null, '', `/chat`);

    //create conversation button in the list.
    const convItem = createChatItem();
    convItem.classList.add('active');
    
    // Temporarily add to top for immediate feedback
    const chatsList = document.getElementById('chats-list');
    chatsList.insertBefore(convItem, chatsList.firstChild);

    //create conversation name.
    const convName = await generateChatName(firstMessage, convItem);

    //submit conv to server.
    // after the server has accepted Submission conv data will be updated.
    const convData = await submitConvToServer(convName);

    //assign Slug to conv Item.
    convItem.setAttribute('slug', convData.slug);
    //update URL
    history.replaceState(null, '', `/chat/${convData.slug}`);

    //update active conv cache.
    activeConv = convData;
    
    // Add to chats array and re-render list with proper grouping
    chats.unshift(convData);
    renderChatsList();
    
    // Reactivate the new chat item
    const newActiveItem = document.querySelector(`.selection-item[slug="${convData.slug}"]`);
    if (newActiveItem) {
        newActiveItem.classList.add('active');
    }

    return;
}


function startNewChat(){
    chatlogElement.classList.add('start-state');
    clearChatlog();
    clearInput();
    history.replaceState(null, '', `/chat`);

    const systemPromptFields = document.querySelectorAll('.system_prompt_field');
    systemPromptFields.forEach(field => {
        field.textContent = defaultPrompt;
    });

    const lastActive = document.getElementById('chats-list').querySelector('.selection-item.active');
    if(lastActive){
        lastActive.classList.remove('active')
    }
    
    // Reset to default model when starting a new chat
    currentChatId = null;
    setModel(null, null);

    document.getElementById('input-container').focus();
}

function createChatItem(conv = null){

    const convItem = chatItemTemplate.content.cloneNode(true);
    const selectionItem = convItem.querySelector('.selection-item');
    const label = convItem.querySelector('.label');

    if(conv){
        selectionItem.setAttribute('slug', conv.slug);
        label.textContent = conv.conv_name;
    }
    else{
        label.textContent = 'New Chat';
    }

    return selectionItem;
}


async function generateChatName(firstMessage, convItem) {
    // Truncate input to prevent long processing and reduce token costs
    // Max 500 characters is sufficient for title generation
    const truncatedMessage = firstMessage.length > 500 
        ? firstMessage.substring(0, 500) + "..." 
        : firstMessage;
    
    const requestObject = {
        payload: {
            model: systemModels.title_generator,
            stream: true,
            max_tokens: 10, // Limit to ~3-5 words
            messages: [
                {
                    role: "system",
                    content: {
                        text: translation.Name_Prompt
                    }
                },
                {
                    role: "user",
                    content: {
                        text: truncatedMessage
                    }
                }
            ]
        },
        broadcast: false,
        threadIndex: '',
        slug: '',
        assistantKey: 'title_generator',
    };

    return new Promise((resolve, reject) => {
        postData(requestObject)
        .then(response => {
            const convElement = convItem.querySelector('.label');
            let convName = ""; // Initialize to an empty string
            
            const onData = (data, done) => {
                if (data && data.content) {
                    let contentText = '';
                    
                    try {
                        // Parse content (comes as JSON string from backend)
                        const content = typeof data.content === 'string' 
                            ? JSON.parse(data.content) 
                            : data.content;
                        
                        // Extract text - all providers use {text: "..."} format
                        contentText = content.text || '';
                    } catch (e) {
                        // Fallback if JSON parsing fails
                        contentText = typeof data.content === 'string' ? data.content : '';
                    }
                    
                    // Filter out JSON strings (model returning structured data instead of plain text)
                    const trimmed = contentText.trim();
                    const isJsonString = trimmed && (trimmed.startsWith('{') || trimmed.startsWith('['));
                    
                    // Only process valid plain text (non-empty and not JSON)
                    if (trimmed && !isJsonString) {
                        // Replace newlines with spaces
                        convName += contentText.replace(/[\n\r]/g, ' ');
                        
                        // Abort after 3 words
                        const words = convName.trim().split(/\s+/).filter(w => w.length > 0);
                        if (words.length >= 3) {
                            const title = words.slice(0, 3).join(" ");
                            convElement.innerText = title;
                            abortCtrl.abort();
                            resolve(title);
                            return;
                        }
                        convElement.innerText = convName;
                    }
                }
                
                if (done) {
                    resolve(convName.trim() || "New Chat");
                }
            };
            processStream(response.body, onData);
        })
        .catch(error => reject(error));
    });

}



async function submitConvToServer(convName) {
    const systemPrompt = document.querySelector('#system_prompt_field').textContent;
    const convKey = await keychainGet('aiConvKey');
    const cryptSystemPrompt = await encryptWithSymKey(convKey, systemPrompt, false);
    const systemPromptStr = JSON.stringify({
        'ciphertext':cryptSystemPrompt.ciphertext,
        'iv':cryptSystemPrompt.iv,
        'tag':cryptSystemPrompt.tag,
    });


    const requestObject = {
        conv_name: convName,
        system_prompt: systemPromptStr
    }

    try {
        const response = await fetch('/req/conv/createChat', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify(requestObject)
        });

        const data = await response.json();

        if (data.success) {
            return data.conv;
        } else {
            // Handle unexpected response
            console.error('Unexpected response:', data);
        }
    } catch (error) {
        console.error('There was a problem with the fetch operation:', error);
    }
}


async function loadConv(btn=null, slug=null){

    abortCtrl.abort();

    if(!btn && !slug){
        return;
    }

    if(!slug) slug = btn.getAttribute('slug');
    if(!btn) btn = document.querySelector(`.selection-item[slug="${slug}"]`);
    // switchDyMainContent('chat');

    const lastActive = document.getElementById('chats-list').querySelector('.selection-item.active');
    if(lastActive){
        lastActive.classList.remove('active')
    }
    btn.classList.add('active');



    switchDyMainContent('chat');

    history.replaceState(null, '', `/chat/${slug}`);

    const convData = await RequestConvContent(slug);

    if(!convData){
        return;
    }

    clearChatlog();
    clearInput();
    activeConv = convData;

    const convKey = await keychainGet('aiConvKey');
    const systemPromptObj = JSON.parse(convData.system_prompt);
    const systemPrompt = await decryptWithSymKey(convKey, systemPromptObj.ciphertext, systemPromptObj.iv, systemPromptObj.tag, false);

    activeConv.system_prompt = systemPrompt;

    const systemPromptFields = document.querySelectorAll('.system_prompt_field');
    systemPromptFields.forEach(field => {
        field.textContent = systemPrompt;
    });

    const msgs = convData.messages;
    for (const msg of msgs) {
        const decryptedContent =  await decryptWithSymKey(convKey, msg.content.text.ciphertext, msg.content.text.iv, msg.content.text.tag);
        // msg.content.text contains the full JSON: {text: "...", groundingMetadata: {...}, auxiliaries: [...]}
        msg.content.text = decryptedContent;
    };

    if(msgs.length > 0){
        chatlogElement.classList.remove('start-state');
    }
    else{
        chatlogElement.classList.add('start-state');
    }
    
    // Update current chat ID for model selection logic
    currentChatId = slug;
    
    // Set model based on chat context
    setModel(null, slug);
    
    initModelFilter();
    loadMessagesOnGUI(convData.messages);
    scrollToLast(true);
}




async function RequestConvContent(slug){

    url = `/req/conv/${slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try{
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',

            },
        });

        if(!response.ok){
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseJson = await response.json();
        if(responseJson.success){
            return responseJson.data;
        }
        else{
            console.error(responseJson.message);
            return;
        }
    }
    catch (err){
        console.error('Error fetching data:', error);
        throw err;
    }
}



async function requestDeleteConv() {
    // First try to get slug from burger menu
    const burgerMenu = document.getElementById('quick-actions');
    let targetSlug = burgerMenu ? burgerMenu.getAttribute('data-room-slug') : null;

    // Fallback to active conversation if no slug in burger menu
    if (!targetSlug && typeof activeConv !== 'undefined' && activeConv) {
        targetSlug = activeConv.slug;
    }

    if (!targetSlug) {
        console.error('No conversation target for deletion');
        return;
    }

    const confirmed = await openModal(ModalType.WARNING, translation.Cnf_deleteConv);
    if (!confirmed) {
        return;
    }

    const url = `/req/conv/removeConv/${targetSlug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });
        const data = await response.json();

        if (data.success) {
            const listItem = document.querySelector(`.selection-item[slug="${targetSlug}"]`);
            if (!listItem) {
                console.error('Could not find list item to remove');
                return;
            }

            const wasActive = listItem.classList.contains('active');
            const list = listItem.parentElement;
            listItem.remove();

            // Only load another conversation or clear if we deleted the currently active one
            if (wasActive) {
                if (list.childElementCount > 0) {
                    loadConv(list.firstElementChild, null);
                }
                else {
                    clearChatlog();
                    clearInput();
                    chatlogElement.classList.remove('active');
                    chatlogElement.classList.add('start-state');

                    history.replaceState(null, '', `/chat`);
                }
            }
        } else {
            console.error('Conv removal was not successful!');
        }
    } catch (error) {
        console.error('Failed to remove conv!', error);
    }
}



async function deleteMessage(btn){
    const confirmed = await openModal(ModalType.WARNING , translation.Cnf_deleteConv);
    if (!confirmed) {
        return;
    }

    const url = `/req/conv/message/delete/${activeConv.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const message = btn.closest('.message');

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                'message_id' : message.id
            })
        });

        const data = await response.json();

        if (data.success) {

            message.remove();



        } else {
            console.error('Failed to remove Message: ' + data.err);
        }
    } catch (error) {
        console.error('Failed to remove conv!');
    }


}

function editChatTitle() {
    // First try to get slug from burger menu
    const burgerMenu = document.getElementById('quick-actions');
    let slug = burgerMenu ? burgerMenu.getAttribute('data-room-slug') : null;
    let activeItem = null;
    let label = null;
    
    if (slug) {
        // Find the selection item with this slug
        activeItem = document.querySelector(`.selection-item[slug="${slug}"]`);
        if (activeItem) {
            label = activeItem.querySelector('.label');
        }
    }
    
    // Fallback to active selection item
    if (!label) {
        activeItem = document.querySelector('.selection-item.active');
        if (activeItem) {
            label = activeItem.querySelector('.label');
            slug = activeItem.getAttribute('slug');
        }
    }
    
    if (!activeItem || !label) {
        console.error('No chat selected for editing');
        return;
    }
    
    const originalText = label.textContent;

    const wrapper = document.createElement('div');
    wrapper.className = 'title-edit-wrapper';

    const input = Object.assign(document.createElement('input'), {
        value: originalText,
        className: 'title-edit-input',
        maxLength: 25,
        onkeydown: (e) => {
            if (e.key === 'Enter') confirmBtn.click();
            if (e.key === 'Escape') cancelBtn.click();
        }
    });

    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'btn-xs title-edit-confirm';
    confirmBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    confirmBtn.onclick = async (e) => {
        e.stopPropagation();
        document.removeEventListener('click', outsideClickHandler);
        const title = input.value.trim() || originalText;
        try {
            await fetch(`/req/conv/updateTitle/${slug}`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({title})
            });
            label.textContent = title;
        } catch (e) {}
        wrapper.replaceWith(label);
    };

    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn-xs title-edit-cancel';
    cancelBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
    cancelBtn.onclick = (e) => {
        e.stopPropagation();
        document.removeEventListener('click', outsideClickHandler);
        wrapper.replaceWith(label);
    };

    const outsideClickHandler = (e) => {
        if (!wrapper.contains(e.target)) {
            cancelBtn.click();
        }
    };

    wrapper.appendChild(input);
    wrapper.appendChild(confirmBtn);
    wrapper.appendChild(cancelBtn);

    label.replaceWith(wrapper);
    input.focus();
    input.select();
    closeBurgerMenus();

    // Verzögere das Hinzufügen des Click-Listeners, damit der aktuelle Klick nicht sofort abbricht
    setTimeout(() => {
        document.addEventListener('click', outsideClickHandler);
    }, 0);
}

async function loadMoreChats() {
    if (!hasMoreChats || isLoadingChats) return;
    
    isLoadingChats = true;
    const loadMoreBtn = document.getElementById('load-more-chats-btn');
    if (loadMoreBtn) {
        loadMoreBtn.disabled = true;
        loadMoreBtn.textContent = translation.Loading || 'Loading...';
    }
    
    try {
        const response = await fetch('/req/conv/loadMore', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                offset: chats.length,
                limit: 20
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.conversations.length > 0) {
            // Add new chats to array
            chats.push(...data.conversations);
            hasMoreChats = data.hasMore;
            
            // Re-render list
            renderChatsList();
            
            // Restore active state if any
            if (activeConv) {
                const activeItem = document.querySelector(`.selection-item[slug="${activeConv.slug}"]`);
                if (activeItem) {
                    activeItem.classList.add('active');
                }
            }
        }
        
        hasMoreChats = data.hasMore;
        
        if (!hasMoreChats && loadMoreBtn) {
            loadMoreBtn.remove();
        }
    } catch (error) {
        console.error('Failed to load more chats:', error);
    } finally {
        isLoadingChats = false;
        if (loadMoreBtn && hasMoreChats) {
            loadMoreBtn.disabled = false;
            loadMoreBtn.textContent = translation.LoadMore || 'Load More';
        }
    }
}


//#endregion
