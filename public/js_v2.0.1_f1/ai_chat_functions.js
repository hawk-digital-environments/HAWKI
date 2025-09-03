let convMessageTemplate;
let chatItemTemplate;
let activeConv;
let defaultPromt;
let chatlogElement;

function initializeAiChatModule(chatsObject){

    convMessageTemplate = document.getElementById('message-template');
    chatItemTemplate = document.getElementById('selection-item-template');
    chatlogElement = document.querySelector('.chatlog');

    defaultPromt = translation.Default_Prompt;

    const systemPromptFields = document.querySelectorAll('.system_prompt_field');
    systemPromptFields.forEach(field => {
        field.textContent = defaultPromt;
    });

    chats = chatsObject;
    chats.forEach(conv => {
        createChatItem(conv);
    });

    if(document.querySelector('.trunk').childElementCount == 0){
        chatlogElement.classList.add('start-state');
    }


    const input = document.getElementById('input-container');
    initFileUploader(input);



    initializeChatlogFunctions();

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
        'threadID': activeThreadIndex,
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
    thumbnails.forEach(thumbnails => {
        removeAtchFromList(atch.dataset.fileId, input.id);
    });

    const messageElement = addMessageToChatlog(submissionData);

    // create and add message element to chatlog.
    messageElement.dataset.rawMsg = submissionData.content.text;
    scrollToLast(true, messageElement);

    const webSearchActive = inputField.closest('.input-container').querySelector('#websearch-btn').classList.contains('active');
    const tools = {
        'web_search': webSearchActive
    }

    const msgAttributes = {
        'threadIndex': activeThreadIndex,
        'broadcasting': false,
        'slug': '',
        'stream': true,
        'model': activeModel.id,
        'tools': tools
    }

    buildRequestObjectForAiConv(msgAttributes);
}


async function buildRequestObjectForAiConv(msgAttributes, messageElement = null, isUpdate = false, isDone = null){
    // let messageElement;
    let msg = "";
    let messageObj;
    let metadata;

    // Start buildRequestObject processing
    buildRequestObject(msgAttributes, async (data, done) => {

        if(data){

            if(!msgAttributes['broadcasting'] && msgAttributes['stream']){
                setSendBtnStatus(SendBtnStatus.STOPPABLE);
            }

            const {messageText, groundingMetadata} = deconstContent(data.content);
            if(groundingMetadata != ""){
                metadata = groundingMetadata;
            }

            const content = messageText;
            msg += content;
            messageObj = data;
            messageObj.message_role = 'assistant';
            messageObj.content = content;
            messageObj.completion = data.isDone;
            messageObj.model = msgAttributes['model'];

            if (!messageElement) {
                initializeMessageFormating()
                messageElement = addMessageToChatlog(messageObj, false);
            }
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


            if(messageElement.querySelector('.think')){
                scrollPanelToLast(messageElement.querySelector('.think').querySelector('.content-container'));
            }

            scrollToLast(false, messageElement);
        }

        if(done){
            setSendBtnStatus(SendBtnStatus.SENDABLE);

            const cryptoContent = JSON.stringify({
                text: msg,
                groundingMetadata : metadata
            });

            const convKey = await keychainGet('aiConvKey');
            const cryptoMsg = await encryptWithSymKey(convKey, cryptoContent, false);

            messageObj.ciphertext = cryptoMsg.ciphertext;
            messageObj.iv = cryptoMsg.iv;
            messageObj.tag = cryptoMsg.tag;

            activateMessageControls(messageElement);

            const requestObj = {
                'isAi': true,
                'threadID': activeThreadIndex,
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

    return;
}


function startNewChat(){
    chatlogElement.classList.add('start-state');
    clearChatlog();
    clearInput();
    history.replaceState(null, '', `/chat`);

    const systemPromptFields = document.querySelectorAll('.system_prompt_field');
    systemPromptFields.forEach(field => {
        field.textContent = defaultPromt;
    });

    const lastActive = document.getElementById('chats-list').querySelector('.selection-item.active');
    if(lastActive){
        lastActive.classList.remove('active')
    }

    document.getElementById('input-container').focus();
}

function createChatItem(conv = null){

    const convItem = chatItemTemplate.content.cloneNode(true);
    const chatsList = document.getElementById('chats-list');
    const label = convItem.querySelector('.label');

    if(conv){
        convItem.querySelector('.selection-item').setAttribute('slug', conv.slug);
        label.textContent = conv.conv_name;
    }
    else{
        label.textContent = 'New Chat';
    }

    chatsList.insertBefore(convItem, chatsList.firstChild);

    return chatsList.firstElementChild;
}


async function generateChatName(firstMessage, convItem) {
    const requestObject = {
        payload: {
            model: systemModels.title_generator,
            stream: true,
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
                        text: firstMessage
                    }
                }
            ]
        },
        broadcast: false,
        threadIndex: '',
        slug: '',
    };

    return new Promise((resolve, reject) => {
        postData(requestObject)
        .then(response => {
            const convElement = convItem.querySelector('.label');
            let convName = ""; // Initialize to an empty string
            const onData = (data, done) => {
                if (data) {
                    convName += deconstContent(data.content).messageText;
                    convElement.innerText = convName;
                }
                if (done) {
                    resolve(convName); // Resolve the promise with convName
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
        // msg.content = [];
        msg.content.text = decryptedContent;
    };

    if(msgs.length > 0){
        chatlogElement.classList.remove('start-state');
    }
    else{
        chatlogElement.classList.add('start-state');
    }
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
                'X-CSRF-TOKEN': csrfToken
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

    const confirmed = await openModal(ModalType.WARNING , translation.Cnf_deleteConv);
    if (!confirmed) {
        return;
    }

    const url = `/req/conv/removeConv/${activeConv.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
        });
        const data = await response.json();

        if (data.success) {
            const listItem = document.querySelector(`.selection-item[slug="${activeConv.slug}"]`);
            const list = listItem.parentElement;
            listItem.remove();

            if(list.childElementCount > 0){
                loadConv(list.firstElementChild, null);
            }
            else{
                clearChatlog();
                clearInput();
                chatlogElement.classList.remove('active');
                chatlogElement.classList.add('start-state');

                history.replaceState(null, '', `/chat`);
            }

        } else {
            console.error('Conv removal was not successful!');
        }
    } catch (error) {
        console.error('Failed to remove conv!');
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
                'X-CSRF-TOKEN': csrfToken
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


//#endregion
