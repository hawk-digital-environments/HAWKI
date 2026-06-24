let convMessageTemplate;
let chatItemTemplate;
/** @type {OldUiConversation} */
let activeConv;
let chatlogElement;

function initializeAiChatModule(chatsObject) {
    convMessageTemplate = document.getElementById('message-template');
    chatItemTemplate = document.getElementById('selection-item-template');
    chatlogElement = document.querySelector('.chatlog');

    chats = chatsObject;
    chats.forEach(conv => {
        createChatItem(conv);
    });

    if (document.querySelector('.trunk').childElementCount == 0) {
        chatlogElement.classList.add('start-state');
    }

    initializeChatlogFunctions();

    window.oldUiBridge.onSendMessage('aiConv', async function (payload) {
        await sendMessageConv(payload);
    });
    window.oldUiBridge.onNewChat(() => {
        startNewChat();
    });
    window.oldUiBridge.onOpenChat((slug) => {
        loadConv(null, slug);
        onSidebarButtonDown('chat');
    });
    window.oldUiBridge.onActiveConversationSystemPromptUpdate(newPrompt => {
        if (!activeConv) {
            console.error('No active conversation to update system prompt for!');
            return;
        }
        updateAiChatInfo(activeConv.slug, newPrompt);
    });
    window.oldUiBridge.onRenameChat(async (slug, newName) => {
        updateAiChatInfo(slug, undefined, newName);
    });
    window.oldUiBridge.onDeleteChat((slug) => {
        requestDeleteConv(slug);
    });
}


// SEND MESSAGE FUNCTION
/**
 * @param {OldUiSendMessagePayload} payload
 * @return {Promise<void>}
 */
async function sendMessageConv(payload) {
    activeThreadIndex = 0;

    async function handleUploads() {
        if (payload.attachments.length > 0) {
            const attachmentsWithoutUuid = payload.attachments.filter(file => {
                return payload.status.getFileUuid(file) === null;
            });

            if (attachmentsWithoutUuid.length > 0) {
                attachmentsWithoutUuid.map(file => payload.status.clearFileIssue(file));
                await uploadAttachmentQueue(payload.status, attachmentsWithoutUuid, 'conv');
            }
        }
    }

    if (payload.mode.is === 'edit') {
        await handleUploads();

        if (payload.status.failed) {
            console.warn('Message has issues, not proceeding to edit.');
            return;
        }

        await confirmEditMessage(payload);
        return;
    }

    if (payload.mode.is === 'regen') {
        await regenerateMessage(payload);
        return;
    }

    if (payload.mode.is === 'thread') {
        activeThreadIndex = payload.mode.threadId;
    }

    let plainContent = {
        text: payload.message
    };

    let inputText = String(escapeHTML(payload.message));

    // if the chat is empty we need to initialize a new chatlog.
    if (document.querySelector('.trunk').childElementCount === 0) {
        await initNewConv(inputText, payload);
    }

    /// UPLOAD ATTACHMENTS
    await handleUploads();

    // If there are already issues, do not proceed to send the message.
    if (payload.status.failed) {
        return;
    }

    // Build attachments array for legacy format
    const attachments = payload.attachments
        .map(file => payload.status.getFileUuid(file))
        .filter(uuid => uuid !== null)
    ;

    /// Encrypt message
    const convKey = window.userKeychain.aiConvKey;
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
            'text': {
                'ciphertext': ciphertext,
                'iv': iv,
                'tag': tag
            },
            'attachments': attachments
        }
    };

    const submissionData = await submitMessageToServer(messageObj, `/req/conv/sendMessage/${activeConv.slug}`, plainContent);

    // Replace the original text
    submissionData.content.text = inputText;

    const messageElement = addMessageToChatlog(submissionData);

    // create and add message element to chatlog.
    messageElement.dataset.rawMsg = submissionData.content.text;
    scrollToLast(true, messageElement);

    payload.waitForResponse(async (response) => {
        const msgAttributes = {
            'threadIndex': activeThreadIndex,
            'broadcasting': false,
            'slug': '',
            'stream': true,
            'model': payload.model.model_id,
            'metadata': {
                'tools': payload.tools.map(tool => tool.name),
                'params': payload.parameters
            }
        };
        await buildRequestObjectForAiConv(response, msgAttributes);
        response.triggerReceived();
    });
}

