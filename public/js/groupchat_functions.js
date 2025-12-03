

let inputField;
let roomMsgTemp;
let roomItemTemplate;
let rooms;
let typingStatusDiv;
let activeRoom = null;
let roomCreationAvatarBlob = null;

// Helper function to check if a string starts with an emoji
function startsWithEmoji(text) {
    if (!text) return false;
    // Emoji regex pattern - matches most common emojis
    const emojiRegex = /^[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F000}-\u{1F02F}\u{1F0A0}-\u{1F0FF}\u{1F100}-\u{1F64F}\u{1F680}-\u{1F6FF}\u{1F900}-\u{1F9FF}\u{1FA00}-\u{1FA6F}\u{1FA70}-\u{1FAFF}\u{FE00}-\u{FE0F}\u{200D}]/u;
    return emojiRegex.test(text);
}

// Helper function to extract first emoji from text
function extractFirstEmoji(text) {
    if (!text) return { emoji: null, remainingText: text };
    const emojiRegex = /([\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F000}-\u{1F02F}\u{1F0A0}-\u{1F0FF}\u{1F100}-\u{1F64F}\u{1F680}-\u{1F6FF}\u{1F900}-\u{1F9FF}\u{1FA00}-\u{1FA6F}\u{1FA70}-\u{1FAFF}\u{FE00}-\u{FE0F}\u{200D}]+)/u;
    const match = text.match(emojiRegex);
    if (match) {
        const emoji = match[0];
        const remainingText = text.slice(emoji.length).trim();
        return { emoji, remainingText };
    }
    return { emoji: null, remainingText: text };
}

// Update GroupChat sidebar notification badge
function updateGroupChatSidebarBadge(type) {
    const invitationBadge = document.getElementById('groupchat-invitation-badge');
    const messageBadge = document.getElementById('groupchat-message-badge');
    
    if (!invitationBadge || !messageBadge) return;
    
    if (!type) {
        // Hide both badges
        invitationBadge.style.display = 'none';
        messageBadge.style.display = 'none';
    } else if (type === 'new-room') {
        // Show red badge only
        invitationBadge.style.display = 'block';
        messageBadge.style.display = 'none';
    } else if (type === 'new-message') {
        // Show green badge only
        invitationBadge.style.display = 'none';
        messageBadge.style.display = 'block';
    } else if (type === 'both') {
        // Show both badges
        invitationBadge.style.display = 'block';
        messageBadge.style.display = 'block';
    }
}

// Check and update sidebar badge based on current rooms state
function checkAndUpdateSidebarBadge() {
    if (!rooms || rooms.length === 0) {
        updateGroupChatSidebarBadge(null);
        return;
    }
    
    // Check for removed rooms
    const hasRemovedRooms = rooms.some(room => room.isRemoved);
    
    // Check for new room invitations (not removed)
    const hasNewRooms = rooms.some(room => room.isNewRoom && !room.isRemoved);
    
    // Check for unread messages (not removed)
    const hasUnread = rooms.some(room => room.hasUnreadMessages && !room.isRemoved);
    
    // Determine badge state
    // Priority: (removed OR new invitation) > unread
    // If both removed/new AND unread exist, show 'both'
    if ((hasRemovedRooms || hasNewRooms) && hasUnread) {
        updateGroupChatSidebarBadge('both');
    } else if (hasRemovedRooms || hasNewRooms) {
        updateGroupChatSidebarBadge('new-room'); // Red badge
    } else if (hasUnread) {
        updateGroupChatSidebarBadge('new-message'); // Green badge
    } else {
        updateGroupChatSidebarBadge(null);
    }
}

