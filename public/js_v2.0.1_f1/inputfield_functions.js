
function initializeInputField(){
    const systemPromptField = document.getElementById('system_prompt_field');
    systemPromptField.addEventListener('blur', function() {
        // console.log("Focus out, content:", systemPromptField.innerText);
        updateAiChatSystemPrompt(systemPromptField.innerText);
    });

}


let activeInputField;
function onInputFieldFocus(inputField){
    activeInputField = inputField;
    const thread = inputField.closest('.thread');
    let targetID = 0;
    if(thread){
        targetID = thread.id;
    }
}

function onInputFieldFocusOut(inputField){
    if(activeInputField === inputField){
        activeInputField = null;
    }
    const thread = inputField.closest('.thread');
    let targetID = 0;

    if(thread){
        targetID = thread.id;
        // turnOffEmptyInput(thread);
    }
}

function resizeInputField(inputField) {
    // console.log('resize')
    inputField.style.height = 'auto';
    inputField.style.height = inputField.scrollHeight + "px";
    inputField.scrollTop = inputField.scrollHeight;
    inputField.scrollTo(inputField.scrollTop, (inputField.scrollTop + inputField.scrollHeight));
}



function toggleOffInputControls(detectedInputPanel){
    const inputs = document.querySelectorAll('.input-container');
    for(let i = 0; i < inputs.length; i++){

        const controls = inputs[i].querySelector('.input-controls');
        const modelSelector = inputs[i].querySelector('#model-selectors');

        //if the iteration panel is the one we already clicked on, continue...
        if(detectedInputPanel && inputs[i] === detectedInputPanel){
            detectedInputPanel.querySelector('.input-controls').classList.add('minimized');
            continue;
        }
        //deactivate all othe inputfields
        if(controls.classList.contains('expanded')){
            controls.classList.remove('expanded');
        }
        if(controls.classList.contains('minimized')){
            controls.classList.remove('minimized');
        }
        //minimize model selectors if an input panel or model selector is not clicked on.
        modelSelector.classList.remove('expanded');
    }
}

function toggleOffRelativeInputControl(inputField){
    const container = inputField.closest('.input-container');
    container.querySelector('.input-controls').classList.add('minimized');
    const controls = container.querySelector('.input-controls');
    if(controls.classList.contains('expanded')){
        controls.classList.remove('expanded');
    }
}



function switchControllerProp(sender, id = null){

    //get expanded content
    const expandedContent = sender.closest('.input-container').querySelector('.expanded-content');
    //get properties panel
    const propsPanel = expandedContent.querySelector('#input-controls-props-panel');
    //trun off all properties elements
    propsPanel.childNodes.forEach(child => {
        if (child.nodeType === Node.ELEMENT_NODE) {
            child.style.display = 'none';
        }
    });
    //turn on target properties element
    if(id){
        const target = propsPanel.querySelector(`#${id}`);
        if (target) {
            target.style.display = 'block';
        }
    }

    //remove active state from all menu items.
    const menuItems = expandedContent.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        if (item.classList.contains('active')) {
            item.classList.remove('active');
        }
    });

    //refind the target menu item.
    //trick to get menu item in exapnded panel when the menu is triggered from a twin button out of expanded panel
    if(id){
        const targetBtn = expandedContent.querySelector(`.menu-item[value=${sender.getAttribute('value')}`);
        if(targetBtn){
            targetBtn.classList.add('active');
        }
    }

}



async function updateAiChatSystemPrompt(inputPrompt){
    activeConv.system_prompt = inputPrompt;


    const convKey = await keychainGet('aiConvKey');

    const cryptSystemPrompt = await encryptWithSymKey(convKey, inputPrompt, false);
    const systemPromptStr = JSON.stringify({
        'ciphertext':cryptSystemPrompt.ciphertext,
        'iv':cryptSystemPrompt.iv,
        'tag':cryptSystemPrompt.tag,
    });

    const url = `/req/conv/updateInfo/${activeConv.slug}`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                'system_prompt': systemPromptStr
            })
        });
        const data = await response.json();

        if (data.success) {
            // console.log('System Prompt updated Successfully');
        } else {
            console.error('Update not successfull');
        }
    } catch (error) {
        console.error('Failed to Update System Prompt!');
    }

}



// Show error message when file validation fails
function showFeedbackMsg(inputfield, type, message) {
    const feedbackEl = inputfield.closest('.input-container').querySelector('#input-feedback-msg');
    feedbackEl.dataset.type = type;
    feedbackEl.innerText = message;

    setTimeout(() => {
        feedbackEl.innerText = '';
        feedbackEl.dataset.type = null
    }, 3000);
    console.log(feedbackEl);
}
