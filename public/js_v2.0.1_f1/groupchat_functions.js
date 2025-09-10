

let inputField;
let roomMsgTemp;
let roomItemTemplate;
let rooms;
let typingStatusDiv;
let activeRoom = null;
let roomCreationAvatarBlob = null;
function initializeGroupChatModule(roomsData){
    rooms = roomsData;
    roomMsgTemp = document.getElementById('message-template');
    roomItemTemplate = document.getElementById('selection-item-template');
    inputField = document.querySelector(".input-field");
    typingStatusDiv = document.querySelector('.isTypingStatus');

    if(roomsData){
        roomsData.forEach(roomItem => {
            createRoomItem(roomItem);
            if(roomItem.hasUnreadMessages){
              flagRoomUnreadMessages(roomItem.slug, true);
            }
            connectWebSocket(roomItem.slug);
            connectWhisperSocket(roomItem.slug)
        });
    }
    document.querySelector('.chatlog').querySelector('.scroll-container').addEventListener('scroll', function() {
        isScrolling = true;
    });
    document.querySelector('.chatlog').querySelector('.scroll-container').addEventListener('scroll', function() {
        setTimeout(function() {
            isScrolling = false;
        }, 800);
    });

    const input = document.getElementById('input-container');
    initFileUploader(input);

    initializeChatlogFunctions();
}

//#region INPUT EVENTS
function onHandleKeydownRoom(event){
    if(event.key === "Enter" && !event.shiftKey){
        event.preventDefault();
        selectActiveThread(event.target);
        onSendMessageToRoom(event.target);
    }
}

function onSendClickRoom(btn){
    selectActiveThread(btn);

    //get inputfield relative to the button for multiple inputfields
    const inputWrapper = btn.closest('.input');
    const inputField = inputWrapper.querySelector('.input-field');
    onSendMessageToRoom(inputField);
}


//#endregion



//#region MESSAGE CONTROLLS

async function onSendMessageToRoom(inputField) {

    if(inputField.value.trim() === "") {
        return;
    }

    inputText = escapeHTML(inputField.value.trim());

    /// UPLOAD ATTACHMENTS
    const input = inputField.closest('.input');
    const attachments = await uploadAttachmentQueue(input.id, 'room', activeRoom.slug);


    const roomKey = await keychainGet(activeRoom.slug);
    const cryptoMsg = await encryptWithSymKey(roomKey, inputText, false);

    const messageObj = {
        'content': {
            "text": {
                'ciphertext': cryptoMsg.ciphertext,
                "iv": cryptoMsg.iv,
                "tag": cryptoMsg.tag,
            },
            "attachments": attachments
        },
        'threadId' : activeThreadIndex,
    };

    const submittedObj = await submitMessageToServer(messageObj, `/req/room/sendMessage/${activeRoom.slug}`)
    submittedObj.content.text = inputText;
    submittedObj.filteredContent = detectMentioning(inputText);

    // empty input field
    inputField.value = "";
    resizeInputField(inputField);
    const fileAtchs = input.querySelector('.file-attachments');
    fileAtchs.querySelector('.attachments-list').innerHTML = "";
    fileAtchs.classList.remove('active');

    addMessageToChatlog(submittedObj);


    /// if HAWKI is targeted send copy to stream controller
    if(submittedObj.filteredContent.aiMention && submittedObj.filteredContent.aiMention.toLowerCase().includes(aiHandle.toLowerCase())){

        const aiCryptoSalt = await fetchServerSalt('AI_CRYPTO_SALT');
        const aiKey = await deriveKey(roomKey, activeRoom.slug, aiCryptoSalt);
        const aiKeyRaw = await exportSymmetricKey(aiKey);
        const aiKeyBase64 = arrayBufferToBase64(aiKeyRaw);

        const webSearchActive = inputField.closest('.input-container').querySelector('#websearch-btn').classList.contains('active');
        const tools = {
            'web_search': webSearchActive
        }

        const msgAttributes = {
            'threadIndex': activeThreadIndex,
            'broadcasting': true,
            'slug': activeRoom.slug,
            'key': aiKeyBase64,
            'stream': false,
            'tools': tools
        }

        buildRequestObject(msgAttributes);
    }

}


const connectWebSocket = (roomSlug) => {
    const webSocketChannel = `Rooms.${roomSlug}`;

    window.Echo.private(webSocketChannel)
        .listen('RoomMessageEvent', async (e) => {
            try {
                const receivedPacket = e.data;
                // console.log('Received Packet', receivedPacket);
                if(receivedPacket.type === 'message'){
                    const messageData = await requestMessageContent(receivedPacket.data.message_id,
                                                                            receivedPacket.data.slug);

                    if(activeRoom && activeRoom.slug === roomSlug){
                        if(messageData.message_role !== 'assistant'){
                            await handleUserMessages(messageData, roomSlug)
                        }else{
                            await handleAIMessage(messageData, roomSlug)
                        }
                        if(messageData.author.username !== userInfo.username){
                            playSound('in');
                        }
                    }
                    else{
                        if(messageData.author.username !== userInfo.username){
                            playSound('out');
                        }
                        flagRoomUnreadMessages(roomSlug, true);
                    }
                }

                if(receivedPacket.type === "messageUpdate"){
                    const messageData = await requestMessageContent(receivedPacket.data.message_id,
                                                                    receivedPacket.data.slug);
                    await handleUpdateMessage(messageData, roomSlug)
                }

                if(receivedPacket.type === "status"){
                    if (receivedPacket.data.isGenerating) {
                        // Display the typing indicator for the user
                        addUserToTypingList(receivedPacket.data.model);
                    } else {
                        // Hide the typing indicator for the user
                        removeUserFromTypingList(receivedPacket.data.model);
                    }
                }

            } catch (error) {
                console.error("Failed to decompress message:", error);
            }
        });
};

