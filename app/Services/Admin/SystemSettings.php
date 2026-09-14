<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Config\EnvironmentConfigProxy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SystemSettings
{
    public function __construct(private readonly EnvironmentConfigProxy $proxy)
    {
        $this->proxy->capture([...array_column(self::DEFINITIONS, 0), 'locale.default_language']);
    }
    public const DEFINITIONS = [
        'APP_NAME' => ['app.name', 'string', 'required|string|max:255'],
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

    public function apply(): void
    {
        $overrides = [];
        foreach (DB::table('admin_settings')->get() as $setting) {
            if (!isset(self::DEFINITIONS[$setting->key])) continue;
            $value = $this->normalize($setting->key, json_decode($setting->value, true, flags: JSON_THROW_ON_ERROR));
            $overrides[self::DEFINITIONS[$setting->key][0]] = $setting->key === 'AI_MENTION_HANDLE' ? '@' . $value : $value;
            if ($setting->key === 'APP_LOCALE') $overrides['locale.default_language'] = $value;
        }
        $this->proxy->apply($overrides);
        // These values are also captured by Laravel before providers boot.
        app()->instance('env', config('app.env'));
        date_default_timezone_set(config('app.timezone'));
    }

    public function rows(): array
    {
        $overrides = DB::table('admin_settings')->pluck('value', 'key');
        $rows = [];
        foreach (self::DEFINITIONS as $key => [$path, $type]) {
            $default = $this->default($key);
            $value = $overrides->has($key) ? $this->normalize($key, json_decode($overrides[$key], true, flags: JSON_THROW_ON_ERROR)) : $default;
            $rows[] = ['id' => $key, 'key' => $key, 'value' => $value, 'default' => $default, 'type' => $type, 'options' => $this->options($key), 'source' => $overrides->has($key) ? 'database' : 'environment'];
        }
        return $rows;
    }

    public function save(string $key, mixed $value, int $userId): void
    {
        abort_unless(isset(self::DEFINITIONS[$key]), 404);
        $rules = ['value' => self::DEFINITIONS[$key][2]];
        if ($key === 'APP_LOCALE') $rules['value'] = ['required', Rule::in(array_column($this->options($key), 'value'))];
        if (str_starts_with($key, 'ALLOWED_')) $rules['value.*'] = ['string', 'max:100', 'regex:~^[a-z0-9.+-]+/[a-z0-9.+*-]+$~'];
        $value = Validator::make(['value' => $value], $rules)->validate()['value'];
        $value = $this->normalize($key, $value);
        if ($value === $this->default($key)) {
            $this->reset($key);
            return;
        }
        DB::table('admin_settings')->upsert(
            [['key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]],
            ['key'], ['value', 'updated_by', 'updated_at']
        );
        $this->apply();
    }

    public function reset(string $key): void
    {
        abort_unless(isset(self::DEFINITIONS[$key]), 404);
        DB::table('admin_settings')->where('key', $key)->delete();
        $this->apply();
    }

    public function environment(): array
    {
        $rows = $this->rows();
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

    private function normalize(string $key, mixed $value): mixed
    {
        if ($key === 'AI_MENTION_HANDLE') return ltrim((string)$value, '@');
        return match (self::DEFINITIONS[$key][1]) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => (int)$value,
            'string' => $value === '' ? null : $value,
            default => $value,
        };
    }

    private function options(string $key): array
    {
        if ($key === 'APP_LOCALE') {
            return collect(config('locale.langs'))->filter(fn($locale) => $locale['active'])
                ->map(fn($locale) => ['value' => $locale['id'], 'label' => $locale['name']])->values()->all();
        }
        $values = match ($key) {
            'AUTHENTICATION_METHOD' => ['LDAP', 'OIDC', 'Shibboleth'],
            'APP_TIMEZONE' => \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),
            default => [],
        };
        return array_map(fn($value) => ['value' => $value, 'label' => $value], $values);
    }
}
