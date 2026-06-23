function addMessageToChatlog(messageObj, isFromServer = false) {
    if (typeof messageObj.content === 'string' && isValidJson(messageObj.content)) {
        messageObj.content = JSON.parse(messageObj.content);
    }
    if (typeof messageObj.content.text === 'string' && isValidJson(messageObj.content.text)) {
        messageObj.content.text = JSON.parse(messageObj.content.text);
    }

    const {messageText, groundingMetadata} = deconstContent(messageObj.content.text);

    /// CLONE
    // clone message element
    const messageTemp = document.getElementById('message-template');
    const messageClone = messageTemp.content.cloneNode(true);

    //Get messageElement
    const messageElement = messageClone.querySelector('.message');

    /// DATASET & ID
    // set dataset attributes
    messageElement.dataset.role = messageObj.message_role;
    messageElement.dataset.rawMsg = messageText;
    if (messageObj.metadata) {
        const meta = messageObj.metadata;
        messageElement.dataset.tools = JSON.stringify(meta.tools ?? []);
        messageElement.dataset.params = JSON.stringify(meta.params ?? {});
    }
    // messageElement.dataset.groundingMetadata = JSON.stringify(groundingMetadata);

    //if date and time is confirmed from the server add them
    if (messageObj.created_at) messageElement.dataset.created_at = messageObj.created_at;

    // set id (whole . deci format)
    if (messageObj.message_id) {
        messageElement.id = messageObj.message_id;
    }

    /// CLASSES & AVATARS
    // add classes AI ME MEMBER to the element
    if (messageObj.message_role === 'assistant') {
        const aiConfig = window.getConfig().ai;
        messageElement.classList.add('AI');
        messageElement.querySelector('.user-inits').remove();
        if (aiConfig && aiConfig.hawkiUserAvatar) {
            messageElement.querySelector('.icon-img').src = window.buildStorageFileUrl(aiConfig.hawkiUserAvatar);
        }
    } else {
        const userInfo = window.getAuthenticatedConnection().userinfo;
        if (messageObj.author.name && messageObj.author.username === userInfo.username) {
            messageElement.classList.add('me');
            if (userInfo.avatar) {
                messageElement.querySelector('.user-inits').style.display = 'none';
                messageElement.querySelector('.icon-img').style.display = 'block';
                messageElement.querySelector('.icon-img').src = window.buildStorageFileUrl(userInfo.avatar);
            } else {
                messageElement.querySelector('.icon-img').style.display = 'none';
                messageElement.querySelector('.user-inits').style.display = 'block';
                const userInitials = messageObj.author.name.slice(0, 1).toUpperCase();
                messageElement.querySelector('.user-inits').innerText = userInitials;
            }
        } else {
            messageElement.classList.add('member');
            const hasAvatar = !!messageObj.author.avatar_url;
            messageElement.querySelector('.icon-img').style.display = hasAvatar ? 'block' : 'none';
            messageElement.querySelector('.user-inits').style.display = hasAvatar ? 'none' : 'block';

            // assign icon to message.
            if (!hasAvatar) {
                messageElement.querySelector('.icon-img').style.display = 'none';
                messageElement.querySelector('.user-inits').style.display = 'block';
                const userInitials = messageObj.author.name.slice(0, 1).toUpperCase();
                messageElement.querySelector('.user-inits').innerText = userInitials;
            } else {
                messageElement.querySelector('.icon-img').style.display = 'block';
                messageElement.querySelector('.user-inits').style.display = 'none';
                messageElement.querySelector('.icon-img').src = messageObj.author.avatar_url;
            }
        }
    }

    /// Set Author Name
    if (messageObj.model && messageObj.message_role === 'assistant') {
        const model = window.getAiModel(messageObj.model);
        messageElement.querySelector('.message-author').innerHTML =
            model ?
                `<span>${messageObj.author.username} </span><span class="message-author-model">(${model.label})</span>` :
                `<span>${messageObj.author.username} </span><span class="message-author-model">(${messageObj.model}) !!! Obsolete !!!</span>`;

        messageElement.dataset.model = messageObj.model;
        messageElement.dataset.author = messageObj.author.username;
    } else {

        let header;
        if (!messageObj.author.isRemoved || messageObj.author.isRemoved === 0) {
            header = messageObj.author.name;
        } else {
            header = `<span>${messageObj.author.name}</span> <span class="message-author-model">(${__('RemovedMember')})</span>`;
        }

        messageElement.querySelector('.message-author').innerHTML = header;
        messageElement.dataset.author = messageObj.author.name;
    }

    /// INDEXING & THREAD
    // if message is from the user, it still doesn't have an assigned ID from the server.
    if (isFromServer) {
        // deconstruct message id
        let [msgWholeNum, msgDecimalNum] = messageObj.message_id.split('.').map(Number);

        // if decimal is 0 the message belongs to trunk
        if (msgDecimalNum === 0) {
            threadIndex = 0;
        } else {
            threadIndex = msgWholeNum;
        }
    } else {
        threadIndex = activeThreadIndex;
    }

    let activeThread = findThreadWithID(threadIndex);


    /// DATE & TIME
    // if message has a date it's already submitted and comes from the server.
    // if not, it has been created by user and does not have a date stamp -> today is the date
    let msgDate;
    if (messageObj.created_at) {
        msgDate = messageObj.created_at.split('+')[0];
    } else {
        todayDate = new Date();
        msgDate = `${todayDate.getFullYear()}-${(todayDate.getMonth() + 1).toString().padStart(2, '0')}-${todayDate.getDate().toString().padStart(2, '0')}`;
    }
    setDateSpan(activeThread, msgDate);


    ///ATTACHMENTS
    if (messageObj.content.attachments && messageObj.content.attachments.length != 0) {

        const attachmentContainer = messageElement.querySelector('.attachments');

        messageObj.content.attachments.forEach(attachment => {

            const thumbnail = createAttachmentThumbnail(attachment.fileData, 'message');
            // Add to file preview container
            attachmentContainer.appendChild(thumbnail);
        });
    }

    /// CONTENT
    // Setup Message Content
    const msgTxtElement = messageElement.querySelector('.message-text');

    if (!messageElement.classList.contains('AI')) {
        let processedContent = detectMentioning(messageText).modifiedText;
        processedContent = convertHyperlinksToLinks(processedContent);
        processedContent = wrapLinksInBlocks(processedContent);
        msgTxtElement.innerHTML = processedContent;
    } else {
        let markdownProcessed = formatMessage(messageText, groundingMetadata);
        msgTxtElement.innerHTML = markdownProcessed;
        formatMathFormulas(msgTxtElement);
        formatHljs(messageElement);

        if (groundingMetadata &&
            groundingMetadata != '' &&
            groundingMetadata.searchEntryPoint &&
            groundingMetadata.searchEntryPoint.renderedContent) {

            addGoogleRenderedContent(messageElement, groundingMetadata);
        } else {
            if (messageElement.querySelector('.google-search')) {
                messageElement.querySelector('.google-search').remove();
            }
        }
    }


    /// check for completion status. ONLY FOR CONV MESSAGES FROM AI.
    if (messageObj.hasOwnProperty('completion')) {
        if (messageObj.completion === 0 && messageElement.querySelector('#incomplete-msg-icon')) {
            messageElement.querySelector('#incomplete-msg-icon').style.display = 'flex';
        } else {
            messageElement.querySelector('#incomplete-msg-icon').style.display = 'none';
        }
    }
    /// READ STATUS
    // if the read status exists in the data
    if (messageElement.classList.contains('me') && messageElement.querySelector('#unread-message-icon')) {
        messageElement.querySelector('#unread-message-icon').style.display = 'none';
    } else if ('read_status' in messageObj) {
        messageElement.dataset.read_stat = messageObj.read_status;

        if (messageObj.read_status) {
            setMessageStatusAsRead(messageElement);
        }
    }


    /// INSERT IN CHATLOG
    // insert into target thread
    if (threadIndex === 0) {
        // if message is a main message then it needs a thread inside
        // clone and insert thread template in message.
        const threadTemplate = document.getElementById('thread-template');
        const threadElement = threadTemplate.content.cloneNode(true);
        threadDiv = threadElement.querySelector('.thread');
        threadDiv.classList.add('branch');

        if (messageObj.message_id) {
            threadDiv.id = messageObj.message_id.split('.')[0];
            // threadDiv.querySelector('.input').id = threadDiv.id;
        }

        messageElement.appendChild(threadDiv);
        activeThread.appendChild(messageElement);

    } else {
        const branchInput = activeThread.querySelector('div[data-id="thread-active-indicator"]');
        messageElement.querySelector('#thread-btn').remove();
        const messageChildrenCount = Array.from(activeThread.children).filter(child => child.classList.contains('message')).length + 1;
        const cmtCount = activeThread.closest('.message').querySelector('#comment-count');
        cmtCount.style.display = 'block';
        cmtCount.innerHTML = messageChildrenCount;

        activeThread.insertBefore(messageElement, branchInput);
    }

    formatHljs(messageElement);
    return messageElement;
}


