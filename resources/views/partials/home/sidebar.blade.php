<div class="main-sidebar">
        <div class="sidebar-content">
            <div class="upper-panel">
                <button id="chat-sb-btn" onclick="onSidebarButtonDown('chat')" href="chat" class="btn-sm sidebar-btn tooltip-parent" aria-describedby="chat-tooltip">
                    <x-icon name="chat-icon" aria-hidden="true"/>
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="chat-tooltip">
                        {{ $translation["Chat"] }}
                    </div>
                </button>
                <button id="groupchat-sb-btn" onclick="onSidebarButtonDown('groupchat')" class="btn-sm sidebar-btn tooltip-parent" aria-describedby="groupchat-tooltip">
                    <x-icon name="assistant-icon" aria-hidden="true" />
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="groupchat-tooltip">
                        {{ $translation["Groupchat"] }}
                    </div>
                </button>

                <button id="profile-sb-btn" onclick="onSidebarButtonDown('profile')" class="btn-sm sidebar-btn tooltip-parent" aria-describedby="profile-tooltip">
                    <div class="profile-icon round-icon" aria-hidden="true">
                        <span class="user-inits" style="display:none"></span>
                        <img class="icon-img" alt="">
                    </div>
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="profile-tooltip">
                        {{ $translation["Profile"] }}
                    </div>
                </button>
            </div>

            <div class="lower-panel">
                <button onclick="logout()" class="btn-sm sidebar-btn tooltip-parent" aria-describedby="logout-tooltip">
                    <x-icon name="logout-icon" aria-hidden="true"/>
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="logout-tooltip">
                        {{ $translation["Logout"] }}
                    </div>
                </button>
                <button class="btn-sm sidebar-btn tooltip-parent" onclick="toggleSettingsPanel(true)" aria-describedby="settings-tooltip">
                    <x-icon name="settings-icon" aria-hidden="true"/>
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="settings-tooltip">
                        {{ $translation["Settings"] }}
                    </div>
                </button>
            </div>
        </div>
        <!-- <div class="logo-panel">
            <img src="{{ asset('img/logo.svg') }}" alt="">
        </div> -->
	</div>
