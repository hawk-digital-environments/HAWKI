@extends('emails.layout')

@section('title', 'Account Created Successfully')

@section('content')
<h1 class="content-title">Welcome to HAWKI, {{ $user->name ?? $user->username }}! 🎉</h1>

<p class="content-text">
    Your account has been successfully created and activated.
</p>

<div class="alert alert-success">
    <strong>✅ Registration Complete!</strong><br>
    You can now access all HAWKI features and start exploring generative AI capabilities.
</div>

<p class="content-text">
    <strong>Next Steps:</strong>
</p>

<ul>
    <li><strong>Complete Your Profile:</strong> Add your bio and customize your settings</li>
    <li><strong>Set Up Security:</strong> Enable passkeys for enhanced account security</li>
    <li><strong>Start Chatting:</strong> Begin your first AI conversation or join a group chat</li>
    <li><strong>Explore Features:</strong> Discover different AI models and collaboration tools</li>
</ul>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ config('app.url') }}" class="btn btn-primary">
        Access Your HAWKI Account
    </a>
</div>

<div class="alert alert-info">
    <strong>💡 Getting Started Tip:</strong><br>
    Visit the help section in your account to learn about HAWKI's privacy features and advanced capabilities.
</div>

<p class="content-text">
    We're excited to have you as part of the HAWKI community. If you have any questions or need assistance, our support team is here to help.
</p>

<p class="content-text">
    Welcome aboard!<br>
    <strong>The HAWKI Team</strong>
</p>
@endsection