function updateMessageElement(messageElement, messageObj, updateContent = false, attachmentUpdate = null) {

    messageElement.id = messageObj.message_id;
    if (messageObj.metadata) {
        const meta = messageObj.metadata;
        messageElement.dataset.tools = JSON.stringify(meta.tools ?? []);
        messageElement.dataset.params = JSON.stringify(meta.params ?? {});
    }

    if (messageElement.querySelector('.thread')) {
        messageElement.querySelector('.thread').id = messageObj.message_id.split('.')[0];
        // messageElement.querySelector('.input').id = messageObj.message_id.split('.')[0];
    }

    if (messageElement.classList.contains('me')) {
        messageElement.querySelector('#sent-status-icon').style.display = 'flex';
    }

    if (messageObj.hasOwnProperty('completion')) {
        if ((messageObj.completion === 0 || messageObj.completion === false) && messageElement.querySelector('#incomplete-msg-icon')) {
            messageElement.querySelector('#incomplete-msg-icon').style.display = 'flex';
        } else {
            messageElement.querySelector('#incomplete-msg-icon').style.display = 'none';
        }
    }

    messageElement.dataset.role = messageObj.message_role;
    const msgTxtElement = messageElement.querySelector('.message-text');

    if (messageElement.classList.contains('AI')) {
        const username = messageElement.dataset.author;
        const model = window.getAiModel(messageObj.model);
        messageElement.querySelector('.message-author').innerHTML =
            model ?
                `<span>${username} </span><span class="message-author-model">(${model.label})</span>` :
                `<span>${username} </span><span class="message-author-model">(${messageObj.model}) !!! Obsolete !!!</span>`;
        messageElement.dataset.model = messageObj.model;
    }

    ///ATTACHMENTS
    if (Array.isArray(attachmentUpdate) && attachmentUpdate.length != 0) {

        const attachmentContainer = messageElement.querySelector('.attachments');
        attachmentContainer.innerHTML = '';

        attachmentUpdate.forEach(attachment => {

            const thumbnail = createAttachmentThumbnail(attachment.fileData, 'message');
            // Add to file preview container
            attachmentContainer.appendChild(thumbnail);
        });
    }

    if (updateContent) {
        const {messageText, groundingMetadata} = deconstContent(messageObj.content.text);

        messageElement.dataset.rawMsg = messageText;
        if (messageObj.message_role === 'user') {
            const filteredContent = detectMentioning(messageText);
            let processedContent = filteredContent.modifiedText;
            processedContent = convertHyperlinksToLinks(processedContent);
            processedContent = wrapLinksInBlocks(processedContent);
            msgTxtElement.innerHTML = processedContent;
        } else {

            let markdownProcessed = formatMessage(messageText, groundingMetadata);
            msgTxtElement.innerHTML = markdownProcessed;
            formatMathFormulas(msgTxtElement);
            formatHljs(messageElement);
            if (groundingMetadata &&
                groundingMetadata != '' &&
                groundingMetadata.searchEntryPoint &&
                groundingMetadata.searchEntryPoint.renderedContent) {

                addGoogleRenderedContent(messageElement, groundingMetadata);
            } else {
                if (messageElement.querySelector('.google-search')) {
                    messageElement.querySelector('.google-search').remove();
                }
            }
        }

        // if the read status exists in the data
        if (messageElement.classList.contains('me') && messageElement.querySelector('#unread-message-icon')) {
            messageElement.querySelector('#unread-message-icon').style.display = 'none';
        } else if ('read_status' in messageObj) {
            messageElement.dataset.read_stat = messageObj.read_status;

            if (messageObj.read_status) {
                setMessageStatusAsRead(messageElement);
            }
        }

    }

    //SET MESSAGE TIME AND EDIT FLAG
    const time = messageObj.created_at.split('+')[1];
    const timeStamp = messageObj.created_at !== messageObj.updated_at ? `edited: ${time}` : `${time}`;
    messageElement.querySelector('#msg-timestamp').innerText = timeStamp;

    activateMessageControls(messageElement);
}