/**
 * @param {SendMessageResponse} response
 * @param {Object} msgAttributes
 * @param {HTMLElement} messageElement
 * @param {boolean} isUpdate
 * @param {function} isDone
 * @return {Promise<void>}
 */
async function buildRequestObjectForAiConv(
    response,
    msgAttributes,
    messageElement = null,
    isUpdate = false,
    isDone = null
) {
    // let messageElement;
    let msg = '';
    let messageObj;
    let metadata;

    return new Promise((resolve, reject) => {
        // Start buildRequestObject processing
        buildRequestObject(msgAttributes, async (data, done) => {

            if (data) {
                response.triggerBodyChunk(data);
                const {messageText, groundingMetadata} = deconstContent(data.content);
                if (groundingMetadata !== '') {
                    metadata = groundingMetadata;
                }

                const content = messageText;
                msg += content;
                messageObj = data;
                messageObj.message_role = 'assistant';
                messageObj.content = content;
                messageObj.completion = data.isDone;
                messageObj.model = msgAttributes['model'];
                messageObj.tools = msgAttributes['metadata'].tools;
                messageObj.params = msgAttributes['metadata'].params;

                if (!messageElement) {
                    initializeMessageFormating();
                    messageElement = addMessageToChatlog(messageObj, false);
                }
                messageElement.dataset.rawMsg = msg;

                if (data.type === 'status') {
                    createStatusElement(data.status, messageElement);
                    return;
                }

                const msgTxtElement = messageElement.querySelector('.message-text');

                msgTxtElement.innerHTML = formatChunk(content, groundingMetadata);
                formatMathFormulas(msgTxtElement);
                formatHljs(messageElement);

                if (groundingMetadata &&
                    groundingMetadata !== '' &&
                    groundingMetadata.searchEntryPoint &&
                    groundingMetadata.searchEntryPoint.renderedContent) {

                    addGoogleRenderedContent(messageElement, groundingMetadata);
                } else {
                    if (messageElement.querySelector('.google-search')) {
                        messageElement.querySelector('.google-search').remove();
                    }
                }


                if (messageElement.querySelector('.think')) {
                    messageElement.querySelectorAll('.think').forEach(el => {
                        scrollPanelToLast(el.querySelector('.content-container'));
                    });
                }

                scrollToLast(false, messageElement);
            }

            if (done) {
                if (!messageElement) {
                    return;
                }

                const msgTxtElement = messageElement.querySelector('.message-text');
                msgTxtElement.innerHTML = formatMessage(messageElement.dataset.rawMsg, metadata);
                formatMathFormulas(msgTxtElement);
                formatHljs(messageElement);

                if (!messageObj) {
                    return;
                }

                const plainContent = {
                    text: msg,
                    groundingMetadata: metadata
                };
                const cryptoContent = JSON.stringify(plainContent);

                const convKey = window.userKeychain.aiConvKey;
                const cryptoMsg = await encryptWithSymKey(convKey, cryptoContent, false);

                messageObj.ciphertext = cryptoMsg.ciphertext;
                messageObj.iv = cryptoMsg.iv;
                messageObj.tag = cryptoMsg.tag;

                activateMessageControls(messageElement);

                const requestObj = {
                    'isAi': true,
                    'threadId': activeThreadIndex,
                    'content': {
                        'text': {
                            'ciphertext': messageObj.ciphertext,
                            'iv': messageObj.iv,
                            'tag': messageObj.tag
                        }
                    },
                    'metadata': {
                        'tools': messageObj.tools,
                        'params': msgAttributes['metadata']?.params ?? null
                    },

                    'model': messageObj.model,
                    'completion': messageObj.completion
                };

                if (isUpdate) {
                    requestObj.message_id = messageElement.id;
                    await requestMsgUpdate(requestObj, messageElement, `/req/conv/updateMessage/${activeConv.slug}`, plainContent);
                } else {
                    const submittedObj = await submitMessageToServer(
                        requestObj,
                        `/req/conv/sendMessage/${activeConv.slug}`,
                        plainContent
                    );

                    submittedObj.content = cryptoContent;
                    messageElement.dataset.rawMsg = msg;
                    // messageElement.dataset.groundingMetadata = metadata;
                    addGoogleRenderedContent(messageElement, metadata);
                    updateMessageElement(messageElement, submittedObj);
                }

                if (isDone) {
                    isDone(true);
                    response.triggerReceived();
                }

                resolve();
            }
        }, () => {
            response.triggerError(window.__('legacy.aiChat.streamProcessingError'));
            reject();
        });
    });
}


