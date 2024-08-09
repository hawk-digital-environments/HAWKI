function update(event) {
    event.preventDefault();
  if (!window.visualViewport) {
      return;
  }
    window.scrollTo(0, 0);
    document.querySelector(".wrapper").style.height = window.visualViewport.height + "px";
}

function load(element, filename){
    let messagesElement = document.querySelector(".messages");
    fetch(`?page=${filename}`)
    .then((response) => {
        return response.text();
    })
    .then((html) => {
        messagesElement.innerHTML = html;
        return;
    }).then(()=>{
        CheckModals();
        if(filename == "feedback_loader.php"){
            voteHover();
        }
        if(filename == "chat.php"){
            loadMessagesFromLocalStorage();
        }
    });

  

    document.querySelector(".menu-item.active")?.classList.remove("active");
    document.querySelector(".menu-item.open")?.classList.remove("open");
    document.querySelector(".submenu-item.active")?.classList.remove("active");
    element.classList.add("active");
    
    element.closest(".submenu")?.previousElementSibling.classList.add("open");
    element.closest(".submenu")?.previousElementSibling.classList.add("active");
    
    document.querySelector(".main").scrollIntoView({ behavior: "smooth", block: "end", inline: "nearest" });
}

//#region Scrolling Controls
//scrolls to the end of the panel.
//if new message is send, it forces the panel to scroll down.
//if the current message is continuing to expand force expand is false.
//(if the user is trying to read the upper parts it wont jump back down.)
let isScrolling = false;
function scrollToLast(forceScroll){
    const msgsPanel = document.querySelector('.messages');
    const documentHeight = msgsPanel.scrollHeight;
    const currentScroll = msgsPanel.scrollTop + msgsPanel.clientHeight;
    if (!isScrolling && (forceScroll || documentHeight - currentScroll < 200)) {
        const messagesElement = document.querySelector(".messages");

        messagesElement.scrollTo({
            top: messagesElement.scrollHeight,
            left: 0,
            behavior: "smooth",
        });
    }
}
//#endregion



//#region SYSTEM PROMPT
let spPanelOpen = false;
document.addEventListener('click', function(event) {
    const isClickOnPanel = document.getElementById('system-prompt-panel').contains(event.target);
    const isClickOnBtn = document.getElementById('system-prompt-btn').contains(event.target);
    if (!isClickOnPanel && !isClickOnBtn) {
        ToggleSystemPrompt(false);
    }
});

function ToggleSystemPrompt(activation){
    const promptPanel = document.getElementById('system-prompt-panel');

    if(spPanelOpen && activation) activation = false;

    if(activation == true){
        const promptText = document.getElementById('system-prompt');
        const msg = document.querySelector('.messages').querySelector('.message');
        if(msg.getAttribute('data-role') !== 'system'){
            console.log('System Prompt not found!');
            return
        }
        const systemPrompt = msg.querySelector('.message-content').querySelector('.message-text').innerText;
        promptText.innerHTML =  systemPrompt.trim();
        
        promptPanel.style.display = 'block';
        requestAnimationFrame(() => {
            promptPanel.style.opacity = '1';
        });
    }
    else{
        if(isEditing){
            toggleSystemPromptEdit();
        }
        promptPanel.style.opacity = '0';
        setTimeout(() => {
            promptPanel.style.display = 'none';
            document.getElementById('system-prompt-info').style.display = 'none'
        }, 300);
    }
    spPanelOpen = !spPanelOpen;
}

function toggleSystemPromptInfo(){
    const info = document.getElementById('system-prompt-info');
    if(info.style.display === 'none'){
        console.log('display none');

        info.style.display = 'block';
    }else{
        console.log('display block');

        info.style.display = 'none';
    }
}

let isEditing = false;

function confirmSystemPromptEdit(){
    const newPrompt = document.getElementById('system-prompt').innerText;
    const msg = document.querySelector('.messages').querySelector('.message');
    msg.querySelector('.message-content').querySelector('.message-text').innerText = newPrompt;
    toggleSystemPromptEdit();
}

function abortSystemPromptEdit(){
    const msg = document.querySelector('.messages').querySelector('.message');
    const systemPrompt = msg.querySelector('.message-content').querySelector('.message-text').innerText;
    document.getElementById('system-prompt').innerText = systemPrompt.trim();
    toggleSystemPromptEdit();
}

function toggleSystemPromptEdit(){
    const editButton = document.getElementById('system-prompt-editButton');
    const confirmBar = document.getElementById('system-prompt-edit-control');
    const systemPromptText = document.getElementById('system-prompt');

    if(isEditing === false){
        editButton.style.display = "none";
        confirmBar.style.display = "grid";
        systemPromptText.setAttribute("contenteditable", true);
        systemPromptText.style.fontStyle = "italic";
        systemPromptText.focus();
        
        var range,selection;
        if(document.createRange)//Firefox, Chrome, Opera, Safari, IE 9+
        {
            range = document.createRange();
            range.selectNodeContents(systemPromptText);
            range.collapse(false);
            selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
        else if(document.selection)//IE 8 and lower
        { 
            range = document.body.createTextRange();
            range.moveToElementText(systemPromptText);
            range.collapse(false);
            range.select();
        }
    }else{
        editButton.style.display = "block";
        confirmBar.style.display = "none";
        systemPromptText.setAttribute("contenteditable", false);
        systemPromptText.style.fontStyle = "normal";
    }
    isEditing = !isEditing;
}


function calculateSystemPromptMaxHeight(){
    const inputWrapper = document.querySelector('.input-wrapper');
    const inputHeight = inputWrapper.getBoundingClientRect().height;

    const spPanel = document.getElementById('system-prompt-panel');

    const windowHeight = window.innerHeight;
    const remInPixels = parseFloat(getComputedStyle(document.documentElement).fontSize);
    const margin = 8 * remInPixels;

    const maxHeight = windowHeight - inputHeight - margin;
    spPanel.style.maxHeight = `${maxHeight}px`;
}

//#endregion