function setDateSpan(activeThread, msgDate, formatDay = true) {

    // Determine if msgDate is today or yesterday
    const msgDateObj = new Date(msgDate);
    let dateText;

    if (formatDay) {
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        if (msgDateObj.toDateString() === today.toDateString()) {
            dateText = __('Today');
        } else if (msgDateObj.toDateString() === yesterday.toDateString()) {
            dateText = __('Yesterday');
        } else {
            const formattedDate = `${msgDateObj.getDate()}.${msgDateObj.getMonth() + 1}.${msgDateObj.getFullYear()}`;
            dateText = formattedDate;
        }
    } else {
        const formattedDate = `${msgDateObj.getDate()}.${msgDateObj.getMonth() + 1}.${msgDateObj.getFullYear()}`;
        dateText = formattedDate;
    }

    // Find the last date span in the thread
    const lastThreadDateSpan = activeThread.querySelector('span.date_span:last-of-type');
    const lastDate = lastThreadDateSpan ? lastThreadDateSpan.getAttribute('data-date') : null;

    // Initialize variable to keep track of the last found date_span
    let lastTrunkDate = null;
    //if in a banch then find out the last time span in the main thread
    if (activeThread.classList.contains('branch')) {
        const parentMsg = activeThread.closest('.message');
        // Traverse previous siblings
        let prevSibling = parentMsg.previousElementSibling;
        while (!lastTrunkDate) {
            // Check if the previous sibling contains a .date_span element
            if (prevSibling.classList.contains('date_span')) {
                lastTrunkDate = prevSibling.dataset.date; // Update the last found .date_span
            }
            prevSibling = prevSibling.previousElementSibling; // Move to the next previous sibling
        }
    }

    // If the date is different, create a new date span
    if (!lastDate || lastDate !== msgDate) {
        // if the date is also different than the last date span in the main thread.
        if (lastTrunkDate != msgDate) {
            const dateSpan = document.createElement('span');
            dateSpan.className = 'date_span';
            dateSpan.textContent = dateText; // Use formatted text
            dateSpan.setAttribute('data-date', msgDate);

            if (activeThread.id === '0') {
                activeThread.appendChild(dateSpan);
            } else {
                const branchInput = activeThread.querySelector('div[data-id="thread-active-indicator"]');
                activeThread.insertBefore(dateSpan, branchInput);
            }
        }
    }
}


