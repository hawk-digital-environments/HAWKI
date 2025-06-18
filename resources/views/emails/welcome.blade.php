<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Willkommen bei HAWKI</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Willkommen bei HAWKI!</h1>
    </div>
    
    <div class="content">
        <h2>Hallo {{ $user->username ?? $user->name ?? 'Benutzer' }}!</h2>
        
        <p>Willkommen bei HAWKI - dem intelligenten Chat-System. Diese E-Mail wurde erfolgreich über das Laravel Mail-System versendet.</p>
        
        <h3>HAWKI Features:</h3>
        <ul>
            <li>KI-gestützte Konversationen</li>
            <li>Sichere Benutzerauthentifizierung</li>
            <li>Gruppenchats und Collaboration</li>
            <li>Real-time Messaging mit WebSockets</li>
            <li>Passkey-basierte Sicherheit</li>
        </ul>
        
        <a href="{{ config('app.url') }}" class="button">
            Zu HAWKI
        </a>
        
        <p><strong>Mail-Test-Info:</strong><br>
        Diese Mail wurde am {{ now()->format('d.m.Y um H:i:s') }} versendet.</p>
        
        <p><strong>System-Info:</strong><br>
        Mail-Driver: {{ config('mail.default') }}<br>
        Host: {{ config('mail.mailers.smtp.host') }}:{{ config('mail.mailers.smtp.port') }}<br>
        Umgebung: {{ app()->environment() }}</p>
    </div>
    
    <div class="footer">
        <p>HAWKI Mail Test - Powered by Laravel</p>
        @if(app()->environment('local'))
            <p style="color: #dc3545;">🔧 Development Mode - Diese Mail wurde lokal abgefangen</p>
        @endif
    </div>
</body>
</html>