//#region CONVERSATION FUNCTIONS

/// Initializing a new conversation.
/**
 * @param {string} firstMessage
 * @param {OldUiSendMessagePayload} payload
 * @return {Promise<void>}
 */
async function initNewConv(firstMessage, payload) {

    // if start State panel is there remove it.
    chatlogElement.classList.remove('start-state');

    // empty chatlog
    clearChatlog();
    window.oldUiMessageHistory.clearConversation();

    history.replaceState(null, '', `/chat`);

    //create conversation button in the list.
    const convItem = createChatItem();
    convItem.classList.add('active');

    //create conversation name.
    const convName = await generateChatName(firstMessage);

    //submit conv to server.
    // after the server has accepted Submission conv data will be updated.
    const convData = await submitConvToServer(convName, payload);

    //assign Slug to conv Item.
    convItem.setAttribute('data-room-slug', convData.slug);
    convItem.setProps({
        slug: convData.slug,
        name: convName
    });
    //update URL
    history.replaceState(null, '', `/chat/${convData.slug}`);

    const convKey = window.userKeychain.aiConvKey;
    const systemPromptObj = JSON.parse(convData.system_prompt);
    convData.name = convName;
    convData.system_prompt = await decryptWithSymKey(convKey, systemPromptObj.ciphertext, systemPromptObj.iv, systemPromptObj.tag, false);

    //update active conv cache.
    activeConv = convData;
    window.oldUiMessageHistory.loadConversation('aiConv', convData);
}

function startNewChat() {
    chatlogElement.classList.add('start-state');
    clearChatlog();
    clearInput();
    window.oldUiMessageHistory.clearConversation();
    history.replaceState(null, '', `/chat`);

    const lastActive = document.getElementById('chats-list').querySelector('svelte-snippet[type="ChatSidebarButton"].active');
    if (lastActive) {
        lastActive.classList.remove('active');
    }
}

function createChatItem(conv = null) {

    /** @type {HTMLSvelteSnippetElement} */
    const snippet = document.createElement('svelte-snippet');
    snippet.setAttribute('type', 'ChatSidebarButton');

    const chatsList = document.getElementById('chats-list');

    if (conv) {
        snippet.setProps({
            slug: conv.slug,
            name: conv.conv_name,
            context: 'aiConv'
        });
        snippet.setAttribute('data-room-slug', conv.slug);
    } else {
        snippet.setProps({
            context: 'aiConv'
        });
    }

    chatsList.insertBefore(snippet, chatsList.firstChild);

    return snippet;
}


async function generateChatName(firstMessage) {
    const requestObject = {
        payload: {
            model: window.getSystemModel('title_generation').model_id,
            stream: true,
            messages: [
                {
                    role: 'system',
                    content: {
                        text: window.getSystemPrompt('title_generation')
                    }
                },
                {
                    role: 'user',
                    content: {
                        text: firstMessage
                    }
                }
            ]
        },
        broadcast: false,
        threadIndex: '',
        slug: ''
    };

    return new Promise((resolve, reject) => {
        postData(requestObject)
            .then(response => {
                let convName = ''; // Initialize to an empty string
                const onData = (data, done) => {
                    if (data) {
                        convName += deconstContent(data.content).messageText;
                    }
                    if (done) {
                        resolve(convName); // Resolve the promise with convName
                    }
                };
                return processStream(response.body, onData);
            })
            .catch(error => reject(error));
    });

}

/**
 * @param {string} convName
 * @param {OldUiSendMessagePayload} payload
 * @return {Promise<*>}
 */
