window.addEventListener('keydown', async (event) => {
    if(event.key == "Z" && event.shiftKey){
        createStatusElement('Generating', '0.000');
    }
    if(event.key == "X" && event.shiftKey){
        createStatusElement('Updating', '0.000');
    }
});



function createStatusElement(status, msgId){

    let statElement = document.querySelector(`.gen-stat-element[data-index="${msgId}"]`);
    let [msgWholeNum, msgDecimalNum] = msgId.split('.').map(Number);

    if (msgDecimalNum === 0) {
        threadIndex = 0;
    } else {
        threadIndex = msgWholeNum;
    }
    let activeThread = findThreadWithID(threadIndex);


    //create a new element for first status
    if(!statElement){
        console.log('no Element');
        const statTemp = document.getElementById('gen-stat-template')
        const statClone = statTemp.content.cloneNode(true);
        statElement = statClone.querySelector(".gen-stat-element");
        statElement.dataset.index = msgId;
        activeThread.appendChild(statElement);
        console.log(activeThread);
    }

    const textEl = statElement.querySelector('.stat-txt');

    textEl.innerText = status;

    tripleDotAnime(statElement, status);
}

function removeStatusElement(msgId){
    let statElement = document.querySelector(`.gen-stat-element[data-index="${msgId}"]`);
    if(statElement){
        statElement.remove();
    }
}



function tripleDotAnime(statElement, status){
    // ---- Clear any existing interval ----
    if (statElement._dotsIntervalId) {
        clearInterval(statElement._dotsIntervalId);
    }

    const textEl = statElement.querySelector('.stat-txt');
    let frame = 0;
    const frames = [" ", " .", " ..", " ..."];

    // ---- Start new interval and store its id ----
    const intervalId = setInterval(()=> {
        textEl.innerText = status + frames[frame];
        frame = (frame + 1) % frames.length;
    }, 500);
    statElement._dotsIntervalId = intervalId;
}
function clearElementInterval(statElement){
    if (statElement) {
        if (statElement._dotsIntervalId) {
            clearInterval(statElement._dotsIntervalId);
        }
    }
}