{{-- Logo data is passed from PlatformScreen query() method --}}

<div class="bg-white rounded-top shadow-sm mb-4 rounded-bottom">

    <div class="row g-0">
        <div class="col col-lg-7 mt-6 p-4">

            <h2 class="text-body-emphasis fw-light">
                Hello, It's Great to See You!
            </h2>

            <p class="text-balance">
                Welcome to HAWKI! This is your AI platform administration interface.
                You can configure models, manage users, and customize the system from here.
            </p>
        </div>
        <div class="d-none d-lg-block col align-self-center text-end text-muted p-4 opacity-25">
            @if($logoSvg && file_exists(public_path($logoSvg)))
                {{-- Use the uploaded logo SVG --}}
                <img src="{{ asset($logoSvg) }}" alt="HAWKI Logo">
                <!-- DEBUG: Logo displayed from {{ $logoSvg }} -->
            @else
                {{-- DEBUG: Logo fallback - logoSvg: {{ $logoSvg ?? 'null' }}, file_exists: {{ $logoSvg ? (file_exists(public_path($logoSvg)) ? 'true' : 'false') : 'n/a' }} --}}
                {{-- Fallback to default Orchid logo --}}
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" role="img" width="6em" height="100%" viewBox="0 0 100 100">
                    <path d="M86.55 30.73c-11.33 10.72-27.5 12.65-42.3 14.43-10.94 1.5-23.3 3.78-30.48 13.04-6.2 8.3-4.25 20.3 2.25 27.8 1.35 2.03 5.7 5.7 6.38 5.3-5.96-8.42-5.88-21.6 2.6-28.4 8.97-7.52 21.2-7.1 32.03-9.7 6.47-1.23 13.3-3.5 19.2-5.34-8.3 7.44-19.38 10.36-29.7 13.75-8.7 3.08-17.22 10.23-17.45 20.1-.17 6.8 3.1 14.9 10.06 17.07 18.56 4.34 39.14-3.16 50.56-18.4 12.7-16.12 13.75-40.2 2.43-57.33-1.33 2.9-3.28 5.5-5.58 7.7z"/>
                    <path d="M0 49.97c-.14 4.35 1.24 13.9 2.63 14.64 1.2-11.48 10.2-20.74 20.83-24.47 17.9-7.06 38.75-3.1 55.66-13.18 5.16-2.3 9.28-9.48 4.36-14.1-2.16-1.76-5.9-5.75-3.7-.72.83 6.22-5.47 10.06-10.63 11.65-10.9 3.34-22.46 3-33.62 4.93-1.9.32-5.9 1.2-2.07-.6 10.52-5.02 23.57-4.38 32.6-12.5 4.8-3.75 2.77-11.16-2.4-13.4C57.4-.35 50.3-.35 43.63.35c-19.85 2.3-37.3 17.7-42.05 37.1C.52 41.57 0 45.77 0 49.97z"/>
                </svg>
            @endif
        </div>
    </div>

    <div class="row bg-light m-0 p-md-4 p-3 border-top rounded-bottom g-md-5 text-balance">

        <div class="col-md-6 my-2">
            <h3 class="text-muted fw-light d-flex align-items-center gap-3">
                <x-orchid-icon path="bs.gear"/>

                <span class="text-body-emphasis lh-1">System Configuration</span>
            </h3>
            <p class="ms-md-5 ps-md-1">
                Configure your HAWKI platform settings, including authentication providers, AI models, and system preferences.
                Access the <a href="{{ route('platform.settings.system') }}" class="text-u-l">System Settings</a> to get started.
            </p>
        </div>

        <div class="col-md-6 my-2">
            <h3 class="text-muted fw-light d-flex align-items-center gap-3">
                <x-orchid-icon path="bs.stars"/>

                <span class="text-body-emphasis lh-1">AI Model Management</span>
            </h3>
            <p class="ms-md-5 ps-md-1">
                Manage your AI providers and language models. Configure API endpoints, model settings, and availability.
                Visit <a href="{{ route('platform.models.api.providers') }}" class="text-u-l">API Management</a> to configure your models.
            </p>
        </div>

        <div class="col-md-6 my-2">
            <h3 class="text-muted fw-light d-flex align-items-center gap-3">
                <x-orchid-icon path="bs.people"/>

                <span class="text-body-emphasis lh-1">User Management</span>
            </h3>
            <p class="ms-md-5 ps-md-1">
                Manage user accounts, roles, and permissions. Configure authentication methods and user access controls.
                Go to <a href="{{ route('platform.systems.users') }}" class="text-u-l">User Management</a> to manage users.
            </p>
        </div>

        <div class="col-md-6 my-2">
            <h3 class="text-muted fw-light d-flex align-items-center gap-3">
                <x-orchid-icon path="bs.paint-bucket"/>

                <span class="text-body-emphasis lh-1">Styling & Customization</span>
            </h3>
            <p class="ms-md-5 ps-md-1">
                Customize the appearance of your HAWKI platform. Upload logos, modify CSS, and configure system images.
                Visit <a href="{{ route('platform.customization.images') }}" class="text-u-l">Customization Settings</a> to customize your platform.
            </p>
        </div>

        <div class="col-md-6 my-2">
            <h3 class="text-muted fw-light d-flex align-items-center gap-3">
                <x-orchid-icon path="bs.bar-chart"/>

                <span class="text-body-emphasis lh-1">Dashboard & Analytics</span>
            </h3>
            <p class="ms-md-5 ps-md-1">
                Monitor platform usage, track user activity, and view analytics. Get insights into your HAWKI platform's performance.
                Check the <a href="{{ route('platform.dashboard.global') }}" class="text-u-l">Dashboard</a> for analytics.
            </p>
        </div>

        <div class="col-md-6 my-2">
            <h3 class="text-muted fw-light d-flex align-items-center gap-3">
                <x-orchid-icon path="bs.envelope"/>

                <span class="text-body-emphasis lh-1">Mail & Notifications</span>
            </h3>
            <div class="ms-md-5 ps-md-1">
                <p>
                    Configure email settings, templates, and notification preferences for your HAWKI platform.
                    Set up <a href="{{ route('platform.settings.mail-configuration') }}" class="text-u-l">Mail Configuration</a> to enable notifications.
                </p>
            </div>
        </div>
    </div>
</div>