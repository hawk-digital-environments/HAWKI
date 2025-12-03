let abortCtrl = new AbortController();



function buildRequestObject(msgAttributes, onData) {
    // Check if activeModel is set
    if(!activeModel){
        console.error('No active model selected. Cannot build request.');
        alert('Bitte wählen Sie ein Modell aus, bevor Sie eine Nachricht senden.');
        return;
    }
    
    const msgs = createMessageLogForAI(msgAttributes['regenerationElement']);
    const isUpdate = msgAttributes['regenerationElement'] ? true : false;
    const msgID = msgAttributes['regenerationElement'] ? msgAttributes['regenerationElement'].id : null;

    const stream = activeModel.tools?.stream ? msgAttributes['stream'] : false;

    const requestObject = {
        broadcast: msgAttributes['broadcasting'],
        threadIndex: msgAttributes['threadIndex'],
        slug: msgAttributes['slug'],

        isUpdate: isUpdate,
        messageId: msgID,

        key: msgAttributes['key'],

        payload:{
            model: activeModel.id,
            stream: stream,
            messages: msgs,
            tools: msgAttributes['tools'],
        }
    };

    // POST request to initiate the AI stream or broadcast
    postData(requestObject)
    .then(response => {

        // Check if broadcasting is true
        if (!msgAttributes['broadcasting']) {
            if(stream){
                if(response === 'AbortError'){
                    onData?.('AbortError');
                }
                // pass stream callback (response) to processStream
                processStream(response.body, onData);
            }
            else{
                processResponse(response, onData);
            }
        } else {
            // For broadcasts (groupchat), call onData with done=true after successful POST
            // Only call if onData callback is provided
            if (onData) {
                onData(null, true);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Only call onData if callback is provided
        if (onData) {
            onData(null, true);
        }
    });
}


async function postData(data) {

    abortCtrl = new AbortController();
    const signal = abortCtrl.signal;

    const url = data.broadcast ? `/req/room/streamAI/${activeRoom.slug}` : '/req/streamAI'
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try{
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
            signal: signal
        });
                // Check for HTTP errors
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! Status: ${response.status}, ${errorText}`);
        }
        return response;

    } catch(error){
        throw error; // Re-throw the error so calling functions can handle it
    }
}

async function processStream(stream, onData) {
    if (!stream) {
        return;
    }

    const reader = stream.getReader();
    const textDecoder = new TextDecoder("utf-8");
    try{
        let buffer = "";
        while (true) {

            const { done, value } = await reader.read();

            if (done) {
                onData(null, true);
                return;
            }

            // Append the latest chunk to the buffer
            buffer += textDecoder.decode(value, { stream: true });
            // Split the buffer string on newline characters
            const parts = buffer.split("\n");
            // The last part might be incomplete, keep it in the buffer
            buffer = parts.pop();
            for (const part of parts) {
                if (part.trim()) {
                    try {
                        const data = JSON.parse(part);
                        
                        // Only log response.created events
                        if (data.content) {
                            try {
                                const content = JSON.parse(data.content);
                                if (content.auxiliaries) {
                                    const statusAux = content.auxiliaries.find(aux => aux.type === 'status');
                                    if (statusAux && statusAux.content) {
                                        const statusData = JSON.parse(statusAux.content);
                                        if (statusData.message === 'Model is starting...') {
                                            const frontendMicrotime = Date.now() / 1000;
                                            
                                            // Extract backend microtime from debug_timestamp auxiliary
                                            let backendMicrotime = null;
                                            let lag = null;
                                            
                                            const timestampAux = content.auxiliaries.find(aux => aux.type === 'debug_timestamp');
                                            if (timestampAux && timestampAux.content) {
                                                const timestampData = JSON.parse(timestampAux.content);
                                                backendMicrotime = timestampData.backend_microtime;
                                                lag = (frontendMicrotime - backendMicrotime).toFixed(3);
                                            }
                                            
                                        }
                                    }
                                }
                            } catch (e) {
                                // Content is not JSON or doesn't have expected structure
                            }
                        }
                        
                        //send back the data
                        if(data.isDone){
                            onData(data, true);
                            return;
                        }
                        onData(data, false);


                    } catch (error) {
                        console.error('Error parsing JSON:', error);
                    }
                }
            }

        }
    }
    catch (error) {
        if (error.name === 'AbortError') {
            
            // Send abort signal to onData callback WITHOUT message (Frontend derives label)
            onData({ 
                status: 'cancelled'
            }, true);
        } else {
            console.error('Error:', error);
            
            // Send error signal to onData callback WITHOUT message (Frontend derives label)
            onData({ 
                status: 'error'
            }, true);
        }
    }

}

async function processResponse(response, onData){

        const responseJson = await response.json();
        onData(responseJson, true);

}


function createMessageLogForAI(regenerationElement = null){
    const systemPromptContent = document.querySelector('#system_prompt_field').textContent;
    systemPrompt = {
        role: 'system',
        content:{
            text: systemPromptContent
        }
    }

    //create a selection array starting with systam prompt
    let selection = [systemPrompt];

    //find the last msg in the thread.
    //if thead is a comment thread last child is the input field -> get the prevous one...
    let lastMsgId;
    if(!regenerationElement){
        const activeThread = document.querySelector(`.thread#${CSS.escape(activeThreadIndex)}`);
        const lastMsg = activeThreadIndex === 0
                        ? activeThread.lastElementChild
                        : [...activeThread.querySelectorAll('.message')].pop();
        lastMsgId = lastMsg.id;
    }
    else{
        lastMsgId = regenerationElement.previousElementSibling.id;
    }

    let [lastWholeNum, lastDecimalNum] = lastMsgId.split('.').map(Number);
    //get last 100 messages
    // REF-> Message Memory Limit
    const messages = Array.from(document.querySelectorAll('.message')).slice(-20);

    //WHOLE CHAT LOG FOR MAIN and ONLY THE THREAD MSGS FOR THREAD
    messages.forEach(msg => {
        let [msgWholeNum, msgDeciNum] = msg.id.split('.').map(Number);

        if (lastDecimalNum === 0) {
            // Case: Last message ID has 0 decimal
            if (msgWholeNum <= lastWholeNum || (msgWholeNum === lastWholeNum && msgDeciNum <= lastDecimalNum)) {
                selection.push(createMsgObject(msg));
            }
        } else {
            // Case: Last message ID has a non-zero decimal
            if (msgWholeNum === lastWholeNum && msgDeciNum <= lastDecimalNum) {
                selection.push(createMsgObject(msg));
            }
        }
    });

    return selection;
}



