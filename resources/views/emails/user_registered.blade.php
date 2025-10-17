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

<div class="alert alert-warning" style="background-color: #fff3cd; border-left: 4px solid #ffc107; color: #856404;">
    <strong>Important: Your {{config('app.name')}} Backup Code</strong><br>
    Please save this backup code securely. You will need it to unlock a new device for {{config('app.name')}}:<br>
    <div style="background: #fff; padding: 12px; margin: 12px 0; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 16px; font-weight: bold; text-align: center; letter-spacing: 2px; color: #2c3e50;">
        {{backup_hash}}
    </div>
    <small>Store this code in a safe place. Do not share it with anyone.</small>
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