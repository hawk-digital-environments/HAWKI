@extends('emails.layout')

@section('title', 'Your HAWKI Authentication Code')

@section('content')
<h1 class="content-title">Your Authentication Code</h1>

<p class="content-text">
    Hello {{ $user['name'] ?? $user['username'] ?? 'User' }},
</p>

<p class="content-text">
    You've requested secure access to your HAWKI account. Please use the authentication code below to complete your login:
</p>

<div class="otp-container">
    <div class="otp-code">{{ $otp }}</div>
</div>

<div class="alert alert-warning">
    <strong>🔒 Security Notice</strong><br>
    • This code expires in <strong>{{ config('auth.passkey_otp_timeout', 300) / 60 }} minutes</strong><br>
    • Never share this code with anyone<br>
    • HAWKI staff will never ask for this code
</div>

<p class="content-text">
    <strong>Didn't request this code?</strong><br>
    If you didn't try to log in to HAWKI, please ignore this email. Your account remains secure.
</p>

<div class="alert alert-info">
    <strong>💡 Pro Tip:</strong> For faster and more secure access, consider setting up passkeys in your HAWKI account settings after logging in.
</div>

<p class="content-text">
    If you're experiencing issues or have security concerns, please contact our support team immediately.
</p>

<p class="content-text">
    Stay secure,<br>
    <strong>The HAWKI Security Team</strong>
</p>
@endsection