function createMsgObject(msg){
    const role = msg.dataset.role === 'assistant' ? 'assistant' : 'user';
    const msgTextEl = msg.querySelector(".message-text");
    const msgTxt = msgTextEl ? msgTextEl.textContent : '';
    const filteredText = msgTxt ? detectMentioning(msgTxt).filteredText : '';

    const attachmentEls = msg.querySelectorAll('.attachment');
    const attachments = Array.from(attachmentEls, att => att.dataset.fileId);

    let msgObject;

    // For assistant messages with rawContent, use the full content (including auxiliaries)
    if (role === 'assistant' && msg.dataset.rawContent) {
        try {
            const rawContent = JSON.parse(msg.dataset.rawContent);
            
            msgObject = {
                role: role,
                content: {
                    text: rawContent.text || filteredText,
                    attachments: attachments
                }
            };
            
            // Include auxiliaries if present
            if (rawContent.auxiliaries && Array.isArray(rawContent.auxiliaries) && rawContent.auxiliaries.length > 0) {
                msgObject.content.auxiliaries = rawContent.auxiliaries;
            }
        } catch (e) {
            // Fallback to standard message object
            msgObject = {
                role: role,
                content:{
                    text: filteredText || '',
                    attachments: attachments
                }
            };
        }
    } else {
        // For user messages or assistant without rawContent
        msgObject = {
            role: role,
            content:{
                text: filteredText || '',
                attachments: attachments
            }
        };
    }

    return msgObject;
}




async function requestPromptImprovement(sender, type) {
    let prompt = '';
    let inputField;
    let message;

    if(type === 'input'){
        inputField = sender.closest('.input').querySelector('.input-field');
        prompt = inputField.value.trim();
        await smoothDeleteWords(inputField, 700)
    }
    if(type === 'message'){
        message = sender.closest('.message').querySelector('.message-content');
        prompt = message.innerText.trim();
    }

    const requestObject = {
        payload: {
            model: systemModels.prompt_improver,
            stream: true,
            messages: [
                {
                    role: "system",
                    content: {
                        text: translation.Improvement_Prompt
                    },
                },
                {
                    role: "user",
                    content: {
                        text: prompt
                    }
                }
            ]
        },
        broadcast: false,
        threadIndex: '', // Empty string is acceptable
        slug: '', // Empty string is acceptable
        assistantKey: 'prompt_improver',
    };

    let result = '';
    postData(requestObject)
    .then(response => {
        const onData = (data, done) => {
            if (data && data.content != "") {
                result += deconstContent(JSON.parse(data.content).text).messageText
                if(type === 'input'){
                    inputField.value = result.trim();
                    resizeInputField(inputField);
                }
                else{
                    message = sender.closest('.message').querySelector('.message-content');
                    message.innerText = result;
                }


            }
            if (done) {
            }
        };
        processStream(response.body, onData);
    })
    .catch((error) => {
    });
    // write a cool math formula

}



async function requestChatlogSummary(msgs = null) {
    // shift removes the first element which is system prompt
    if(!msgs){
        msgs = createMessageLogForAI();
    }

    const messages = [
        {
            role: "system",
            content: {
                text: translation.Summary_Prompt
            },
        },
        {
            role: "user",
            content: {
                text: JSON.stringify(msgs)
            }
        }
    ];

    const requestObject = {
        broadcast: false,
        threadIndex: '',
        slug: '',
        assistantKey: 'summarizer',
        payload:{
            model: systemModels.summarizer,
            stream: false,
            messages: messages
        }
    };
    try {
        const response = await postData(requestObject);
        return new Promise((resolve, reject) => {
            const onData = (data, done) => {
                if (done) {
                    resolve(deconstContent(JSON.parse(data.content).text).messageText);
                }
            };
            processResponse(response, onData);
        });
    } catch (error) {
        throw error; // re-throw the error if you want the caller to handle it
    }
}


function convertMsgObjToLog(messages){
    let list = [];
    for(let i = 0; i < messages.length; i++){
        msg = messages[i];
        const role = msg.message_role === 'assistant' ? 'assistant' : 'user';
        const msgTxt = msg.content.hasOwnProperty('text') ? msg.content.text : msg.content;
        const filteredText = detectMentioning(msgTxt).filteredText;
        const messageObject = {
            role: role,
            content:{
                text: filteredText,
            }
        }
        list.push(messageObject);
    }

    return list;
}
