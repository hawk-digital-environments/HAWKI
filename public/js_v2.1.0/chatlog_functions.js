
let activeThreadIndex = 0;
let activeModel;
let isScrolling = false; // Flag to track if the user is scrolling
let observer;
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
            return data.messageData;
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
        thread.querySelector('.input-field').focus();
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
        updateMessageElement(addedMsg, messageObj);


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

function flagRoomUnreadMessages(slug, active){
    const selector = document.querySelector(`.selection-item[slug="${slug}"`)
    if(active){
        selector.querySelector('#unread-msg-flag').style.display = 'block'
        document.getElementById('mark-as-read-btn').removeAttribute("disabled");
    }
    else{
        selector.querySelector('#unread-msg-flag').style.display = 'none';
        document.getElementById('mark-as-read-btn').setAttribute('disabled', true);
    }
}

async function markAsSeen(element) {
    sendReadStatToServer(element.id);
    setTimeout(() => {
        setMessageStatusAsRead(element);

        if(document.querySelectorAll('.message[data-read_stat="false"]').length === 0){
            flagRoomUnreadMessages(activeRoom.slug, false);
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
            console.error('failed to inform server');
        }
    } catch (error) {
        console.error('failed to inform server');
    }
}

//#endregion


//#region Model
function selectModel(btn){
    const value = JSON.parse(btn.getAttribute('value'));
    setModel(value.id);
}
function setModel(modelID = null){
    let model;
    if(!modelID){
        if(localStorage.getItem("definedModel")){

            model = modelsList.find(m => m.id === localStorage.getItem("definedModel"));
        }
        // if there is no defined model
        // if there is no defined model
        // or the defined model is outdated or cruppted
        if(!model){
            model = modelsList.find(m => m.id === defaultModels.default_model);
        }
    }
    else{
        model = modelsList.find(m => m.id === modelID);
    }
    activeModel = model;
    localStorage.setItem("definedModel", activeModel.id);

    // If the selected model is a web search model, enable the web search button



    // if (activeModel.id === defaultModels.default_web_search_model) {
    //             console.log('web search model');
    //     websearchBtns.forEach(btn => {
    //         webSearchButton.classList.add('active');
    //     });

    // } else {
    //     console.log('not web search model');

    //     const webSearchButton = document.querySelector('#websearch-btn').parentElement;
    //     if (webSearchButton) {
    //         webSearchButton.classList.remove('active');
    //     }
    // }

    //UI UPDATE...
    const selectors = document.querySelectorAll('.model-selector');
    selectors.forEach(selector => {
        //if this is our target model selector
        //if this is our target model selector
        if(JSON.parse(selector.getAttribute('value')).id === activeModel.id){
            selector.classList.add('active');

            const labels = document.querySelectorAll('.model-selector-label');

            labels.forEach(label => {

                if (activeModel.id === defaultModels.default_web_search_model){
                    label.closest('.input-container').querySelector('#websearch-btn').classList.add('active');
                }
                else{
                    label.closest('.input-container').querySelector('#websearch-btn').classList.remove('active');
                }
                label.innerHTML = activeModel.label;
            });
        }
        else{
            selector.classList.remove('active');
        }
    });

}

// Change the Model to a websearch capable model (available models atm.: gemini-2.0-flash-exp)
function selectWebSearchModel(button) {
    const isActive = button.classList.contains('active');
    const input = button.parentElement.closest('.input-container').querySelector('.input');

    if (isActive) {
        button.classList.remove('active');
        removeInputFilter(input.id, 'web_search');

    } else {
        button.classList.add('active');
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


