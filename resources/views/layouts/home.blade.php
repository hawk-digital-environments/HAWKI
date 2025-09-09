
<!DOCTYPE html>
<html class="lightMode">
<head>


	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no" />
	<meta name="csrf-token" content="{{ csrf_token() }}">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">


	<title>{{ env('APP_NAME') }}</title>

	<link rel="icon" href="{{ asset('favicon.ico') }}">


    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/home-style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/settings_style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/hljs_custom.css') }}">

    @vite('resources/js/app.js')
    @vite('resources/css/app.css')

	<script src="{{ asset('js_v2.0.1_f1/functions.js') }}"></script>
	<script src="{{ asset('js_v2.0.1_f1/home_functions.js') }}"></script>
	<script src="{{ asset('js_v2.0.1_f1/stream_functions.js') }}"></script>
	<script src="{{ asset('js_v2.0.1_f1/ai_chat_functions.js') }}"></script>
	<script src="{{ asset('js_v2.0.1_f1/chatlog_functions.js') }}"></script>
	<script src="{{ asset('js_v2.0.1_f1/inputfield_functions.js') }}"></script>
	<script src="{{ asset('js_v2.0.1_f1/message_functions.js') }}"></script>
	<script src="{{ asset('js_v2.0.1_f1/groupchat_functions.js') }}"></script>
	<script src="{{ asset('js_v2.0.1_f1/syntax_modifier.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/settings_functions.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/encryption.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/image-selector.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/export.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/user_profile.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/file_manager.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/attachment_handler.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/model_list_filtering.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/announcements.js') }}"></script>

	@if(config('sanctum.allow_external_communication'))
		<script src="{{ asset('js_v2.0.1_f1/sanctum_functions.js') }}"></script>
    @endif


	{!! $settingsPanel !!}
    <script>
		SwitchDarkMode(false);
		UpdateSettingsLanguage('{{ Session::get("language")['id'] }}');
	</script>

</head>
<body>


	<div class="wrapper">

		@include('partials.home.sidebar')
		<div class="main">
			@yield('content')
		</div>
	</div>

	@include('partials.home.modals.guidelines-modal')
	@include('partials.home.modals.add-member-modal')
	@include('partials.home.modals.session-expiry-modal')
	@include('partials.home.modals.file-viewer-modal')
	@include('partials.home.modals.announcements-modal')

	@include('partials.overlay')

    @php
        $templates = collect(File::files(resource_path('views/partials/home/templates')))
            ->sortBy(fn($file) => $file->getFilename())
            ->values();
    @endphp
    @foreach ($templates as $temp)
        @include('partials.home.templates.' . $viewName = str_replace('.blade', '',  $temp->getFilenameWithoutExtension()))
    @endforeach
    @include('partials.home.modals.confirm-modal')

</body>
</html>

<script>

	const userInfo = @json($user);
	const userAvatarUrl = @json($userData['avatar_url']);
	const hawkiAvatarUrl = @json($userData['hawki_avatar_url']);
	const activeModule = @json($activeModule);


    const activeLocale = {!! json_encode(Session::get('language')) !!};
	const translation = @json($translation);

	const modelsList = @json($models).models;
	const defaultModels = @json($models).defaultModels;
	const systemModels = @json($models).systemModels;

	const aiHandle = "{{ config('hawki.aiHandle') }}";

    const announcementList = @json($announcements);

    const converterActive = @json($converterActive);


    window.addEventListener('DOMContentLoaded', async (event) => {
        setModel();

		const passkey = await getPassKey()
		if(!passkey){
			console.log('passkey not found!');
			window.location.href = '/handshake';
		}

		setSessionCheckerTimer(0);
		CheckModals()

		const tempLink = @json(session('invitation_tempLink'));
	    if (tempLink){
			await handleTempLinkInvitation(tempLink);
		}

		handleUserInvitations();


		//Module Checkup
		setActiveSidebarButton(activeModule);

		const sidebarBtn = document.getElementById('profile-sb-btn');
		if(userAvatarUrl){
			sidebarBtn.querySelector('.user-inits').style.display = 'none';
			sidebarBtn.querySelector('.icon-img').style.display = 'flex';
			sidebarBtn.querySelector('.icon-img').setAttribute('src', userAvatarUrl);
		}
		else{
			sidebarBtn.querySelector('.icon-img').style.display = 'none';
			const userInitials =  userInfo.name.slice(0, 1).toUpperCase();
			sidebarBtn.querySelector('.user-inits').style.display = "flex";
			sidebarBtn.querySelector('.user-inits').innerText = userInitials
		}


		initializeGUI();
		checkWindowSize(800, 200);

        initAnnouncements(announcementList);


		setTimeout(() => {
			if(@json($activeOverlay)){
				setOverlay(false, true)
			}
		}, 100);
    });


</script>