function deconstContent(inputContent) {

    let messageText = '';
    let groundingMetadata = '';

    if (typeof inputContent === 'object' && inputContent.hasOwnProperty('text') && typeof inputContent.text === 'string') {
        inputContent = inputContent.text;
    }

    if (isValidJson(inputContent)) {
        const json = JSON.parse(inputContent);
        if (json.hasOwnProperty('groundingMetadata')) {
            groundingMetadata = json.groundingMetadata;
        }
        if (json.hasOwnProperty('text')) {
            messageText = json.text;
        } else {
            messageText = inputContent;
        }
    } else {
        messageText = inputContent;
    }

    return {
        messageText: messageText,
        groundingMetadata: groundingMetadata
    };

}


function isValidJson(string) {
    try {
        JSON.parse(string);
        return true;
    } catch (e) {
        return false;
    }
}

// Helper function to escape special characters in regular expressions
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}


/// Finds out if HAWKI is mentioned in the text.
/// rawText = text from input field or decrypted from server.
function detectMentioning(rawText) {
    // aiMentioned: if AI is mentioned
    // filteredText: text without mentioning,
    // modifiedText: text with mentioning (bold),
    // aiMention: the mentioning of ai,
    // userMentions: mentioning members of the room.
    let returnObj = {
        aiMentioned: false,
        filteredText: rawText,
        modifiedText: rawText,
        aiMention: '',
        userMentions: []
    };

    const mentionRegex = /@\w+/g;
    const mentionMatches = rawText.match(mentionRegex);

    if (mentionMatches) {
        let processedText = rawText;

        for (const mention of mentionMatches) {
            const aiHandle = window.getConfig().ai.handle;
            if (mention.toLowerCase() === aiHandle.toLowerCase()) {
                returnObj.aiMentioned = true;
                returnObj.aiMention = mention; // Remove the '@' for aiMention
                processedText = processedText.replace(new RegExp(mention, 'i'), '').trim();
            } else {
                returnObj.userMentions.push(mention.substring(1)); // Remove the '@' for other mentions
            }
        }
        returnObj.filteredText = processedText;
        returnObj.modifiedText = rawText.replace(mentionRegex, (match) => `<b>${match.toLowerCase()}</b>`);
    }
    return returnObj;
}