function initializeGroupChatModule(roomsData){
    
    // Merge server data with existing rooms (preserve isNewRoom from WebSocket updates)
    if (rooms && rooms.length > 0) {
        roomsData.forEach(serverRoom => {
            const existingRoom = rooms.find(r => r.slug === serverRoom.slug);
            if (existingRoom) {
                // Preserve client-side state
                if (existingRoom.hasUnreadMessages) {
                    serverRoom.hasUnreadMessages = true;
                }
                if (existingRoom.isNewRoom) {
                    serverRoom.isNewRoom = true;
                }
            }
        });
        
        // Add any rooms that exist locally but not on server (e.g., new invitations)
        rooms.forEach(localRoom => {
            const existsOnServer = roomsData.find(r => r.slug === localRoom.slug);
            if (!existsOnServer) {
                roomsData.push(localRoom);
            }
        });
    }
    
    rooms = roomsData;
    
    roomMsgTemp = document.getElementById('message-template');
    roomItemTemplate = document.getElementById('selection-item-template');
    inputField = document.querySelector(".input-field");
    typingStatusDiv = document.querySelector('.isTypingStatus');

    if(roomsData){
        roomsData.forEach(roomItem => {
            createRoomItem(roomItem);
            
            // Check if room has been removed, is a new invitation, or has unread messages
            if (roomItem.isRemoved) {
                // User was removed - red badge
                flagRoomUnreadMessages(roomItem.slug, true, true);
                // Don't connect WebSocket - user is removed
            } else if (roomItem.isNewRoom) {
                // New room invitation - red badge
                flagRoomUnreadMessages(roomItem.slug, true, true);
                // Don't connect WebSocket - user is not a member yet!
            } else {
                if (roomItem.hasUnreadMessages) {
                    // Unread messages - green badge
                    flagRoomUnreadMessages(roomItem.slug, true, false);
                }
                
                // Connect WebSocket only for rooms where user is a member
                connectWebSocket(roomItem.slug);
                connectWhisperSocket(roomItem.slug);
            }
        });
        
        // Update sidebar badge based on initial state
        checkAndUpdateSidebarBadge();
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

        const inputContainer = inputField.closest('.input-container');
        const webSearchBtn = inputContainer ? inputContainer.querySelector('#websearch-btn') : null;
        const webSearchActive = webSearchBtn ? webSearchBtn.classList.contains('active') : false;
        
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
                        
                        // Mark room as having unread messages
                        const room = rooms.find(r => r.slug === roomSlug);
                        if (room) {
                            room.hasUnreadMessages = true;
                        }
                        
                        // Show green dot for new message (false = not new room)
                        flagRoomUnreadMessages(roomSlug, true, false);
                        
                        // Update sidebar badge
                        checkAndUpdateSidebarBadge();
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
    
    // Decrypt auxiliaries if present
    if (messageData.content.auxiliaries) {
        const auxiliariesJson = await decryptWithSymKey(aiKey, 
                                                        messageData.content.auxiliaries.ciphertext,
                                                        messageData.content.auxiliaries.iv,
                                                        messageData.content.auxiliaries.tag, false);
        messageData.content.auxiliaries = JSON.parse(auxiliariesJson);
        //console.log('[handleAIMessage] Decrypted auxiliaries:', messageData.content.auxiliaries.length);
    }

    // CREATE AND UPDATE MESSAGE
    let element = document.getElementById(messageData.message_id);
    if (!element) {
        element = addMessageToChatlog(messageData, true);
        activateMessageControls(element);
    }else{
        updateMessageElement(element, messageData, true);
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
    
    // Debug: Check what we got from server
    //console.log('[handleUpdateMessage] Message ID:', messageData.message_id);
    //console.log('[handleUpdateMessage] Has encrypted auxiliaries:', !!messageData.content.auxiliaries);
    //console.log('[handleUpdateMessage] Full content structure:', Object.keys(messageData.content));
    
    // Decrypt auxiliaries if present (for AI messages)
    if (messageData.message_role === 'assistant' && messageData.content.auxiliaries) {
        const auxiliariesJson = await decryptWithSymKey(key, 
                                                        messageData.content.auxiliaries.ciphertext,
                                                        messageData.content.auxiliaries.iv,
                                                        messageData.content.auxiliaries.tag, false);
        messageData.content.auxiliaries = JSON.parse(auxiliariesJson);
        //console.log('[handleUpdateMessage] Decrypted auxiliaries:', messageData.content.auxiliaries.length);
    }

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
        // Check if activeRoom exists and matches the room
        if (!activeRoom || activeRoom.slug !== roomSlug) return;

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

    defaultPrompt = translation.Default_Prompt;

    roomCreationPanel.querySelector('#chat-name-input').value = '';
    roomCreationPanel.querySelector('#user-search-bar').value = '';
    roomCreationPanel.querySelector('#room-description-input').value = '';
    roomCreationPanel.querySelector('#room-creation-avatar').setAttribute('src', '');
    roomCreationPanel.querySelector('#room-creation-avatar').style.display = 'none';


    roomCreationPanel.querySelector('#system-prompt-input').value = defaultPrompt;
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

    // Check if no invitees selected
    if (listOfInvitees.length === 0) {
        const msg = invModal.querySelector(".error-msg");
        msg.innerText = translation.Cnf_checkMembersAdded;
        return;
    }

    await createAndSendInvitations(listOfInvitees, activeRoom.slug);
    closeModal(btn);

    // Reload the room to show newly invited members
    if (activeRoom && activeRoom.slug) {
        await loadRoom(null, activeRoom.slug);
    }
}

async function createAndSendInvitations(usersList, roomSlug){
    console.log(usersList);
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
        // Save room key
        await keychainSet(data.room.slug, roomKey, true);
        
        // Mark room as no longer new (invitation accepted)
        const roomInArray = rooms.find(r => r.slug === data.room.slug);
        if (roomInArray) {
            roomInArray.isNewRoom = false;
        }
    }
}

function openInvitationPanel(){
    const modal = document.querySelector('#add-member-modal');
    modal.querySelector('#user-search-bar').value = '';
    modal.querySelector('#searchResults').innerHTML = '';
    modal.querySelector('#searchResults').style.display = 'none';
    modal.querySelector('.added-members-list').innerHTML = '';
    modal.querySelector(".error-msg").innerText = '';
    tempSearchResult='';
    modal.style.display = 'flex';
}

function showRoomInvitationModal(room) {
    const modal = document.getElementById('room-invitation-modal');
    if (!modal) {
        return;
    }
    
    const roomNameElement = modal.querySelector('#invitation-room-name');
    const messageElement = modal.querySelector('#invitation-message');
    const errorElement = modal.querySelector('#invitation-error');
    const acceptBtn = modal.querySelector('#accept-invitation-btn');
    const deleteBtn = modal.querySelector('#delete-invitation-btn');
    const normalActions = modal.querySelector('#invitation-actions');
    const errorActions = modal.querySelector('#invitation-error-actions');
    
    // Check if all elements exist
    if (!errorElement || !normalActions || !errorActions || !messageElement || !roomNameElement || !acceptBtn || !deleteBtn) {
        return;
    }
    
    // Reset modal state
    errorElement.style.display = 'none';
    errorElement.textContent = '';
    normalActions.style.display = 'flex';
    errorActions.style.display = 'none';
    messageElement.textContent = translation?.DoYouWantToJoinThisRoom || 'Do you want to join this room?';
    
    // Build invitation message
    let invitationText = `${translation?.Room || 'Room'}: ${room.room_name}`;
    if (room.invited_by) {
        invitationText += `<br><small style="color: var(--text-secondary);">${translation?.InvitedBy || 'Invited by'}: ${room.invited_by}</small>`;
    }
    roomNameElement.innerHTML = invitationText;
    
    // Remove old event listeners and add new ones
    acceptBtn.replaceWith(acceptBtn.cloneNode(true));
    deleteBtn.replaceWith(deleteBtn.cloneNode(true));
    const newAcceptBtn = modal.querySelector('#accept-invitation-btn');
    const newDeleteBtn = modal.querySelector('#delete-invitation-btn');
    
    newAcceptBtn.addEventListener('click', async () => {
        try {
            await acceptRoomInvitation(room);
            modal.style.display = 'none';
        } catch (error) {
            // Show error in modal
            if (error.message?.includes('Room key not found')) {
                errorElement.textContent = translation?.InvitationErrorNoRoomKey || 'This invitation cannot be accepted because the room key is not available. This usually happens when you were invited as an external user but never joined via the invitation link. Please ask the room administrator to send you a new invitation or delete this one.';
            } else if (error.name === 'OperationError' || error.message?.includes('decrypt')) {
                errorElement.textContent = translation?.InvitationErrorExternalUser || 'This invitation was created for an external user and cannot be accepted with your account. Please contact the room administrator or delete this invitation.';
            } else {
                errorElement.textContent = translation?.InvitationErrorGeneric || 'Failed to accept invitation. Please try again or delete this invitation.';
            }
            
            errorElement.style.display = 'block';
            normalActions.style.display = 'none';
            errorActions.style.display = 'flex';
            messageElement.textContent = '';
        }
    });
    
    newDeleteBtn.addEventListener('click', async () => {
        await deleteRoomInvitation(room);
        modal.style.display = 'none';
    });
    
    modal.style.display = 'flex';
}

function showRoomRemovedModal(room) {
    const modal = document.getElementById('room-removed-modal');
    if (!modal) {
        console.error('Room removed modal not found');
        return;
    }
    
    const roomNameElement = modal.querySelector('#removed-room-name');
    const acknowledgeBtn = modal.querySelector('#acknowledge-removal-btn');
    
    if (!roomNameElement || !acknowledgeBtn) {
        console.error('Modal elements not found');
        return;
    }
    
    // Set room name
    roomNameElement.textContent = `${translation?.Room || 'Room'}: ${room.room_name}`;
    
    // Remove old event listener and add new one
    acknowledgeBtn.replaceWith(acknowledgeBtn.cloneNode(true));
    const newAcknowledgeBtn = modal.querySelector('#acknowledge-removal-btn');
    
    newAcknowledgeBtn.addEventListener('click', async () => {
        // Actually leave the room (backend call)
        await leaveRoomAfterRemoval(room.slug);
        modal.style.display = 'none';
    });
    
    modal.style.display = 'flex';
}

async function leaveRoomAfterRemoval(slug) {
    try {
        const response = await fetch(`/req/room/leaveRoom/${slug}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Successfully left room:', slug);
            
            // Remove room from local array and UI
            await removeRoomFromList(slug);
            
            // Switch to welcome panel if no rooms left
            const activeRooms = rooms.filter(r => !r.isRemoved);
            if (activeRooms.length === 0) {
                history.replaceState(null, '', `/groupchat`);
                switchDyMainContent('group-welcome-panel');
            }
        } else {
            console.error('Failed to leave room:', data.message);
        }
    } catch (error) {
        console.error('Error leaving room:', error);
    }
}

async function removeRoomFromList(slug) {
    // Remove from rooms array
    const roomIndex = rooms.findIndex(r => r.slug === slug);
    if (roomIndex !== -1) {
        rooms.splice(roomIndex, 1);
    }
    
    // Remove from UI
    const roomElement = document.querySelector(`.selection-item[slug="${slug}"]`);
    if (roomElement) {
        roomElement.remove();
    }
    
    // Update sidebar badge
    if (typeof checkAndUpdateSidebarBadge === 'function') {
        checkAndUpdateSidebarBadge();
    }
}

async function acceptRoomInvitation(room) {
    
    // First, try to get the invitation to check if it's a temp-hash invitation
    const invitationResponse = await fetch('/req/inv/requestUserInvitations', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        },
    });
    
    if (!invitationResponse.ok) {
        throw new Error('Failed to fetch invitations');
    }
    
    const invitationData = await invitationResponse.json();
    const invitation = invitationData.formattedInvitations?.find(inv => inv.room_slug === room.slug);
    
    if (!invitation) {
        throw new Error('Invitation not found');
    }
    
    // Check if it's a temp-hash invitation (iv and tag are not '0')
    const isTempHash = invitation.iv !== '0' && invitation.tag !== '0';
    
    if (isTempHash) {
        await convertTempHashInvitation(room.slug, invitation.role);
    }
    
    // Now decrypt and accept the (possibly newly created) invitation
    await handleUserInvitationsForRoom(room.slug);
    
    // Mark as no longer new
    room.isNewRoom = false;
    
    // Remove red badge
    flagRoomUnreadMessages(room.slug, false);
    
    // Update sidebar badge
    checkAndUpdateSidebarBadge();
    
    // Now load the room
    await loadRoom(null, room.slug);
}

async function convertTempHashInvitation(roomSlug, role) {
    try {
        // Get room key from keychain
        let roomKey = await keychainGet(roomSlug);
        
        if (!roomKey) {
            throw new Error('Room key not found. You may not have access to this room yet.');
        }
        
        // Export room key to base64
        const roomKeyBase64 = arrayBufferToBase64(roomKey);
        
        // Call backend to convert invitation
        const response = await fetch('/req/inv/convertTempHashInvitation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                room_slug: roomSlug,
                encrypted_room_key: roomKeyBase64,
                role: role
            })
        });
        
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to convert invitation');
        }
        
        const data = await response.json();
        
        return data;
    } catch (error) {
        throw error;
    }
}

async function deleteRoomInvitation(room) {
    try {
        // Ask for confirmation
        const confirmed = await openModal(ModalType.CONFIRM, translation.Cnf_declineInvitation || 'Do you really want to decline this invitation?');
        if (!confirmed) {
            return;
        }
        
        // Call backend to delete invitation
        const response = await fetch(`/req/inv/deleteInvitation/${room.slug}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to decline invitation');
        }
        
        // Remove room from rooms array
        const roomIndex = rooms.findIndex(r => r.slug === room.slug);
        if (roomIndex !== -1) {
            rooms.splice(roomIndex, 1);
        }
        
        // Remove room item from UI
        const roomElement = document.querySelector(`.selection-item[slug="${room.slug}"]`);
        if (roomElement) {
            roomElement.remove();
        }
        
        // Update sidebar badge
        checkAndUpdateSidebarBadge();
        
        console.log('Invitation declined successfully');
    } catch (error) {
        console.error('Error declining invitation:', error);
        alert(translation.Error_declineInvitation || 'Failed to decline invitation. Please try again.');
    }
}

async function handleUserInvitationsForRoom(roomSlug) {
    try {
        console.log('[handleUserInvitationsForRoom] Fetching invitations');
        const response = await fetch('/req/inv/requestUserInvitations', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        
        if (data.formattedInvitations) {
            const invitations = data.formattedInvitations;
            
            const privateKeyBase64 = await keychainGet('privateKey');
            const privateKey = base64ToArrayBuffer(privateKeyBase64);

            for (const inv of invitations) {
                // Only process invitation for this specific room
                if (inv.room_slug === roomSlug) {
                    try {
                        const encryptedRoomKeyBuffer = base64ToArrayBuffer(inv.invitation);
                        const roomKey = await decryptWithPrivateKey(encryptedRoomKeyBuffer, privateKey);
                        if (roomKey) {
                            await finishInvitationHandling(inv.invitation_id, roomKey);
                            return; // Success
                        }
                    } catch (error) {
                        throw error;
                    }
                }
            }
            throw new Error('No invitation found for this room');
        } else {
            throw new Error('No invitations in response');
        }
    } catch (error) {
        throw error;
    }
}




//#endregion

//#region Load Room

function createRoomItem(roomData){
    const roomElement = roomItemTemplate.content.cloneNode(true);
    const roomsList = document.getElementById('rooms-list');

    const label = roomElement.querySelector('.label');
    
    // Set room icon or initials
    const roomIcon = roomElement.querySelector('#room-icon');
    const roomInitials = roomElement.querySelector('#room-initials');

    if (roomData.room_icon) {
        // Has uploaded icon
        roomIcon.setAttribute('src', roomData.room_icon);
        roomIcon.style.display = 'block';
        label.textContent = roomData.room_name;
    } else if (roomData.room_name) {
        // Check if name starts with emoji
        const { emoji, remainingText } = extractFirstEmoji(roomData.room_name);
        
        if (emoji) {
            // Use emoji as icon, show remaining text as name
            roomInitials.textContent = emoji;
            roomInitials.style.display = 'flex';
            label.textContent = remainingText || roomData.room_name; // Fallback to full name if only emoji
        } else {
            // Use first 2 characters as initials
            roomInitials.textContent = roomData.room_name.slice(0, 2).toUpperCase();
            roomInitials.style.display = 'flex';
            label.textContent = roomData.room_name;
        }
    }

    const selectionItem = roomElement.querySelector('.selection-item');
    selectionItem.setAttribute('slug', roomData.slug);
    
    // Hide burger menu button for removed rooms (clicking room opens modal instead)
    if (roomData.isRemoved) {
        const burgerBtn = roomElement.querySelector('.burger-btn');
        if (burgerBtn) {
            burgerBtn.style.display = 'none';
        }
    }
    
    roomsList.insertBefore(roomElement, roomsList.firstChild);
}


async function loadRoom(btn=null, slug=null, openControlPanel=false){
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

    // Check if this room has been removed
    const roomToCheck = rooms.find(r => r.slug === slug);
    if (roomToCheck && roomToCheck.isRemoved) {
        // Show removal notification modal
        showRoomRemovedModal(roomToCheck);
        return;
    }
    
    // Check if this is a new room invitation
    if (roomToCheck && roomToCheck.isNewRoom) {
        // Show invitation modal instead of opening room
        showRoomInvitationModal(roomToCheck);
        return;
    }

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

    // Only switch to chat view if we're not opening the control panel directly
    if (!openControlPanel) {
        switchDyMainContent('chat');
    }
    history.replaceState(null, '', `/groupchat/${slug}`);

    clearChatlog();
    clearInput();

    activeRoom = roomData;
    activeRoom.currentUserRole = roomData.role; // Store current user's role
    const chatControlPanel = document.querySelector('#room-control-panel');
    
    // Apply role-based UI restrictions
    applyRoleBasedUI(roomData.role);
    
    // Disable input for Viewers
    const inputField = document.querySelector('.input-field');
    if (roomData.role === 'viewer') {
        inputField.setAttribute('contenteditable', 'false');
        inputField.style.opacity = '0.5';
        inputField.style.cursor = 'not-allowed';
        inputField.textContent = '';
        inputField.setAttribute('placeholder', 'Nur Admins und Editors dürfen Nachrichten senden');
    } else {
        inputField.setAttribute('contenteditable', 'true');
        inputField.style.opacity = '1';
        inputField.style.cursor = 'text';
        inputField.removeAttribute('placeholder');
    }
    
    // Check if name starts with emoji for display
    const { emoji, remainingText } = extractFirstEmoji(roomData.name);
    const displayName = (emoji && !roomData.room_icon) ? (remainingText || roomData.name) : roomData.name;
    
    chatControlPanel.querySelector('#chat-name').textContent = displayName;
    chatControlPanel.querySelector('#chat-slug').textContent = roomData.slug;

    if(roomData.room_icon){
        chatControlPanel.querySelector('#info-panel-chat-icon').style.display = "block";
        chatControlPanel.querySelector('#control-panel-chat-initials').style.display = "none";
        chatControlPanel.querySelector('#info-panel-chat-icon').setAttribute('src', roomData.room_icon);
    }
    else{
        // Check if name starts with emoji
        const { emoji, remainingText } = extractFirstEmoji(roomData.name);
        
        chatControlPanel.querySelector('#info-panel-chat-icon').style.display = "none";
        chatControlPanel.querySelector('#control-panel-chat-initials').style.display = "block";
        
        if (emoji) {
            // Use emoji as icon
            chatControlPanel.querySelector('#control-panel-chat-initials').innerHTML = emoji;
        } else {
            // Use first 2 characters as initials
            chatControlPanel.querySelector('#control-panel-chat-initials').innerHTML = roomData.name.slice(0, 2).toUpperCase();
        }
        
        chatControlPanel.querySelector('#info-panel-chat-icon').setAttribute('src', '');
    }

    loadRoomMembers(roomData);
    updateChatHeader(roomData);

    // Mark all messages as read and invitation as accepted when room is opened
    await markAllMessagesAsRead(slug);
    
    // If this was a new room invitation, mark it as no longer new
    const room = rooms.find(r => r.slug === slug);
    if (room && room.isNewRoom) {
        room.isNewRoom = false;
        // Remove red badge, may still have green badge if unread messages
        flagRoomUnreadMessages(slug, false);
        checkAndUpdateSidebarBadge();
    }

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
        
        // Debug: Check if auxiliaries exist in encrypted form
        //console.log('[DEBUG] Message ID:', msgData.message_id, 'Has encrypted auxiliaries:', !!msgData.content.auxiliaries);
        
        // Decrypt auxiliaries if present
        if (msgData.content.auxiliaries) {
            const auxiliariesJson = await decryptWithSymKey(key, msgData.content.auxiliaries.ciphertext,
                                                                 msgData.content.auxiliaries.iv,
                                                                 msgData.content.auxiliaries.tag, false);
            msgData.content.auxiliaries = JSON.parse(auxiliariesJson);
            //console.log('[DEBUG] Decrypted auxiliaries count:', msgData.content.auxiliaries.length);
        }
    }
    
    // Update current chat ID for model selection logic
    currentChatId = slug;
    
    // Set model based on chat context
    setModel(null, slug);
    
    filterRoleElements(roomData.role);
    loadMessagesOnGUI(roomData.messagesData);
    scrollToLast(true);
}



function updateChatHeader(roomData) {
    const chatHeader = document.getElementById('group-chat-header');
    const headerIcon = document.getElementById('chat-header-icon');
    const headerInitials = document.getElementById('chat-header-initials');
    const headerName = document.getElementById('chat-header-name');

    // Show the header
    chatHeader.style.display = 'flex';

    // Set room icon or initials
    if (roomData.room_icon) {
        // Has uploaded icon
        headerIcon.setAttribute('src', roomData.room_icon);
        headerIcon.style.display = 'block';
        headerInitials.style.display = 'none';
        headerName.textContent = roomData.name;
    } else {
        // Check if name starts with emoji
        const { emoji, remainingText } = extractFirstEmoji(roomData.name);
        
        if (emoji) {
            // Use emoji as icon, show remaining text as name
            headerInitials.textContent = emoji;
            headerInitials.style.display = 'flex';
            headerIcon.style.display = 'none';
            headerName.textContent = remainingText || roomData.name; // Fallback to full name if only emoji
        } else {
            // Use first 2 characters as initials
            headerInitials.textContent = roomData.name.slice(0, 2).toUpperCase();
            headerInitials.style.display = 'flex';
            headerIcon.style.display = 'none';
            headerName.textContent = roomData.name;
        }
    }
}

function loadRoomMembers(roomData) {
    const membersList = document.getElementById('room-control-panel').querySelector('.members-list');
    const assistantsList = document.getElementById('room-control-panel').querySelector('.assistants-list');
    
    // Clear existing members and assistants
    membersList.innerHTML = `
        <button class="btn-sm add-member-btn admin-only" id="invite-btn" onclick="openInvitationPanel()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>`;
    assistantsList.innerHTML = '';

    roomData.members.forEach(member => {
        // Skip system users except assistants
        if (member.employeetype === 'system' && member.role !== 'assistant') return;
        
        // Determine if this is an assistant
        const isAssistant = member.role === 'assistant';
        const targetList = isAssistant ? assistantsList : membersList;

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
        
        // Append to appropriate list
        if (isAssistant) {
            targetList.appendChild(memberBtnTemp);
        } else {
            // Append to members list before invite button
            targetList.insertBefore(memberBtnTemp, membersList.querySelector('#invite-btn'));
        }
    });
    
    // If user is Viewer and cannot view all members, show placeholder for hidden members
    if (roomData.can_view_all_members === false) {
        const visibleCount = roomData.members.length;
        const totalMembersCount = roomData.total_members_count || 0;
        const totalInvitationsCount = (roomData.invitations && roomData.invitations.length) || 0;
        
        // Hidden members are: (total members - visible members) + all invitations
        const hiddenMembersCount = totalMembersCount - visibleCount;
        const hiddenCount = hiddenMembersCount + totalInvitationsCount;
        
        if (hiddenCount > 0) {
            const placeholderBtn = document.createElement('button');
            placeholderBtn.className = 'btn-sm';
            placeholderBtn.style.cssText = 'opacity: 0.6; cursor: default; pointer-events: none;';
            placeholderBtn.innerHTML = `<div style="font-size: 0.75rem; text-align: center;">+${hiddenCount}</div>`;
            placeholderBtn.title = `${hiddenCount} weitere Mitglieder und Einladungen (nur für Admins und Editors sichtbar)`;
            membersList.insertBefore(placeholderBtn, membersList.querySelector('#invite-btn'));
        }
    }

    // Add pending invitations (grayed out) - only for Admins and Editors
    if (roomData.invitations && 
        roomData.invitations.length > 0 && 
        roomData.can_view_all_members !== false) {
        // Clone invitations section template (separator + label)
        const invitationsSectionTemplate = document.getElementById('invitations-section-template');
        const invitationsSection = invitationsSectionTemplate.content.cloneNode(true);
        membersList.insertBefore(invitationsSection, membersList.querySelector('#invite-btn'));

        roomData.invitations.forEach(invitation => {
            const memberBtnTemp = document.getElementById('member-listBtn-template').content.cloneNode(true);
            const memberBtnIcon = memberBtnTemp.querySelector('#member-icon');
            const memberBtnInit = memberBtnTemp.querySelector('#member-init');
            const memberBtn = memberBtnTemp.querySelector('#member-btn');

            // Style as pending/grayed out
            memberBtn.style.opacity = '0.5';
            memberBtn.style.cursor = 'pointer';
            memberBtn.title = translation.PendingInvitation || 'Pending invitation';

            if (invitation.avatar_url) {
                memberBtnIcon.setAttribute('src', invitation.avatar_url);
                memberBtnIcon.style.cursor = 'pointer';
                memberBtnInit.remove();
            } else {
                memberBtnIcon.remove();
                memberBtnInit.textContent = invitation.name.slice(0, 2).toUpperCase();
                memberBtnInit.style.cursor = 'pointer';
            }

            // Set invitation object in the button attribute
            memberBtn.setAttribute('memberObj', JSON.stringify(invitation));
            // Append to the header and the list
            membersList.insertBefore(memberBtnTemp, membersList.querySelector('#invite-btn'));
        });
    }
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
                ignoreList.push(hawkiUsername);
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
                        searchBar.value = option.innerText; // Fill the search bar with the selected username
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
    // Only submit changes if user is Admin
    if (activeRoom && activeRoom.currentUserRole === 'admin') {
        submitInfoField();
    }
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
    const listItem = document.querySelector(`.selection-item[slug="${activeRoom.slug}"]`);
    const list = listItem.parentElement;

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
            if(list.length > 0){
                await loadRoom(list.firstElementChild, null);
                switchDyMainContent('chat');
            }
            else{
                switchDyMainContent('group-welcome-panel');
                history.replaceState(null, '', `/groupchat`);
            }

        } else {
            console.error('Room leave was not successful!');
        }
    } catch (error) {
        console.error('Failed to leave room!');
    }
}


