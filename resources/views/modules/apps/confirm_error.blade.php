@extends('layouts.apps_layout')

@section('title', __('apps.confirm_error.title'))

@section('content')
    <h1>{{__('apps.confirm_error.headline')}}</h1>
    <p>{{__('apps.confirm_error.description')}}</p>
    <div class="nav-buttons">
        <a href="{{route('login')}}" class="btn-lg-fill">{{__('apps.request_timeout.back_button')}}</a>
    </div>
@endsection
