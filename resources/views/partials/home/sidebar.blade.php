<div class="main-sidebar">
        <div class="sidebar-content">
            <div class="upper-panel">
                <button id="chat-sb-btn" onclick="onSidebarButtonDown('chat')" href="chat" class="btn-sm sidebar-btn tooltip-parent">
                    <x-icon name="chat-icon"/>

                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Chat"] }}
                    </div>
                </button>
                <button id="groupchat-sb-btn" onclick="onSidebarButtonDown('groupchat')" class="btn-sm sidebar-btn tooltip-parent">
                    <x-icon name="assistant-icon"/>

                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Groupchat"] }}
                    </div>
                </button>

                <button id="profile-sb-btn" onclick="onSidebarButtonDown('profile')" class="btn-sm sidebar-btn tooltip-parent">
                    <div class="profile-icon round-icon">
                        <span class="user-inits" style="display:none"></span>
                        <img class="icon-img"   alt="">
                    </div>
                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Profile"] }}
                    </div>
                </button>
            </div>



            <div class="lower-panel">
                <button onclick="logout()" class="btn-sm sidebar-btn tooltip-parent" >
                    <x-icon name="logout-icon"/>
                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Logout"] }}
                    </div>
                </button>
                <button class="btn-sm sidebar-btn tooltip-parent" onclick="toggleSettingsPanel(true)">
                    <x-icon name="settings-icon"/>
                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Settings"] }}
                    </div>
                </button>
            </div>
        </div>
        <!-- <div class="logo-panel">
            <img src="{{ asset('img/logo.svg') }}" alt="">
        </div> -->

	</div>
