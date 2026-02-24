
//#region Model Selection
function selectModel(btn){
    const value = JSON.parse(btn.getAttribute('value'));
    setModel(value.id);
}
function setModel(modelID = null){
    let model;
    if(!modelID){
        if(localStorage.getItem("definedModel")){
            model = modelsList.find(m => m.id === localStorage.getItem("definedModel"));
        }
        // if there is no defined model
        // or the defined model is outdated or cruppted
        if(!model){
            model = modelsList.find(m => m.id === defaultModels.default_model);
        }
    }
    else{
        model = modelsList.find(m => m.id === modelID);
    }
    activeModel = model;
    localStorage.setItem("definedModel", activeModel.id);

    //UI UPDATE...
    const selectors = document.querySelectorAll('.model-selector');
    selectors.forEach(selector => {
        //if this is our target model selector
        if(JSON.parse(selector.getAttribute('value')).id === activeModel.id){

            const modelObject = modelsList.find(m => m.id === activeModel.id);
            selector.classList.add('active');

            if(modelObject.tools.web_search && modelObject.tools.web_search === true){
                document.querySelectorAll('#websearch-btn').forEach(btn => {
                    btn.classList.add('active');
                })
            }
            else{
                document.querySelectorAll('#websearch-btn').forEach(btn => {
                    btn.classList.remove('active');
                })
            }

            const labels = document.querySelectorAll('.model-selector-label');
            labels.forEach(label => {
                label.innerHTML = activeModel.label;
            });
        }
        else{
            selector.classList.remove('active');
        }
    });
    setModelParamDefault();
}

//#endregion

// #region Model Parameters Setup

let paramsButtonRef = null;

function openMsgParamsControlPanel(sender){
    const panel = document.getElementById('model-parameters-control-panel');
    panel.querySelectorAll('.hint-box').forEach(el => {
        el.classList.remove('active');
    });

    paramsButtonRef = sender;

    const inputContainer = sender.closest('.input-container');
    const rect = inputContainer.getBoundingClientRect();

    panel.style.bottom = `${window.innerHeight - rect.top}px`;
    panel.style.right  = `${window.innerWidth  - rect.right}px`;
    panel.style.left   = '';
    sender.classList.add('active');
    panel.style.display = 'flex';

    setTimeout(() => {
        panel.style.width = `${panel.getBoundingClientRect().width + 10}px`;
        panel.style.opacity = '1';
    }, 50);

    // Add outside click listener after a small delay to prevent immediate closing
    setTimeout(() => {
        document.addEventListener('click', handleParamsOutsideClick);
    }, 100);
}

function closeMsgParamsControlPanel(){
    const panel = document.getElementById('model-parameters-control-panel');
    panel.style.opacity = '0';
    setTimeout(() => {
        panel.style.display = 'none';
    }, 150);

    if (paramsButtonRef) {
        paramsButtonRef.classList.remove('active');
        paramsButtonRef = null;
    }

    document.removeEventListener('click', handleParamsOutsideClick);
}

function handleParamsOutsideClick(event){
    const panel = document.getElementById('model-parameters-control-panel');

    if (!panel || panel.style.display === 'none') {
        return;
    }

    const isClickInsidePanel = panel.contains(event.target);
    const isClickOnButton = paramsButtonRef && paramsButtonRef.contains(event.target);

    if (!isClickInsidePanel && !isClickOnButton) {
        closeMsgParamsControlPanel();
    }
}

function openInputModelSelector(sender){
    const inputContainer = sender.closest('.input-container');
    const rect = inputContainer.getBoundingClientRect();

    const menu = sender.parentElement.querySelector('#model-selector-burger');
    menu.style.position = 'fixed';
    menu.style.bottom   = `${window.innerHeight - rect.top}px`;
    menu.style.right    = `${window.innerWidth  - rect.right}px`;
    menu.style.left     = '';
    menu.style.top      = '';

    openBurgerMenu('model-selector-burger', sender, false, true, true);
}


document.addEventListener('DOMContentLoaded', () =>{
    initModelParamsPanel()
});
function initModelParamsPanel(){
    const panel = document.getElementById('model-parameters-control-panel');
    panel.querySelectorAll('input[type="range"]').forEach(el => {
        el.addEventListener('input', () => {
            handleSliderInput(el);
            if(el.dataset.param === 'temperature'){
                activeModel.params.temperature = parseFloat(el.value);
            } else if(el.dataset.param === 'top_p'){
                activeModel.params.top_p = parseFloat(el.value);
            }
        });
    })
}

function setModelParamDefault(){
    const params = activeModel.default_params;

    const panel = document.getElementById('model-parameters-control-panel');
    panel.querySelector('.default-temp').innerText = params.temp ?? 'N.A.';
    panel.querySelector('.default-top-p').innerText = params.top_p ?? 'N.A.';

    if(params){
        setModelParams(params);
        updateParamsPanel(params);
    }
}
function setModelParamPreset(temp, top_p){
    setModelParams({'temp': temp, 'top_p': top_p});
    updateParamsPanel({'temp': temp, 'top_p': top_p});
}
function setModelParams(params){
    activeModel.params = {
        'temperature': params.temp,
        'top_p': params.top_p
    };
}
function updateParamsPanel(params){
    const panel = document.getElementById('model-parameters-control-panel');
    setSliderValue(
        panel.querySelector('#temperature-input'),
        params.temp
    );
    setSliderValue(
        panel.querySelector('#top-p-input'),
        params.top_p
    );
}
function setSliderValue(slider, value){
    slider.value = value;
    handleSliderInput(slider);
}
function handleSliderInput(input){
    const indic = input.parentElement.querySelector('.input-indicator');
    indic.innerText = input.value;
}

// #endregion