function removeListItem(slug){
        // Remove from rooms array
        const roomIndex = rooms.findIndex(r => r.slug === slug);
        if (roomIndex !== -1) {
            rooms.splice(roomIndex, 1);
            console.log('Room removed from array:', slug);
        }
        
        // Remove from DOM
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

    if(username === hawkiUsername){
        console.error('You can not remove HAWKI from the Room!');
        return false;
    }


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
            console.log(data.message);
            return true;
        }
        console.error(data.message);
    } catch (error) {
        console.error('Failed to remove user!');
    }

    return false;
}

async function removeInvitation(username){

    const confirmed = await openModal(ModalType.CONFIRM, translation.Cnf_removeMember);
    if (!confirmed) {
        return false;
    }

    const url = `/req/inv/deleteInvitation/${activeRoom.slug}`;
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
            console.log(data.message);
            return true;
        }
        console.error(data.message);
    } catch (error) {
        console.error('Failed to remove invitation!');
    }

    return false;
}



//#endregion

//#region Room Info controls

function selectRoomAvatar(btn, upload = false){

    const imageElement = btn.parentElement.querySelector('.selectable-image');
    const initials = btn.parentElement.querySelector('#control-panel-chat-initials');
    
    // Define save callback
    const saveCallback = async function(croppedImage) {
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
    };
    
    // Define delete callback (only for existing rooms with upload=true)
    const deleteCallback = (upload && activeRoom && activeRoom.slug) ? async function() {
        await removeRoomAvatar();
    } : null;
    
    openImageSelection(imageElement.getAttribute('src'), saveCallback, deleteCallback);
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
            
            // Update room icon in the chat list
            updateRoomIconInList(activeRoom.slug, data.url);
            
            // Update room icon in the header
            updateChatHeaderIcon(data.url);
            
            return data.url;

        } else {
            console.error('Upload not successfull');
        }
    } catch (error) {
        console.error('Failed to upload image to server!');
    }
}