async function requestMessageContent(messageId, slug){
    try{
        const response = await fetch(`/req/room/message/get/${slug}/${messageId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
        });

        if(!response.ok){
            console.error(`HTTP error! status: ${response.status}`);
            return null;
        }
        return await response.json();
    } catch (error) {
        console.error('There was a problem with the fetch operation:', error);
    }

}


async function handleUserMessages(messageData, slug){
    const roomKey = await keychainGet(slug);
    messageData.content.text = await decryptWithSymKey(roomKey,
                                                        messageData.content.text.ciphertext,
                                                        messageData.content.text.iv,
                                                        messageData.content.text.tag);

    let element = document.getElementById(messageData.message_id);
    if (!element) {
        element = addMessageToChatlog(messageData, true);
        activateMessageControls(element);
    }
    else{
        updateMessageElement(element, messageData);
    }

    // Observe unread messages
    if(element.dataset.read_stat === 'false'){
        observer.observe(element);
    }
    if(!element.querySelector('.branch')){
        const thread = element.parentElement;
        checkThreadUnreadMessages(thread);
    }
}


async function handleAIMessage(messageData, slug){

    const roomKey = await keychainGet(slug);
    const aiCryptoSalt = await fetchServerSalt('AI_CRYPTO_SALT');
    const aiKey = await deriveKey(roomKey, slug, aiCryptoSalt);

    messageData.content.text = await decryptWithSymKey(aiKey,
                                                        messageData.content.text.ciphertext,
                                                        messageData.content.text.iv,
                                                        messageData.content.text.tag);

    // CREATE AND UPDATE MESSAGE
    let element = document.getElementById(messageData.message_id);
    if (!element) {
        element = addMessageToChatlog(messageData, true);
        activateMessageControls(element);
    }else{
        updateMessageElement(element, messageData);
    }

    // Observe unread messages
    if(element.dataset.read_stat === 'false'){
        observer.observe(element);
    }
    if(!element.querySelector('.branch')){
        const thread = element.parentElement;
        checkThreadUnreadMessages(thread);
    }
}

async function handleUpdateMessage(messageData, slug){
    let key;
    const roomKey = await keychainGet(slug);

    if(messageData.message_role === 'assistant'){
        const aiCryptoSalt = await fetchServerSalt('AI_CRYPTO_SALT');
        key = await deriveKey(roomKey, slug, aiCryptoSalt);
    }else{
        key = roomKey;
    }

    messageData.content.text = await decryptWithSymKey(key,
                                                    messageData.content.text.ciphertext,
                                                    messageData.content.text.iv,
                                                    messageData.content.text.tag);

    let element = document.getElementById(messageData.message_id);

    regenerateBtn = element.querySelector('#regenerate-btn');
    if(regenerateBtn && regenerateBtn.disabled){
        regenerateBtn.disabled = false;
        regenerateBtn.style.opacity = '1';
    }

    updateMessageElement(element, messageData, true);


    // Observe unread messages
    if(element.dataset.read_stat === 'false'){
        observer.observe(element);
    }
    if(!element.querySelector('.branch')){
        const thread = element.parentElement;
        checkThreadUnreadMessages(thread);
    }
}


//#endregion




//#region STATUS UPDATES

let typingTimer;
const typingInterval = 1000; // 1 second
let isTyping = false;
let typingUsers = {}; // Object to track users who are typing
const typingTimeout = 5000; // 5 seconds timeout


function onGroupchatType() {
    // Start or reset the timer on keydown
    clearTimeout(typingTimer);

    if (!isTyping) {
        isTyping = true;
        startTyping();
    }

    // Set the timer to stop typing after the interval
    typingTimer = setTimeout(stopTyping, typingInterval);
}

function startTyping() {
    const webSocketChannel = `Rooms.${activeRoom.slug}`;

    Echo.private(webSocketChannel)
        .whisper('typing', {
            user: userInfo.username,
            typing: true
        });
}

function stopTyping() {
    isTyping = false;
    const webSocketChannel = `Rooms.${activeRoom.slug}`;

    Echo.private(webSocketChannel)
        .whisper('typing', {
            user: userInfo.username,
            typing: false
        });
}

function connectWhisperSocket(roomSlug){

    const webSocketChannel = `Rooms.${roomSlug}`;
    Echo.private(webSocketChannel)
    .listenForWhisper('typing', (e) => {
        if (activeRoom.slug !== roomSlug) return;

        if (e.typing) {
            // Display the typing indicator for the user
            addUserToTypingList(e.user);
        } else {
            // Hide the typing indicator for the user
            removeUserFromTypingList(e.user);
        }
        updateTypingStatus();
    });
}


function addUserToTypingList(user) {
    if (typingUsers[user]) {
        clearTimeout(typingUsers[user]);
    }

    // Add/update the user with a timeout to remove them after the typingTimeout
    typingUsers[user] = setTimeout(() => {
        removeUserFromTypingList(user);
        updateTypingStatus();
    }, typingTimeout);
    updateTypingStatus();
}

function removeUserFromTypingList(user) {
    if (typingUsers[user]) {
        clearTimeout(typingUsers[user]);
        delete typingUsers[user];
    }
    updateTypingStatus();
}

function updateTypingStatus() {
    const users = Object.keys(typingUsers);

    if (users.length === 0) {
        typingStatusDiv.textContent = '';
        typingStatusDiv.style.display = 'none'; // Hide if no one is typing
    } else if (users.length === 1) {
        typingStatusDiv.textContent = `${users[0]} is typing...`;
        typingStatusDiv.style.display = 'block';
    } else if (users.length === 2) {
        typingStatusDiv.textContent = `${users[0]} & ${users[1]} are typing...`;
        typingStatusDiv.style.display = 'block';
    } else {
        typingStatusDiv.textContent = `${users[0]} & others are typing...`;
        typingStatusDiv.style.display = 'block';
    }
}

//#endregion



//#region ROOM CONTROLLS

function openRoomCreatorPanel(){
    activeRoom = null;
    history.replaceState(null, '', `/groupchat`);
    switchDyMainContent('room-creation');

    const lastActive = document.getElementById('rooms-list').querySelector('.selection-item.active');
    if(lastActive){
        lastActive.classList.remove('active')
    }

    const roomCreationPanel = document.getElementById('room-creation');

    defaultPromt = translation.Default_Prompt;

    roomCreationPanel.querySelector('#chat-name-input').value = '';
    roomCreationPanel.querySelector('#user-search-bar').value = '';
    roomCreationPanel.querySelector('#room-description-input').value = '';
    roomCreationPanel.querySelector('#room-creation-avatar').setAttribute('src', '');
    roomCreationPanel.querySelector('#room-creation-avatar').style.display = 'none';


    roomCreationPanel.querySelector('#system-prompt-input').value = defaultPromt;
    resizeInputField(roomCreationPanel.querySelector('#system-prompt-input'));
}

function finishRoomCreation(){
    const textareas = document.querySelector('.inputs-list').querySelectorAll('textarea');
    textareas.forEach(txt => {
        txt.value = "";
    });
    const addedMembers = document.querySelector('.added-members-list');
    while (addedMembers.firstChild) {
        addedMembers.removeChild(addedMembers.lastChild);
    }

    if(activeRoom){
        switchDyMainContent('chat');
        history.replaceState(null, '', `/groupchat/${activeRoom.slug}`);
    }
    else{
        switchDyMainContent('group-welcome-panel');
        history.replaceState(null, '', `/groupchat`);
    }
}


async function createNewRoom(){

    const inputs = document.querySelector('.inputs-list');
    const name = inputs.querySelector('#chat-name-input').value;
    const description = inputs.querySelector('#room-description-input').value;

    if (!name || !description) {
        document.getElementById('room-creation').querySelector('#alert-message').innerText = 'Please Fill all the required inputs.';
        return;
    }

    requestObj = {
        'room_name': name,
    }

    try {
        fetch('/req/room/createRoom', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify(requestObj)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                onSuccessfulRoomCreation(data.roomData);

            } else {
                // Handle unexpected response
                console.error('Unexpected response:', data);
                alert('Failed to create room. Please try again.');
            }
        })
    } catch (error) {
        console.error('There was a problem with the fetch operation:', error);
    }
}


async function onSuccessfulRoomCreation(roomData){

    const inputs = document.querySelector('.inputs-list');
    const description = inputs.querySelector('#room-description-input').value;
    const systemPrompt = inputs.querySelector('#system-prompt-input').value;
    const image = roomCreationAvatarBlob;

    //generate encryption key
    const roomKey = await generateKey();

    //save key in keychain (don't need to wait for it)
    await keychainSet(roomData.slug, roomKey, true);

    //encrypt room description and system prompt
    const cryptDescription = await encryptWithSymKey(roomKey, description, false);
    const descriptionStr = JSON.stringify({
        'ciphertext':cryptDescription.ciphertext,
        'iv':cryptDescription.iv,
        'tag':cryptDescription.tag,
    });
    const cryptSystemPrompt = await encryptWithSymKey(roomKey, systemPrompt, false);
    const systemPromptStr = JSON.stringify({
        'ciphertext':cryptSystemPrompt.ciphertext,
        'iv':cryptSystemPrompt.iv,
        'tag':cryptSystemPrompt.tag,
    });


    const formData = new FormData();
    if(systemPromptStr) formData.append('system_prompt', systemPromptStr)
    if(descriptionStr)formData.append('description', descriptionStr)
    if(image) formData.append('image',  image)

    updateRoomInfo(roomData.slug, formData)
    rooms.push(roomData);

    //create invitation
    // Loop through the invitees to handle the encryption
    const membersBtnList = document.querySelector('.inputs-list').querySelector('.added-members-list').querySelectorAll('.added-member');

    let usersList = [];

    membersBtnList.forEach(element => {
        const user = JSON.parse(element.dataset.obj);
        usersList.push(user);
    });

    await createAndSendInvitations(usersList, roomData.slug);

    //close UI
    finishRoomCreation();
    //create sidebar button
    createRoomItem(roomData);
    //load room
    loadRoom(null, roomData.slug);
    //connect to broadcasting
    connectWebSocket(roomData.slug);
}

//#endregion

//#region INVITATION MANAGEMENT


async function sendInvitation(btn){
    const invModal = btn.closest('.modal-content');

    const addedList = invModal.querySelector('.added-members-list');
    listOfInvitees = [];
    addedList.childNodes.forEach(child => {
        if (child.dataset && child.dataset.obj) {
            const userObj = JSON.parse(child.dataset.obj);
            listOfInvitees.push(userObj);
        }
    });
    await createAndSendInvitations(listOfInvitees, activeRoom.slug);
    closeModal(btn);
}

async function createAndSendInvitations(usersList, roomSlug){

    const roomKey = await keychainGet(roomSlug);
    const invitations = [];
    for (const invitee of usersList) {
        let invitation;
        if (invitee.publicKey) {

            const encryptedRoomKey = await encryptWithPublicKey(roomKey, base64ToArrayBuffer(invitee.publicKey));

            invitation = {
                username: invitee.username,
                encryptedRoomKey: encryptedRoomKey.ciphertext, // This should be just the encrypted data for public key
                iv: '0',
                tag: '0',
                role: invitee.role
            };

        } else {

            // Generate a temporary hash for this invitee
            const tempHash = generateTempHash(); // Generate a temporary hash
            const encryptedRoomKey = await encryptWithTempHash(roomKey, tempHash);

            invitation = {
                username: invitee.username,
                encryptedRoomKey: encryptedRoomKey.ciphertext,
                iv: encryptedRoomKey.iv,
                tag: encryptedRoomKey.tag,
                role: invitee.role
            };

            const mailContent = {
                username: invitee.username,
                hash: tempHash,
                slug: roomSlug
            }
            await sendInvitationEmail(mailContent);
        }
        invitations.push(invitation);
    }
    //store invitations on database
    requestStoreInvitationsOnServer(invitations, roomSlug);
}



async function requestStoreInvitationsOnServer(invitations, slug){
    // Send the invitations to the server to store
    await fetch(`/req/inv/store-invitations/${slug}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify({invitations})
    });
}

