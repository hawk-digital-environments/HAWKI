@extends('emails.layout')

@section('title', $emailData['subject'] ?? 'HAWKI Notification')

@section('content')
<h1 class="content-title">{{ $emailData['title'] ?? 'HAWKI Notification' }}</h1>

@if(isset($user))
<p class="content-text">
    Hello {{ $user->name ?? $user->username }},
</p>
@endif

<div class="content-text">
    {!! $emailData['message'] ?? 'You have a new notification from HAWKI.' !!}
</div>

@if(isset($emailData['action_url']) && isset($emailData['action_text']))
<div style="text-align: center; margin: 32px 0;">
    <a href="{{ $emailData['action_url'] }}" class="btn btn-primary">
        {{ $emailData['action_text'] }}
    </a>
</div>
@endif

@if(isset($emailData['alert_type']) && isset($emailData['alert_message']))
<div class="alert alert-{{ $emailData['alert_type'] }}">
    {!! $emailData['alert_message'] !!}
</div>
@endif

@if(isset($emailData['additional_info']))
<div class="alert alert-info">
    {!! $emailData['additional_info'] !!}
</div>
@endif

<p class="content-text">
    @if(isset($emailData['signature']))
        {!! $emailData['signature'] !!}
    @else
        Best regards,<br>
        <strong>The HAWKI Team</strong>
    @endif
</p>
@endsection