
function addMessageToChatlog(messageObj, isFromServer = false){

    const {messageText, groundingMetadata} = deconstContent(messageObj.content.text);

    /// CLONE
    // clone message element
    const messageTemp = document.getElementById('message-template')
    const messageClone = messageTemp.content.cloneNode(true);

    //Get messageElement
    const messageElement = messageClone.querySelector(".message");

    /// DATASET & ID
    // set dataset attributes
    messageElement.dataset.role = messageObj.message_role;
    messageElement.dataset.rawMsg = messageText;
    if(messageObj.metadata){
        const meta = messageObj.metadata;
        messageElement.dataset.tools = JSON.stringify(meta.tools ?? []);
        messageElement.dataset.params = JSON.stringify(meta.params ?? {});
    }
    // messageElement.dataset.groundingMetadata = JSON.stringify(groundingMetadata);

    //if date and time is confirmed from the server add them
    if(messageObj.created_at) messageElement.dataset.created_at = messageObj.created_at;

    // set id (whole . deci format)
    if(messageObj.message_id){
        messageElement.id = messageObj.message_id;
    }

    /// CLASSES & AVATARS
    // add classes AI ME MEMBER to the element
    if(messageObj.message_role === "assistant"){
        messageElement.classList.add('AI');
        messageElement.querySelector('.user-inits').remove();
        messageElement.querySelector('.icon-img').src = hawkiAvatarUrl;
    }
    else{
        if(messageObj.author.name && messageObj.author.username === userInfo.username){
            messageElement.classList.add('me');
            if(userAvatarUrl){
                messageElement.querySelector('.user-inits').style.display = "none";
                messageElement.querySelector('.icon-img').style.display = "block";
                messageElement.querySelector('.icon-img').src = userAvatarUrl;
            }
            else{
                messageElement.querySelector('.icon-img').style.display = "none";
                messageElement.querySelector('.user-inits').style.display = "block";
                const userInitials =  messageObj.author.name.slice(0, 1).toUpperCase();
                messageElement.querySelector('.user-inits').innerText = userInitials
            }
        }else{
            messageElement.classList.add('member');
            const hasAvatar = !!messageObj.author.avatar_url;
            messageElement.querySelector('.icon-img').style.display = hasAvatar ? "block" : "none";
            messageElement.querySelector('.user-inits').style.display = hasAvatar ? "none" : "block";

            // assign icon to message.
            if(!hasAvatar){
                messageElement.querySelector('.icon-img').style.display = "none";
                messageElement.querySelector('.user-inits').style.display = "block";
                const userInitials =  messageObj.author.name.slice(0, 1).toUpperCase();
                messageElement.querySelector('.user-inits').innerText = userInitials
            }
            else{
                messageElement.querySelector('.icon-img').style.display = "block";
                messageElement.querySelector('.user-inits').style.display = "none";
                messageElement.querySelector('.icon-img').src = messageObj.author.avatar_url;
            }
        }
    }

    /// Set Author Name
    if(messageObj.model && messageObj.message_role === 'assistant'){
        model = modelsList.find(m => m.id === messageObj.model);
        messageElement.querySelector('.message-author').innerHTML =
            model ?
            `<span>${messageObj.author.username} </span><span class="message-author-model">(${model.label})</span>`:
            `<span>${messageObj.author.username} </span><span class="message-author-model">(${messageObj.model}) !!! Obsolete !!!</span>`;

        messageElement.dataset.model = messageObj.model;
        messageElement.dataset.author = messageObj.author.username;
    }
    else{

        let header;
        if(!messageObj.author.isRemoved || messageObj.author.isRemoved === 0){
            header = messageObj.author.name
        }
        else{
            header = `<span>${messageObj.author.name}</span> <span class="message-author-model">(${translation.RemovedMember})</span>`
        }

        messageElement.querySelector('.message-author').innerHTML = header;
        messageElement.dataset.author = messageObj.author.name;
    }

    /// INDEXING & THREAD
    // if message is from the user, it still doesn't have an assigned ID from the server.
    if(isFromServer){
        // deconstruct message id
        let [msgWholeNum, msgDecimalNum] = messageObj.message_id.split('.').map(Number);

        // if decimal is 0 the message belongs to trunk
        if (msgDecimalNum === 0) {
            threadIndex = 0;
        } else {
            threadIndex = msgWholeNum;
        }
    }
    else{
        threadIndex = activeThreadIndex;
    }

    let activeThread = findThreadWithID(threadIndex);


    /// DATE & TIME
    // if message has a date it's already submitted and comes from the server.
    // if not, it has been created by user and does not have a date stamp -> today is the date
    let msgDate;
    if(messageObj.created_at){
        msgDate = messageObj.created_at.split('+')[0];
    }
    else{
        todayDate = new Date();
        msgDate = `${todayDate.getFullYear()}-${(todayDate.getMonth() + 1).toString().padStart(2, '0')}-${todayDate.getDate().toString().padStart(2, '0')}`;
    }
    setDateSpan(activeThread, msgDate);


    ///ATTACHMENTS
    if(messageObj.content.attachments && messageObj.content.attachments.length != 0){

        const attachmentContainer = messageElement.querySelector('.attachments');

        messageObj.content.attachments.forEach(attachment => {

            const thumbnail = createAttachmentThumbnail(attachment.fileData, 'message');
            // Add to file preview container
            attachmentContainer.appendChild(thumbnail);
        });
    }

    /// CONTENT
    // Setup Message Content
    const msgTxtElement = messageElement.querySelector(".message-text");

    if(!messageElement.classList.contains('AI')){
        let processedContent = detectMentioning(messageText).modifiedText;
        processedContent = convertHyperlinksToLinks(processedContent);
        processedContent = wrapLinksInBlocks(processedContent);
        msgTxtElement.innerHTML = processedContent;
    }
    else{
        let markdownProcessed = formatMessage(messageText, groundingMetadata);
        msgTxtElement.innerHTML = markdownProcessed;
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
    }


    /// check for completion status. ONLY FOR CONV MESSAGES FROM AI.
    if (messageObj.hasOwnProperty('completion')){
        if (messageObj.completion === 0 && messageElement.querySelector('#incomplete-msg-icon')) {
            messageElement.querySelector('#incomplete-msg-icon').style.display = 'flex';
        }else{
            messageElement.querySelector('#incomplete-msg-icon').style.display = 'none';
        }
    }
        /// READ STATUS
    // if the read status exists in the data
    if(messageElement.classList.contains('me') && messageElement.querySelector('#unread-message-icon')){
        messageElement.querySelector('#unread-message-icon').style.display = "none";
    }
    else if ('read_status' in messageObj) {
        messageElement.dataset.read_stat = messageObj.read_status;

        if(messageObj.read_status){
            setMessageStatusAsRead(messageElement);
        }
    }


    /// INSERT IN CHATLOG
    // insert into target thread
    if(threadIndex === 0){
        // if message is a main message then it needs a thread inside
        // clone and insert thread template in message.
        const threadTemplate = document.getElementById('thread-template');
        const threadElement = threadTemplate.content.cloneNode(true);
        threadDiv = threadElement.querySelector('.thread');
        threadDiv.classList.add('branch');
        threadDiv.querySelector('.model-selector-label').innerHTML = activeModel.label;

        if(messageObj.message_id){
            threadDiv.id = messageObj.message_id.split('.')[0];
            threadDiv.querySelector('.input').id = threadDiv.id;
        }

        const input = threadDiv.querySelector('.input-container');

        messageElement.appendChild(threadDiv);
        activeThread.appendChild(messageElement);
	    initFileUploader(input);

    }
    else{
        const branchInput = activeThread.querySelector('.input-container');
        messageElement.querySelector('#thread-btn').remove();
        const messageChildrenCount = Array.from(activeThread.children).filter(child => child.classList.contains('message')).length + 1;
        const cmtCount = activeThread.closest('.message').querySelector('#comment-count');
        cmtCount.style.display = 'block';
        cmtCount.innerHTML = messageChildrenCount;

        activeThread.insertBefore(messageElement, branchInput);
    }

    formatHljs(messageElement);
    return  messageElement;
}


