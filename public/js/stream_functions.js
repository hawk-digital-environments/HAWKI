let abortCtrl = new AbortController();


async function buildRequestObject(msgAttributes, onData, onError) {
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
            broadcast: msgAttributes['broadcasting'],
            stream: msgAttributes['stream'] || false,
            messages: msgs,
            tools: msgAttributes['metadata']?.tools ?? null,
            params: msgAttributes['metadata']?.params ?? null
        }
    };

    // POST request to initiate the AI stream or broadcast
    return postData(requestObject)
        .then(response => {
            // Check if broadcasting is true
            if (!msgAttributes['broadcasting']) {
                if (response === 'AbortError') {
                    onData('AbortError');
                }
                // pass stream callback (response) to processStream
                return processStream(response.body, onData);
            } else if (onData) {
                setTimeout(() => onData(null, true), 3000); // Simulate a delay for broadcasting
            }
        })
        .catch(error => {
            if (onError) {
                onError(error);
            } else {
                modalError(
                    window.__('legacy.stream.connectionErrorMessage'),
                    window.__('legacy.stream.connectionErrorTitle')
                );
            }
            if (onData) onData(null, true);
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
        throw new Error('An error occurred while fetching data.');
    }
}

/**
 * @param {ReadableStream} stream
 * @param onData
 * @return {Promise<void>}
 */
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

    // The message log is built by traversing the DOM and creating the message objects.
    // If the AI response failed, there is a chance that some message objects are invalid. We filter them out here.
    // Yes, this is fixing a symptom but since this whole section is gone in a month or two, I don't want to spend time fixing the root cause.
    return selection.filter(msg => {
        if (!msg.role || !msg.content || !msg.content.text) {
            console.warn('Invalid message object found and filtered out:', msg);
            return false;
        }
        return true;
    });
}


function createMsgObject(msg) {
    const role = msg.dataset.role === 'assistant' ? 'assistant' : 'user';
    let msgTxt = 'Something is wrong: The message body could not be extracted.';
    const id = msg.id;
    const messageData = window.oldUiMessageHistory.findMessageById(id);
    if (messageData) {
        msgTxt = messageData.content.text;
    }
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
    // @todo when migrating split this up into two prompts, one for chat and one for group chat, that way we don't need to do the if statement here.
    const improvementPrompt = window.getSystemPrompt('prompt_improvement');
    const aiHandle = window.getConfig().ai.handle;

    let extendedSystemPrompt = improvementPrompt;

    if (activeModule === 'chat') {
        extendedSystemPrompt += `
You are currently in a one-on-one chat with a user.
The prompt you are improving is a message that the user has sent to the AI.
`;
    } else {
        extendedSystemPrompt += `
You are currently in a group chat with multiple users and an AI model.
The prompt you are improving is a message that that one of the users has written to either others, or the AI.
If the message contains "${aiHandle}", it is directed to the AI, otherwise it is directed to other users.
If it contains "${aiHandle}", you MUST keep that marker in the improved message.
`;
    }

    extendedSystemPrompt += `
You MUST answer in the language with code: ${language}

IMPERATIVE: Notify the user about issues!
Your response will replace the original message! You must start your message with \`[NOT_IMPROVED]\` followed by a short explanation
of why the message could not be improved, if applicable. Then, you will provide the improved message. That way, the message will be
kept, but a warning will be shown to the user. If the message is improved, just return the improved message without anything else;
which will be shown directly to the user. If the message is already good, just return it as is. Do not add any extra text or explanation.
`;

    const requestObject = {
        payload: {
            model: window.getSystemModel('prompt_improvement').model_id,
            stream: true,
            messages: [
                {
                    role: 'system',
                    content: {
                        text: extendedSystemPrompt
                    }
                },
                {
                    role: 'user',
                    content: {
                        text: message
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

    const onData = (data, done) => {
        if (!done && data && data.content !== '') {
            result += data.content;
        }
        // If the model failed, we will show the user and return the original message so that the user can try again.
        if (done && result.includes('[NOT_IMPROVED]')) {
            window.oldUiBridge.triggerSendToast(result.replace('[NOT_IMPROVED]', '').trim(), 'error');
            result = message;
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
        throw error; // re-throw the error if you want the caller to handle it
    }
}


function convertMsgObjToLog(messages) {
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
