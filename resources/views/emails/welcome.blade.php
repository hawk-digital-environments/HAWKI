@extends('emails.layout')

@section('title', 'Welcome to HAWKI')

@section('content')
<h1 class="content-title">Welcome to HAWKI, {{ $user->name ?? $user->username }}! 🎉</h1>

<p class="content-text">
    We're thrilled to have you join our community of researchers, students, and educators who are exploring the possibilities of generative AI in academic environments.
</p>

<div class="alert alert-success">
    <strong>Your account is now active!</strong><br>
    You can start using HAWKI's powerful AI features right away.
</div>

<p class="content-text">
    <strong>What can you do with HAWKI?</strong>
</p>

<ul style="color: #64748b; margin: 16px 0 24px 20px; line-height: 1.7;">
    <li><strong>1:1 AI Conversations:</strong> Have private, encrypted conversations with advanced AI models</li>
    <li><strong>Group Chat Rooms:</strong> Collaborate with colleagues in AI-enhanced group discussions</li>
    <li><strong>Multi-Model Support:</strong> Access various AI models including OpenAI, Google, and local options</li>
    <li><strong>Privacy-First Design:</strong> Your conversations are protected with end-to-end encryption</li>
    <li><strong>Academic Focus:</strong> Tools and features designed specifically for university environments</li>
</ul>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ config('app.url') }}" class="btn btn-primary">
        Start Using HAWKI
    </a>
</div>

<div class="alert alert-info">
    <strong>Getting Started Tip:</strong><br>
    Visit your profile settings to customize your experience and set up additional security features like passkeys.
</div>

<p class="content-text">
    If you have any questions or need assistance, don't hesitate to reach out to our support team or explore our documentation.
</p>

<p class="content-text">
    Welcome aboard!<br>
    <strong>The HAWKI Team</strong>
</p>
@endsection