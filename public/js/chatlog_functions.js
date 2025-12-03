
let activeThreadIndex = 0;
let activeModel;
let isScrolling = false; // Flag to track if the user is scrolling
let observer;
let currentChatId = null; // Track current chat ID for model selection logic

function initializeChatlogFunctions(){
    initializeInputField();
    setSendBtnStatus(SendBtnStatus.SENDABLE);

    const scrollContainer = document.querySelector('.chatlog .scroll-container');

    if (scrollContainer) {
        scrollContainer.addEventListener('scroll', function() {
            isScrolling = true;
            clearTimeout(scrollTimeout); // Clear any existing timeout
            scrollTimeout = setTimeout(function() {
                isScrolling = false;
            }, 800); // After 800ms, user is considered not scrolling
        });
    }


    // Initialize Intersection Observer
    observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Mark the message as seen
                markAsSeen(entry.target);
                // Stop observing the message once it's seen
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.5 // Adjust threshold as needed
    });


}

function switchDyMainContent(contentID){


    const mainPanel = document.querySelector('.dy-main-panel');

    const contents = mainPanel.querySelectorAll('.dy-main-content');

    contents.forEach(content => {
        if(content.id === contentID){
            content.style.display = "flex";
        }
        else{
            content.style.display = "none";
        }
    });
}



function clearChatlog(){
    const content = document.querySelector('.trunk')
    while (content.firstChild) {
        content.removeChild(content.lastChild);
    }
}

function clearInput(){
    const input = document.querySelector('.input');
    input.querySelector('.attachments-list').querySelectorAll('.attachment').forEach(atch => {
        removeAtchFromList(atch.dataset.fileId, input.id);
    })
    input.querySelector('.input-field').value = '';
}


async function submitMessageToServer(requestObj, url){
    try {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify(requestObj)
        });

        const data = await response.json();
        if (data.success) {
            // Return entire response data, including conv_updated_at if present
            return {
                ...data.messageData,
                conv_updated_at: data.conv_updated_at
            };
            // updateMessageElement(messageElement, data.messageData);
        } else {
            // Handle unexpected response
            console.error('Unexpected response:', data);
        }
    } catch (error) {
        console.error('There was a problem with the operation:', error);
    }
}

async function requestMsgUpdate(messageObj, messageElement, url){
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify(messageObj)
        });

        const data = await response.json();
        if (data.success) {
            updateMessageElement(messageElement, data.messageData);
            
            // Update chat timestamp if available
            if (data.conv_updated_at && typeof updateChatTimestampFromServer === 'function') {
                updateChatTimestampFromServer(data.conv_updated_at);
            }
        } else {
            // Handle unexpected response
            console.error('Unexpected response:', data);
        }
    } catch (error) {
        console.error('There was a problem with the operation:', error);
    }
}


//#region SendButton Status

const SendBtnStatus = {
    SENDABLE: 'sendable',
    LOADING: 'loading',
    STOPPABLE: 'stoppable',
};
let sendbtnstat;

function setSendBtnStatus(status) {
    // Get all elements with the class 'send-btn'
    const sendBtns = document.querySelectorAll('#send-btn');

    // Iterate through each send button
    sendBtns.forEach((sendBtn) => {
        switch (status) {
            case SendBtnStatus.SENDABLE:
                sendBtn.querySelector('#send-icon').style.display = 'flex';
                sendBtn.querySelector('#loading-icon').style.display = 'none';
                sendBtn.querySelector('#stop-icon').style.display = 'none';
                break;
            case SendBtnStatus.LOADING:
                sendBtn.querySelector('#send-icon').style.display = 'none';
                sendBtn.querySelector('#loading-icon').style.display = 'flex';
                sendBtn.querySelector('#stop-icon').style.display = 'none';
                break;
            case SendBtnStatus.STOPPABLE:
                sendBtn.querySelector('#send-icon').style.display = 'none';
                sendBtn.querySelector('#loading-icon').style.display = 'none';
                sendBtn.querySelector('#stop-icon').style.display = 'flex';
                break;
            default:
                console.error("Invalid status");
                break;
        }
    });

    // Update the sendbtnstat variable
    // Update the sendbtnstat variable
    sendbtnstat = status;
}
function getSendBtnStat(){
    return sendbtnstat;
}




//#endregion

//#region EVENTS

function onThreadButtonEvent(btn){
    const thread = btn.closest('.message').querySelector('.thread');

    if(thread.classList.contains('visible')){
        thread.classList.remove('visible');
    }else{
        thread.classList.add('visible');
        
        // Apply role-based UI to thread input field
        if (typeof applyRoleBasedUI === 'function' && typeof activeRoom !== 'undefined' && activeRoom?.currentUserRole) {
            applyRoleBasedUI(activeRoom.currentUserRole);
        }
        
        // Only focus input if user can send messages
        if (activeRoom?.currentUserRole === 'admin' || activeRoom?.currentUserRole === 'editor') {
            thread.querySelector('.input-field')?.focus();
        }
    }
}

