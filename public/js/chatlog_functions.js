let activeThreadIndex = 0;
let activeModel;
let autoFollow = true; // true = scroll follows new content. ONLY changed by explicit user input (wheel/touch) or forceScroll — never by scroll events, which fire for programmatic scrolls too.
let observer;

function initializeChatlogFunctions() {
    initializeInputField();

    const scrollContainer = document.querySelector('.chatlog .scroll-container');

    if (scrollContainer) {
        const isAtBottom = () =>
            scrollContainer.scrollHeight - scrollContainer.scrollTop - scrollContainer.clientHeight < 50;

        // Wheel up = disengage. Wheel down while at the bottom = re-engage.
        scrollContainer.addEventListener('wheel', (e) => {
            if (e.deltaY < 0) {
                autoFollow = false;
            } else if (e.deltaY > 0 && isAtBottom()) {
                autoFollow = true;
            }
        }, {passive: true});

        scrollContainer.addEventListener('touchmove', () => {
            autoFollow = false;
        }, {passive: true});

        scrollContainer.addEventListener('touchend', () => {
            if (isAtBottom()) autoFollow = true;
        }, {passive: true});
    }

    // markstream batch-renders nodes across multiple idle/rAF callbacks, so scrollHeight
    // keeps growing after the initial scrollToLast(true) fires. Observe the message
    // container directly: whenever it grows and autoFollow is active, snap to the bottom.
    const trunk = document.querySelector('.trunk');
    if (trunk) {
        new ResizeObserver(() => {
            if (!autoFollow || !scrollContainer) return;

            // 'data-no-auto-scroll' is used for the OldUiStyling svelte component to overrule this behaviour.
            if (document.body.getAttribute('data-no-auto-scroll') === 'true') {
                autoFollow = false;
                return;
            }
            
            scrollContainer.scrollTo({top: scrollContainer.scrollHeight, left: 0, behavior: 'auto'});
        }).observe(trunk);
    }

    // Initialize Intersection Observer
    observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Mark the message as seen
                markAsSeen(entry.target);
                // Stop observing the message once it's seen
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.5 // Adjust threshold as needed
    });

    window.oldUiBridge.onExitMode((state) => {
        if (state.is === 'thread') {
            const thread = findThreadWithID(state.threadId);
            if (thread) {
                // Keep open if there are messages in the thread
                if (thread.querySelectorAll('.message').length > 0) {
                    return;
                }
                onThreadButtonEvent(thread.querySelector('button'));
            }
        }
    });
}

function switchDyMainContent(contentID) {
    const mainPanel = document.querySelector('.dy-main-panel');

    const contents = mainPanel.querySelectorAll('.dy-main-content');

    contents.forEach(content => {
        if (content.id === contentID) {
            content.style.display = 'flex';
        } else {
            content.style.display = 'none';
        }
    });
}

function clearChatlog() {
    const content = document.querySelector('.trunk');
    while (content.firstChild) {
        content.removeChild(content.lastChild);
    }
}

function clearInput() {
    window.oldUiBridge.triggerClearActiveConversation();
}

async function submitMessageToServer(requestObj, url, plainContent) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify(requestObj)
        });

        const data = await response.json();
        if (data.success) {
            window.oldUiMessageHistory.addMessageToConversation({
                ...data.messageData,
                content: {
                    ...data.messageData.content,
                    ...plainContent
                }
            });
            return data.messageData;
            // updateMessageElement(messageElement, data.messageData);
        } else {
            // Handle unexpected response
            console.error('Unexpected response:', data);
        }
    } catch (error) {
        console.error('There was a problem with the operation:', error);
    }
}

async function requestMsgUpdate(messageObj, messageElement, url, plainContent) {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(messageObj)
        });

        const data = await response.json();
        if (data.success) {
            const lookupData = data.legacyResource || data.messageData;
            window.oldUiMessageHistory.updateMessageInConversation({
                ...lookupData,
                content: {
                    ...lookupData.content,
                    ...plainContent
                }
            });
            updateMessageElement(messageElement, data.messageData, false, lookupData.content.attachments || []);
        } else {
            // Handle unexpected response
            console.error('Unexpected response:', data);
        }
    } catch (error) {
        console.error('There was a problem with the operation:', error);
    }
}

function onThreadButtonEvent(btn) {
    const thread = btn.closest('.message').querySelector('.thread');

    if (!thread) {
        return;
    }

    if (thread.classList.contains('visible')) {
        thread.classList.remove('visible');
        if (thread && thread.id) {
            window.oldUiBridge.triggerExitThread();
        }
    } else {
        thread.classList.add('visible');
        if (thread.querySelectorAll('.message').length === 0) {
            onEditThreadButtonEvent(btn);
        }
    }
}

function onEditThreadButtonEvent(btn) {
    const thread = btn.closest('.message').querySelector('.thread');

    if (!thread || !thread.id) {
        console.error('Thread ID not found for edit button');
        return;
    }

    window.oldUiBridge.triggerEnterMode('thread', thread.id);
}

function selectActiveThread(sender) {
    const thread = sender.closest('.thread');

    if (!thread) {
        activeThreadIndex = 0;
        return;
    }
    activeThreadIndex = Number(thread.id);
}

