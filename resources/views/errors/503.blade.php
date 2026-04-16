@php
    // Check if this is maintenance mode or another 503 error
    $isMaintenanceMode = app()->isDownForMaintenance();
    
    // Only load database content for maintenance mode
    $dbLogo = null;
    $maintenanceContent = null;
    
    if ($isMaintenanceMode) {
        // Load logo from database
        try {
            $dbLogo = \App\Models\AppSystemImage::getByName('logo_svg');
        } catch (\Exception $e) {
            // Logo loading failed, will use fallback
        }
        
        // Load localized content from database
        try {
            $localizationController = app(\App\Http\Controllers\LocalizationController::class);
            $maintenanceContent = $localizationController->getLocalizedContent('error503_maintenance');
        } catch (\Exception $e) {
            // Log error but continue with fallback
            \Log::error('503 maintenance page: Failed to load localized content', ['error' => $e->getMessage()]);
        }
    }
@endphp

@if($isMaintenanceMode)
    {{-- Maintenance Mode: Show Update Page --}}
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ config('app.name') }} - UPDATING</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    </head>
    <body>
        <div class="container">
            <div class="content">
                {{-- Load logo from database with fallback --}}
                @if($dbLogo && file_exists(public_path($dbLogo->file_path)))
                    <img id="HAWK_logo" src="{{ asset($dbLogo->file_path) }}" alt="{{ config('app.name') }} Logo">
                @else
                    <img id="HAWK_logo" src="{{ asset('img/logo.svg') }}" alt="{{ config('app.name') }} Logo">
                @endif
                
                @if($maintenanceContent)
                    {!! $maintenanceContent !!}
                @else
                    {{-- Fallback: Diese Seite sollte über die Datenbank konfiguriert werden --}}
                    <h1>Welcome to {{ config('app.name') }}</h1>
                    <img src="https://i.pinimg.com/originals/ed/77/47/ed7747ca797333eb6447917b803af306.gif" alt="Loading animation">
                    <h2>We are updating {{ config('app.name') }} right now!<br>Please try again later :)</h2>
                    <p style="font-size: 0.875rem; color: #94a3b8; margin-top: 2rem;">
                        Note: To customize this page, run: php artisan db:seed --class=AppLocalizedTextSeeder
                    </p>
                @endif
            </div>
        </div>
    </body>
    </html>

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700&display=swap");

        body {
            overflow: hidden;
            -ms-overflow-style: none; 
            font-family: "Fira Sans", sans-serif;
            color: var(--text-color);
            background-color: var(--background-color);
            background-color: black;
        }
        .container{
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            color: white;
        }
        .content {
            margin: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
        }
        h2{
            text-align:center;
        }
        #HAWK_logo {
            max-width: 10rem;
            height: auto;
        }
    </style>
@else
    {{-- General 503 Error: Service Temporarily Unavailable (Standard - nicht konfigurierbar) --}}
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ config('app.name') }} - Service Unavailable</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    </head>
    <body>
        <div class="container">
            <div class="content">
                {{-- Standard Logo (nicht aus DB) --}}
                <img id="HAWK_logo" src="{{ asset('img/logo.svg') }}" alt="{{ config('app.name') }} Logo">
                
                {{-- Standard 503 Fehlermeldung --}}
                <h1>503</h1>
                <h2>Service Temporarily Unavailable</h2>
                <p>{{ config('app.name') }} is currently experiencing technical difficulties.</p>
                <p class="small">Please try again in a few moments.</p>
                
                @if(config('app.env') === 'local' && isset($exception))
                    <details class="error-details">
                        <summary>Error Details (Development Only)</summary>
                        <pre>{{ $exception->getMessage() }}</pre>
                    </details>
                @endif
            </div>
        </div>
    </body>
    </html>

    <style>
        @import url("https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700&display=swap");

        body {
            overflow: hidden;
            -ms-overflow-style: none; 
            font-family: "Fira Sans", sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 0;
        }
        .container{
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }
        .content {
            margin: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 2rem;
            text-align: center;
        }
        #HAWK_logo {
            max-width: 8rem;
            height: auto;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        h1 {
            font-size: 5rem;
            font-weight: 700;
            margin: 0;
            color: #ef4444;
            line-height: 1;
        }
        h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 1rem 0;
            color: #f8fafc;
        }
        p {
            font-size: 1.125rem;
            color: #cbd5e1;
            margin: 0.5rem 0;
            max-width: 500px;
        }
        p.small {
            font-size: 0.875rem;
            color: #94a3b8;
        }
        .error-details {
            margin-top: 2rem;
            text-align: left;
            background: #1e293b;
            border-radius: 8px;
            padding: 1rem;
            max-width: 600px;
            width: 100%;
        }
        .error-details summary {
            cursor: pointer;
            color: #f59e0b;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .error-details pre {
            margin: 0.5rem 0 0 0;
            color: #fbbf24;
            font-size: 0.875rem;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
@endif
