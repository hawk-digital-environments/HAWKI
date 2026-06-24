@extends('layouts.home')
@section('content')

    <div class="main-panel-grid">

        <div class="dy-sidebar expanded" id="groupchat-sidebar">
            <div class="dy-sidebar-wrapper">
                <!-- <div class="welcome-panel">
				<h1>{{ Auth::user()->name }}</h1>
			</div> -->
                <div class="header">
                    @php $tooltipId = str()->uuid() @endphp
                    <button class="btn-md-stroke" onclick="openRoomCreatorPanel()" aria-labelledby="{{ $tooltipId }}">
                        <div class="icon" aria-hidden="true">
                            <x-icon name="plus"/>
                        </div>
                        <div class="label" aria-hidden="true" id="{{ $tooltipId }}"><strong>{{ __("CreateRoom") }}</strong></div>
                    </button>
                    <h3 class="title">{{ __("Rooms") }}</h3>

                </div>
                <div class="dy-sidebar-content-panel" tabindex="-1">
                    <div class="dy-sidebar-scroll-panel">
                        <div class="selection-list" id="rooms-list">


                        </div>
                    </div>
                </div>

                <div class="dy-sidebar-expand-btn" onclick="togglePanelClass('chat-sidebar', 'expanded')">
                    <x-icon name="chevron-right"/>
                </div>

            </div>
        </div>

        <div class="dy-main-panel">

            <div class="dy-main-content" id="group-welcome-panel">

                <div class="scroll-container" id="welcome-content">
                    <div class="group-welcome-wrapper scroll-panel">
                        {!! __("_GroupWelcome") !!}
                        <button class="btn-lg-fill" onclick="openRoomCreatorPanel()">{{ __("CreateARoom") }}</button>
                    </div>
                </div>
            </div>

            <div class="dy-main-content" id="chat">
                <div class="chatlog">
                    <div class="chatlog-container ">

                        <div class="scroll-container">
                            <div class="scroll-panel">
                                <div class="thread trunk" id="0">

                                </div>
                            </div>

                        </div>
                    </div>
                    <x-svelte type="ChatHeader" :props="['context' => 'room']"/>
                    <div class="input-container admin-only editor-only">
                        <div class="input-stats-container">
                            <div class="isTypingStatus"></div>
                            <div id="input-feedback-msg"></div>
                        </div>
                        <x-svelte type="ChatComposer" :props="['context' => 'room']"/>
                    </div>
                </div>
                <p class="warning">{{ __("MistakeWarning") }}</p>

            </div>

            @include('partials.home.room-creation')
            @include('partials.home.room-control-panel')

        </div>
    </div>

    <script>

        window.waitUntilReady(async function () {

            await loadMessageFormattingDependencies();
            initializeGroupChatModule(@json($userData['rooms']));

            const slug = @json($slug);
            if (slug) {
                await loadRoom(null, slug);
            } else {
                switchDyMainContent('group-welcome-panel');
            }

        });


    </script>
@endsection