function findThreadWithID(threadId) {
    return document.querySelector(`.thread#${CSS.escape(threadId)}`);
}


//#region Message

//CREATE MESSAGE ELEMENT AND PUT IT IN THE CHATLOG
function loadMessagesOnGUI(messages) {
    // Sorting messages by ID
    messages.sort((a, b) => {
        return +a.message_id - +b.message_id;
    });

    let threads = [];
    messages.forEach(messageObj => {
        const addedMsg = addMessageToChatlog(messageObj, true);
        updateMessageElement(addedMsg, messageObj);

        // Observe unread messages
        if (addedMsg.dataset.read_stat === 'false') {
            observer.observe(addedMsg);
        }
        if (addedMsg.querySelector('.branch')) {
            threads.push(addedMsg.querySelector('.branch'));
        }
    });
    threads.forEach(thread => {
        checkThreadUnreadMessages(thread);
    });
}


function checkThreadUnreadMessages(thread) {
    // Select unread message elements from the specified thread
    const unread_msgs = thread.querySelectorAll('.message[data-read_stat="false"]');
    // Find the closest ancestor message of the current thread
    const parentMsg = thread.closest('.message');

    // Show or hide the unread icon based on the number of unread messages
    if (unread_msgs.length !== 0) { // Corrected to 'length'
        parentMsg.querySelector('#unread-thread-icon').style.display = 'block';
    } else {
        parentMsg.querySelector('#unread-thread-icon').style.display = 'none';
    }
}

function flagRoomUnreadMessages(slug, active) {
    /** @var {HTMLSvelteSnippetElement} selector */
    const selector = document.querySelector(`svelte-snippet[type="ChatSidebarButton"][data-room-slug="${slug}"]`);
    if (active) {
        selector.setProps({hasUnreadMessages: true});
    } else {
        selector.setProps({hasUnreadMessages: false});
    }
}

async function markAsSeen(element) {
    sendReadStatToServer(element.id);
    setTimeout(() => {
        setMessageStatusAsRead(element);

        if (document.querySelectorAll('.message[data-read_stat="false"]').length === 0) {
            flagRoomUnreadMessages(activeRoom.slug, false);
        }

        if (element.id.split('.')[1] !== '000') {
            const thread = element.closest('.message').querySelector('.branch');
            if (thread) {
                checkThreadUnreadMessages(thread);
            }
        }
    }, 3000);
}

async function markAllAsRead(slug) {
    // Legacy support -> If we are currently not in the conversation with the slug
    // we MUST open it, otherwise we can't show the information.
    if (activeRoom.slug !== slug) {
        await loadRoom(null, slug);
    }
    const unread_msgs = document.querySelectorAll('.message[data-read_stat="false"]');

    unread_msgs.forEach(element => {
        observer.unobserve(element);
        setMessageStatusAsRead(element);
        sendReadStatToServer(element.id);
        if (element.id.split('.')[1] !== '000') {
            const thread = element.closest('.message').querySelector('.branch');
            if (thread) {
                checkThreadUnreadMessages(thread);
            }
        }
    });
    flagRoomUnreadMessages(activeRoom.slug, false);
}

async function sendReadStatToServer(message_id) {
    url = `/req/room/readstat/${activeRoom.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({'message_id': message_id})
        });
        const data = await response.json();

        if (!data.success) {
            console.error('failed to inform server');
        }
    } catch (error) {
        console.error('failed to inform server');
    }
}

//#endregion


//scrolls to the end of the panel.
//if new message is send, it forces the panel to scroll down.
//if the current message is continuing to expand force expand is false.
//(if the user is trying to read the upper parts it wont jump back down.)
// Function to handle the auto-scroll behavior
function scrollToLast(forceScroll, targetElement = null) {
    const msgsPanel = document.querySelector('.chatlog .scroll-container');
    if (!msgsPanel) return;

    // Deliberate events (message sent, conversation loaded) re-engage following.
    if (forceScroll) autoFollow = true;

    // Defer until after the current render frame so batch-rendered content
    // (markstream progressive node rendering) has had a chance to settle heights.
    requestAnimationFrame(() => {
        // Re-check inside the rAF: the user may have wheeled up between the
        // call and this frame — an outdated pre-check would yank them back down.
        if (!forceScroll && !autoFollow) return;

        // Smooth only for deliberate events (new message sent, conversation loaded).
        // Instant for streaming chunks — smooth on every chunk creates a tractor-beam
        // animation that fights the user's scroll input.
        const behavior = forceScroll ? 'smooth' : 'auto';

        if (targetElement) {
            const thread = targetElement.closest('.thread');
            const isBranchMessage = thread && thread.classList.contains('branch');

            if (isBranchMessage && !thread.classList.contains('visible')) {
                thread.classList.add('visible');
            }

            // getBoundingClientRect is always viewport-relative, so it works
            // correctly regardless of deferred/batch rendering state inside the element.
            const containerRect = msgsPanel.getBoundingClientRect();
            const elementRect = targetElement.getBoundingClientRect();
            const gap = elementRect.bottom - containerRect.bottom + 50;

            if (gap > 0) {
                msgsPanel.scrollBy({top: gap, behavior});
            }
        } else {
            msgsPanel.scrollTo({top: msgsPanel.scrollHeight, left: 0, behavior});
        }
    });
}