function updateRoomIconInList(slug, iconUrl) {
    const roomElement = document.querySelector(`.selection-item[slug="${slug}"]`);
    if (!roomElement) return;
    
    const roomIcon = roomElement.querySelector('#room-icon');
    const roomInitials = roomElement.querySelector('#room-initials');
    
    if (roomIcon && roomInitials) {
        roomIcon.setAttribute('src', iconUrl);
        roomIcon.style.display = 'block';
        roomInitials.style.display = 'none';
    }
}

function updateChatHeaderIcon(iconUrl) {
    const headerIcon = document.querySelector('#chat-header-icon');
    const headerInitials = document.querySelector('#chat-header-initials');
    
    if (headerIcon && headerInitials) {
        headerIcon.setAttribute('src', iconUrl);
        headerIcon.style.display = 'block';
        headerInitials.style.display = 'none';
    }
}

async function removeRoomAvatar() {
    if (!activeRoom || !activeRoom.slug) {
        console.error('No active room');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const url = `/req/room/removeAvatar/${activeRoom.slug}`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        });
        const data = await response.json();

        if (data.success) {
            // Update control panel
            const imageElement = document.querySelector('#info-panel-chat-icon');
            const initials = document.querySelector('#control-panel-chat-initials');
            
            if (imageElement && initials) {
                imageElement.style.display = 'none';
                imageElement.setAttribute('src', '');
                initials.style.display = 'flex';
                initials.textContent = activeRoom.name.slice(0, 2).toUpperCase();
            }

            // Update room icon in the chat list
            removeRoomIconFromList(activeRoom.slug, activeRoom.name);
            
            // Update room icon in the header
            removeChatHeaderIcon(activeRoom.name);
            
            console.log('Room avatar removed successfully');
        } else {
            console.error('Failed to remove avatar');
        }
    } catch (error) {
        console.error('Error removing room avatar:', error);
    }
}