//#endregion


//#region THREAD FUNCTIONS

function selectActiveThread(sender){
    const thread = sender.closest('.thread');

    if(!thread){
        activeThreadIndex = 0;
        return
    }
    activeThreadIndex = Number(thread.id);
}

function findThreadWithID(threadId){
    return document.querySelector(`.thread#${CSS.escape(threadId)}`)
}

//#endregion



//#region Message

//CREATE MESSAGE ELEMENT AND PUT IT IN THE CHATLOG
function loadMessagesOnGUI(messages) {
    // Sorting messages by ID
    messages.sort((a, b) => {
        return +a.message_id - +b.message_id;
    });

    // Add all main messages to the chat log and observe them
    activeThreadIndex = 0;
    let threads = []
    messages.forEach(messageObj => {
        const addedMsg = addMessageToChatlog(messageObj, true);
        updateMessageElement(addedMsg, messageObj, true); // ✅ Set updateContent = true to populate dataset.rawContent


        // Observe unread messages
        if(addedMsg.dataset.read_stat === 'false'){
            observer.observe(addedMsg);
        }
        if(addedMsg.querySelector('.branch')){
            threads.push(addedMsg.querySelector('.branch'));
        }
    });
    threads.forEach(thread => {
        checkThreadUnreadMessages(thread);
    });
}


function checkThreadUnreadMessages(thread) {
    // Select unread message elements from the specified thread
    const unread_msgs = thread.querySelectorAll('.message[data-read_stat="false"]');
    // Find the closest ancestor message of the current thread
    const parentMsg = thread.closest('.message');

    // Show or hide the unread icon based on the number of unread messages
    if (unread_msgs.length !== 0) { // Corrected to 'length'
        parentMsg.querySelector('#unread-thread-icon').style.display = "block";
    } else {
        parentMsg.querySelector('#unread-thread-icon').style.display = "none";
    }
}

function flagRoomUnreadMessages(slug, active, isNewRoom = false){
    const selector = document.querySelector(`.selection-item[slug="${slug}"]`);
    
    // If element doesn't exist (user in different route), just return
    if (!selector) {
        return;
    }
    
    if(active){
        const flag = selector.querySelector('#unread-msg-flag');
        if (!flag) return; // Safety check
        
        flag.style.display = 'block';
        
        // Set color based on type
        if (isNewRoom) {
            flag.classList.add('new-room');
            flag.classList.remove('new-message');
        } else {
            flag.classList.add('new-message');
            flag.classList.remove('new-room');
        }
        
        const markAsReadBtn = document.getElementById('mark-as-read-btn');
        if (markAsReadBtn) {
            markAsReadBtn.removeAttribute("disabled");
        }
    }
    else{
        const flag = selector.querySelector('#unread-msg-flag');
        if (!flag) return; // Safety check
        
        flag.style.display = 'none';
        flag.classList.remove('new-room', 'new-message');
        
        const markAsReadBtn = document.getElementById('mark-as-read-btn');
        if (markAsReadBtn) {
            markAsReadBtn.setAttribute('disabled', true);
        }
    }
}

async function markAsSeen(element) {
    sendReadStatToServer(element.id);
    setTimeout(() => {
        setMessageStatusAsRead(element);

        if(document.querySelectorAll('.message[data-read_stat="false"]').length === 0){
            flagRoomUnreadMessages(activeRoom.slug, false);
            
            // Update hasUnreadMessages in rooms array
            if (typeof rooms !== 'undefined' && activeRoom) {
                const room = rooms.find(r => r.slug === activeRoom.slug);
                if (room) {
                    room.hasUnreadMessages = false;
                }
            }
            
            // Update sidebar badge
            if (typeof checkAndUpdateSidebarBadge === 'function') {
                checkAndUpdateSidebarBadge();
            }
        }

        if(element.id.split('.')[1] !== '000'){
            const thread = element.closest('.message').querySelector('.branch');
            if(thread){
                checkThreadUnreadMessages(thread);
            }
        }
    }, 3000);
}

function markAllAsRead(){
    const unread_msgs = document.querySelectorAll('.message[data-read_stat="false"]');

    unread_msgs.forEach(element => {
        observer.unobserve(element);
        setMessageStatusAsRead(element);
        sendReadStatToServer(element.id);
        if(element.id.split('.')[1] !== '000'){
            const thread = element.closest('.message').querySelector('.branch');
            if(thread){
                checkThreadUnreadMessages(thread);
            }
        }
    });
    flagRoomUnreadMessages(activeRoom.slug, false);
    
    // Update hasUnreadMessages in rooms array
    if (typeof rooms !== 'undefined' && activeRoom) {
        const room = rooms.find(r => r.slug === activeRoom.slug);
        if (room) {
            room.hasUnreadMessages = false;
        }
    }
    
    // Update sidebar badge
    if (typeof checkAndUpdateSidebarBadge === 'function') {
        checkAndUpdateSidebarBadge();
    }
}

