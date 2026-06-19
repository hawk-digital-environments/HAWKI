@extends('layouts.gateway')
@section('content')

    <button class="slide-back-btn" onclick="switchBackSlide()" aria-label="{{ __("Back") }}">
        <x-icon name="chevron-left" aria-hidden="true"/>
    </button>

    <div class="wrapper">

        <div class="container">

            <div class="slide" data-index="1">
                <h3>{{ __("HS_EnterPasskeyMsg") }}</h3>

                <form id="passkey-form" autocomplete="off">

                    <div class="password-input-wrapper">
                        <input
                            class="passkey-input"
                            placeholder="{{ __('Reg_SL5_PH1') }}"
                            id="passkey-input"
                            type="text"
                            autocomplete="new-password"
                            autocorrect="off"
                            autocapitalize="off"
                            spellcheck="false"
                        />
                        @php $tooltipId = str()->uuid() @endphp
                        <button type="button" class="btn-xs tooltip-parent" id="visibility-toggle" aria-labelledby="{{ $tooltipId }}">
                            <x-icon name="eye" id="eye" aria-hidden="true"/>
                            <x-icon name="eye-off" id="eye-off" style="display: none" aria-hidden="true"/>
                            <div class="tooltip tooltip-below" aria-hidden="true" id="{{ $tooltipId }}">{{ __("DataKeyShowToolTip") }}</div>
                        </button>
                    </div>
                </form>

                <div class="nav-buttons">
                    <button id="verifyEnteredPassKey-btn" onclick="verifyEnteredPassKey(this)" class="btn-lg-fill align-end">{{ __("Continue") }}</button>
                </div>
                <p class="red-text" id="alert-message"></p>
                <button onclick="switchSlide(2)" class="btn-text btn-md">{{ __("HS_ForgottenPasskey") }}</button>
            </div>

            <div class="slide" data-index="2">
                <h3>{{ __("HS_EnterBackupMsg") }}</h3>

                <div class="backup-hash-row">
                    <input id="backup-hash-input" type="text">
                    @php $tooltipId = str()->uuid() @endphp
                    <button class="btn-sm border fast-access-btn tooltip-parent" onclick="uploadTextFile()" aria-labelledby="{{ $tooltipId }}">
                        <x-icon name="upload" aria-hidden="true"/>
                        <div class="tooltip" aria-hidden="true" id="{{ $tooltipId }}">{{ __("UploadTextFileTooltip") }}</div>
                    </button>
                </div>

                <div class="nav-buttons">
                    <button onclick="extractPasskey()" class="btn-lg-fill align-end">{{ __("Continue") }}</button>
                </div>

                <p class="red-text" id="backup-alert-message"></p>
                <button onclick="switchSlide(3)" class="btn-md btn-text">{{ __("HS_ForgottenBackup") }}</button>

            </div>

            <div class="slide" data-index="3">
                <h2>{{ __("HS_LostBothT") }}</h2>
                <h3>{{ __("HS_LostBothB") }}</h3>
                <div class="nav-buttons">
                    <button onclick="requestProfileReset()" class="btn-lg-fill align-end">{{ __("HS_ResetProfile") }}</button>
                </div>
            </div>

            <div class="slide" data-index="4">
                <h2>{{ __("HS_PasskeyIs") }}</h2>
                <h3 id="passkey-field" class="demo-hash"></h3>
                <div class="nav-buttons">
                    <button onclick="redirectToChat()" class="btn-lg-fill align-end">{{ __("Continue") }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.OLD_UI_MIGHT_NEED_MIGRATION = true;

        window.waitUntilReady(async function () {
            window.waitUntilReadyToMigrate(async () => {
                if (await getPassKey()) {
                    await window.oldUiBridge.runMigrations('after_passkey');
                    window.location.href = '/chat';
                } else {
                    await window.oldUiBridge.runMigrations('after_login');
                    switchSlide(1);
                    setTimeout(() => {
                        if (@json($activeOverlay)) {
                            setOverlay(false, true);
                        }
                    }, 100);
                }
            });
        });

        window.waitUntilReady(function () {
            initializePasskeyInputs(false);
        });
    </script>
@endsection