function updateMessageElement(messageElement, messageObj, updateContent = false){

    messageElement.id = messageObj.message_id;
    if(messageObj.metadata){
        const meta = messageObj.metadata;
        messageElement.dataset.tools = JSON.stringify(meta.tools ?? []);
        messageElement.dataset.params = JSON.stringify(meta.params ?? {});
    }

    if(messageElement.querySelector('.thread')){
        messageElement.querySelector('.thread').id = messageObj.message_id.split('.')[0];
        messageElement.querySelector('.input').id = messageObj.message_id.split('.')[0]
    }

    if(messageElement.classList.contains('me')){
        messageElement.querySelector('#sent-status-icon').style.display = 'flex';
    }

    if (messageObj.hasOwnProperty('completion')){
        if ((messageObj.completion === 0 || messageObj.completion === false) && messageElement.querySelector('#incomplete-msg-icon')) {
            messageElement.querySelector('#incomplete-msg-icon').style.display = 'flex';
        }else{
            messageElement.querySelector('#incomplete-msg-icon').style.display = 'none';
        }
    }

    messageElement.dataset.role = messageObj.message_role;
    const msgTxtElement = messageElement.querySelector(".message-text");

    if (messageElement.classList.contains('AI')) {
        const username = messageElement.dataset.author;
        const model = modelsList.find(m => m.id === messageObj.model);
        messageElement.querySelector('.message-author').innerHTML =
            model ?
                `<span>${username} </span><span class="message-author-model">(${model.label})</span>` :
                `<span>${username} </span><span class="message-author-model">(${messageObj.model}) !!! Obsolete !!!</span>`;
        messageElement.dataset.model = messageObj.model;
    }

    if(updateContent){
        const {messageText, groundingMetadata} = deconstContent(messageObj.content.text);

        messageElement.dataset.rawMsg = messageText;
        if(messageObj.message_role === "user"){
            const filteredContent = detectMentioning(messageText);
            let processedContent = filteredContent.modifiedText;
            processedContent = convertHyperlinksToLinks(processedContent);
            processedContent = wrapLinksInBlocks(processedContent);
            msgTxtElement.innerHTML = processedContent;
        }
        else{

            let markdownProcessed = formatMessage(messageText, groundingMetadata);
            msgTxtElement.innerHTML = markdownProcessed;
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
        }

        // if the read status exists in the data
        if(messageElement.classList.contains('me') && messageElement.querySelector('#unread-message-icon')){
            messageElement.querySelector('#unread-message-icon').style.display = "none";
        }
        else if ('read_status' in messageObj) {
            messageElement.dataset.read_stat = messageObj.read_status;

            if(messageObj.read_status){
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




function setDateSpan(activeThread, msgDate, formatDay = true){

    // Determine if msgDate is today or yesterday
    const msgDateObj = new Date(msgDate);
    let dateText;

    if(formatDay){
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        if (msgDateObj.toDateString() === today.toDateString()) {
            dateText = translation.Today;
        } else if (msgDateObj.toDateString() === yesterday.toDateString()) {
            dateText = translation.Yesterday;
        } else {
            const formattedDate = `${msgDateObj.getDate()}.${msgDateObj.getMonth()+1}.${msgDateObj.getFullYear()}`
            dateText = formattedDate;
        }
    }
    else{
        const formattedDate = `${msgDateObj.getDate()}.${msgDateObj.getMonth()+1}.${msgDateObj.getFullYear()}`
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
        if(lastTrunkDate != msgDate){
            const dateSpan = document.createElement('span');
            dateSpan.className = 'date_span';
            dateSpan.textContent = dateText; // Use formatted text
            dateSpan.setAttribute('data-date', msgDate);

            if(activeThread.id === "0"){
                activeThread.appendChild(dateSpan);
            }
            else{
                const branchInput = activeThread.querySelector('.input-container');
                activeThread.insertBefore(dateSpan, branchInput);
            }
        }
    }
}



function deconstContent(inputContent){

    let messageText = '';
    let groundingMetadata = '';

    if(isValidJson(inputContent)){
        const json = JSON.parse(inputContent);
        if(json.hasOwnProperty('groundingMetadata')){
            groundingMetadata = json.groundingMetadata
        }
        if(json.hasOwnProperty('text')){
            messageText = json.text;
        }
        else{
            messageText = inputContent;
        }
    }
    else{
        messageText = inputContent;
    }

    return {
        messageText: messageText,
        groundingMetadata: groundingMetadata
    }

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
function detectMentioning(rawText){
    // aiMentioned: if AI is mentioned
    // filteredText: text without mentioning,
    // modifiedText: text with mentioning (bold),
    // aiMention: the mentioning of ai,
    // userMentions: mentioning members of the room.
    let returnObj = {
        aiMentioned: false,
        filteredText: rawText,
        modifiedText: rawText,
        aiMention: "",
        userMentions: []
    };

    const mentionRegex = /@\w+/g;
    const mentionMatches = rawText.match(mentionRegex);

    if (mentionMatches) {
        let processedText = rawText;

        for (const mention of mentionMatches) {
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


function setMessageStatusAsRead(messageElement){
    messageElement.dataset.read_stat = true;
    messageElement.querySelector('#unread-message-icon').style.display = "none";
}

//#region MSG_CTL: COPY

function activateMessageControls(msgElement){

    if(!msgElement.classList.contains('me') && msgElement.querySelector('#edit-btn')){
        msgElement.querySelector('#edit-btn').remove();
    }
    if(!msgElement.classList.contains('AI') && msgElement.querySelector('#regenerate-btn')){
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
                copyBtn.addEventListener("click", function() {
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

            copyBtn.addEventListener("click", function() {
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

function copyMathBlock(block){
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


function editMessage(provider){
    const msgControls = provider.closest('.message-controls');
    const controls = msgControls.querySelector('.controls');
    const editControls = msgControls.querySelector('.edit-bar');

    controls.style.display = 'none';
    editControls.style.display = 'flex';

    const message = provider.closest('.message');
    const wrapper = message.querySelector('.message-wrapper');
    wrapper.classList.add('edit-mode');

    const content = message.querySelector('.message-content');

    /// PASTE STYLE
    content.addEventListener("paste", function(e) {
        e.preventDefault();

        // Get the plain text from clipboard
        let text = (e.clipboardData || window.clipboardData).getData("text/plain");

        // Get selection and range
        const selection = window.getSelection();
        if (!selection.rangeCount) return;
        const range = selection.getRangeAt(0);

        // Split text by lines
        const lines = text.split(/\r?\n/);
        // Create a DocumentFragment to hold nodes
        const fragment = document.createDocumentFragment();

        for (let i = 0; i < lines.length; i++) {
            if(i > 0) fragment.appendChild(document.createElement("br"));
            fragment.appendChild(document.createTextNode(lines[i]));
        }

        // Insert the fragment at the cursor
        range.deleteContents();
        range.insertNode(fragment);

        // Move cursor to the end of the pasted content
        // Create a new range after the inserted content
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
    });


    content.setAttribute('contenteditable', true);
    content.dataset.tempContent = content.innerHTML;
    const rawMsg = content.closest('.message').dataset.rawMsg;
    content.innerHTML = escapeHTML(rawMsg).replace(/\n/g, '<br>');

    content.focus();

    var range,selection;
    if(document.createRange)
    {
        range = document.createRange();
        range.selectNodeContents(content);
        range.collapse(false);
        selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }
    else if(document.selection)
    {
        range = document.body.createTextRange();
        range.moveToElementText(content);
        range.collapse(false);
        range.select();
    }
}

function abortEditMessage(provider){
    const msgControls = provider.closest('.message-controls');
    const controls = msgControls.querySelector('.controls');
    const editControls = msgControls.querySelector('.edit-bar');
    controls.style.display = 'flex';
    editControls.style.display = 'none';



    const wrapper = provider.closest('.message-wrapper');
    wrapper.classList.remove('edit-mode');


    // if(wrapper.querySelectorAll('.attachment').length > 0){
    //     const atchs = wrapper.querySelectorAll('.attachment');
    //     atchs.forEach(atch => {
    //         atch.classList.remove('edit-mode');
    //         const rmBtn = atch.querySelector('.remove-btn');
    //         rmBtn.disabled = true;
    //         rmBtn.style.display = 'none';
    //     })
    // };


    const content = wrapper.querySelector('.message-content');
    content.setAttribute('contenteditable', false);
    content.innerHTML = content.dataset.tempContent;
    content.removeAttribute('data-temp-content')
}

async function confirmEditMessage(provider){
    const msgControls = provider.closest('.message-controls');
    const messageElement = provider.closest('.message');

    if(!messageElement.classList.contains('me')){
        return;
    }

    const controls = msgControls.querySelector('.controls');
    const editControls = msgControls.querySelector('.edit-bar');
    controls.style.display = 'flex';
    editControls.style.display = 'none';

    const wrapper = provider.closest('.message-wrapper');
    wrapper.classList.remove('edit-mode');

    const content = wrapper.querySelector('.message-content');
    content.setAttribute('contenteditable', false);

    const cont = content.innerText;
    messageElement.dataset.rawMsg = cont;

    content.innerHTML = content.dataset.tempContent;
    content.removeAttribute('data-temp-content');

    messageElement.dataset.rawMsg = cont;
    messageElement.querySelector(".message-text").innerHTML = detectMentioning(cont).modifiedText;

    let key;
    let url;

    switch(activeModule){
        case('chat'):
            url = `/req/conv/updateMessage/${activeConv.slug}`
            key = await keychainGet('aiConvKey');
        break;
        case('groupchat'):
            url = `/req/room/updateMessage/${activeRoom.slug}`
            const roomKey = await keychainGet(`${activeRoom.slug}`);

            if(messageElement.dataset.role === 'assistant'){
                const aiCryptoSalt = await fetchServerSalt('AI_CRYPTO_SALT');
                key = await deriveKey(roomKey, activeRoom.slug, aiCryptoSalt);
            }else{
                key = roomKey;
            }
        break;
    }

    const cryptoMsg = await encryptWithSymKey(key, cont, false);
    const messageObj = {
        'content':{
                'text': {
                    'ciphertext': cryptoMsg.ciphertext,
                    'iv': cryptoMsg.iv,
                    'tag': cryptoMsg.tag,
                }
            },
        'isAi': false,
        'model': '',
        'completion': true,
        'message_id': messageElement.id,
    }

    requestMsgUpdate(messageObj, messageElement ,url);
}

//#endregion



//#region MSG_CTL: TTS

let currentUtterance = null; // Track the current utterance
let previousProvider = null; // Track the previous provider (button)

const readIcon =
`<svg>
    <path d="M8.25 3.75L4.5 6.75H1.5V11.25H4.5L8.25 14.25V3.75Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M14.3018 3.69727C15.7078 5.10372 16.4977 7.01103 16.4977 8.99977C16.4977 10.9885 15.7078 12.8958 14.3018 14.3023M11.6543 6.34477C12.3573 7.04799 12.7522 8.00165 12.7522 8.99602C12.7522 9.99038 12.3573 10.944 11.6543 11.6473" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>`
const stopReadIcon =
`<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="10"></circle>
    <rect x="9" y="9" width="6" height="6"></rect>
</svg>`

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
    const msgText = provider.closest(".message").dataset.rawMsg;
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

async function onRegenerateBtn(btn){
    openRegenerateDropDown(btn);
}

let regenerateState = {
    messageElement: null,
    model: null,
    tools: new Set(),
    params: []
};
let menu;
let regenerateButtonRef = null;

function openRegenerateDropDown(sender){
    menu = document.getElementById('regenerate-controls');
    regenerateButtonRef = sender;
    const btnRect = sender.getBoundingClientRect();

    menu.style.top = `${btnRect.bottom}px`;
    menu.style.left = `${btnRect.left}px`;
    sender.classList.add('active');
    menu.style.display = `block`;

    setTimeout(() => {
        menu.style.width = `${menu.getBoundingClientRect().width + 10}px`;
        menu.style.opacity = `1`;
    }, 50);

    const msgElement = sender.closest(".message");

    regenerateState.messageElement = msgElement;
    const modelId = msgElement.dataset.model;
    const model = modelsList.find(m => m.id === modelId);
    regenerateState.model = model || activeModel;
    const msgTools = JSON.parse(msgElement.dataset.tools);
    let tools = [];
    let missingTools = [];
    msgTools.forEach(msgTool => {
        if(toolKit.includes(msgTool)){
            tools.push(msgTool);
        }
        else{
            missingTools.push(msgTool);
        }
    })
    regenerateState.tools = new Set(tools);

    if(missingTools.length > 0){
        menu.querySelector('#expired-tool-warning').style.display = 'block';
        menu.querySelector('#expired-tool-warning')
            .querySelector('.expired-tool-name').innerText = missingTools.join(', ');
    }
    else{
        menu.querySelector('#expired-tool-warning').style.display = 'none';
    }


    const storedParams = JSON.parse(msgElement.dataset.params || '{}');
    regenerateState.params = {
        temperature: storedParams.temperature ?? activeModel.params?.temperature ?? null,
        top_p: storedParams.top_p ?? activeModel.params?.top_p ?? null,
    };
    // Initialize regeneration filters based on current tools
    setRegenerationFilters(Array.from(regenerateState.tools));

    initModelSubMenu(regenerateState.model);
    initToolSubMenu(regenerateState.tools);
    initParamsSubMenu(regenerateState.params);

    bindRegenerateMenuEvents();
    updateIndicators();

    // Apply initial model filtering based on current tools
    refreshModelList(null, 'regeneration');

    // Add outside click listener after a small delay to prevent immediate closing
    setTimeout(() => {
        document.addEventListener('click', handleOutsideClick);
    }, 100);
}


function bindRegenerateMenuEvents(){

    menu.querySelectorAll('.reg-submenu-btn')
        .forEach(btn => {
            btn.onclick = () => {
                toggleSubMenu(btn.getAttribute('reference'));
            };
        })

    menu.querySelectorAll('.model-selector')
        .forEach(btn => {
            btn.onclick = handleModelSelection;
        });

    menu.querySelectorAll('.tool-selector')
        .forEach(btn => {
            btn.onclick = handleToolToggle;
        });

    menu.querySelector('.confirm')
        .onclick = handleRegenerateClick;
}
function updateIndicators(){
    const modelId = typeof regenerateState.model === 'string' ? regenerateState.model : regenerateState.model?.id;
    const modelLabel = typeof regenerateState.model === 'string'
        ? modelsList.find(m => m.id === modelId)?.label || modelId
        : regenerateState.model?.label || modelId;

    menu.querySelector('.reg-submenu-btn[reference="models-list"]')
        .querySelector('.indicator').innerText = modelLabel || '';
    menu.querySelector('.reg-submenu-btn[reference="tools-list"]')
        .querySelector('.indicator').innerText = regenerateState.tools.size;
}


function initModelSubMenu(model) {
    const selectors = menu.querySelectorAll('.model-selector');
    const modelId = typeof model === 'string' ? model : model?.id;

    selectors.forEach(btn => {
        btn.classList.toggle(
            'active',
            btn.dataset.modelId === modelId
        );
    });
}
function handleModelSelection(e){
    const btn = e.currentTarget;

    // Prevent selecting disabled models
    if (btn.disabled) {
        return;
    }

    btn.parentElement
        .querySelectorAll('.model-selector.active')
        .forEach(el => el.classList.remove('active'));

    btn.classList.add('active');
    regenerateState.model = JSON.parse(btn.value);
    updateIndicators();
}


function initToolSubMenu(tools){
    menu.querySelectorAll(`.tool-selector`).forEach(btn => {btn.classList.remove('active')});
    tools.forEach(tool => {
        const btn = menu.querySelector(`.tool-selector[data-reference="${tool}"]`);
        btn.classList.add('active');
    });
}
function handleToolToggle(e){
    const btn = e.currentTarget;
    const tool = btn.dataset.reference;

    btn.classList.toggle('active');

    if (btn.classList.contains('active')) {
        regenerateState.tools.add(tool);
        addRegenerationFilter(tool);
    } else {
        regenerateState.tools.delete(tool);
        removeRegenerationFilter(tool);
    }

    // Check if current model is still compatible
    const updatedModel = refreshRegenerationModelList(regenerateState.model);

    if (updatedModel && updatedModel !== regenerateState.model) {
        // Model was changed to a fallback
        regenerateState.model = updatedModel;
        initModelSubMenu(updatedModel);
    } else if (!updatedModel) {
        // No compatible models found - revert tool selection
        btn.classList.toggle('active');
        if (btn.classList.contains('active')) {
            regenerateState.tools.add(tool);
            addRegenerationFilter(tool);
        } else {
            regenerateState.tools.delete(tool);
            removeRegenerationFilter(tool);
        }

        // Show error message in menu
        showRegenerationError(translation.Input_Err_FilterConflict || 'No compatible models available for selected tools');
        return;
    }

    // Refresh the model list UI (enable/disable buttons)
    refreshModelList(null, 'regeneration');
    updateIndicators();
}



function initParamsSubMenu(params){

    menu.querySelectorAll('input[type="range"]').forEach(el => {
        el.addEventListener('input', () => {
            handleSliderInput(el);
            setParamValues(el)
        });
    })

    setSliderValue(
        menu.querySelector('#temperature-input'),
        params.temperature
    );
    setSliderValue(
        menu.querySelector('#top-p-input'),
        params.top_p
    );
}
function setParamValues(el){
    if(el.dataset.param === 'temperature'){
        regenerateState.params.temperature = parseFloat(el.value);
    }
    if(el.dataset.param === 'top_p'){
        regenerateState.params.top_p = parseFloat(el.value);
    }
}




function handleRegenerateClick(){
    const { messageElement, model, tools , params} = regenerateState;

    if (!model) {
        console.error('No model selected for regeneration');
        return;
    }

    if (!messageElement) {
        console.error('No message element for regeneration');
        return;
    }
    const metadata = {
        'tools': Array.from(tools),
        'params': params
    }
    regenerateMessage(
        messageElement,
        model,
        metadata
    );

    closeRegenerateMenu();
}

function showRegenerationError(message){
    // Find or create error message element in menu
    let errorEl = menu.querySelector('.regeneration-error');

    if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'regeneration-error';
        errorEl.style.cssText = 'color: #ff4444; padding: 8px; font-size: 12px; text-align: center;';
        menu.querySelector('.reg-wrapper').appendChild(errorEl);
    }

    errorEl.textContent = message;
    errorEl.style.display = 'block';

    // Auto-hide after 3 seconds
    setTimeout(() => {
        errorEl.style.display = 'none';
    }, 3000);
}

function handleOutsideClick(event){
    const menu = document.getElementById('regenerate-controls');

    // Check if menu is visible
    if (!menu || menu.style.display === 'none') {
        return;
    }

    // Check if click is outside both the menu and the regenerate button
    const isClickInsideMenu = menu.contains(event.target);
    const isClickOnButton = regenerateButtonRef && regenerateButtonRef.contains(event.target);

    if (!isClickInsideMenu && !isClickOnButton) {
        closeRegenerateMenu();
    }
}
function closeRegenerateMenu(){
    menu.style.opacity = '0';
    closeAllSubMenus();
    setTimeout(() => {
        menu.style.display = 'none';
    }, 150);

    // Remove active state from button
    if (regenerateButtonRef) {
        regenerateButtonRef.classList.remove('active');
        regenerateButtonRef = null;
    }

    // Clear regeneration filters
    clearRegenerationFilters();

    // Remove outside click listener
    document.removeEventListener('click', handleOutsideClick);

    regenerateState = {
        messageElement: null,
        model: null,
        tools: new Set(),
        params: []
    };
}



function toggleSubMenu(id){
    const subMenus = menu.querySelectorAll('.sub-menu');
    subMenus.forEach((subMenu) => {
        if(subMenu.id === id && !subMenu.classList.contains('active')){
            handlesSubMenuToggle(subMenu, true);
        }
        else{
            handlesSubMenuToggle(subMenu, false);
        }
    })
}

function closeAllSubMenus(){
    menu.querySelectorAll('.sub-menu').forEach((subMenu) => {
        handlesSubMenuToggle(subMenu, false);
    });

}

function handlesSubMenuToggle(menu, active){
    if(active){
        menu.style.display = 'block';
        menu.classList.add('active');
    }
    else{
        menu.classList.remove('active');
        setTimeout(()=>{
            menu.style.display = 'none';
        }, 300)
    }
}


async function regenerateMessage(messageElement, model, metadata, Done = null){
    if(!messageElement.classList.contains('AI')){
        return;
    }

    const threadIndex = messageElement.closest('.thread').id;

    //reset message content
    messageElement.querySelector('.message-text').innerHTML = '';
    messageElement.dataset.rawMsg = '';
    initializeMessageFormating();

    let msgAttributes = {};
    switch(activeModule){
        case('chat'):
            msgAttributes = {
                'threadIndex': threadIndex,
                'broadcasting': false,
                'slug': '',
                'regenerationElement': messageElement,
                'stream': !!(model.capabilities && model.capabilities.stream),
                'model': model.id,
                'metadata': metadata
            }

            await buildRequestObjectForAiConv(msgAttributes, messageElement, true, async(isDone)=>{
                if(Done){
                    Done(true);
                }
            });
            break;
        case('groupchat'):
            const roomKey = await keychainGet(activeRoom.slug);
            const aiCryptoSalt = await fetchServerSalt('AI_CRYPTO_SALT');
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
                'model': model.id,
                'metadata': metadata
            }
            buildRequestObject(msgAttributes,  async (updatedText, done) => {});
            break;
    }
}
//#endregion
let GEN_STAT_VIDEO_URL = null;
document.addEventListener("DOMContentLoaded", async () => {
    const src = darkMode === 'disabled'
                                ? '/animations/DocSearch-lightMode.webm'
                                : '/animations/DocSearch-darkMode.webm';
    const response = await fetch(src);
    const blob = await response.blob();
    GEN_STAT_VIDEO_URL = URL.createObjectURL(blob);

});

function createStatusElement(status, messageElement){
    let statElement = messageElement.querySelector(`.gen-stat-element`);
    //create a new element for first status
    if(!statElement){
        const statTemp = document.getElementById('gen-stat-template')
        const statClone = statTemp.content.cloneNode(true);
        statElement = statClone.querySelector(".gen-stat-element");
        messageElement.querySelector('.message-text').appendChild(statElement);
    }

    statElement.querySelector('video').src = GEN_STAT_VIDEO_URL

    // const list = JSON.parse(status);
    const formatter = new Intl.ListFormat(activeLocale.label, {
        style: "long",
        type: "conjunction"
    });

    const value = status.value;
    const toolNames = value.map(tool => {
        const key = `The_${tool}`;

        if (translation.hasOwnProperty(key)) {
            return translation[key];
        }

        // fallback: web_search -> Web Search
        return tool
            .split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    });
    const list = formatter.format(toolNames);
    statElement.querySelector('.stat-txt').innerText = `${translation.Exec_prefix} ${list}`;
}