async function sendInvitationEmail(mailContent){
    // Send the invitations to the server to store
    await fetch(`/req/inv/sendExternInvitation`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify(mailContent)
    });

}


async function handleUserInvitations() {
    try{
        const response = await fetch('/req/inv/requestUserInvitations', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
        });

        if(!response.ok){
            console.error(`HTTP error! status: ${response.status}`);
            return null;
        }

        const data = await response.json();
        if(data.formattedInvitations){

            const invitations = data.formattedInvitations;
            const privateKeyBase64 = await keychainGet('privateKey');
            // Retrieve and convert private key
            const privateKey = base64ToArrayBuffer(privateKeyBase64);

            for (const inv of invitations) {
                try {

                    // Convert the encryptedRoomKey from Base64 to ArrayBuffer
                    const encryptedRoomKeyBuffer = base64ToArrayBuffer(inv.invitation);
                    // Decrypt the roomKey using the user's private key
                    const roomKey = await decryptWithPrivateKey(encryptedRoomKeyBuffer, privateKey);
                    if (roomKey) {
                        await finishInvitationHandling(inv.invitation_id, roomKey)
                    }
                } catch (error) {
                    console.error(`Failed to decrypt invitation: ${inv.invitation_id}`, error);
                }
            }
        }
        return 'Error fetching public keys';
    }
    catch (error){
        console.error('Error fetching public keys data:', error);
        throw error;
    }
}

