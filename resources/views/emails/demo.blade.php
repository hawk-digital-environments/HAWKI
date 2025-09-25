@extends('emails.layout')

@section('title', 'HAWKI Email Templates Demo')

@section('content')
<h1 class="content-title">HAWKI Email Templates Showcase 🎨</h1>

<p class="content-text">
    This is a demonstration of the new HAWKI email template system featuring modern design, responsive layout, and comprehensive branding.
</p>

<h2 style="font-size: 20px; color: #1e293b; margin: 32px 0 16px 0;">Features Included:</h2>

<ul>
    <li><strong>Responsive Design:</strong> Optimized for desktop, tablet, and mobile devices</li>
    <li><strong>Dark Mode Support:</strong> Automatically adapts to user's preferred color scheme</li>
    <li><strong>HAWKI Branding:</strong> Consistent corporate identity with brand colors</li>
    <li><strong>Security Focus:</strong> Special styling for security-related communications</li>
    <li><strong>Accessibility:</strong> High contrast ratios and screen reader compatible</li>
</ul>

<h2 style="font-size: 20px; color: #1e293b; margin: 32px 0 16px 0;">Alert Styles:</h2>

<div class="alert alert-success">
    <strong>Success Alert:</strong> Used for positive confirmations and completed actions.
</div>

<div class="alert alert-info">
    <strong>Info Alert:</strong> Used for helpful tips and additional information.
</div>

<div class="alert alert-warning">
    <strong>Warning Alert:</strong> Used for important notices and security reminders.
</div>

<div class="alert alert-error">
    <strong>Error Alert:</strong> Used for error messages and critical issues.
</div>

<h2 style="font-size: 20px; color: #1e293b; margin: 32px 0 16px 0;">Button Examples:</h2>

<div style="text-align: center; margin: 24px 0;">
    <a href="#" class="btn btn-primary" style="margin: 8px;">Primary Button</a>
    <a href="#" class="btn btn-success" style="margin: 8px;">Success Button</a>
</div>

<h2 style="font-size: 20px; color: #1e293b; margin: 32px 0 16px 0;">OTP Code Display:</h2>

<div class="otp-container">
    <div class="otp-code">123456</div>
</div>

<p class="content-text">
    All templates now use a consistent layout system with shared styling and components, making maintenance easier and ensuring brand consistency across all email communications.
</p>

<p class="content-text">
    Best regards,<br>
    <strong>The HAWKI Development Team</strong>
</p>
@endsection