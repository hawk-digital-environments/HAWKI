async function toggleAccessTokensPanel(active){

    const panel = document.querySelector('#access-token-modal');
    const newTokenField = panel.querySelector('#newAccessTokenName');
    const chart = panel.querySelector('#access-token-chart');

    if(active){
        newTokenField.style.display = 'none';
        chart.innerHTML = '';
        const tokens = await fetchUserTokens();

        // console.log(tokens);
        for(let i = 0; i < tokens.length; i++){
            const t = tokens[i];
            addTokenToList(t);
        };

        panel.style.display = "flex";

    }
    else{
        panel.style.display = "none"
    }
}

async function fetchUserTokens(){
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(`/req/profile/fetch-tokens`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });
        const data = await response.json();
        if (data.success) {
            return data.tokens;
        } else {
            console.error('Failed to fetch tokens!');
        }
    } catch (error) {
        console.error('Failed to fetch tokens!');
    }
}

async function addNewToken(){
    const panel = document.querySelector('#access-token-modal');
    const newTokenField = panel.querySelector('#newAccessTokenName');

    if(newTokenField.style.display === "block"){
        const name = newTokenField.value;

        if(name === ''){
            return;
        }
        const data = await requestNewToken(name);
        const token = {
            'id': data.id,
            'name': data.name,
            'token': data.token
        }
        addTokenToList(token);
        newTokenField.value = '';
        newTokenField.style.display = 'none';
        await openModal(ModalType.INFO,
            `
             <p>${translation.Cnf_tokenMsg1}</p>
             <p>******</p>
             <p><b>${data.token}</b></p>
             <p>******</p>
             <p class="red-text">${translation.Cnf_tokenMsg2}</p>
            `,
            `<h3>${translation.Cnf_tokenMsgSuccess}</h3>`
        )

        return;
    }
    newTokenField.style.display="block";
    panel.querySelector('#createButton').innerText='Confirm';
}

async function requestNewToken(name){
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(`/req/profile/create-token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                'name': name
            })
        });
        const data = await response.json();
        if (data.success) {
           return data;
        } else {
            console.error(data.error);
        }
    } catch (error) {
        console.error('Token Creation Failed!');
    }
}

function addTokenToList(token){
    const panel = document.querySelector('#access-token-modal');
    const chart = panel.querySelector('#access-token-chart');

    const rowTemp = document.getElementById('token-list-row-temp');
    const rowClone = rowTemp.content.cloneNode(true);
    const rowElement = rowClone.querySelector("#token-item");

    rowElement.querySelector('.index').innerText = chart.childElementCount + 1;
    rowElement.querySelector('.token-name').innerText = token.name;
    rowElement.dataset.index = token.id;

    chart.appendChild(rowElement);
}

async function requestTokenRevoke(btn){
    const item = btn.closest('#token-item')
    const name = item.querySelector('.token-name').innerText;
    const confirm = await openModal(ModalType.WARNING, `${translation.Cnf_tokenRevoke} ${name}`)
    if(!confirm){
        return;
    }

    const tokenId = item.dataset.index;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(`/req/profile/revoke-token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                'tokenId': tokenId
            })
        });
        const data = await response.json();
        if (data.success) {
            item.remove();
        } else {
            console.error('Token Remove Failed!');
        }
    } catch (error) {
        console.error('Token Remove Failed!');
    }
}
