@extends('layouts.apps_layout')

@section('scripts')
    <script src="{{ asset('js_v2.0.1_f1/encryption.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/apps_confirm_functions.js') }}"></script>
    <script>
        let userInfo = @json($user);
    </script>
@endsection

@section('title', __('apps.confirm.title', ['appName' => $app_name]))

@section('content')
    <h1>{{__('apps.confirm.headline', ['appName' => $app_name])}}</h1>
    <p>{{__('apps.confirm.description', ['appName' => $app_name, 'name' => $user->name, 'username' => $user->username])}}</p>
    <h3>{{__('apps.confirm.about_headline')}}</h3>
    @if($logo || $description)
        <div class="app_details">
            @if($logo)
                <picture class="app_logo">
                    <source srcset="{{ $logo }}">
                    <img src="{{ $logo }}" alt="{{ $app_name }} logo">
                </picture>
            @endif
            @if($description)
                <p>{{ $description }}</p>
            @endif
        </div>
    @endif
    @component('partials.link-button', ['href' => $url, 'blank' => true])
        {{__('apps.confirm.open_link', ['appName' => $app_name])}}
    @endcomponent
    @component('partials.alert', [
        'type' => 'error',
        'attributes' => 'id="app-alert-error" style="display:none"',
        'message' => __('apps.confirm.error_message'),
    ])
    @endcomponent
    @component('partials.alert', [
        'type' => 'info',
        'attributes' => 'id="app-alert-waiting" style="display:none"',
        'message' => __('apps.confirm.working_message')
    ])
    @endcomponent
@endsection

@section('buttons')
    <button id="app-decline-button"
            class="btn-lg-fill"
            data-post-url="{{route('apps.confirm.decline')}}">
        {{__('apps.confirm.decline_button')}}
    </button>
    <button id="app-accept-button"
            class="btn-lg-fill"
            data-post-url="{{route('apps.confirm.accept')}}"
            data-user-public-key="{{ $user_public_key }}">
        {{__('apps.confirm.accept_button')}}
    </button>
@endsection
