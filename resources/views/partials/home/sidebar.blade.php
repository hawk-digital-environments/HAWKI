<div class="main-sidebar">
        <div class="sidebar-content">
            <div class="upper-panel">
                @php $tooltipId = str()->uuid() @endphp
                <button id="chat-sb-btn" onclick="onSidebarButtonDown('chat')" href="chat" class="btn-sm sidebar-btn tooltip-parent" aria-describedby="{{ $tooltipId }}">
                    <x-icon name="chat-icon" aria-hidden="true"/>
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="{{ $tooltipId }}">
                        {{ $translation["Chat"] }}
                    </div>
                </button>
                @php $tooltipId = str()->uuid() @endphp
                <button id="groupchat-sb-btn" onclick="onSidebarButtonDown('groupchat')" class="btn-sm sidebar-btn tooltip-parent" aria-describedby="{{ $tooltipId }}">
                    <x-icon name="assistant-icon" aria-hidden="true" />
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="{{ $tooltipId }}">
                        {{ $translation["Groupchat"] }}
                    </div>
                </button>
                @php $tooltipId = str()->uuid() @endphp
                <button id="profile-sb-btn" onclick="onSidebarButtonDown('profile')" class="btn-sm sidebar-btn tooltip-parent" aria-describedby="{{ $tooltipId }}">
                    <div class="profile-icon round-icon" aria-hidden="true">
                        <span class="user-inits" style="display:none"></span>
                        <img class="icon-img">
                    </div>
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="{{ $tooltipId }}">
                        {{ $translation["Profile"] }}
                    </div>
                </button>
            </div>

            <div class="lower-panel">
                @php $tooltipId = str()->uuid() @endphp
                <button onclick="logout()" class="btn-sm sidebar-btn tooltip-parent" aria-describedby="{{ $tooltipId }}">
                    <x-icon name="logout-icon" aria-hidden="true"/>
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="{{ $tooltipId }}">
                        {{ $translation["Logout"] }}
                    </div>
                </button>
                @php $tooltipId = str()->uuid() @endphp
                <button class="btn-sm sidebar-btn tooltip-parent" onclick="toggleSettingsPanel(true)" aria-describedby="{{ $tooltipId }}">
                    <x-icon name="settings-icon" aria-hidden="true"/>
                    <div class="label tooltip tt-abs-left" aria-hidden="true" id="{{ $tooltipId }}">
                        {{ $translation["Settings"] }}
                    </div>
                </button>
            </div>
        </div>
        <!-- <div class="logo-panel">
            <img src="{{ asset('img/logo.svg') }}" alt="">
        </div> -->
	</div>