async function handleTempLinkInvitation(tempLink){
    const parsedLink = JSON.parse(tempLink);
    tempHash = parsedLink.tempHash;
    slug = parsedLink.slug;

    // GET INVITATION OBJECT
    try{
        const response = await fetch(`/req/inv/requestInvitation/${slug}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
        });

        if(!response.ok){
            console.error(`HTTP error! status: ${response.status}`);
            return null;
        }
        const data = await response.json();
        roomKey = await decryptWithTempHash(data.invitation, tempHash, data.iv, data.tag);
        if(roomKey){
            await finishInvitationHandling(data.invitation_id, roomKey);
        }
    }
    catch (err){
        console.error('Error fetching data:', err);
        throw err;
    }
}

async function finishInvitationHandling(invitation_id, roomKey){
    // Send invitation_id to server to confirm successful decryption
    const response = await fetch('/req/inv/roomInvitationAccept', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ invitation_id: invitation_id })
    });

    const data = await response.json();
    if(data.success){

        await keychainSet(data.room.slug, roomKey, true);

        createRoomItem(data.room);
        connectWebSocket(data.room.slug);
        connectWhisperSocket(data.room.slug);
    }
}

function openInvitationPanel(){
    const modal = document.querySelector('#add-member-modal');
    modal.querySelector('#user-search-bar').value = '';
    modal.querySelector('#searchResults').innerHTML = '';
    modal.querySelector('#searchResults').style.display = 'none';
    modal.querySelector('.added-members-list').innerHTML = '';
    tempSearchResult='';
    modal.style.display = 'flex';
}





//#endregion

//#region Load Room

function createRoomItem(roomData){
    const roomElement = roomItemTemplate.content.cloneNode(true);
    const roomsList = document.getElementById('rooms-list');

    const label = roomElement.querySelector('.label');
    label.textContent = roomData.room_name;

    roomElement.querySelector('.selection-item').setAttribute('slug', roomData.slug);
    roomsList.insertBefore(roomElement, roomsList.firstChild);
}


async function loadRoom(btn=null, slug=null){
    if(rooms.length === 0){
        history.replaceState(null, '', `/groupchat`);
        switchDyMainContent('group-welcome-panel');
        return;
    }

    if(!btn && !slug){
        return;
    }

    if(!slug) slug = btn.getAttribute('slug');
    if(!btn) btn = document.querySelector(`.selection-item[slug="${slug}"]`);


    let roomData;
    try{
        roomData = await RequestRoomContent(slug);
    }
    catch{
        console.error('room not found', slug);
        history.replaceState(null, '', `/groupchat`);
        switchDyMainContent('group-welcome-panel');
        return;
    }

    const lastActive = document.getElementById('rooms-list').querySelector('.selection-item.active');
    if(lastActive){
        lastActive.classList.remove('active')
    }
    btn.classList.add('active');

    switchDyMainContent('chat');
    history.replaceState(null, '', `/groupchat/${slug}`);

    clearChatlog();
    clearInput();

    activeRoom = roomData;
    const chatControlPanel = document.querySelector('#room-control-panel');
    chatControlPanel.querySelector('#chat-name').textContent = roomData.name;
    chatControlPanel.querySelector('#chat-slug').textContent = roomData.slug;

    if(roomData.room_icon){
        chatControlPanel.querySelector('#info-panel-chat-icon').style.display = "block";
        chatControlPanel.querySelector('#control-panel-chat-initials').style.display = "none";
        chatControlPanel.querySelector('#info-panel-chat-icon').setAttribute('src', roomData.room_icon);
    }
    else{
        chatControlPanel.querySelector('#info-panel-chat-icon').style.display = "none";
        chatControlPanel.querySelector('#control-panel-chat-initials').style.display = "block";
        chatControlPanel.querySelector('#control-panel-chat-initials').innerHTML = roomData.name.slice(0, 2).toUpperCase();
        chatControlPanel.querySelector('#info-panel-chat-icon').setAttribute('src', '');
    }

    loadRoomMembers(roomData);

    const roomKey = await keychainGet(slug);
    const aiCryptoSalt = await fetchServerSalt('AI_CRYPTO_SALT');
    const aiKey = await deriveKey(roomKey, slug, aiCryptoSalt);

    if(roomData.room_description){
        const descriptObj = JSON.parse(roomData.room_description);
        const roomDescription = await decryptWithSymKey(roomKey, descriptObj.ciphertext, descriptObj.iv, descriptObj.tag, false);
        chatControlPanel.querySelector('#description-field').textContent = roomDescription;
        activeRoom.room_description = roomDescription;
    }
    if(roomData.system_prompt){
        const systemPromptObj = JSON.parse(roomData.system_prompt);
        const systemPrompt = await decryptWithSymKey(roomKey, systemPromptObj.ciphertext, systemPromptObj.iv, systemPromptObj.tag, false);
        chatControlPanel.querySelector('#system_prompt-field').innerText = systemPrompt;
        document.getElementById('input-controls-props-panel').querySelector('#system_prompt_field').textContent = systemPrompt;
        activeRoom.system_prompt = systemPrompt;
    }

    for (const msgData of roomData.messagesData) {
        const key = msgData.message_role === 'assistant' ? aiKey : roomKey;
        msgData.content.text = await decryptWithSymKey(key, msgData.content.text.ciphertext,
                                                            msgData.content.text.iv,
                                                            msgData.content.text.tag, false);
    }
    filterRoleElements(roomData.role);
    loadMessagesOnGUI(roomData.messagesData);
    scrollToLast(true);
}



function loadRoomMembers(roomData) {
    const membersList = document.getElementById('room-control-panel').querySelector('.members-list');
    // Clear existing members
    membersList.innerHTML = `
        <button class="btn-sm add-member-btn admin-only" id="invite-btn" onclick="openInvitationPanel()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>`;

    roomData.members.forEach(member => {
        if (member.employeetype === 'system') return;

        const memberBtnTemp = document.getElementById('member-listBtn-template').content.cloneNode(true);
        const memberBtnIcon = memberBtnTemp.querySelector('#member-icon');
        const memberBtnInit = memberBtnTemp.querySelector('#member-init');
        const memberBtn = memberBtnTemp.querySelector('#member-btn');

        if (member.avatar_url) {
            memberBtnIcon.setAttribute('src', member.avatar_url);
            memberBtnInit.remove();
        } else {
            memberBtnIcon.remove();
            memberBtnInit.textContent = member.name.slice(0, 2).toUpperCase();
        }
        // Set member object in the button attribute
        memberBtn.setAttribute('memberObj', JSON.stringify(member));
        // Append to the header and the list
        membersList.insertBefore(memberBtnTemp, membersList.querySelector('#invite-btn'));
    });
}



async function RequestRoomContent(slug){

    url = `/req/room/${slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try{
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });

        if(!response.ok){
            console.error(`HTTP error! status: ${response.status}`);
            return null;
        }

        return await response.json();
    }
    catch (err){
        console.error('Error fetching data:', error);
        throw err;
    }
}

const MemberRoles = {
    ADMIN: { id: 'admin', className: 'admin-only' },
    EDITOR: { id: 'editor', className: 'editor-only' },
    VIEWER: { id: 'viewer', className: 'viewer-only' }
};
function filterRoleElements(roleId) {
    const role = Object.values(MemberRoles).find(r => r.id === roleId);

    if (!role) {
        throw new Error('Invalid User Role.');
    }

    const elementsByClass = (role) => document.querySelectorAll(`.${role.className}`);
    const toggleDisplay = (elements, shouldShow) => elements.forEach(element => {
        if (shouldShow) {
            const originalDisplay = element.dataset.originalDisplay || element.style.display;
            element.style.display = originalDisplay || 'block';
        } else {
            if (!element.dataset.originalDisplay) {
                element.dataset.originalDisplay = window.getComputedStyle(element).display;
            }
            element.style.display = 'none';
        }
    });

    for (const currentRole of Object.values(MemberRoles)) {
        const elements = elementsByClass(currentRole);
        if (roleId === MemberRoles.ADMIN.id || roleId === currentRole.id) {
            toggleDisplay(elements, true);
        } else {
            toggleDisplay(elements, false);
        }
    }
}

//#endregion

//#region Add Member
let tempSearchResult;
async function searchUser(searchBar) {
    const query = searchBar.value.trim();
    const resultPanel = searchBar.closest('.search-panel').querySelector('#searchResults');

    if (query.length > 2) { // Start searching after 3 characters
        try {
            // const response = await fetch(`/req/room/search?query=${encodeURIComponent(query)}`);
            const response = await fetch(`/req/room/search`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    "query": encodeURIComponent(query)
                })

            });

            const data = await response.json();

            if (data.success) {
                resultPanel.innerHTML = ''; // Clear previous results

                const addedList = searchBar.closest('.add-members-section').querySelector('.added-members-list');
                ignoreList = [];
                ignoreList.push('HAWKI');
                ignoreList.push(userInfo.username);
                if(activeRoom){
                    activeRoom.members.forEach(member => {
                        ignoreList.push(member.username);
                    });
                }
                addedList.childNodes.forEach(child => {
                    if (child.dataset && child.dataset.obj) {
                        const username = JSON.parse(child.dataset.obj).username;
                        ignoreList.push(username);
                    }
                });

                data.users.forEach(user => {
                    // Check if the user's username is already in the ignoreList
                    const isAlreadyAdded = ignoreList.some(invitedUsername => invitedUsername === user.username);
                    if(isAlreadyAdded){
                        const index = data.users.indexOf(user);
                        data.users.splice(index, 1);
                        return;
                    }

                    const option = document.createElement('li');
                    option.dataset.value = JSON.stringify(user);
                    option.innerText = `${user.name} - ${user.username} (${user.email})`;
                    option.addEventListener('click', ()=>{
                        searchBar.value = user.username; // Fill the search bar with the selected username
                        tempSearchResult = JSON.stringify(user);
                        resultPanel.innerHTML = '';
                        resultPanel.style.display = "none";
                    })

                    resultPanel.appendChild(option);

                });
                resultPanel.style.display = data.users.length > 0 ? "block" : "none";

            } else {
                resultPanel.style.display = "none";
                resultPanel.innerHTML = ''; // Clear results if no user found
            }
        } catch (error) {
            console.error('There was an error processing your request.', error);
            // Handle the error appropriately here
        }
    } else {
        resultPanel.style.display = "none";
        resultPanel.innerHTML = ''; // Clear results if query is too short
    }
}

function onHandleKeydownUserSearch(event, searchBar){
    if(event.key === "Enter" && !event.shiftKey){
        event.preventDefault();

        const resultsPanel = searchBar.closest('.search-panel').querySelector('#searchResults');
        if(resultsPanel.childElementCount > 0 ){
            const first = resultsPanel.firstElementChild
            searchBar.value = first.innerText;
            tempSearchResult = first.dataset.value;
            resultsPanel.innerHTML = '';
            resultsPanel.style.display = "none";
            return;
        }
        addUserToList(searchBar);
    }
}

function onAddUserButton(btn){
    const srcPanel = btn.closest('.search-panel');
    const searchBar = srcPanel.querySelector("#user-search-bar");
    addUserToList(searchBar);
}

function addUserToList(searchBar) {

    const selectedUser = searchBar.value.trim();
    if (!selectedUser || !tempSearchResult || tempSearchResult.length === 0) {
        // alert('Please select a valid user.');
        return;
    }

    // Ensure the added member list exists
    const addedList = searchBar.closest('.add-members-section').querySelector('.added-members-list');
    if (!addedList) {
        return;
    }
    // Clear the search bar value
    searchBar.value = '';

    // Create a new element for the added user
    const temp = document.getElementById('added-member-template');
    const item = temp.content.cloneNode(true);
    const element = item.querySelector('.added-member');

    // Store the user object with the selected
    tempSearchResult = JSON.parse(tempSearchResult);
    tempSearchResult.role = searchBar.parentElement.querySelector('#user-role-selector').value;
    element.dataset.obj = JSON.stringify(tempSearchResult);
    element.querySelector('p').innerHTML = `<b>${tempSearchResult.name}</b> - ${tempSearchResult.role}`;

    // Apply a random background color
    element.style.backgroundColor = generateRandomColor();

    // Add the new element to the list
    addedList.appendChild(element);

    // Optionally clear the temporary search result for new searches
    tempSearchResult = null;
}

function removeAddedMember(btn){
    const am = btn.closest('.added-member');
    am.remove();
}

function generateRandomColor() {
    const r = Math.floor(Math.random() * 128) + 127; // Random value between 127 and 255
    const g = Math.floor(Math.random() * 128) + 127;
    const b = Math.floor(Math.random() * 128) + 127;
    return `rgba(${r}, ${g}, ${b}, 0.7)`;
}
//#endregion

//#region Room Control Panel
function openRoomCP(){

    // if edit modes are still active deactivate them
    const cp = document.getElementById('room-control-panel')
    const editBtns = cp.querySelectorAll('#edit-abort');
    editBtns.forEach(btn => {
        if(btn.closest('.edit-panel').parentElement.querySelector('.text-field').getAttribute('contenteditable') === true){
            abortTextPanelEdit(btn);
        }
    });

    const textField = document.getElementById('system_prompt-field');
    textField.addEventListener('paste', function(e) {
        // Prevent the default paste behavior
        e.preventDefault();

        // Get clipboard data as plain text
        const text = (e.clipboardData || window.clipboardData).getData('text');

        // Insert the plain text at the cursor position
        document.execCommand('insertText', false, text);
    });
    const descField = document.getElementById('description-panel');
    descField.addEventListener('paste', function(e) {
        // Prevent the default paste behavior
        e.preventDefault();

        // Get clipboard data as plain text
        const text = (e.clipboardData || window.clipboardData).getData('text');

        // Insert the plain text at the cursor position
        document.execCommand('insertText', false, text);
    });
    switchDyMainContent('room-control-panel');
}

function closeRoomCP(){
    submitInfoField();
    switchDyMainContent('chat');
}

function editTextPanel(btn) {
    const editPanel = btn.closest('.edit-panel');
    const textPanel = editPanel.closest('.text-cont');
    const textField = textPanel.querySelector('.text-field');

    textField.dataset.txtCache = textField.innerText;

    // Switch buttons
    const confirmBtn = editPanel.querySelector('#edit-confirm');
    const abortBtn = editPanel.querySelector('#edit-abort');

    confirmBtn.style.display = "inline-block";
    abortBtn.style.display = "inline-block";
    btn.style.display = "none";

    // Make the text field editable
    textField.setAttribute('contenteditable', true);
    if(textField.closest('.text-panel')){
        textField.closest('.text-panel').classList.add('editMode');
    }
    textField.focus();

    var range,selection;
    if(document.createRange)//Firefox, Chrome, Opera, Safari, IE 9+
    {
        range = document.createRange();
        range.selectNodeContents(textField);
        range.collapse(false);
        selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }
    else if(document.selection)//IE 8 and lower
    {
        range = document.body.createTextRange();
        range.moveToElementText(textField);
        range.collapse(false);
        range.select();
    }
}

function abortTextPanelEdit(btn){

    const editPanel = btn.closest('.edit-panel');
    const textPanel = editPanel.closest('.text-cont');
    const textField = textPanel.querySelector('.text-field');
    const editBtn = editPanel.querySelector('#edit-btn');
    const confirmBtn = editPanel.querySelector('#edit-confirm');

    btn.style.display = "none";
    confirmBtn.style.display = "none";
    editBtn.style.display = "inline-block";

    textField.setAttribute('contenteditable', false);
    if(textField.closest('.text-panel')){
        textField.closest('.text-panel').classList.remove('editMode');
    }
    textField.innerText = textField.dataset.txtCache;
    textField.removeAttribute('data-txtCache')
}

function confirmTextPanelEdit(btn){
    const editPanel = btn.closest('.edit-panel');
    const textPanel = editPanel.closest('.text-cont');
    const textField = textPanel.querySelector('.text-field');
    const editBtn = editPanel.querySelector('#edit-btn');
    const abortBtn = editPanel.querySelector('#edit-abort');

    btn.style.display = "none";
    abortBtn.style.display = "none";
    editBtn.style.display = "inline-block";

    textField.setAttribute('contenteditable', false);
    if(textField.closest('.text-panel')){
        textField.closest('.text-panel').classList.remove('editMode');
    }
    textField.removeAttribute('data-txtCache')
}

async function submitInfoField(){

    const roomCP = document.getElementById('room-control-panel');


    const chatName = roomCP.querySelector('#chat-name').textContent;
    document.getElementById('rooms-list')
            .querySelector(`.selection-item[slug="${activeRoom.slug}"`)
            .querySelector('.label').innerText = chatName;

    const description = roomCP.querySelector('#description-field').textContent;
    const systemPrompt = roomCP.querySelector('#system_prompt-field').textContent;

    const roomKey = await keychainGet(activeRoom.slug);

    const cryptDescription = await encryptWithSymKey(roomKey, description, false);
    const descriptionStr = JSON.stringify({
        'ciphertext':cryptDescription.ciphertext,
        'iv':cryptDescription.iv,
        'tag':cryptDescription.tag,
    });
    const cryptSystemPrompt = await encryptWithSymKey(roomKey, systemPrompt, false);
    const systemPromptStr = JSON.stringify({
        'ciphertext':cryptSystemPrompt.ciphertext,
        'iv':cryptSystemPrompt.iv,
        'tag':cryptSystemPrompt.tag,
    });

    const formData = new FormData();
    if(chatName) formData.append('name', chatName);
    if(systemPromptStr) formData.append('system_prompt', systemPromptStr)
    if(descriptionStr)formData.append('description', descriptionStr)

    updateRoomInfo(activeRoom.slug, formData);

}


async function requestDeleteRoom() {

    const confirmed = await openModal(ModalType.CONFIRM, translation.Cnf_deleteRoom);
    if (!confirmed) {
        return;
    }

    const url = `/req/room/removeRoom/${activeRoom.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });
        const data = await response.json();

        if (data.success) {
            removeListItem(activeRoom.slug);
        } else {
            console.error('Room removal was not successful!');
        }
    } catch (error) {
        console.error('Failed to remove room!');
    }
}


async function leaveRoom(){

    const confirmed = await openModal(ModalType.CONFIRM, translation.Cnf_leaveRoom);
    if (!confirmed) {
        return;
    }

    const url = `/req/room/leaveRoom/${activeRoom.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });
        const data = await response.json();

        if (data.success) {
            removeListItem(activeRoom.slug);
            loadRoom(list.firstElementChild, null);
            switchDyMainContent('chat');

        } else {
            console.error('Room leave was not successful!');
        }
    } catch (error) {
        console.error('Failed to leave room!');
    }
}


function removeListItem(slug){
        const listItem = document.querySelector(`.selection-item[slug="${slug}"]`);
        const list = listItem.parentElement;
        listItem.remove();

        if(list.childElementCount > 0){
            loadRoom(list.firstElementChild, null);
            switchDyMainContent('chat');

        }
        else{
            switchDyMainContent('group-welcome-panel');
            history.replaceState(null, '', `/groupchat`);
        }
}

async function removeMemberFromRoom(username){

    const confirmed = await openModal(ModalType.CONFIRM, translation.Cnf_removeMember);
    if (!confirmed) {
        return false;
    }

    const url = `/req/room/removeMember/${activeRoom.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({'username': username})
        });
        const data = await response.json();

        if (data.success) {
            return true;
        } else {
            console.error('Removeing user was not successful!');
        }
    } catch (error) {
        console.error('Failed to remove user!');
    }

}