async function sendReadStatToServer(message_id){
    url = `/req/room/readstat/${activeRoom.slug}`
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({'message_id': message_id,})
        });
        const data = await response.json();

        if (!data.success) {
            console.error('[sendReadStatToServer] Server returned success=false');
        }
    } catch (error) {
        console.error('[sendReadStatToServer] Error:', error);
    }
}

//#endregion


//#region Model
function selectModel(btn){
    const value = JSON.parse(btn.getAttribute('value'));
    const selectedModel = value;
    
    // Check if the selected model is incompatible with current filters (e.g., web_search)
    // If user clicks on a filtered-out model, automatically remove conflicting filters
    if (btn.classList.contains('filtered-out')) {
        const inputContainer = btn.closest('.input-container');
        const input = inputContainer ? inputContainer.querySelector('.input') : null;
        
        if (input) {
            // Check which filters are active and incompatible
            const websearchBtn = inputContainer.querySelector('#websearch-btn');
            
            // If web_search is active but model doesn't support it, deactivate it
            if (websearchBtn && websearchBtn.classList.contains('active') && !selectedModel.tools?.web_search) {
                websearchBtn.classList.remove('active', 'active-set');
                removeInputFilter(input.id, 'web_search');
            }
            
            // Add more filter checks here if needed (vision, file_upload, etc.)
        }
    }
    
    setModel(value.id);
    
    // Store model selection per chat when forceDefaultModel is enabled
    if (typeof forceDefaultModel !== 'undefined' && forceDefaultModel === true && currentChatId) {
        const chatModelKey = `chat_${currentChatId}_model`;
        localStorage.setItem(chatModelKey, value.id);
    }
}

function setModel(modelID = null, chatId = null){
    // Check if modelsList is empty or undefined
    if(!modelsList || modelsList.length === 0){
        console.error('ModelsList is empty or undefined. No models available.');
        activeModel = null;
        
        // Show user-friendly warning
        const modelLabel = document.querySelectorAll('.model-selector-label');
        modelLabel.forEach(label => {
            label.innerHTML = 'Kein Modell verfügbar';
            label.style.color = '#ff6b6b';
        });
        return;
    }
    
    let model;
    if(!modelID){
        // Determine model selection based on forceDefaultModel setting
        if (typeof forceDefaultModel !== 'undefined' && forceDefaultModel === true) {
            // Force default model mode: use chat-specific or default model
            if (chatId) {
                const chatModelKey = `chat_${chatId}_model`;
                const chatModel = localStorage.getItem(chatModelKey);
                if (chatModel) {
                    model = modelsList.find(m => m.id === chatModel);
                }
            }
            // If no chat-specific model, use default model
            if (!model) {
                model = modelsList.find(m => m.id === defaultModels?.default_model);
            }
        } else {
            // Legacy behavior: use globally defined model
            if(localStorage.getItem("definedModel")){
                model = modelsList.find(m => m.id === localStorage.getItem("definedModel"));
            }
            // if there is no defined model or the defined model is outdated or corrupted
            if(!model){
                model = modelsList.find(m => m.id === defaultModels?.default_model);
            }
        }
        
        // If still no model found, use the first available model
        if(!model && modelsList.length > 0){
            model = modelsList[0];
            console.warn('No default model configured. Using first available model:', model.id);
        }
    }
    else{
        model = modelsList.find(m => m.id === modelID);
    }
    
    // Check if model exists, if not, return early and show error
    if(!model){
        console.error('No valid model found. ModelsList:', modelsList, 'DefaultModels:', defaultModels);
        activeModel = null;
        
        // Show user-friendly warning
        const modelLabel = document.querySelectorAll('.model-selector-label');
        modelLabel.forEach(label => {
            label.innerHTML = 'Kein Modell verfügbar';
            label.style.color = '#ff6b6b';
        });
        return;
    }
    
    activeModel = model;
    
    // Update localStorage based on forceDefaultModel setting
    if (typeof forceDefaultModel !== 'undefined' && forceDefaultModel === true) {
        // Store per-chat model selection
        if (chatId) {
            const chatModelKey = `chat_${chatId}_model`;
            localStorage.setItem(chatModelKey, activeModel.id);
        }
    } else {
        // Legacy behavior: store globally
        localStorage.setItem("definedModel", activeModel.id);
    }

    //UI UPDATE...
    // Only update UI if activeModel is valid
    if(activeModel){
        const selectors = document.querySelectorAll('.model-selector');
        selectors.forEach(selector => {
            //if this is our target model selector
            if(JSON.parse(selector.getAttribute('value')).id === activeModel.id){
                selector.classList.add('active');

                const labels = document.querySelectorAll('.model-selector-label');

                labels.forEach(label => {
                    const inputContainer = label.closest('.input-container');
                    const websearchBtn = inputContainer ? inputContainer.querySelector('#websearch-btn') : null;

                    if (websearchBtn) {
                        // Check if the model supports web_search tool
                        // This supports both file-based and DB-based configs
                        const supportsWebSearch = activeModel.tools?.web_search === true;
                        const input = inputContainer.querySelector('.input');
                        
                        if (supportsWebSearch) {
                            // Model supports web search
                            // Only auto-enable if configured AND not already active
                            if (typeof webSearchAutoEnable !== 'undefined' && webSearchAutoEnable === true) {
                                if (!websearchBtn.classList.contains('active')) {
                                    websearchBtn.classList.add('active', 'active-set');
                                    if (input) {
                                        addInputFilter(input.id, 'web_search');
                                    }
                                }
                            }
                            // If auto-enable is false, keep current state (don't change anything)
                        } else {
                            // Model doesn't support web search - always deactivate it
                            if (websearchBtn.classList.contains('active')) {
                                websearchBtn.classList.remove('active', 'active-set');
                                if (input) {
                                    removeInputFilter(input.id, 'web_search');
                                }
                            }
                        }
                    }
                    label.innerHTML = activeModel.label;
                });
            }
            else{
                selector.classList.remove('active');
            }
        });
    }

}

