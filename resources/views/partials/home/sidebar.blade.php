<div class="main-sidebar">
        <div class="sidebar-content">
            <div class="upper-panel">
                @if(Auth::user()->hasAccess('chat.access'))
                <button id="chat-sb-btn" onclick="onSidebarButtonDown('chat')" href="chat" class="btn-sm sidebar-btn tooltip-parent">
                    <x-icon name="chat-icon"/>

                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Chat"] }}
                    </div>
                </button>
                @endif

                @if(Auth::user()->hasAccess('groupchat.access') && config('hawki.groupchat_active', false))
                <button id="groupchat-sb-btn" onclick="onSidebarButtonDown('groupchat')" class="btn-sm sidebar-btn tooltip-parent" style="position: relative;">
                    <x-icon name="assistant-icon"/>
                    <!-- Red badge for new invitations (top-right) -->
                    <div class="notification-badge new-room" id="groupchat-invitation-badge"></div>
                    <!-- Green badge for new messages (bottom-right) -->
                    <div class="notification-badge new-message" id="groupchat-message-badge"></div>

                    <div class="label tooltip tt-abs-left">
                        {{ $translation["Groupchat"] }}
                    </div>
                </button>
                @endif

                <button id="news-sb-btn" onclick="onSidebarButtonDown('news')" href="chat" class="btn-sm sidebar-btn tooltip-parent">
                    <x-icon name="news"/>

                    <div class="label tooltip tt-abs-left">
                        {{ $translation["News"] }}
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
