<div class="modal" id="member-info-modal">
    <div class="modal-panel">
        <div class="modal-content-wrapper">
            
            <div class="closeButton" onclick="closeMemberInfoModal()">
                <svg viewBox="0 0 100 100"><path class="fill-svg" d="M 19.52 19.52 a 6.4 6.4 90 0 1 9.0496 0 L 51.2 42.1504 L 73.8304 19.52 a 6.4 6.4 90 0 1 9.0496 9.0496 L 60.2496 51.2 L 82.88 73.8304 a 6.4 6.4 90 0 1 -9.0496 9.0496 L 51.2 60.2496 L 28.5696 82.88 a 6.4 6.4 90 0 1 -9.0496 -9.0496 L 42.1504 51.2 L 19.52 28.5696 a 6.4 6.4 90 0 1 0 -9.0496 z"/></svg>
            </div>
        
            <div class="modal-content">
       
                <div class="avatar-editable">
                    <img class="icon-img selectable-image" id="member-avatar"   alt="">
                    <div class="control-panel-chat-initials" id="member-inits"></div>
                </div>

                <h3 id="youTag" class="zero-b-margin top-gap-1">( {{ $translation["You"] }} )</h3>
                <h3 id="username" class="zero-b-margin top-gap-1"></h3>
                <p id="dis-name" class="zero-v-margin"></p>
                <p id="role" class="" ></p>

                <button class="btn-md-txt red-text admin-only" id="remove-member-btn">{{ $translation["MemberRemove"] }}</button>

            </div>
        </div>
    </div>
</div>


<script>

    function openMemberInfoPanel(btn){

        const data = JSON.parse(btn.getAttribute('memberObj'));
        const panel = document.getElementById('member-info-modal');



        if(data.avatar_url){
            panel.querySelector('#member-inits').style.display = "none";
            panel.querySelector('#member-avatar').style.display = "flex";
            panel.querySelector('#member-avatar').setAttribute('src', data.avatar_url);
        }
        else{
            panel.querySelector('#member-avatar').style.display = "none";
            panel.querySelector('#member-inits').style.display = "flex";
            panel.querySelector('#member-inits').innerText = data.name.slice(0, 1).toUpperCase()
        }

        panel.querySelector('#username').innerText = data.username;
        panel.querySelector('#dis-name').innerText = data.name;
        // console.log(data.role);
        panel.querySelector('#role').innerText = data.role;

        if(data.username === userInfo.username){
            panel.querySelector('#remove-member-btn').style.display = "none";
            panel.querySelector('#youTag').style.display = "block";
        }
        else{
            panel.querySelector('#youTag').style.display = "none";
            panel.querySelector('#remove-member-btn').style.display = "block";

            panel.querySelector('#remove-member-btn').addEventListener('click', async function() {
                const success = await removeMemberFromRoom(data.username);
                if(success){
                    closeMemberInfoModal();
                    btn.remove();
                }
            });
        }


        panel.style.display = 'flex';
    }

    function closeMemberInfoModal() {
        document.getElementById("member-info-modal").style.display = "none";
    }

</script>