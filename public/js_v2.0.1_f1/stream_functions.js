let abortCtrl = new AbortController();



function buildRequestObject(msgAttributes, onData) {
    const msgs = createMessageLogForAI(msgAttributes['regenerationElement']);
    const isUpdate = msgAttributes['regenerationElement'] ? true : false;
    const msgID = msgAttributes['regenerationElement'] ? msgAttributes['regenerationElement'].id : null;
    const requestModel = activeModel;

    const stream = requestModel.streamable ? msgAttributes['stream'] : false;

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
            messages: msgs
        }
    };

    // POST request to initiate the AI stream or broadcast
    postData(requestObject)
    .then(response => {

        // Check if broadcasting is true
        if (!msgAttributes['broadcasting']) {
            if(stream){
                if(response === 'AbortError'){
                    onData('AbortError');
                }
                // pass stream callback (response) to processStream
                processStream(response.body, onData);
            }
            else{
                processResponse(response, onData);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        onData(null, true); // Call onData with done=true if there's an error
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
                'X-CSRF-TOKEN': csrfToken
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
        console.log('Fetching Aborted'. error);
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
            console.log('Fetch aborted while reading response body stream.');
        } else {
            console.error('Error:', error);
        }
        onData(null, true);
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
    const messages = Array.from(document.querySelectorAll('.message')).slice(-100);

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
    const msgTxt = msg.querySelector(".message-text").textContent;
    // ToDo: insert search results
    // I'm not sure if the data from processStream() lands before getting plotted
    const filteredText = detectMentioning(msgTxt).filteredText;

    messageObject = {
        role: role,
        content:{
            text: filteredText,
        }
    }
    return messageObject;
}




async function requestPromptImprovement(sender) {
    const inputField = sender.closest('.input').querySelector('.input-field');
    const prompt = inputField.value.trim();

    await smoothDeleteWords(inputField, 700)

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
        slug: '' // Empty string is acceptable
    };

    let result = '';
    postData(requestObject)
    .then(response => {
        const onData = (data, done) => {
            if (data && data.content != "") {
                result += deconstContent(JSON.parse(data.content).text).messageText
                inputField.value = result.trim();
                resizeInputField(inputField);   
            }
            if (done) {
                // console.log('done');
            }
        };
        processStream(response.body, onData);
    })
    .catch((error) => {
        // console.log(error);
    });
    // write a cool math formula

}



async function requestChatlogSummery(msgs = null) {
    // shift removes the first element which is system prompt
    if(!msgs){
        msgs = createMessageLogForAI();
    }

    const messages = [
        {
            role: "system",
            content: {
                text: translation.Summery_Prompt
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
        // console.log(error);
        throw error; // re-throw the error if you want the caller to handle it
    }
}


function convertMsgObjToLog(messages){
    // console.log(messages);
    let list = [];
    for(let i = 0; i < messages.length; i++){
        msg = messages[i];
        const role = msg.message_role === 'assistant' ? 'assistant' : 'user';
        const msgTxt = msg.content;
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

