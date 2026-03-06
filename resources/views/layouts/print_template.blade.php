<!DOCTYPE html>
<html class="lightMode">
<head>

	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no" />
	<meta name="csrf-token" content="{{ csrf_token() }}">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">

	<title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <link rel="stylesheet" href="{{ asset('css/print_styles.css') }}">

    @vite('resources/js/app.js')

	<script src="{{ asset('js/message_functions.js') }}"></script>
	<script src="{{ asset('js/stream_functions.js') }}"></script>
	<script src="{{ asset('js/syntax_modifier.js') }}"></script>
    <script src="{{ asset('js/encryption.js') }}"></script>
    <script src="{{ asset('js/export.js') }}"></script>
    <script src="{{ asset('js/file_manager.js') }}"></script>
    <script src="{{ asset('js/attachment_handler.js') }}"></script>


</head>
<body>
    <div class="wrapper">
        <div class="chatlog-container">

            <div class="scroll-container">
                <div class="scroll-panel">

                </div>
            </div>

            </div>
    </div>



<template id="thread-template">
	<div class="thread" id="0">

	</div>
</template>

<template id="message-template">
	<div class="message" id="">
		<div class="message-wrapper">
			<div class="message-header">
				<div class="message-author"></div>
			</div>
			<div class="attachments"></div>

			<div class="message-content">
				<span class="assistant-mention"></span>
				<span class="message-text"></span>
			</div>

		</div>
	</div>
</template>

@include('partials.home.templates.attachment-template')
</body>
</html>


<script>

    const userInfo = @json($user);
	const userAvatarUrl = @json($userData['avatar_url']);
	const hawkiAvatarUrl = @json($userData['hawki_avatar_url']);
	const activeModule = @json($activeModule);
    const chatData = @json($chatData);
	const activeLocale = {!! json_encode(Session::get('language')) !!};
	const aiHandle = "{{ config('hawki.aiHandle') }}";
	const webSearchAutoEnable = {{ config('hawki.websearch_auto_enable') ? 'true' : 'false' }};
	const forceDefaultModel = {{ config('hawki.force_default_model') ? 'true' : 'false' }};

	const modelsList = @json($models).models.filter(model => !model.hasOwnProperty('visible') || model.visible);
	const defaultModels = @json($models).defaultModels;
	const systemModels = @json($models).systemModels;

	const translation = @json($translation);
	window.addEventListener('DOMContentLoaded', async (event) => {
        preparePrintPage();
    });

</script>
