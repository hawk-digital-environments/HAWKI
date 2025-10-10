<?php

namespace App\Orchid\Screens\Settings;

use App\Models\AppSetting;
use App\Orchid\Layouts\Settings\SystemSettingsTabMenu;
use App\Orchid\Traits\OrchidSettingsManagementTrait;
use App\Services\SettingsService;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class MailConfigurationSettingsScreen extends Screen
{
    use OrchidSettingsManagementTrait;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * Construct the screen
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Query data.
     *
     * @return array
     */
    public function query(): iterable
    {
        $mailSettings = AppSetting::where('group', 'mail')->get();

        return [
            'mail' => $mailSettings,
        ];
    }

    /**
     * Display header name.
     */
    public function name(): ?string
    {
        return 'Mail Configuration';
    }

    public function description(): ?string
    {
        return 'Configure mail server settings and email delivery providers.';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('saveSettings'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            SystemSettingsTabMenu::class,
            ...$this->buildMailSettingsLayout(),
        ];
    }

    /**
     * Build layout for mail settings
     *
     * @return \Orchid\Screen\Layout[]
     */
    private function buildMailSettingsLayout()
    {
        $generalSettings = [];
        $smtpSettings = [];
        $herdSettings = [];
        $sendmailSettings = [];
        $logSettings = [];

        // Gruppiere die Einstellungen nach Kategorien entsprechend der settings.php
        foreach ($this->query()['mail'] as $setting) {
            $key = $setting->key;

            // General Mail Settings (default mailer, from address/name)
            if (in_array($key, ['mail_default']) || str_starts_with($key, 'mail_from.')) {
                $generalSettings[] = $this->generateFieldForSetting($setting);
            }
            // SMTP Mailer Configuration
            elseif (str_starts_with($key, 'mail_mailers.smtp.')) {
                $smtpSettings[] = $this->generateFieldForSetting($setting);
            }
            // Herd Mailer Configuration
            elseif (str_starts_with($key, 'mail_mailers.herd.')) {
                $herdSettings[] = $this->generateFieldForSetting($setting);
            }
            // Sendmail Configuration
            elseif (str_starts_with($key, 'mail_mailers.sendmail.')) {
                $sendmailSettings[] = $this->generateFieldForSetting($setting);
            }
            // Log Mailer Configuration
            elseif (str_starts_with($key, 'mail_mailers.log.')) {
                $logSettings[] = $this->generateFieldForSetting($setting);
            }
            // Legacy keys (backwards compatibility)
            elseif (in_array($key, ['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password'])) {
                $generalSettings[] = $this->generateFieldForSetting($setting);
            }
            // Fallback für unbekannte Keys
            else {
                $generalSettings[] = $this->generateFieldForSetting($setting);
            }
        }

        // Array für alle Layouts vorbereiten
        $layouts = [];

        // General Mail Settings
        if (! empty($generalSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($generalSettings),
            ])
                ->title('General Mail Settings')
                ->description('Basic email configuration including default mailer and sender information.');
        }

        // SMTP Configuration
        if (! empty($smtpSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($smtpSettings),
            ])
                ->title('SMTP Mailer Configuration')
                ->description('SMTP server settings for email delivery via external mail services.');
        }

        // Herd Configuration
        if (! empty($herdSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($herdSettings),
            ])
                ->title('Herd Mailer Configuration (Local Development)')
                ->description('Laravel Herd mail server configuration for local development environments.');
        }

        // Sendmail Configuration
        if (! empty($sendmailSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($sendmailSettings),
            ])
                ->title('Sendmail Configuration')
                ->description('Local sendmail binary configuration for server-based email delivery.');
        }

        // Log Configuration
        if (! empty($logSettings)) {
            $layouts[] = Layout::block([
                Layout::rows($logSettings),
            ])
                ->title('Log Mailer Configuration')
                ->description('Email logging configuration for development and debugging purposes.');
        }

        return $layouts;
    }

    /**
     * The permissions required to access this screen.
     */
    public function permission(): ?iterable
    {
        return [
            'platform.systems.settings',
        ];
    }
}