async function submitConvToServer(convName, payload) {
    const convKey = window.userKeychain.aiConvKey;
    const cryptSystemPrompt = await encryptWithSymKey(convKey, payload.systemPrompt, false);
    const systemPromptStr = JSON.stringify({
        'ciphertext': cryptSystemPrompt.ciphertext,
        'iv': cryptSystemPrompt.iv,
        'tag': cryptSystemPrompt.tag
    });


    const requestObject = {
        conv_name: convName,
        system_prompt: systemPromptStr
    };

    try {
        const response = await fetch('/req/conv/createChat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
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


async function loadConv(btn = null, slug = null) {

    abortCtrl.abort();

    if (!btn && !slug) {
        return;
    }

    if (!slug) slug = btn.getAttribute('data-room-slug');
    if (!btn) btn = document.querySelector(`svelte-snippet[type="ChatSidebarButton"][data-room-slug="${slug}"]`);
    // switchDyMainContent('chat');

    const lastActive = document.getElementById('chats-list').querySelector('svelte-snippet[type="ChatSidebarButton"].active');
    if (lastActive) {
        lastActive.classList.remove('active');
    }
    btn.classList.add('active');


    switchDyMainContent('chat');

    history.replaceState(null, '', `/chat/${slug}`);

    const convData = await RequestConvContent(slug);

    if (!convData) {
        return;
    }

    clearChatlog();
    clearInput();
    activeConv = convData;

    const convKey = window.userKeychain.aiConvKey;
    const systemPromptObj = JSON.parse(convData.system_prompt);
    activeConv.system_prompt = await decryptWithSymKey(convKey, systemPromptObj.ciphertext, systemPromptObj.iv, systemPromptObj.tag, false);

    const msgs = convData.messages;
    for (const msg of msgs) {
        const decryptedContent = await decryptWithSymKey(convKey, msg.content.text.ciphertext, msg.content.text.iv, msg.content.text.tag);
        msg.content.text = decryptedContent;
    }

    if (msgs.length > 0) {
        chatlogElement.classList.remove('start-state');
    } else {
        chatlogElement.classList.add('start-state');
    }
    window.oldUiMessageHistory.loadConversation('aiConv', activeConv);
    window.oldUiBridge.triggerLoadSystemPrompt(activeConv.system_prompt);
    loadMessagesOnGUI(convData.messages);
    scrollToLast(true);
}


async function RequestConvContent(slug) {

    url = `/req/conv/${slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'

            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseJson = await response.json();
        if (responseJson.success) {
            return responseJson.data;
        } else {
            console.error(responseJson.message);
            return;
        }
    } catch (err) {
        console.error('Error fetching data:', err);
        throw err;
    }
}


async function requestDeleteConv(slug) {
    const url = `/req/conv/removeConv/${slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const data = await response.json();

        if (data.success) {
            const listItem = document.querySelector(`svelte-snippet[type="ChatSidebarButton"][data-room-slug="${slug}"]`);
            const list = listItem.parentElement;
            listItem.remove();

            if (list.childElementCount > 0) {
                await loadConv(list.firstElementChild, null);
            } else {
                clearChatlog();
                clearInput();
                window.oldUiMessageHistory.clearConversation();
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


async function deleteMessage(btn) {
    const confirmed = await openModal(ModalType.WARNING, __('Cnf_deleteConv'));
    if (!confirmed) {
        return;
    }

    const url = `/req/conv/message/delete/${activeConv.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const message = btn.closest('.message');

    window.oldUiMessageHistory.removeMessageFromConversation(message.id);

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                'message_id': message.id
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

async function updateAiChatInfo(slug, systemPrompt, chatName) {
    const payload = {};
    /** @type {Array<() => void>} */
    const updaters = [];

    if (systemPrompt) {
        const convKey = window.userKeychain.aiConvKey;
        const cryptSystemPrompt = await encryptWithSymKey(convKey, systemPrompt, false);
        payload.system_prompt = JSON.stringify({
            'ciphertext': cryptSystemPrompt.ciphertext,
            'iv': cryptSystemPrompt.iv,
            'tag': cryptSystemPrompt.tag
        });
        if (activeConv && activeConv.slug === slug) {
            updaters.push(() => activeConv.system_prompt = systemPrompt);
            updaters.push(() => window.oldUiMessageHistory.updateConversation({system_prompt: systemPrompt}));
            updaters.push(() => window.oldUiBridge.triggerLoadSystemPrompt(systemPrompt));
        }
    }

    if (chatName) {
        payload.conv_name = chatName;
        if (activeConv && activeConv.slug === slug) {
            updaters.push(() => activeConv.name = chatName);
            updaters.push(() => window.oldUiMessageHistory.updateConversation({name: chatName}));
        }
    }

    const url = `/req/conv/updateInfo/${slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (data.success) {
            updaters.forEach(updater => updater());
        } else {
            console.error('Update not successfull');
        }
    } catch (error) {
        console.error('Failed to Update System Prompt!');
    }

}
