let abortCtrl = new AbortController();


function buildRequestObject(msgAttributes, onData, onError) {
    const msgs = createMessageLogForAI(msgAttributes['regenerationElement']);
    const isUpdate = msgAttributes['regenerationElement'] ? true : false;
    const msgID = msgAttributes['regenerationElement'] ? msgAttributes['regenerationElement'].id : null;
    const requestObject = {
        broadcast: msgAttributes['broadcasting'],
        threadIndex: msgAttributes['threadIndex'],
        slug: msgAttributes['slug'],

        isUpdate: isUpdate,
        messageId: msgID,

        key: msgAttributes['key'],

        payload: {
            model: msgAttributes.model ?? activeModel.modelId,
            stream: true,
            messages: msgs,
            tools: msgAttributes['metadata']?.tools ?? null,
            params: msgAttributes['metadata']?.params ?? null
        }
    };

    // POST request to initiate the AI stream or broadcast
    postData(requestObject)
        .then(response => {
            // Check if broadcasting is true
            if (!msgAttributes['broadcasting']) {
                if (response === 'AbortError') {
                    onData('AbortError');
                }
                // pass stream callback (response) to processStream
                processStream(response.body, onData);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (onData) onData(null, true);
            if (onError) {
                onError(error);
            } else {
                modalError(
                    window.__('legacy.stream.connectionErrorMessage'),
                    window.__('legacy.stream.connectionErrorTitle')
                );
            }
        });
}


async function postData(data) {

    abortCtrl = new AbortController();
    const signal = abortCtrl.signal;
    window.oldUiBridge.bindAbortController(abortCtrl);

    const url = data.broadcast ? `/req/room/streamAI/${activeRoom.slug}` : '/req/streamAI';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
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

    } catch (error) {
        console.log('Error while posting data', data, 'resulted in', error);
        throw new Error('An error occurred while fetching data.');
    }
}

async function processStream(stream, onData) {
    if (!stream) {
        return;
    }

    const reader = stream.getReader();
    const textDecoder = new TextDecoder('utf-8');
    try {
        let buffer = '';
        while (true) {

            const {done, value} = await reader.read();

            if (done) {
                onData(null, true);
                return;
            }


            // Append the latest chunk to the buffer
            buffer += textDecoder.decode(value, {stream: true});
            // Split the buffer string on newline characters
            const parts = buffer.split('\n');
            // The last part might be incomplete, keep it in the buffer
            buffer = parts.pop();
            for (const part of parts) {
                if (part.trim()) {
                    const data = JSON.parse(part);
                    //send back the data
                    if (data.isDone) {
                        onData(data, true);
                        return;
                    }
                    onData(data, false);
                }
            }

        }
    } catch (error) {
        if (error.name === 'AbortError') {
            console.log('Fetch aborted while reading response body stream.');
        } else {
            throw error; // re-throw the error if it's not an AbortError
        }
        onData(null, true);
    }

}

async function processResponse(response, onData) {

    const responseJson = await response.json();
    onData(responseJson, true);

}


function createMessageLogForAI(regenerationElement = null) {
    const systemPromptContent = window.oldUiMessageHistory.systemPrompt || window.getSystemPrompt('default');
    systemPrompt = {
        role: 'system',
        content: {
            text: systemPromptContent
        }
    };

    //create a selection array starting with systam prompt
    let selection = [systemPrompt];

    //find the last msg in the thread.
    //if thead is a comment thread last child is the input field -> get the prevous one...
    let lastMsgId;
    if (!regenerationElement) {
        const activeThread = document.querySelector(`.thread#${CSS.escape(activeThreadIndex)}`);
        const lastMsg = activeThreadIndex === 0
            ? activeThread.lastElementChild
            : [...activeThread.querySelectorAll('.message')].pop();
        lastMsgId = lastMsg.id;
    } else {
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


function createMsgObject(msg) {
    const role = msg.dataset.role === 'assistant' ? 'assistant' : 'user';
    const msgTxt = msg.querySelector('.message-text').textContent;
    const filteredText = detectMentioning(msgTxt).filteredText;

    const attachmentEls = msg.querySelectorAll('.attachment');
    const attachments = Array.from(attachmentEls, att => att.dataset.fileId);

    return {
        role: role,
        content: {
            text: filteredText,
            attachments: attachments
        }
    };
}


async function requestPromptImprovement(message, chatSystemPrompt) {
    const language = window.getConnection().locale;

    let prompt = `
This is the message you should improve:
[[[MESSAGE_START]]]
${message}
[[[MESSAGE_END]]]

The current chat contains the following system prompt, use this only as context for improving the message!
[[[SYSTEM_PROMPT_START]]]
${chatSystemPrompt}
[[[SYSTEM_PROMPT_END]]]

You MUST answer in the language with code: ${language}
    `;

    const requestObject = {
        payload: {
            model: window.getSystemModel('prompt_improvement').model_id,
            stream: true,
            messages: [
                {
                    role: 'system',
                    content: {
                        text: __('Improvement_Prompt')
                    }
                },
                {
                    role: 'user',
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
    const response = await postData(requestObject);


    const onData = (data) => {
        if (data && data.content !== '') {
            result += deconstContent(JSON.parse(data.content).text).messageText;
        }
    };

    await processStream(response.body, onData);

    return result;
}


async function requestChatlogSummery(msgs = null) {
    // shift removes the first element which is system prompt
    if (!msgs) {
        msgs = createMessageLogForAI();
    }

    const messages = [
        {
            role: 'system',
            content: {
                text: window.getSystemPrompt('summary')
            }
        },
        {
            role: 'user',
            content: {
                text: JSON.stringify(msgs)
            }
        }
    ];

    const requestObject = {
        broadcast: false,
        threadIndex: '',
        slug: '',
        payload: {
            model: window.getSystemModel('summary').model_id,
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


function convertMsgObjToLog(messages) {
    // console.log(messages);
    let list = [];
    for (let i = 0; i < messages.length; i++) {
        msg = messages[i];
        const role = msg.message_role === 'assistant' ? 'assistant' : 'user';
        const msgTxt = msg.content.hasOwnProperty('text') ? msg.content.text : msg.content;
        const filteredText = detectMentioning(msgTxt).filteredText;
        const messageObject = {
            role: role,
            content: {
                text: filteredText
            }
        };
        list.push(messageObject);
    }

    return list;
}