//#endregion

//#region Room Info controls

function selectRoomAvatar(btn, upload = false){

    const imageElement = btn.parentElement.querySelector('.selectable-image');
    const initials = btn.parentElement.querySelector('#control-panel-chat-initials');
    openImageSelection(imageElement.getAttribute('src'), async function(croppedImage) {
        let url;
        if(upload){
            url = await uploadRoomAvatar(croppedImage);
        }
        else{
            url = URL.createObjectURL(croppedImage);
        }

        imageElement.style.display = 'block';
        if(initials){
            initials.style.display = 'none';
        }
        imageElement.setAttribute('src', url);
        roomCreationAvatarBlob = croppedImage;
    });
}

async function uploadRoomAvatar(image){
    console.log("uploadRoomAvatar");
    const url = `/req/room/uploadAvatar/${activeRoom.slug}`;

    const temp = activeRoom ? 1 : 0;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const formData = new FormData();
    formData.append('image', image);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            // console.log('Image Uploaded Successfully');
            return data.url;

        } else {
            console.error('Upload not successfull');
        }
    } catch (error) {
        console.error('Failed to upload image to server!');
    }
}


async function updateRoomInfo(slug, formData){

    if(!slug){
        slug = activeRoom.slug;
        if(!slug){
            console.error('room slug not found');
        }
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const url = `/req/room/updateInfo/${slug}`;

    try{
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',

            },
            body: formData
        });

        if(!response.ok){
            console.log('response');
            console.log(response);
            console.error(`HTTP error! status: ${response.status}`);
            return null;
        }

        const data = await response.json();

        if(data.success){
            return data;
            console.log('Room Information updated successfully');
        }
    }
    catch (error){
        console.error('Error fetching data:', error);
        throw error;
    }
}
//#endregion
