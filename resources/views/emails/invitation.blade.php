@extends('emails.layout')

@section('title', 'You\'re Invited to Join a HAWKI Group Chat')

@section('content')
<h1 class="content-title">You're invited to collaborate! 🚀</h1>

<p class="content-text">
    Hello there!
</p>

<p class="content-text">
    <strong>{{ $emailData['inviter_name'] ?? 'A colleague' }}</strong> has invited you to join an exciting group chat conversation on HAWKI, where you can collaborate using advanced AI technology.
</p>

@if(isset($emailData['room_name']))
<div class="alert alert-info">
    <strong>Group Chat:</strong> {{ $emailData['room_name'] }}<br>
    @if(isset($emailData['room_description']))
        <strong>About:</strong> {{ $emailData['room_description'] }}
    @endif
</div>
@endif

<p class="content-text">
    HAWKI is a privacy-focused platform that enables university communities to harness the power of generative AI for research, learning, and collaboration. Your conversations are protected with end-to-end encryption.
</p>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ $emailData['url'] }}" class="btn btn-primary">
        Join the Conversation
    </a>
</div>

<div class="alert alert-warning">
    <strong>⏰ Time-sensitive invitation</strong><br>
    This invitation link will expire in 48 hours for security reasons. Click the button above to join now.
</div>

<p class="content-text">
    <strong>What makes HAWKI special?</strong>
</p>

<ul>
    <li><strong>Privacy-First:</strong> All conversations are end-to-end encrypted</li>
    <li><strong>University-Focused:</strong> Designed specifically for academic environments</li>
    <li><strong>Multi-Model AI:</strong> Access to various AI models for different needs</li>
    <li><strong>Collaborative:</strong> Work together with AI assistance in real-time</li>
</ul>

<p class="content-text">
    If you don't have a HAWKI account yet, you'll be guided through a quick registration process when you click the invitation link.
</p>

<p class="content-text">
    Questions about HAWKI or need help getting started? Feel free to reach out to our support team.
</p>

<p class="content-text">
    Looking forward to seeing you in the conversation!<br>
    <strong>The HAWKI Team</strong>
</p>
@endsection

@section('additional-styles')
<style>
    .room-info {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 1px solid #0369a1;
        border-radius: 8px;
        padding: 20px;
        margin: 24px 0;
        text-align: center;
    }
    
    .room-name {
        font-size: 18px;
        font-weight: 700;
        color: #0c4a6e;
        margin-bottom: 8px;
    }
    
    .room-description {
        color: #075985;
        font-size: 14px;
    }
</style>
@endsection
