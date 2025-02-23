@extends('layouts.home')
@section('content')


<div class="main-panel-grid">

	<div class="dy-sidebar expanded" id="groupchat-sidebar">
		<div class="dy-sidebar-wrapper">
			<!-- <div class="welcome-panel">
				<h1>{{ Auth::user()->name }}</h1>
			</div> -->
			<div class="header">
				
				<button class="btn-md-stroke" onclick="openRoomCreatorPanel()">
					<div class="icon">
						<x-icon name="plus"/>
					</div>
					<div class="label"><strong>{{ $translation["CreateRoom"] }}</strong></div>
				</button>
				<h3 class="title">{{ $translation["Rooms"] }}</h3>

			</div>
			<div class="dy-sidebar-content-panel">
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
					{!! $translation["_GroupWelcome"] !!}
					<button class="btn-lg-fill" onclick="openRoomCreatorPanel()">{{ $translation["CreateARoom"] }}</button>
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
				@include('partials.home.input-field', ['lite' => false])
			</div>
			<p class="warning">{{ $translation["MistakeWarning"] }}</p>

		</div>

		@include('partials.home.room-creation')
		@include('partials.home.room-control-panel')

	</div>
</div>

<script>

window.addEventListener('DOMContentLoaded', async function (){
	
	initializeGroupChatModule(@json($userData['rooms']));
	
	const slug = @json($slug);
	if (slug){
		await loadRoom(null, slug);
	}
	else{
        switchDyMainContent('group-welcome-panel');
	}
	
});


</script>
@endsection