function setMessageStatusAsRead(messageElement) {
    messageElement.dataset.read_stat = true;
    messageElement.querySelector('#unread-message-icon').style.display = 'none';
}

//#region MSG_CTL: COPY

function activateMessageControls(msgElement) {

    if (!msgElement.classList.contains('me') && msgElement.querySelector('#edit-btn')) {
        msgElement.querySelector('#edit-btn').remove();
    }
    if (!msgElement.classList.contains('AI') && msgElement.querySelector('#regenerate-btn')) {
        msgElement.querySelector('#regenerate-btn').remove();
    }
    const codeBlocks = msgElement.querySelectorAll('pre');
    for (let i = 0; i < codeBlocks.length; i++) {
        const code = codeBlocks[i];
        const header = code.querySelector('.hljs-code-header');

        if (!header.querySelector('.copy-btn')) {
            const copyBtnTemp = document.getElementById('copy-btn-template');
            const clone = document.importNode(copyBtnTemp.content, true);
            const copyBtn = clone.querySelector('.copy-btn');

            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    copyCodeBlock(copyBtn);
                });
                header.appendChild(copyBtn);
            }
        }
    }

    const mathBlocks = msgElement.querySelectorAll('.math');
    for (let i = 0; i < mathBlocks.length; i++) {
        const mathBlock = mathBlocks[i];

        if (!mathBlock.querySelector('.copy-btn')) {
            const copyBtnTemp = document.getElementById('copy-btn-template');
            const clone = document.importNode(copyBtnTemp.content, true);
            const copyBtn = clone.querySelector('.copy-btn');
            copyBtn.classList.add('math-copy-btn');

            copyBtn.addEventListener('click', function () {
                copyMathBlock(mathBlock);
            });
            mathBlock.appendChild(copyBtn);
        }
    }
    const controls = msgElement.querySelector('.message-controls');
    controls.style.display = 'flex';
}

function copyCodeBlock(btn) {
    const codeBlock = btn.closest('pre').querySelector('code');
    const clone = codeBlock.cloneNode(true);
    const msgTxt = clone.textContent.trim();
    const trimmedMsg = msgTxt.trim();
    navigator.clipboard.writeText(trimmedMsg);
}

function copyMathBlock(block) {
    const m = block.dataset.rawmath;
    navigator.clipboard.writeText(m);
}

// Copies content of the message box without the css attributes
function CopyMessageToClipboard(provider) {
    const messageElement = provider.closest('.message');

    // Get the text content of the modified clone
    const content = messageElement.dataset.rawMsg;

    const trimmedMsg = content.trim();
    navigator.clipboard.writeText(trimmedMsg);
}

function copyCodeBlockToClipboard(provider) {
    const codeBlock = provider.closest('pre').querySelector('code');

    // Get the text content of the modified clone
    const content = codeBlock.innerHTML;

    const trimmedCont = content.trim();
    navigator.clipboard.writeText(trimmedMsg);
}

//#endregion


//#region MSG_CTL: EDIT


function editMessage(provider) {
    const messageId = provider.closest('.message').id;
    const message = window.oldUiMessageHistory.findMessageById(messageId);
    window.oldUiBridge.triggerEnterMode('edit', message);
}

/**
 * @param {OldUiSendMessagePayload} payload
 * @return {Promise<void>}
 */
