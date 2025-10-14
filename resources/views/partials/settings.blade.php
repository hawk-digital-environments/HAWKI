<!-- settings.blade.php -->

<div class="settings-modal">
    <div class="settings-panel">
        <div class="settings-wrapper ">
            <div class="settings-content scroll-container">
                <div class="scroll-panel">
                    <h1>{{ $translation["Settings"] }}</h1>
                    <h3 id="swtichContent-btn" class="top-gap-3">
                        <div href="#" onclick="ToggleSettingsContent('aboutHAWKI',true)">
                            {{ $translation["AboutHAWKI"] }}
                        </div>
                        <svg viewBox="0 0 25 25">
                            <g class="button-path-stroke-color" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M 12 16 l 4 -4 l -4 -4 M 8 12 H 16"/>
                            </g>
                        </svg>
                    </h3>
                    <h3 id="swtichContent-btn" class="">
                        <div href="#" onclick="ToggleSettingsContent('guideline',true)">
                            {{ $translation["Guidelines"] }}
                        </div>
                        <svg viewBox="0 0 25 25">
                            <g class="button-path-stroke-color" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M 12 16 l 4 -4 l -4 -4 M 8 12 H 16"/>
                            </g>
                        </svg>
                    </h3>

                    @if(count($langs) != 1)
                        <div class="settings-section">
                            <h3>{{ $translation["language"] }}</h3>
                            <div class="language-selection">

                                @foreach ($langs as $lang)
                                    <a id="{{$lang['id']}}_btn" class="language-btn" onclick="changeLanguage( '{{$lang['id']}}' )">{{$lang['label']}}</a>
                                @endforeach

                            </div>
                        </div>
                    @endif

                    <div class="settings-section">
                        <h3>{{ $translation["theme"] }}</h3>
                        <div class="darkMode-switch-panel">
                            <div class="darkMode-switch">
                                <div class="toggle-area"  onclick="SwitchDarkMode(true)">
                                    <div id="theme-toggle">
                                        <img id="darkMode-switch-icon" src="{{ asset('img/moon.svg') }}" alt="HAWK Logo" width="20px">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="about-content">
                <div class="content-header">
                    <div class="back-btn" onclick="ToggleSettingsContent('aboutHAWKI',false)">
                        <svg viewBox="0 0 25 25" width="50" height="50">
                            <g class="button-path-stroke-color" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M 12 8 l -4 4 l 4 4 M 16 12 H 8"/>
                            </g>
                        </svg>
                    </div>
                    <h1>{{ $translation['AboutHAWKI'] }}</h1>
                </div>

                <div class="content-text-container">
                    <p>{{ $translation['AboutHAWKI_Info'] }}</p>
                    <p>
                        <a class="accentText contributor-title" target="_blank" href="https://www.hawk.de/en/university/organization-and-persons/register-of-persons/stefan-wolwer"><b>Prof. Stefan Wölwer</b></a>{{ $translation['AboutHAWKI_StefanInfo'] }}<br/>
                        <a class="accentText contributor-title" target="_blank" href="https://www.hawk.de/de/hochschule/organisation-und-personen/personenverzeichnis/jonas-trippler"><b>Jonas Trippler</b></a>{{ $translation['AboutHAWKI_JonasInfo'] }}<br/>
                        <a class="accentText contributor-title" target="_blank" href="https://www.hawk.de/de/hochschule/organisation-und-personen/personenverzeichnis/vincent-timm"><b>Vincent Timm</b></a>{{ $translation['AboutHAWKI_VincentInfo'] }}<br/>
                        <a class="accentText contributor-title" target="_blank" href="https://www.hawk.de/de/hochschule/organisation-und-personen/personenverzeichnis/arian-sadafi"><b>Arian Sadafi</b></a>{{ $translation['AboutHAWKI_ArianInfo'] }}</p>
                    </p>
                </div>
            </div>

            <div class="guideline-content">
                    <div class="back-btn" onclick="ToggleSettingsContent('guideline',false)">
                      <svg viewBox="0 0 25 25" width="50" height="50">
                            <g class="button-path-stroke-color" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M 12 8 l -4 4 l 4 4 M 16 12 H 8"/>
                            </g>
                        </svg>
                    </div>
                    <div id="policy-content" class="content-text-container"></div>
            </div>

            <div class="closeButton" onclick="toggleSettingsPanel(false)">
                <svg viewBox="0 0 100 100"><path class="fill-svg" d="M 19.52 19.52 a 6.4 6.4 90 0 1 9.0496 0 L 51.2 42.1504 L 73.8304 19.52 a 6.4 6.4 90 0 1 9.0496 9.0496 L 60.2496 51.2 L 82.88 73.8304 a 6.4 6.4 90 0 1 -9.0496 9.0496 L 51.2 60.2496 L 28.5696 82.88 a 6.4 6.4 90 0 1 -9.0496 -9.0496 L 42.1504 51.2 L 19.52 28.5696 a 6.4 6.4 90 0 1 0 -9.0496 z"/></svg>
            </div>
        </div>
    </div>
</div>