// Change the Model to a websearch capable model (available models atm.: gemini-2.0-flash-exp)
function selectWebSearchModel(button) {
    const isActive = button.classList.contains('active');
    const input = button.parentElement.closest('.input-container').querySelector('.input');

    if (isActive) {
        button.classList.remove('active', 'active-set');
        removeInputFilter(input.id, 'web_search');

    } else {
        button.classList.add('active', 'active-set');
        addInputFilter(input.id, 'web_search');
    }
}

//#endregion



//#region Scrolling Controls
//scrolls to the end of the panel.
//if new message is send, it forces the panel to scroll down.
//if the current message is continuing to expand force expand is false.
//(if the user is trying to read the upper parts it wont jump back down.)
// Function to handle the auto-scroll behavior
let scrollTimeout; // To clear timeout when scrolling
function scrollToLast(forceScroll, targetElement = null) {
    const msgsPanel = document.querySelector('.chatlog .scroll-container');
    if (!msgsPanel) return;

    let scrollTargetPosition = msgsPanel.scrollHeight; // Default to end of chatlog

    if (targetElement) {
        // Check if the message is in a branch thread
        const thread = targetElement.closest('.thread');
        const isBranchMessage = thread && thread.classList.contains('branch');


        if (isBranchMessage) {
            // Ensure thread is visible
            if (!thread.classList.contains('visible')) {
                thread.classList.add('visible');
            }


            const messageHeight = targetElement.offsetHeight;
            // Calculate position based on thread position and the message's position in thread
            const messageTopOffset = targetElement.offsetTop + messageHeight - (window.innerHeight - 200);

            const threadTopOffset = thread.offsetTop;


            // Position should include parent message position plus the position within the thread
            scrollTargetPosition =  threadTopOffset + messageTopOffset;


            // Add some padding to ensure message is fully visible
            // scrollTargetPosition -= 100;
        } else {


            // Add some padding to ensure message is fully visible
            const messageHeight = targetElement.offsetHeight;

            // For main thread messages, just use their position
            scrollTargetPosition = targetElement.offsetTop + messageHeight;
            if (messageHeight > msgsPanel.clientHeight / 2) {
                // For tall messages, show the top
                scrollTargetPosition -= 10;
            } else {
                // For normal messages, center them better
                scrollTargetPosition -= Math.min(100, msgsPanel.clientHeight / 4);
            }
        }
    }

    const currentScroll = msgsPanel.scrollTop + msgsPanel.clientHeight;
    const scrollDistance = scrollTargetPosition - currentScroll;
    const scrollThreshold = 500; // Define a threshold distance

    if (!isScrolling && (forceScroll || scrollDistance < scrollThreshold)) {
        msgsPanel.scrollTo({
            top: scrollTargetPosition,
            left: 0,
            behavior: "smooth",
        });
    }
}

function scrollPanelToLast(panel){
    const panelHeight = panel.scrollHeight;
    const currentScroll = panel.scrollTop + panel.clientHeight;
    panel.scrollTo({
        top: panel.scrollHeight,
        left: 0,
    });
}

//#endregion