async function confirmEditMessage(payload) {
    if (payload.mode.is !== 'edit') {
        throw new Error('Invalid mode for confirmEditMessage. Expected "edit".');
    }
    const messageElement = document.getElementById(payload.mode.messageId);
    if (!messageElement) {
        throw new Error(`Message element with ID ${payload.mode.messageId} not found.`);
    }

    if (!messageElement.classList.contains('me')) {
        return;
    }

    const cont = payload.message;

    messageElement.dataset.rawMsg = cont;
    messageElement.querySelector('.message-text').innerHTML = detectMentioning(cont).modifiedText;

    let key;
    let url;

    switch (payload.contextType) {
        case('aiConv'):
            url = `/req/conv/updateMessage/${activeConv.slug}`;
            key = window.userKeychain.aiConvKey;
            break;
        case('room'):
            url = `/req/room/updateMessage/${activeRoom.slug}`;
            const roomKey = window.userKeychain.roomKeys[activeRoom.slug] || {};

            if (messageElement.dataset.role === 'assistant') {
                key = roomKey.aiKey;
            } else {
                key = roomKey.roomKey;
            }
            break;
    }

    // Build attachments array for legacy format
    const attachments = payload.attachments
        .map(file => payload.status.getFileUuid(file))
        .filter(uuid => uuid !== null);

    const cryptoMsg = await encryptWithSymKey(key, cont, false);
    const messageObj = {
        'content': {
            'text': {
                'ciphertext': cryptoMsg.ciphertext,
                'iv': cryptoMsg.iv,
                'tag': cryptoMsg.tag
            },
            'attachments': attachments
        },
        'isAi': false,
        'model': '',
        'completion': true,
        'message_id': messageElement.id
    };

    await requestMsgUpdate(messageObj, messageElement, url, {text: cont});
}

//#endregion


//#region MSG_CTL: TTS

let currentUtterance = null; // Track the current utterance
let previousProvider = null; // Track the previous provider (button)

const readIcon =
    `<svg>
    <path d="M8.25 3.75L4.5 6.75H1.5V11.25H4.5L8.25 14.25V3.75Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M14.3018 3.69727C15.7078 5.10372 16.4977 7.01103 16.4977 8.99977C16.4977 10.9885 15.7078 12.8958 14.3018 14.3023M11.6543 6.34477C12.3573 7.04799 12.7522 8.00165 12.7522 8.99602C12.7522 9.99038 12.3573 10.944 11.6543 11.6473" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>`;
const stopReadIcon =
    `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="10"></circle>
    <rect x="9" y="9" width="6" height="6"></rect>
</svg>`;

function messageReadAloud(provider) {
    const synth = window.speechSynthesis;

    // Check if the same button was clicked
    if (provider === previousProvider) {
        if (synth.speaking) {
            synth.cancel();
            currentUtterance = null;
            previousProvider = null;
            // Change icon back to "volume"
            provider.innerHTML = readIcon;
        }
        return;
    }

    if (synth.speaking) {
        synth.cancel();
        currentUtterance = null;
        previousProvider.innerHTML = readIcon;
    }
    // Start speaking and change icon to "stop"
    const msgText = provider.closest('.message').dataset.rawMsg;
    const utterance = new SpeechSynthesisUtterance(msgText);

    currentUtterance = utterance;
    previousProvider = provider;
    provider.innerHTML = stopReadIcon;

    synth.speak(utterance);

    // Reset icon when speech ends
    utterance.onend = () => {
        if (provider === previousProvider) {
            previousProvider = null;
            provider.innerHTML = readIcon;
        }
    };
}

//#endregion


//#region MSG_CTL: REGENERATE

async function onRegenerateBtn(btn) {
    openRegenerateDropDown(btn);
}

let menu;

function openRegenerateDropDown(sender) {
    const messageId = sender.closest('.message').id;
    const message = window.oldUiMessageHistory.findMessageById(messageId);
    window.oldUiBridge.triggerEnterMode('regen', message);
}

/**
 *
 * @param {OldUiSendMessagePayload} payload
 * @return {Promise<void>}
 */
