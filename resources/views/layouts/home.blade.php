
<!DOCTYPE html>
<html class="lightMode">
<head>


	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<meta name="csrf-token" content="{{ csrf_token() }}">


    <title>{{ config('app.name') }}</title>

	<link rel="icon" href="{{ route('system.image', 'favicon') }}">


{{--    <link rel="stylesheet" href="{{ asset('css_v2.1.0/gfont-firesans/firesans.css') }}">--}}
    <link rel="stylesheet" href="{{ route('css.get', 'style') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'custom-styles') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'chat_modules') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'home-style') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'settings_style') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'hljs_custom') }}">

    @vite('resources/js/app.js')
    @vite('resources/css/app.css')

    <script src="{{ asset('js/functions.js') }}"></script>
    <script src="{{ asset('js/home_functions.js') }}"></script>
    <script src="{{ asset('js/stream_functions.js') }}"></script>
    <script src="{{ asset('js/ai_chat_functions.js') }}"></script>
    <script src="{{ asset('js/chatlog_functions.js') }}"></script>
    <script src="{{ asset('js/inputfield_functions.js') }}"></script>
    <script src="{{ asset('js/message_functions.js') }}"></script>
    <script src="{{ asset('js/groupchat_functions.js') }}"></script>
    <script src="{{ asset('js/syntax_modifier.js') }}"></script>
    <script src="{{ asset('js/settings_functions.js') }}"></script>
    <script src="{{ asset('js/encryption.js') }}"></script>
    <script src="{{ asset('js/image-selector.js') }}"></script>
    <script src="{{ asset('js/export.js') }}"></script>
    <script src="{{ asset('js/user_profile.js') }}"></script>
    <script src="{{ asset('js/file_manager.js') }}"></script>
    <script src="{{ asset('js/attachment_handler.js') }}"></script>
    <script src="{{ asset('js/model_list_filtering.js') }}"></script>
    <script src="{{ asset('js/announcements.js') }}"></script>

	@if(config('sanctum.allow_external_communication'))
        <script src="{{ asset('js/sanctum_functions.js') }}"></script>
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
	@include('partials.home.modals.room-invitation-modal')
	@include('partials.home.modals.room-removed-modal')
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
    const hawkiUsername = @json($userData['hawki_username'])

    const activeLocale = {!! json_encode(Session::get('language')) !!};
	const translation = @json($translation);

	const modelsList = @json($models).models.filter(model => !model.hasOwnProperty('visible') || model.visible);
	const defaultModels = @json($models).defaultModels;
	const systemModels = @json($models).systemModels;

	const aiHandle = "{{ config('hawki.aiHandle') }}";
	const webSearchAutoEnable = {{ config('hawki.websearch_auto_enable') ? 'true' : 'false' }};
	const forceDefaultModel = {{ config('hawki.force_default_model') ? 'true' : 'false' }};

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

		// DO NOT auto-accept invitations - let user click on room to accept
		// handleUserInvitations();

		// Listen for new invitations via WebSocket
		window.Echo.private(`User.${userInfo.username}`)
			.listen('RoomInvitationEvent', async (e) => {
				console.log('=== RoomInvitationEvent received ===');
				const invitationData = e.data;
				console.log('Invitation data:', invitationData);
				
				// Initialize rooms array if not exists
				if (typeof rooms === 'undefined') {
					console.log('Rooms array not initialized, creating empty array');
					rooms = [];
				}
				
				// Check if room already exists (e.g., from previous removal)
				const existingRoom = rooms.find(r => r.slug === invitationData.room.slug);
				console.log('Room already exists:', !!existingRoom);
				
				if (existingRoom) {
					// Update existing room to new invitation status
					console.log('Updating existing room to invitation status');
					existingRoom.isNewRoom = true;
					existingRoom.isRemoved = false;
					existingRoom.hasUnreadMessages = false;
					existingRoom.room_name = invitationData.room.room_name;
					existingRoom.room_icon = invitationData.room.room_icon;
					existingRoom.invited_by = invitationData.room.invited_by;
					
					// Update UI if in GroupChat
					if (typeof flagRoomUnreadMessages === 'function') {
						console.log('Setting badge on re-invited room');
						flagRoomUnreadMessages(existingRoom.slug, true, true);  // Red badge
					}
					
					// Show burger button again if it was hidden
					const selector = document.querySelector(`.selection-item[slug="${existingRoom.slug}"]`);
					if (selector) {
						const burgerBtn = selector.querySelector('.burger-btn');
						if (burgerBtn) {
							burgerBtn.style.display = 'block';
							console.log('Burger button shown for re-invited room');
						}
					}
				} else {
					// Create new room
					const newRoom = {
						slug: invitationData.room.slug,
						room_name: invitationData.room.room_name,
						room_icon: invitationData.room.room_icon,
						invited_by: invitationData.room.invited_by,
						hasUnreadMessages: false,
						isNewRoom: true,
						isRemoved: false
					};
					rooms.push(newRoom);
					console.log('New room added to array:', newRoom);
					
					// If GroupChat module is active AND initialized, create room item
					console.log('typeof createRoomItem:', typeof createRoomItem);
					console.log('typeof roomItemTemplate:', typeof roomItemTemplate);
					console.log('roomItemTemplate defined:', !!roomItemTemplate);
					
					if (typeof createRoomItem === 'function' && typeof roomItemTemplate !== 'undefined' && roomItemTemplate) {
						console.log('Creating room item in DOM');
						createRoomItem(newRoom);
						
						if (typeof flagRoomUnreadMessages === 'function') {
							console.log('Setting badge on new room');
							flagRoomUnreadMessages(newRoom.slug, true, true);  // Red badge
						}
						// Don't connect WebSocket for new invitations - user is not a member yet!
					} else {
						console.log('GroupChat module not ready, room added to array only');
					}
				}
				
				// Update sidebar badge (red for new invitation)
				if (typeof checkAndUpdateSidebarBadge === 'function') {
					console.log('Updating sidebar badge');
					checkAndUpdateSidebarBadge();
				} else {
					console.log('checkAndUpdateSidebarBadge not available');
				}
				
				console.log('=== End RoomInvitationEvent ===');
			})
			.listen('RoomMemberRemovedEvent', async (e) => {
				console.log('=== RoomMemberRemovedEvent received ===');
				console.log('Event data:', e);
				const { room_slug, room_name } = e;
				console.log('Room slug:', room_slug);
				console.log('Room name:', room_name);
				
				console.log('Current rooms array:', rooms);
				
				// Find the room in the rooms array
				const room = rooms?.find(r => r.slug === room_slug);
				console.log('Found room in array:', room);
				
				if (room) {
					// Mark room as removed
					room.isRemoved = true;
					room.isNewRoom = false; // Not an invitation anymore
					
					console.log('Room marked as removed. Updated room:', room);
					
					// Hide burger menu button for this room
					const selector = document.querySelector(`.selection-item[slug="${room_slug}"]`);
					if (selector) {
						const burgerBtn = selector.querySelector('.burger-btn');
						if (burgerBtn) {
							burgerBtn.style.display = 'none';
							console.log('Burger button hidden for removed room');
						}
					}
					
					// Add red badge to room in list - use function if available, otherwise set directly
					const setBadge = () => {
						if (typeof flagRoomUnreadMessages === 'function') {
							console.log('Using flagRoomUnreadMessages function');
							flagRoomUnreadMessages(room_slug, true, true);
						} else {
							// Fallback: set badge directly
							console.log('flagRoomUnreadMessages not available, setting badge directly');
							const selector = document.querySelector(`.selection-item[slug="${room_slug}"]`);
							if (selector) {
								const flag = selector.querySelector('#unread-msg-flag');
								if (flag) {
									flag.style.display = 'block';
									flag.classList.add('new-room');
									flag.classList.remove('new-message');
									console.log('Badge set directly on room element');
								} else {
									console.warn('Flag element not found');
								}
							} else {
								console.warn('Room element not found in DOM');
							}
						}
					};
					
					// Try immediately
					setBadge();
					
					// Try again after a small delay in case DOM isn't ready
					setTimeout(setBadge, 100);
					
					// Update sidebar badge
					if (typeof checkAndUpdateSidebarBadge === 'function') {
						console.log('Calling checkAndUpdateSidebarBadge...');
						checkAndUpdateSidebarBadge();
					} else {
						console.error('checkAndUpdateSidebarBadge function NOT available!');
					}
					
					// If user is currently viewing this room, close it
					if (typeof activeRoom !== 'undefined' && activeRoom?.slug === room_slug) {
						console.log('User is viewing this room, closing it...');
						if (typeof closeActiveRoom === 'function') {
							closeActiveRoom();
						}
					}
				} else {
					console.error('Room NOT found in rooms array!');
					console.log('Looking for slug:', room_slug);
					console.log('Available room slugs:', rooms?.map(r => r.slug));
				}
				console.log('=== End RoomMemberRemovedEvent ===');
			});

		// Initialize rooms array and check for unread messages (for sidebar badge)
		const roomsData = @json($userData['rooms']);
		
		if (roomsData && roomsData.length > 0) {
			// Set global rooms variable if not already set by GroupChat module
			if (typeof rooms === 'undefined') {
				rooms = roomsData;
			}
			
			// Connect WebSockets only for rooms where user is a member (not for new invitations)
			roomsData.forEach(roomItem => {
				// Skip WebSocket connection for new invitations (user is not a member yet)
				if (roomItem.isNewRoom) {
					return;
				}
				
				if (typeof connectWebSocket === 'function') {
					connectWebSocket(roomItem.slug);
				}
				if (typeof connectWhisperSocket === 'function') {
					connectWhisperSocket(roomItem.slug);
				}
			});
			
			// Update sidebar badge based on initial state
			if (typeof checkAndUpdateSidebarBadge === 'function') {
				checkAndUpdateSidebarBadge();
			}
		}

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
