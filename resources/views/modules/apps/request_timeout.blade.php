@extends('layouts.apps_layout')

@section('title', __('apps.request_timeout.title'))

@section('content')
    <h1>{{__('apps.request_timeout.headline')}}</h1>
    <p>{{__('apps.request_timeout.description')}}</p>
@endsection

@section('buttons')
    <a href="{{route('login')}}" class="btn-lg-fill">{{__('apps.request_timeout.back_button')}}</a>
@endsection
