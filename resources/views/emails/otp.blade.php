<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} Log-In Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .logo {
            height: 60px;
            margin-bottom: 20px;
        }
        .otp-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-label {
            font-size: 16px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            font-family: "Courier New", monospace;
            margin: 10px 0;
        }
        .validity {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 10px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #0c5460;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .timestamp {
            font-size: 12px;
            color: #888;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ route('system.image', ['name' => 'logo_svg']) }}" alt="{{ $appName }} Logo" class="logo">
            <h1>{{ $appName }} Log-in Code</h1>
        </div>
        
        <p>Hallo <strong>{{ $user['username'] ?? $user['name'] ?? 'Benutzer' }}</strong>,</p>
        
        <p>Sie haben einen Log-in Code für Ihren {{ $appName }} Account angefordert.</p>
        
        <div class="otp-container">
            <div class="otp-label">Der Log-in Code lautet:</div>
            <div class="otp-code">{{ $otp }}</div>
            <div class="validity">Gültig für 5 Minuten</div>
        </div>
        
        <div class="info">
            <strong>Hinweis:</strong> Geben Sie diesen Code in der Anwendung ein, um fortzufahren.
        </div>
        
        <div class="warning">
            <strong>Sicherheitshinweis:</strong> Falls Sie diese E-Mail nicht angefordert haben, ignorieren Sie sie bitte. Teilen Sie diesen Code niemals mit anderen.
        </div>
        
        <div class="footer">
            <p>Mit freundlichen Grüßen,<br>
            Ihr <strong>{{ $appName }}</strong> Team</p>
            
            <div class="timestamp">
                Gesendet am: {{ now()->format('d.m.Y H:i:s') }}
            </div>
        </div>
    </div>
</body>
</html>