function removeRoomIconFromList(slug, roomName) {
    // Find room element by slug attribute
    const allRoomElements = document.querySelectorAll('.selection-item');
    let roomElement = null;
    
    for (let elem of allRoomElements) {
        if (elem.getAttribute('slug') === slug) {
            roomElement = elem;
            break;
        }
    }
    
    if (!roomElement) {
        console.warn('Room element not found in list for slug:', slug);
        return;
    }
    
    const roomIcon = roomElement.querySelector('#room-icon');
    const roomInitials = roomElement.querySelector('#room-initials');
    
    if (roomIcon && roomInitials && roomName) {
        roomIcon.style.display = 'none';
        roomIcon.setAttribute('src', '');
        roomInitials.style.display = 'flex';
        roomInitials.textContent = roomName.slice(0, 2).toUpperCase();
        console.log('Room icon removed from list for:', roomName);
    } else {
        console.warn('Could not find room icon or initials elements', {roomIcon, roomInitials, roomName});
    }
}

function removeChatHeaderIcon(roomName) {
    const headerIcon = document.querySelector('#chat-header-icon');
    const headerInitials = document.querySelector('#chat-header-initials');
    
    if (headerIcon && headerInitials) {
        headerIcon.style.display = 'none';
        headerInitials.style.display = 'flex';
        headerInitials.textContent = roomName.slice(0, 2).toUpperCase();
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

function applyRoleBasedUI(role) {
    // Hide/show elements based on role
    // admin-only: visible for admins only
    // editor-only: visible for editors and admins
    
    const adminOnlyElements = document.querySelectorAll('.admin-only');
    const editorOnlyElements = document.querySelectorAll('.editor-only');
    
    adminOnlyElements.forEach(el => {
        if (role === 'admin') {
            // Show element - try both attribute names for compatibility
            const originalDisplay = el.getAttribute('data-original-display') || 
                                   el.getAttribute('data-originaldisplay') || 
                                   el.dataset.originalDisplay || 
                                   '';
            el.style.display = originalDisplay || '';
        } else {
            el.style.display = 'none';
        }
    });
    
    editorOnlyElements.forEach(el => {
        if (role === 'admin' || role === 'editor') {
            // Show element - try both attribute names for compatibility
            const originalDisplay = el.getAttribute('data-original-display') || 
                                   el.getAttribute('data-originaldisplay') || 
                                   el.dataset.originalDisplay || 
                                   '';
            el.style.display = originalDisplay || '';
        } else {
            el.style.display = 'none';
        }
    });
}

async function markAllMessagesAsRead(slug) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    try {
        const response = await fetch(`/req/room/markAllAsRead/${slug}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update local room state
            const room = rooms.find(r => r.slug === slug);
            if (room) {
                room.hasUnreadMessages = false;
            }
            
            // Update badges
            flagRoomUnreadMessages(slug, false);
            if (typeof checkAndUpdateSidebarBadge === 'function') {
                checkAndUpdateSidebarBadge();
            }
        }
        
        return data.success;
    } catch (error) {
        console.error('[markAllMessagesAsRead] Error:', error);
        return false;
    }
}
//#endregion
