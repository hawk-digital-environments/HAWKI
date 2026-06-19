function initializeInputField() {

    window.oldUiBridge.onCurrentChatModelIdUpdate(newModelId => {
        localStorage.setItem('definedModel', newModelId);
    });
    window.oldUiBridge.onExportTrigger(type => {
        switch (type) {
            case 'print':
                exportPrintPage();
                break;
            case 'pdf':
                exportAsPDF();
                break;
            case 'json':
                exportAsJson();
                break;
            case 'csv':
                exportAsCsv();
                break;
            case 'word':
                exportAsWord();
                break;
        }
    });

    window.oldUiBridge.onContextReady(() => {
        /** @type AiModel|undefined|null */
        let model;
        if (localStorage.getItem('definedModel')) {
            model = window.getAiModel(localStorage.getItem('definedModel'));
        }
        if (!model) {
            model = window.getSystemModel('default');
        }
        window.oldUiBridge.triggerLoadInitialModel(model);
    });

    window.oldUiBridge.onImproveMessage(async (data) => {
        return await requestPromptImprovement(data.message, data.systemPrompt);
    });
}


function resizeInputField(inputField) {
    // console.log('resize')
    inputField.style.height = 'auto';
    inputField.style.height = inputField.scrollHeight + 'px';
    inputField.scrollTop = inputField.scrollHeight;
    inputField.scrollTo(inputField.scrollTop, (inputField.scrollTop + inputField.scrollHeight));
}


// Show error message when file validation fails

function showFeedbackMsg(inputfield, type, message) {
    const feedbackEl = inputfield.closest('.input-container').querySelector('#input-feedback-msg');
    feedbackEl.dataset.type = type;
    feedbackEl.innerText = message;

    setTimeout(() => {
        feedbackEl.innerText = '';
        feedbackEl.dataset.type = null;
    }, 5000);
}