async function regenerateMessage(payload) {
    if (!payload.model) {
        return;
    }

    const messageElement = document.getElementById(payload.mode.messageId);
    if (!messageElement) {
        throw new Error(`Message element with ID ${payload.mode.messageId} not found.`);
    }

    const metadata = {
        'tools': payload.tools.map(tool => tool.name),
        'params': payload.parameters
    };

    const threadIndex = messageElement.closest('.thread').id;

    //reset message content
    messageElement.querySelector('.message-text').innerHTML = '';
    messageElement.dataset.rawMsg = '';
    await initializeMessageFormating();

    payload.waitForResponse(async (response) => {
        let msgAttributes = {};
        switch (activeModule) {
            case('chat'):
                msgAttributes = {
                    'threadIndex': threadIndex,
                    'broadcasting': false,
                    'slug': '',
                    'regenerationElement': messageElement,
                    'stream': true,
                    'model': payload.model.model_id,
                    'metadata': metadata
                };

                await buildRequestObjectForAiConv(
                    response,
                    msgAttributes,
                    messageElement,
                    true,
                    async (isDone) => void 0);
                break;
            case('groupchat'):
                const roomKey = window.userKeychain.roomKeys[activeRoom.slug] || null;
                const aiCryptoSalt = window.getConfig().salts.ai;
                const aiKey = await deriveKey(roomKey, activeRoom.slug, aiCryptoSalt);
                const aiKeyRaw = await exportSymmetricKey(aiKey);
                const aiKeyBase64 = arrayBufferToBase64(aiKeyRaw);

                msgAttributes = {
                    'threadIndex': threadIndex,
                    'broadcasting': true,
                    'slug': activeRoom.slug,
                    'key': aiKeyBase64,
                    'regenerationElement': messageElement,
                    'stream': false,
                    'model': payload.model.model_id,
                    'metadata': metadata
                };
                buildRequestObject(msgAttributes, async (updatedText, done) => {
                });
                break;
        }
    });
}

//#endregion
let GEN_STAT_VIDEO_URL = null;
window.waitUntilReady(async () => {
    const src = (typeof darkMode !== 'undefined' && darkMode === 'disabled')
        ? '/animations/DocSearch-lightMode.webm'
        : '/animations/DocSearch-darkMode.webm';
    const response = await fetch(src);
    const blob = await response.blob();
    GEN_STAT_VIDEO_URL = URL.createObjectURL(blob);

    const gridDotData = await fetch('/animations/Reasoning.mp4');
    const girdBlob = await gridDotData.blob();
    GRID_VIDEO_URL = URL.createObjectURL(girdBlob);

});

function createStatusElement(status, messageElement) {
    let statElement = messageElement.querySelector(`.gen-stat-element`);
    //create a new element for first status
    if (!statElement) {
        const statTemp = document.getElementById('gen-stat-template');
        const statClone = statTemp.content.cloneNode(true);
        statElement = statClone.querySelector('.gen-stat-element');
        messageElement.querySelector('.message-text').appendChild(statElement);
    }


    let statusText = '';
    if (status.key === 'tool_call' || status.key === 'max_execution') {
        statElement.querySelector('#grid-animation-block').classList.remove('active');
        statElement.querySelector('video').src = GEN_STAT_VIDEO_URL;
        statElement.querySelector('video').style.display = 'flex';

        // const list = JSON.parse(status);
        const locales = window.getConfig().locale.available;
        const preferredLocale = window.getConfig().locale.current;
        const activeLocale =
            locales.find(loc => loc.lang === preferredLocale)
            || locales.find(loc => loc.lang === window.getConfig().locale.default)
            || locales[0];
        const formatter = new Intl.ListFormat(
            activeLocale.shortName,
            {
                style: 'long',
                type: 'conjunction'
            });
        const value = status.value;
        const uniqueTools = [...new Set(value)];

        const toolNames = uniqueTools.map(tool => {
            const key = `The_${tool}`;

            if (hasTranslation(key)) {
                return __(key);
            }

            // fallback: web_search -> Web Search
            return tool
                .split('_')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        });
        const list = formatter.format(toolNames);
        statusText = `${__('Exec_prefix')} ${list}`;
    }
    if (status.key === 'reasoning') {
        statElement.querySelector('video').style.display = 'none';
        statElement.querySelector('#grid-animation-block').classList.add('active');
        statusText = __('Reasoning_Msg');
    }
    statElement.querySelector('.stat-txt').innerText = statusText;
    // tripleDotAnime(statElement, statusText)
}
