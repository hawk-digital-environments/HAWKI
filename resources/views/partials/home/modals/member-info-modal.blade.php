<div class="modal" id="member-info-modal">
    <div class="modal-panel">
        <div class="modal-content-wrapper">

            <button class="closeButton btn-sm" onclick="closeMemberInfoModal()" aria-label="{{ __("Close") }}">
                <svg viewBox="0 0 100 100" aria-hidden="true">
                    <path class="fill-svg" d="M 19.52 19.52 a 6.4 6.4 90 0 1 9.0496 0 L 51.2 42.1504 L 73.8304 19.52 a 6.4 6.4 90 0 1 9.0496 9.0496 L 60.2496 51.2 L 82.88 73.8304 a 6.4 6.4 90 0 1 -9.0496 9.0496 L 51.2 60.2496 L 28.5696 82.88 a 6.4 6.4 90 0 1 -9.0496 -9.0496 L 42.1504 51.2 L 19.52 28.5696 a 6.4 6.4 90 0 1 0 -9.0496 z"/>
                </svg>
            </button>

            <div class="modal-content">

                <div class="avatar-editable">
                    <img class="icon-img selectable-image" id="member-avatar" alt="">
                    <div class="control-panel-chat-initials" id="member-inits"></div>
                </div>

                <h3 id="youTag" class="zero-b-margin top-gap-1">( {{ __("You") }} )</h3>
                <h3 id="username" class="zero-b-margin top-gap-1"></h3>
                <p id="dis-name" class="zero-v-margin"></p>
                <div class="member-role-row">
                    <p id="role" class=""></p>
                    <select class="member-role-selector" id="role-selector" aria-label="{{ __("MemberRoleEdit") }}">
                        <option value="admin">Admin</option>
                        <option value="editor">Editor</option>
                        <option value="viewer">Viewer</option>
                    </select>
                    <button class="btn-xs" id="edit-role-btn" aria-label="{{ __("MemberRoleEdit") }}" title="{{ __("MemberRoleEdit") }}">
                        <x-icon name="edit" aria-hidden="true"/>
                    </button>
                    <button class="btn-xs" id="confirm-role-btn" aria-label="{{ __("MemberRoleConfirm") }}" title="{{ __("MemberRoleConfirm") }}">
                        <x-icon name="check" aria-hidden="true"/>
                    </button>
                </div>

                <button class="btn-md-txt red-text admin-only" id="remove-member-btn">{{ __("MemberRemove") }}</button>

            </div>
        </div>
    </div>
</div>


<script>

    function openMemberInfoPanel(btn) {

        const data = JSON.parse(btn.getAttribute('memberObj'));
        const panel = document.getElementById('member-info-modal');
        const userInfo = window.getAuthenticatedConnection().userinfo;


        if (data.avatar_url) {
            panel.querySelector('#member-inits').style.display = 'none';
            panel.querySelector('#member-avatar').style.display = 'flex';
            panel.querySelector('#member-avatar').setAttribute('src', data.avatar_url);
        } else {
            panel.querySelector('#member-avatar').style.display = 'none';
            panel.querySelector('#member-inits').style.display = 'flex';
            panel.querySelector('#member-inits').innerText = data.name.slice(0, 1).toUpperCase();
        }

        panel.querySelector('#username').innerText = data.username;
        panel.querySelector('#dis-name').innerText = data.name;

        const isSelf = data.username === userInfo.username;
        const canManage = !isSelf && activeRoom?.role === 'admin';

        const removeBtn = panel.querySelector('#remove-member-btn');
        panel.querySelector('#youTag').style.display = isSelf ? 'block' : 'none';
        removeBtn.style.display = canManage ? 'block' : 'none';
        // Assign (instead of addEventListener) so handlers of previously opened members don't pile up
        removeBtn.onclick = canManage ? async function () {
            const success = await removeMemberFromRoom(data.username);
            if (success) {
                closeMemberInfoModal();
                btn.remove();
            }
        } : null;

        initMemberRoleEditor(panel, btn, data, canManage);


        panel.style.display = 'flex';
    }

    function initMemberRoleEditor(panel, btn, data, canManage) {
        const roleText = panel.querySelector('#role');
        const selector = panel.querySelector('#role-selector');
        const editBtn = panel.querySelector('#edit-role-btn');
        const confirmBtn = panel.querySelector('#confirm-role-btn');

        const setEditing = (editing) => {
            roleText.style.display = editing ? 'none' : 'block';
            selector.style.display = editing ? 'block' : 'none';
            editBtn.style.display = !editing && canManage ? 'flex' : 'none';
            confirmBtn.style.display = editing ? 'flex' : 'none';
            confirmBtn.disabled = false;
        };

        roleText.innerText = data.role;
        setEditing(false);

        editBtn.onclick = canManage ? function () {
            selector.value = data.role;
            setEditing(true);
            selector.focus();
        } : null;

        confirmBtn.onclick = canManage ? async function () {
            const newRole = selector.value;
            if (newRole !== data.role) {
                confirmBtn.disabled = true;
                const success = await changeMemberRole(data.username, newRole);
                if (!success) {
                    confirmBtn.disabled = false;
                    return;
                }
                data.role = newRole;
                btn.setAttribute('memberObj', JSON.stringify(data));
                roleText.innerText = newRole;
            }
            setEditing(false);
        } : null;
    }

    function closeMemberInfoModal() {
        document.getElementById('member-info-modal').style.display = 'none';
    }

</script>
