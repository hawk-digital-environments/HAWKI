<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>@yield('title', config('app.name'))</title>
    
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    
    <style type="text/css">
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        
        /* HAWKI Brand Colors */
        :root {
            --hawki-primary: #2563eb;
            --hawki-primary-dark: #1d4ed8;
            --hawki-secondary: #64748b;
            --hawki-accent: #f59e0b;
            --hawki-success: #10b981;
            --hawki-warning: #f59e0b;
            --hawki-error: #ef4444;
            --hawki-background: #f8fafc;
            --hawki-surface: #ffffff;
            --hawki-text-primary: #1e293b;
            --hawki-text-secondary: #64748b;
            --hawki-border: #e2e8f0;
        }
        
        /* Base styles */
        body {
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background-color: var(--hawki-background);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: var(--hawki-text-primary);
        }
        
        /* Container */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: var(--hawki-surface);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        /* Header */
        .email-header {
            background: linear-gradient(135deg, var(--hawki-primary) 0%, var(--hawki-primary-dark) 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .logo {
            font-size: 32px;
            font-weight: 800;
            color: white;
            letter-spacing: -0.025em;
            margin-bottom: 8px;
        }
        
        .tagline {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Content */
        .email-content {
            padding: 40px 30px;
        }
        
        .content-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--hawki-text-primary);
            margin-bottom: 16px;
            line-height: 1.3;
        }
        
        .content-text {
            color: var(--hawki-text-secondary);
            margin-bottom: 24px;
            line-height: 1.7;
        }
        
        .content-text:last-child {
            margin-bottom: 0;
        }
        
        /* OTP Code */
        .otp-container {
            text-align: center;
            margin: 32px 0;
        }
        
        .otp-code {
            display: inline-block;
            background: linear-gradient(135deg, var(--hawki-primary) 0%, var(--hawki-primary-dark) 100%);
            color: white;
            padding: 20px 32px;
            font-size: 28px;
            font-weight: 800;
            border-radius: 8px;
            letter-spacing: 4px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);
        }
        
        /* Alert boxes */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin: 24px 0;
            border-left: 4px solid;
            font-size: 14px;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            border-left-color: var(--hawki-warning);
            color: #92400e;
        }
        
        .alert-info {
            background-color: #dbeafe;
            border-left-color: var(--hawki-primary);
            color: #1e40af;
        }
        
        .alert-success {
            background-color: #d1fae5;
            border-left-color: var(--hawki-success);
            color: #065f46;
        }
        
        .alert-error {
            background-color: #fee2e2;
            border-left-color: var(--hawki-error);
            color: #991b1b;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--hawki-primary) 0%, var(--hawki-primary-dark) 100%);
            color: white !important;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 8px -1px rgba(37, 99, 235, 0.35);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--hawki-success) 0%, #059669 100%);
            color: white !important;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.25);
        }
        
        /* Footer */
        .email-footer {
            background-color: #f1f5f9;
            padding: 30px;
            text-align: center;
            border-top: 1px solid var(--hawki-border);
        }
        
        .footer-text {
            font-size: 14px;
            color: var(--hawki-text-secondary);
            margin-bottom: 16px;
        }
        
        .footer-text:last-child {
            margin-bottom: 0;
        }
        
        .footer-links a {
            color: var(--hawki-primary);
            text-decoration: none;
            margin: 0 8px;
            font-size: 14px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        /* Lists */
        ul {
            padding-left: 20px;
            margin: 16px 0 24px 0;
        }
        
        li {
            margin-bottom: 8px;
            color: var(--hawki-text-secondary);
            line-height: 1.7;
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-container {
                background-color: #1e293b !important;
            }
            
            .email-content {
                background-color: #1e293b !important;
            }
            
            .content-title {
                color: #f8fafc !important;
            }
            
            .content-text {
                color: #cbd5e1 !important;
            }
            
            .email-footer {
                background-color: #0f172a !important;
                border-top-color: #334155 !important;
            }
            
            li {
                color: #cbd5e1 !important;
            }
        }
        
        /* Mobile responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0 16px;
                border-radius: 8px;
            }
            
            .email-header,
            .email-content,
            .email-footer {
                padding: 24px 20px;
            }
            
            .logo {
                font-size: 28px;
            }
            
            .content-title {
                font-size: 20px;
            }
            
            .otp-code {
                font-size: 24px;
                padding: 16px 24px;
                letter-spacing: 3px;
            }
            
            .btn {
                display: block;
                width: 100%;
                padding: 14px 24px;
            }
        }
        
        @yield('additional-styles')
    </style>
</head>
<body>
    <div style="padding: 20px 0;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center">
                    <div class="email-container">
                        <!-- Header -->
                        <div class="email-header">
                            <div class="logo">{{ config('app.name', 'HAWKI') }}</div>
                            <div class="tagline">@yield('tagline', 'Generative AI für Universitäten')</div>
                        </div>
                        
                        <!-- Content -->
                        <div class="email-content">
                            @yield('content')
                        </div>
                        
                        <!-- Footer -->
                        <div class="email-footer">
                            <p class="footer-text">
                                This is an automated message from {{ config('app.name', 'HAWKI') }}. Please do not reply to this email.
                            </p>
                            <div class="footer-links">
                                @if(config('app.url'))
                                    <a href="{{ config('app.url') }}" target="_blank">Visit HAWKI</a>
                                @endif
                                @if(env('IMPRINT_LOCATION'))
                                    <a href="{{ env('IMPRINT_LOCATION') }}" target="_blank">Legal Notice</a>
                                @endif
                                <a href="{{ config('app.url') }}/dataprotection" target="_blank">Privacy Policy</a>
                            </div>
                            <p class="footer-text" style="margin-top: 16px;">
                                &copy; {{ date('Y') }} {{ config('app.name', 'HAWKI') }}. All rights reserved.
                            </p>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>