<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Config\EnvironmentConfigProxy;
use App\Services\Frontend\View\BrandTheme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SystemSettings
{
    /** An absolute HTTP(S) URL or a path on this host; protocol-relative URLs are rejected. */
    private const LINK = 'nullable|string|max:2000|regex:~^(?:https?:/)?/[^/\s"\'<>][^\s"\'<>]*$~';

    /** Settings whose value is a CSS OKLCH color. */
    public const OKLCH = ['BRAND_COLOR_SUCCESS', 'BRAND_COLOR_WARNING', 'BRAND_COLOR_ERROR', 'BRAND_COLOR_INFO'];

    /** Settings whose value names an uploaded font file. */
    public const FONT = ['BRAND_FONT'];

    /** Settings whose value lists uploaded sign-in backgrounds. */
    public const MEDIA = ['BRAND_LOGIN_BACKGROUNDS'];

    /** Settings whose value is the markup of an uploaded SVG file. */
    public const SVG = ['BRAND_LOGO', 'BRAND_FAVICON'];

    private const COLOR = 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/';

    public const DEFINITIONS = [
        'APP_NAME' => ['app.name', 'string', 'required|string|max:255'],
        'BRAND_ORGANIZATION_NAME' => ['hawki.branding.organization_name', 'string', 'nullable|string|max:255'],
        'BRAND_ORGANIZATION_URL' => ['hawki.branding.organization_url', 'string', 'nullable|url:http,https|max:2000'],
        'BRAND_ACCENT_COLOR' => ['hawki.branding.accent_color', 'string', self::COLOR],
        'BRAND_COLOR_SUCCESS' => ['hawki.branding.status_colors.success', 'string', 'nullable|string|max:60'],
        'BRAND_COLOR_WARNING' => ['hawki.branding.status_colors.warning', 'string', 'nullable|string|max:60'],
        'BRAND_COLOR_ERROR' => ['hawki.branding.status_colors.error', 'string', 'nullable|string|max:60'],
        'BRAND_COLOR_INFO' => ['hawki.branding.status_colors.info', 'string', 'nullable|string|max:60'],
        'BRAND_CORNER_STYLE' => ['hawki.branding.corner_style', 'string', 'required|in:round,soft,square'],
        'BRAND_FONT' => ['hawki.branding.font', 'string', 'nullable|string|max:80'],
        'BRAND_LOGIN_BACKGROUNDS' => ['hawki.branding.login_backgrounds', 'json', 'present|array|max:20'],
        'BRAND_LOGIN_NEWS' => ['hawki.branding.login_news', 'json', 'present|array|max:' . LoginNews::MAX_ENTRIES],
        'BRAND_ONBOARDING' => ['hawki.branding.onboarding', 'json', 'present|array|max:' . Onboarding::MAX_STEPS],
        'BRAND_LOGO' => ['hawki.branding.logo', 'string', 'nullable|string'],
        'BRAND_LOGO_HEIGHT' => ['hawki.branding.logo_height', 'number', 'nullable|integer|min:12|max:36'],
        'BRAND_FAVICON' => ['hawki.branding.favicon', 'string', 'nullable|string'],
        'LEGAL_IMPRINT_URL' => ['hawki.legal.imprint_url', 'string', self::LINK],
        'LEGAL_PRIVACY_URL' => ['hawki.legal.privacy_url', 'string', self::LINK],
        'LEGAL_TERMS_URL' => ['hawki.legal.terms_url', 'string', self::LINK],
        'APP_URL' => ['app.url', 'string', 'required|url:http,https|max:2000'],
        'APP_ENV' => ['app.env', 'string', 'required|alpha_dash:ascii|max:50'],
        'APP_TIMEZONE' => ['app.timezone', 'string', 'required|timezone:all_with_bc'],
        'APP_LOCALE' => ['app.locale', 'string', 'required|string'],
        'AUTHENTICATION_METHOD' => ['auth.authMethod', 'string', 'required|in:LDAP,OIDC,Shibboleth'],
        'SESSION_LIFETIME' => ['session.lifetime', 'number', 'required|integer|min:1|max:525600'],
        'SESSION_EXPIRE_ON_CLOSE' => ['session.expire_on_close', 'boolean', 'required|boolean'],
        'SESSION_ENCRYPT' => ['session.encrypt', 'boolean', 'required|boolean'],
        'AI_MENTION_HANDLE' => ['hawki.aiHandle', 'string', 'required|alpha_dash|max:50'],
        'ACCESSIBILITY_STATEMENT_URL' => ['hawki.accessibility.statement_url', 'string', 'nullable|url:http,https|max:2000'],
        'APP_SECURITY_PASSKEY_ALLOW_PASTE' => ['hawki.security.passkey.allow_paste', 'boolean', 'required|boolean'],
        'APP_SECURITY_PASSKEY_AUTO_GENERATE' => ['hawki.security.passkey.auto_generate', 'boolean', 'required|boolean'],
        'APP_SECURITY_PASSKEY_CHAR_LIMITATION' => ['hawki.security.passkey.char_limitation', 'boolean', 'required|boolean'],
        'ALLOW_EXTERNAL_COMMUNICATION' => ['external_access.enabled', 'boolean', 'required|boolean'],
        'ALLOW_USER_TOKEN_CREATION' => ['external_access.allow_user_token', 'boolean', 'required|boolean'],
        'ALLOW_EXTERNAL_APPS' => ['external_access.apps', 'boolean', 'required|boolean'],
        'ALLOW_EXTERNAL_APPS_GROUPS_AI' => ['external_access.apps_groups_ai', 'boolean', 'required|boolean'],
        'ALLOW_EXTERNAL_APPS_CONNECT_REQUEST_TIMEOUT' => ['external_access.app_connect_request_timeout', 'number', 'required|integer|min:60|max:86400'],
        'MAX_FILE_SIZE' => ['filesystems.upload_limits.max_file_size', 'number', 'required|integer|min:1|max:1073741824'],
        'MAX_AVATAR_FILE_SIZE' => ['filesystems.upload_limits.max_avatar_file_size', 'number', 'required|integer|min:1|max:52428800'],
        'MAX_ATTACHMENT_FILES' => ['filesystems.upload_limits.max_attachment_files', 'number', 'required|integer|min:0|max:100'],
        'ALLOWED_FILE_MIME_TYPES' => ['filesystems.upload_limits.allowed_file_mime_types', 'json', 'present|array'],
        'ALLOWED_AVATAR_MIME_TYPES' => ['filesystems.upload_limits.allowed_avatar_mime_types', 'json', 'present|array'],
        'REMOVE_FILES_AFTER_MONTHS' => ['filesystems.garbage_collections.remove_files_after_months', 'number', 'required|integer|min:1|max:120'],
        'CHECK_TOOL_STATUS' => ['tools.check_tool_status', 'boolean', 'required|boolean'],
    ];

    public function __construct(private readonly EnvironmentConfigProxy $proxy)
    {
        $this->proxy->capture([...array_column(self::DEFINITIONS, 0), 'locale.default_language']);
    }

    public function apply(): void
    {
        $overrides = [];

        foreach (DB::table('admin_settings')->get() as $setting) {
            if (!isset(self::DEFINITIONS[$setting->key])) {
                continue;
            }

            $value = $this->normalize($setting->key, json_decode($setting->value, true, flags: \JSON_THROW_ON_ERROR));
            $overrides[self::DEFINITIONS[$setting->key][0]] = 'AI_MENTION_HANDLE' === $setting->key ? '@' . $value : $value;

            if ('APP_LOCALE' === $setting->key) {
                $overrides['locale.default_language'] = $value;
            }
        }

        $this->proxy->apply($overrides);
        // These values are also captured by Laravel before providers boot.
        app()->instance('env', config('app.env'));
        date_default_timezone_set(config('app.timezone'));
    }

    public function rows(): array
    {
        $overrides = DB::table('admin_settings')->get()->keyBy('key');
        $rows = [];

        foreach (array_keys(self::DEFINITIONS) as $key) {
            $rows[] = $this->rowFromOverride($key, $overrides->get($key));
        }

        return $rows;
    }

    /**
     * Returns one effective setting. A mutation can lock its persisted override before checking
     * the version, while settings that use deployment defaults remain valid synthetic rows.
     */
    public function row(string $key, bool $lock = false): array
    {
        abort_unless(isset(self::DEFINITIONS[$key]), 404);
        $query = DB::table('admin_settings')->where('key', $key);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $this->rowFromOverride($key, $query->first());
    }

    public function save(string $key, mixed $value, int $userId): void
    {
        abort_unless(isset(self::DEFINITIONS[$key]), 404);
        $rules = ['value' => self::DEFINITIONS[$key][2]];

        if ('APP_LOCALE' === $key) {
            $rules['value'] = ['required', Rule::in(array_column($this->options($key), 'value'))];
        }

        if (str_starts_with($key, 'ALLOWED_')) {
            $rules['value.*'] = ['string', 'max:100', 'regex:~^[a-z0-9.+-]+/[a-z0-9.+*-]+$~'];
        }

        $value = Validator::make(['value' => $value], $rules)->validate()['value'];

        // Checked strictly before normalizing, which would quietly drop invalid entries.
        if (\in_array($key, self::MEDIA, true)) {
            BrandMedia::backgrounds($value, strict: true);
        }

        if ('BRAND_LOGIN_NEWS' === $key) {
            LoginNews::entries($value, strict: true);
        }

        if ('BRAND_ONBOARDING' === $key) {
            Onboarding::steps($value, strict: true);
        }

        $value = $this->normalize($key, $value);

        if (\in_array($key, self::OKLCH, true) && null !== $value) {
            // Stored in one canonical spelling; it is written into every page's stylesheet.
            $value = BrandTheme::oklch($value) ?? throw ValidationException::withMessages(['value' => __('admin.errors.invalid_value')]);
        }

        if (\in_array($key, self::FONT, true) && null !== $value && !app(BrandFonts::class)->exists($value)) {
            throw ValidationException::withMessages(['value' => __('admin.fonts.invalid')]);
        }

        if (\in_array($key, self::SVG, true) && null !== $value) {
            try {
                // Stored sanitized: the markup is later embedded in pages every visitor loads.
                $value = app(ProviderIconService::class)->validateSvg($value);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['value' => $exception->errors()['icon']]);
            }
        }

        if ($this->default($key) === $value) {
            $this->reset($key);

            return;
        }

        DB::table('admin_settings')->upsert(
            [['key' => $key, 'value' => json_encode($value, \JSON_THROW_ON_ERROR), 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]],
            ['key'],
            ['value', 'updated_by', 'updated_at'],
        );
        $this->apply();
        $this->pruneFiles($key);
    }

    public function reset(string $key): void
    {
        abort_unless(isset(self::DEFINITIONS[$key]), 404);
        DB::table('admin_settings')->where('key', $key)->delete();
        $this->apply();
        $this->pruneFiles($key);
    }

    /** A changed font or background setting can leave its previous file unused. */
    private function pruneFiles(string $key): void
    {
        if (\in_array($key, self::FONT, true)) {
            app(BrandFonts::class)->prune();
        }

        if (\in_array($key, self::MEDIA, true)) {
            app(BrandMedia::class)->prune();
        }
    }

    public function environment(): array
    {
        $rows = array_map(
            static fn (array $row) => match (true) {
                \in_array($row['key'], self::SVG, true) => ['value' => null === $row['value'] ? '[not set]' : '[set]'] + $row,
                \in_array($row['key'], [...self::MEDIA, 'BRAND_LOGIN_NEWS', 'BRAND_ONBOARDING'], true) => ['value' => \count((array) $row['value']) . ' entries'] + $row,
                default => $row,
            },
            $this->rows(),
        );

        foreach (['APP_DEBUG' => 'app.debug', 'DB_CONNECTION' => 'database.default', 'QUEUE_CONNECTION' => 'queue.default', 'CACHE_STORE' => 'cache.default', 'SESSION_DRIVER' => 'session.driver', 'MAIL_MAILER' => 'mail.default'] as $key => $path) {
            $rows[] = ['id' => $key, 'key' => $key, 'value' => config($path), 'source' => 'deployment'];
        }

        // Never send secret values, even to users who can view deployment diagnostics.
        foreach (['APP_KEY' => 'app.key', 'DB_PASSWORD' => 'database.connections.' . config('database.default') . '.password', 'MAIL_PASSWORD' => 'mail.mailers.smtp.password'] as $key => $path) {
            $rows[] = ['id' => $key, 'key' => $key, 'value' => filled(config($path)) ? '[set]' : '[not set]', 'source' => 'deployment'];
        }

        return $rows;
    }

    private function default(string $key): mixed
    {
        return $this->normalize($key, $this->proxy->default(self::DEFINITIONS[$key][0]));
    }

    private function rowFromOverride(string $key, ?object $override): array
    {
        [, $type] = self::DEFINITIONS[$key];
        $default = $this->default($key);
        $source = null === $override ? 'environment' : 'database';
        $value = null === $override
            ? $default
            : $this->normalize($key, json_decode($override->value, true, flags: \JSON_THROW_ON_ERROR));

        return [
            'id' => $key,
            'key' => $key,
            'value' => $value,
            'default' => $default,
            'type' => $type,
            'options' => $this->options($key),
            'source' => $source,
        ];
    }

    private function normalize(string $key, mixed $value): mixed
    {
        if ('AI_MENTION_HANDLE' === $key) {
            return ltrim((string) $value, '@');
        }

        // The database may reorder the keys of a stored JSON object; one canonical shape keeps comparisons exact.
        if (\in_array($key, self::MEDIA, true)) {
            return BrandMedia::backgrounds($value);
        }

        if ('BRAND_LOGIN_NEWS' === $key) {
            return LoginNews::entries($value);
        }

        if ('BRAND_ONBOARDING' === $key) {
            return Onboarding::steps($value);
        }

        return match (self::DEFINITIONS[$key][1]) {
            'boolean' => filter_var($value, \FILTER_VALIDATE_BOOLEAN),
            // An optional number keeps "not set" apart from zero.
            'number' => null === $value && str_starts_with(self::DEFINITIONS[$key][2], 'nullable') ? null : (int) $value,
            'string' => '' === $value ? null : $value,
            default => $value,
        };
    }

    private function options(string $key): array
    {
        if ('APP_LOCALE' === $key) {
            return collect(config('locale.langs'))->filter(static fn ($locale) => $locale['active'])
                ->map(static fn ($locale) => ['value' => $locale['id'], 'label' => $locale['name']])->values()->all();
        }

        $values = match ($key) {
            'AUTHENTICATION_METHOD' => ['LDAP', 'OIDC', 'Shibboleth'],
            'BRAND_CORNER_STYLE' => ['round', 'soft', 'square'],
            'APP_TIMEZONE' => \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),
            default => [],
        };

        return array_map(static fn ($value) => ['value' => $value, 'label' => $value], $values);
    }
}
