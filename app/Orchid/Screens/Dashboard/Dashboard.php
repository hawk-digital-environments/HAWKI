<?php

namespace App\Orchid\Screens\Dashboard;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class Dashboard extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        $now = Carbon::now();

        // =========================
        // SYSTEM CONFIGURATION STATUS
        // =========================
        $systemConfig = $this->getSystemConfigurationStatus();

        // =========================
        // SYSTEM STATISTIKEN
        // =========================
        $systemStats = $this->getSystemStatistics($now, $request);

        // =========================
        // CHAT STATISTIKEN (1:1)
        // =========================
        $chatStats = $this->getChatStatistics();

        // =========================
        // GROUPCHAT STATISTIKEN
        // =========================
        $groupchatStats = $this->getGroupchatStatistics($request);

        // =========================
        // SYSTEM ASSISTANTS STATISTIKEN
        // =========================
        $assistantStats = $this->getSystemAssistantStatistics();

        // Strukturiere Daten für verschachtelte Arrays in Views
        return [
            // Config als verschachteltes Array
            'config' => [
                'authStatus' => $systemConfig['config.authStatus'],
                'authMessage' => $systemConfig['config.authMessage'],
                'backupStatus' => $systemConfig['config.backupStatus'],
                'backupMessage' => $systemConfig['config.backupMessage'],
                'providersStatus' => $systemConfig['config.providersStatus'],
                'providersMessage' => $systemConfig['config.providersMessage'],
                'providersIssues' => $systemConfig['config.providersIssues'] ?? [],
            ],
            // System als verschachteltes Array
            'system' => [
                'totalUsers' => $systemStats['system.totalUsers'],
                'newUsersThisMonth' => $systemStats['system.newUsersThisMonth'],
                'newUsersPercentage' => $systemStats['system.newUsersPercentage'],
                'activeProviders' => $systemStats['system.activeProviders'],
                'top5Providers' => $systemStats['system.top5Providers'],
                'totalProviderRequests' => $systemStats['system.totalProviderRequests'],
                'totalInputTokens' => $systemStats['system.totalInputTokens'],
                'totalOutputTokens' => $systemStats['system.totalOutputTokens'],
                'activeModels' => $systemStats['system.activeModels'],
                'top5Models' => $systemStats['system.top5Models'],
                'excludeSystemModels' => $systemStats['system.excludeSystemModels'],
            ],
            // Chat als verschachteltes Array
            'chat' => [
                'totalChats' => $chatStats['chat.totalChats'],
                'totalMessages' => $chatStats['chat.totalMessages'],
                'totalFiles' => $chatStats['chat.totalFiles'],
            ],
            // Groupchat als verschachteltes Array
            'groupchat' => [
                'totalRooms' => $groupchatStats['groupchat.totalRooms'],
                'totalUsers' => $groupchatStats['groupchat.totalUsers'],
                'totalMessages' => $groupchatStats['groupchat.totalMessages'],
                'avgUsersPerRoom' => $groupchatStats['groupchat.avgUsersPerRoom'],
                'topRooms' => $groupchatStats['groupchat.topRooms'],
                'sortBy' => $groupchatStats['groupchat.sortBy'],
            ],
            // Assistants als verschachteltes Array
            'assistants' => [
                'improvements' => $assistantStats['assistants.improvements'],
                'summaries' => $assistantStats['assistants.summaries'],
                'titles' => $assistantStats['assistants.titles'],
            ],
            // Für Layout::metrics() benötigen wir die flache Struktur
            'system.totalUsers' => $systemStats['system.totalUsers'],
            'system.newUsersThisMonth' => $systemStats['system.newUsersThisMonth'],
            'system.newUsersPercentage' => $systemStats['system.newUsersPercentage'],
            'system.activeProviders' => $systemStats['system.activeProviders'],
            'system.activeModels' => $systemStats['system.activeModels'],
            'chat.totalChats' => $chatStats['chat.totalChats'],
            'chat.totalMessages' => $chatStats['chat.totalMessages'],
            'chat.totalFiles' => $chatStats['chat.totalFiles'],
            'groupchat.totalRooms' => $groupchatStats['groupchat.totalRooms'],
            'groupchat.totalUsers' => $groupchatStats['groupchat.totalUsers'],
            'groupchat.totalMessages' => $groupchatStats['groupchat.totalMessages'],
            'groupchat.avgUsersPerRoom' => $groupchatStats['groupchat.avgUsersPerRoom'],
            'assistants.improvements' => $assistantStats['assistants.improvements'],
            'assistants.summaries' => $assistantStats['assistants.summaries'],
            'assistants.titles' => $assistantStats['assistants.titles'],
            'assistants.toolRequests' => $assistantStats['assistants.toolRequests'],
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return config('app.name').' Dashboard';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Overview of global metrics for '.config('app.name');
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            // =========================
            // SYSTEM CONFIGURATION STATUS
            // =========================
            Layout::view('orchid.dashboard.system-config-status'),

            // =========================
            // SYSTEM STATISTIKEN (Reihe 1)
            // =========================
            Layout::metrics([
                'Total Users' => 'system.totalUsers',
                'New Users This Month' => 'system.newUsersThisMonth',
                'Growth Rate' => 'system.newUsersPercentage',
            ])
                ->title('System Statistics'),

            // =========================
            // AI INFRASTRUCTURE
            // =========================
            Layout::view('orchid.dashboard.ai-overview'),

            // =========================
            // CHAT STATISTIKEN
            // =========================
            Layout::metrics([
                'Total 1:1 Chats' => 'chat.totalChats',
                'Total Messages' => 'chat.totalMessages',
                'Uploaded Files' => 'chat.totalFiles',
            ])
                ->title('1:1 Chat Statistics'),

            // =========================
            // SYSTEM ASSISTANTS
            // =========================
            Layout::metrics([
                'Generated Improvements' => 'assistants.improvements',
                'Generated Summaries' => 'assistants.summaries',
                'Generated Titles' => 'assistants.titles',
                'Tool Requests' => 'assistants.toolRequests',
            ])
                ->title('System Assistants'),

            // =========================
            // GROUPCHAT STATISTIKEN
            // =========================
            Layout::metrics([
                'Total Rooms' => 'groupchat.totalRooms',
                'Total Members' => 'groupchat.totalUsers',
                'Total Messages' => 'groupchat.totalMessages',
                'Avg Users/Room' => 'groupchat.avgUsersPerRoom',
            ])
                ->title('Groupchat Statistics'),

            // =========================
            // MOST ACTIVE GROUPCHATS
            // =========================
            Layout::view('orchid.dashboard.top-groupchats'),
        ];
    }

    /**
     * Holt System Configuration Status Checks
     */
    private function getSystemConfigurationStatus(): array
    {
        $authConfigured = $this->checkAuthenticationConfiguration();
        $backupConfigured = $this->checkBackupConfiguration();
        $providersConfigured = $this->checkModelProvidersConfiguration();

        return [
            'config.authStatus' => $authConfigured['status'],
            'config.authMessage' => $authConfigured['message'],
            'config.backupStatus' => $backupConfigured['status'],
            'config.backupMessage' => $backupConfigured['message'],
            'config.providersStatus' => $providersConfigured['status'],
            'config.providersMessage' => $providersConfigured['message'],
            'config.providersIssues' => $providersConfigured['issues'] ?? [],
        ];
    }

    /**
     * Prüft ob Authentication konfiguriert ist
     */
    private function checkAuthenticationConfiguration(): array
    {
        try {
            $authMethod = config('auth.authentication_method');

            if (empty($authMethod)) {
                return [
                    'status' => 'danger',
                    'message' => 'No authentication method set',
                ];
            }

            // Normalisiere Auth-Methode
            $authMethodUpper = strtoupper($authMethod);

            switch ($authMethodUpper) {
                case 'LOCAL_ONLY':
                    return $this->checkLocalOnlyAuth();

                case 'LDAP':
                    return $this->checkLdapAuth();

                case 'OIDC':
                    return $this->checkOidcAuth();

                case 'SHIBBOLETH':
                    return $this->checkShibbolethAuth();

                case 'CUSTOM':
                    return $this->checkCustomAuth();

                default:
                    return [
                        'status' => 'warning',
                        'message' => "Unknown auth method: {$authMethod}",
                    ];
            }
        } catch (\Exception $e) {
            Log::error('Authentication check failed: '.$e->getMessage());

            return [
                'status' => 'danger',
                'message' => 'Auth check error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Prüft LOCAL_ONLY Authentication
     */
    private function checkLocalOnlyAuth(): array
    {
        // LOCAL_ONLY benötigt keine zusätzliche Konfiguration
        return [
            'status' => 'success',
            'message' => 'Local authentication enabled',
        ];
    }

    /**
     * Prüft LDAP Authentication
     */
    private function checkLdapAuth(): array
    {
        $connection = config('ldap.default', 'default');
        $host = config("ldap.connections.{$connection}.hosts.0");
        $port = config("ldap.connections.{$connection}.port");
        $baseDn = config("ldap.connections.{$connection}.base_dn");

        if (empty($host)) {
            return [
                'status' => 'warning',
                'message' => 'LDAP host not configured',
            ];
        }

        if (empty($baseDn)) {
            return [
                'status' => 'warning',
                'message' => 'LDAP base DN not configured',
            ];
        }

        // Optional: Teste LDAP-Verbindung
        try {
            $timeout = 2;
            $fp = @fsockopen($host, $port ?? 389, $errno, $errstr, $timeout);
            if ($fp) {
                fclose($fp);

                return [
                    'status' => 'success',
                    'message' => "LDAP: {$host}:".($port ?? 389).' (reachable)',
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => "LDAP configured but unreachable: {$host}",
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => "LDAP: {$host} (connection test failed)",
            ];
        }
    }

    /**
     * Prüft OIDC Authentication
     */
    private function checkOidcAuth(): array
    {
        $idp = config('open_id_connect.oidc_idp');
        $clientId = config('open_id_connect.oidc_client_id');
        $clientSecret = config('open_id_connect.oidc_client_secret');

        if (empty($idp)) {
            return [
                'status' => 'warning',
                'message' => 'OIDC IdP URL not configured',
            ];
        }

        if (empty($clientId)) {
            return [
                'status' => 'warning',
                'message' => 'OIDC Client ID not configured',
            ];
        }

        if (empty($clientSecret)) {
            return [
                'status' => 'warning',
                'message' => 'OIDC Client Secret not configured',
            ];
        }

        // Parse domain from IdP URL
        $domain = parse_url($idp, PHP_URL_HOST) ?? $idp;

        return [
            'status' => 'success',
            'message' => "OIDC: {$domain}",
        ];
    }

    /**
     * Prüft Shibboleth Authentication
     */
    private function checkShibbolethAuth(): array
    {
        $loginPath = config('shibboleth.login_path');
        $usernameVar = config('shibboleth.attribute_map.username');

        if (empty($loginPath)) {
            return [
                'status' => 'warning',
                'message' => 'Shibboleth login path not configured',
            ];
        }

        if (empty($usernameVar)) {
            return [
                'status' => 'warning',
                'message' => 'Shibboleth username attribute not configured',
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Shibboleth: '.$loginPath,
        ];
    }

    /**
     * Prüft Custom Authentication
     */
    private function checkCustomAuth(): array
    {
        $customClass = config('auth.authentication_method_custom_class');

        if (empty($customClass)) {
            return [
                'status' => 'warning',
                'message' => 'Custom auth class not configured',
            ];
        }

        if (! class_exists($customClass)) {
            return [
                'status' => 'danger',
                'message' => "Custom auth class not found: {$customClass}",
            ];
        }

        $className = class_basename($customClass);

        return [
            'status' => 'success',
            'message' => "Custom: {$className}",
        ];
    }

    /**
     * Prüft Backup Configuration
     */
    private function checkBackupConfiguration(): array
    {
        try {
            // Suche nach Backups in verschiedenen möglichen Pfaden
            $possiblePaths = [
                storage_path('app/HAWKI2'),
                storage_path('app/backups'),
                storage_path('app'),
            ];

            $allFiles = [];
            $backupPath = null;

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $files = glob($path.'/*.zip');
                    if (! empty($files)) {
                        $allFiles = array_merge($allFiles, $files);
                        if ($backupPath === null) {
                            $backupPath = $path;
                        }
                    }
                }
            }

            if (empty($allFiles)) {
                return [
                    'status' => 'warning',
                    'message' => 'No backup files found',
                ];
            }

            // Zähle Backups
            $backupCount = count($allFiles);

            // Finde letztes Backup
            $lastBackupFile = max(array_map('filemtime', $allFiles));
            $lastBackupDate = Carbon::createFromTimestamp($lastBackupFile);
            $daysSinceBackup = $lastBackupDate->diffInDays(now());

            // Status basierend auf Alter des letzten Backups
            $daysRounded = (int) round($daysSinceBackup);

            if ($daysRounded > 7) {
                return [
                    'status' => 'warning',
                    'message' => "{$backupCount} backups, last: {$daysRounded}d ago",
                ];
            } elseif ($daysRounded >= 1) {
                return [
                    'status' => 'success',
                    'message' => "{$backupCount} backups, last: {$daysRounded}d ago",
                ];
            } else {
                // Weniger als 1 Tag = zeige Stunden
                $hoursSinceBackup = (int) $lastBackupDate->diffInHours(now());
                if ($hoursSinceBackup < 1) {
                    return [
                        'status' => 'success',
                        'message' => "{$backupCount} backups, last: <1h ago",
                    ];
                } else {
                    return [
                        'status' => 'success',
                        'message' => "{$backupCount} backups, last: {$hoursSinceBackup}h ago",
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Backup check failed: '.$e->getMessage());

            return [
                'status' => 'info',
                'message' => 'Backup check error',
            ];
        }
    }

    /**
     * Prüft Model Providers Configuration
     */
    private function checkModelProvidersConfiguration(): array
    {
        try {
            $activeProviders = DB::table('api_providers')
                ->where('is_active', 1)
                ->count();

            $activeModels = DB::table('ai_models')
                ->where('is_active', 1)
                ->count();

            $assistants = DB::table('ai_assistants')
                ->where('status', 'active')
                ->count();

            if ($activeProviders === 0) {
                return [
                    'status' => 'danger',
                    'message' => 'No active AI providers',
                ];
            }

            if ($activeModels === 0) {
                return [
                    'status' => 'danger',
                    'message' => 'No active models',
                ];
            }

            // Prüfe auf Konfigurationsprobleme bei System Assistants
            $systemAssistants = DB::table('ai_assistants')
                ->leftJoin('users', 'ai_assistants.owner_id', '=', 'users.id')
                ->where(function ($query) {
                    $query->where('ai_assistants.owner_id', 1)
                        ->orWhere('users.employeetype', 'system');
                })
                ->get(['ai_assistants.id', 'ai_assistants.key', 'ai_assistants.ai_model', 'ai_assistants.prompt']);

            $configIssues = [];

            // Prüfe zuerst alle Provider auf Connection-Probleme
            $allProviders = DB::table('api_providers')->where('is_active', true)->get();
            foreach ($allProviders as $provider) {
                if ($provider->additional_settings) {
                    $settings = json_decode($provider->additional_settings, true);
                    if (isset($settings['last_connection_test'])) {
                        $test = $settings['last_connection_test'];

                        // Prüfe auf fehlgeschlagene Connection
                        if (! $test['success']) {
                            $error = $test['error'] ?? 'Connection failed';
                            $configIssues[] = 'Provider "'.$provider->provider_name.'": Connection Failed - '.$error;
                        }

                        // Prüfe auf langsame Response Time (> 1 Sekunde)
                        if (isset($test['response_time_ms']) && $test['response_time_ms'] > 1000) {
                            $responseTime = round($test['response_time_ms'] / 1000, 2);
                            $configIssues[] = 'Provider "'.$provider->provider_name.'": Slow Response - '.$responseTime.'s (> 1s)';
                        }
                    }
                }
            }

            // Dann prüfe System Assistants
            foreach ($systemAssistants as $assistant) {
                $issues = [];

                // Prüfe ob AI Model zugewiesen ist
                if (! $assistant->ai_model) {
                    $issues[] = 'No AI Model';
                } else {
                    // Prüfe ob AI Model aktiv ist
                    // Note: ai_model column references system_id in ai_models table
                    $model = DB::table('ai_models')->where('system_id', $assistant->ai_model)->first();
                    if ($model) {
                        if (! $model->is_active) {
                            $issues[] = 'AI Model Inactive';
                        }

                        // Prüfe für default_model ob es sichtbar ist
                        if ($assistant->key === 'default_model' && ! $model->is_visible) {
                            $issues[] = 'Default Model Must Be Visible';
                        }

                        // Prüfe ob Provider aktiv ist
                        if ($model->provider_id) {
                            $provider = DB::table('api_providers')->where('id', $model->provider_id)->first();
                            if ($provider && ! $provider->is_active) {
                                $issues[] = 'Provider Inactive';
                            }
                        }
                    }
                }

                // Prüfe System Prompt für relevante Assistenten
                if (in_array($assistant->key, ['default_model', 'title_generator', 'prompt_improver', 'summarizer']) && ! $assistant->prompt) {
                    $issues[] = 'No System Prompt';
                }

                if (! empty($issues)) {
                    $configIssues[] = 'Assistant "'.$assistant->key.'": '.implode(', ', $issues);
                }
            }

            // Status basierend auf gefundenen Problemen
            if (! empty($configIssues)) {
                $issueCount = count($configIssues);

                return [
                    'status' => 'warning',
                    'message' => "{$activeProviders} provider(s), {$activeModels} model(s), {$issueCount} issue(s)",
                    'issues' => $configIssues, // Detaillierte Issues für Modal
                ];
            }

            $message = "{$activeProviders} provider(s), {$activeModels} model(s)";
            if ($assistants > 0) {
                $message .= ", {$assistants} assistant(s)";
            }

            return [
                'status' => 'success',
                'message' => $message,
                'issues' => [], // Keine Issues
            ];
        } catch (\Exception $e) {
            Log::error('Provider check failed: '.$e->getMessage());

            return [
                'status' => 'danger',
                'message' => 'Provider check failed',
            ];
        }
    }

    /**
     * Holt System Statistiken
     */
    private function getSystemStatistics(Carbon $now, Request $request): array
    {
        // Total Users
        $totalUsers = DB::table('users')->where('isRemoved', 0)->count();

        // New Users This Month
        $newUsersThisMonth = DB::table('users')
            ->where('isRemoved', 0)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $newUsersPercentage = $totalUsers > 0
            ? round(($newUsersThisMonth / $totalUsers) * 100, 2)
            : 0;

        // Active Providers
        $activeProviders = DB::table('api_providers')
            ->where('is_active', 1)
            ->count();

        // Top 5 Providers
        $top5Providers = DB::table('usage_records')
            ->select('api_provider', DB::raw('COUNT(*) as request_count'))
            ->whereNotNull('api_provider')
            ->groupBy('api_provider')
            ->orderByDesc('request_count')
            ->limit(5)
            ->get();

        $top5ProvidersData = [];
        foreach ($top5Providers as $provider) {
            $top5ProvidersData[] = [
                'provider' => $provider->api_provider,
                'requests' => number_format($provider->request_count),
            ];
        }

        // Total Provider Requests
        $totalProviderRequests = DB::table('usage_records')
            ->whereNotNull('model')
            ->count();

        // Total Input Tokens
        $totalInputTokens = DB::table('usage_records')
            ->whereNotNull('model')
            ->sum('prompt_tokens');

        // Total Output Tokens
        $totalOutputTokens = DB::table('usage_records')
            ->whereNotNull('model')
            ->sum('completion_tokens');

        // Active Models
        $activeModels = DB::table('ai_models')
            ->where('is_active', 1)
            ->count();

        // Check if system models should be excluded
        $excludeSystemModels = $request->input('exclude_system_models', false);

        // Define system assistant types to exclude
        $systemAssistantTypes = ['title', 'improver', 'summarizer'];

        // Top 5 Models
        $top5ModelsQuery = DB::table('usage_records')
            ->select('api_provider', 'model', DB::raw('COUNT(*) as request_count'))
            ->whereNotNull('model')
            ->whereNotNull('api_provider');

        // Exclude system assistant requests if filter is active
        if ($excludeSystemModels) {
            $top5ModelsQuery->whereNotIn('type', $systemAssistantTypes);
        }

        $top5Models = $top5ModelsQuery
            ->groupBy('api_provider', 'model')
            ->orderByDesc('request_count')
            ->limit(5)
            ->get();

        $top5ModelsData = [];
        foreach ($top5Models as $model) {
            $top5ModelsData[] = [
                'provider' => $model->api_provider,
                'model' => $model->model,
                'requests' => number_format($model->request_count),
            ];
        }

        return [
            'system.totalUsers' => number_format($totalUsers),
            'system.newUsersThisMonth' => number_format($newUsersThisMonth),
            'system.newUsersPercentage' => $newUsersPercentage.'%',
            'system.activeProviders' => $activeProviders,
            'system.top5Providers' => $top5ProvidersData,
            'system.totalProviderRequests' => number_format($totalProviderRequests),
            'system.totalInputTokens' => number_format($totalInputTokens),
            'system.totalOutputTokens' => number_format($totalOutputTokens),
            'system.activeModels' => $activeModels,
            'system.top5Models' => $top5ModelsData,
            'system.excludeSystemModels' => $excludeSystemModels,
        ];
    }

    /**
     * Holt 1:1 Chat Statistiken
     */
    private function getChatStatistics(): array
    {
        // Total 1:1 Chats
        $totalChats = DB::table('ai_convs')->count();

        // Total Chat Messages
        $totalChatMessages = DB::table('ai_conv_msgs')->count();

        // Total Uploaded Files (Attachments für ai_conv_msgs)
        $totalUploadedFiles = DB::table('attachments')
            ->where('attachable_type', 'App\\Models\\AiConvMsg')
            ->count();

        return [
            'chat.totalChats' => number_format($totalChats),
            'chat.totalMessages' => number_format($totalChatMessages),
            'chat.totalFiles' => number_format($totalUploadedFiles),
        ];
    }

    /**
     * Holt Groupchat Statistiken
     */
    private function getGroupchatStatistics(?Request $request = null): array
    {
        // Total Groupchat Rooms
        $totalRooms = DB::table('rooms')->count();

        // Total Users in Groupchats (unique)
        $totalUsersInGroupchats = DB::table('members')
            ->where('isMember', 1)
            ->distinct('user_id')
            ->count('user_id');

        // Total Groupchat Messages
        $totalGroupchatMessages = DB::table('messages')->count();

        // Average Users per Groupchat
        $avgUsersPerGroupchat = $totalRooms > 0
            ? round($totalUsersInGroupchats / $totalRooms, 2)
            : 0;

        // Hole Sortier-Parameter (messages oder users)
        $sortBy = $request ? $request->input('groupchat_sort_by', 'messages') : 'messages';
        $orderColumn = $sortBy === 'users' ? 'user_count' : 'message_count';

        // Most Active Groupchats (Top 5) mit User-Count
        $mostActiveGroupchats = DB::table('messages')
            ->select(
                'rooms.room_name',
                'rooms.id',
                DB::raw('COUNT(messages.id) as message_count'),
                DB::raw('(SELECT COUNT(DISTINCT user_id) FROM members WHERE members.room_id = rooms.id AND members.isMember = 1) as user_count')
            )
            ->join('rooms', 'messages.room_id', '=', 'rooms.id')
            ->groupBy('rooms.id', 'rooms.room_name')
            ->orderByDesc($orderColumn)
            ->limit(5)
            ->get();

        $topGroupchats = [];
        foreach ($mostActiveGroupchats as $room) {
            $topGroupchats[] = [
                'name' => $room->room_name,
                'messages' => number_format($room->message_count),
                'users' => number_format($room->user_count),
            ];
        }

        return [
            'groupchat.totalRooms' => number_format($totalRooms),
            'groupchat.totalUsers' => number_format($totalUsersInGroupchats),
            'groupchat.totalMessages' => number_format($totalGroupchatMessages),
            'groupchat.avgUsersPerRoom' => $avgUsersPerGroupchat,
            'groupchat.topRooms' => $topGroupchats,
            'groupchat.sortBy' => $sortBy,
        ];
    }

    /**
     * Holt System Assistants Statistiken aus usage_records
     */
    private function getSystemAssistantStatistics(): array
    {
        // Anzahl generierter Verbesserungen
        $improvementsCount = DB::table('usage_records')
            ->where('type', 'improvement')
            ->count();

        // Anzahl generierter Zusammenfassungen
        $summariesCount = DB::table('usage_records')
            ->where('type', 'summary')
            ->count();

        // Anzahl generierter Titel
        $titlesCount = DB::table('usage_records')
            ->where('type', 'title')
            ->count();

        // Anzahl Tool Requests (server_tool_use ist nicht NULL und nicht leer)
        $toolRequestsCount = DB::table('usage_records')
            ->whereNotNull('server_tool_use')
            ->where('server_tool_use', '!=', '[]')
            ->where('server_tool_use', '!=', '')
            ->count();

        return [
            'assistants.improvements' => number_format($improvementsCount),
            'assistants.summaries' => number_format($summariesCount),
            'assistants.titles' => number_format($titlesCount),
            'assistants.toolRequests' => number_format($toolRequestsCount),
        ];
    }
}
