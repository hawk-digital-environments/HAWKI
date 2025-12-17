<?php

namespace App\Orchid\Screens\Dashboard;

use App\Orchid\Layouts\Charts\LineChart;
use App\Orchid\Layouts\Charts\PieChart;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class RequestsDashboard extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        // Überprüfen, ob die benötigten Tabellen existieren
        $usageRecordsExists = Schema::hasTable('usage_records');
        $usageUsersDailyExists = Schema::hasTable('usage_users_daily');

        // Überprüfen, ob Daten in den Tabellen vorhanden sind
        $hasUsageRecords = false;
        if ($usageRecordsExists) {
            $hasUsageRecords = DB::table('usage_records')->exists();
        }

        // Wenn die benötigten Tabellen nicht existieren oder leer sind, zeige Platzhalter
        if (! $usageRecordsExists || ! $hasUsageRecords) {
            Log::warning('Usage records table does not exist or is empty. Showing placeholder data.');

            return $this->getPlaceholderData();
        }

        // Current date and selected period
        $now = Carbon::now();

        // Hole den ausgewählten Monat aus dem Request (Format: Y-m, z.B. "2025-12")
        $selectedMonth = $request->input('monthly_date', $now->format('Y-m'));
        // Fallback auf aktuellen Monat, wenn leer oder ungültig
        if (empty($selectedMonth) || ! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = $now->format('Y-m');
        }
        $monthDate = Carbon::createFromFormat('Y-m', $selectedMonth);
        $monthName = $monthDate->format('F Y');

        // Hole das ausgewählte Datum für "Requests per Hour" (Format: Y-m-d)
        $selectedDate = $request->input('date', $now->format('Y-m-d'));
        // Fallback auf heutiges Datum, wenn leer oder ungültig
        if (empty($selectedDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = $now->format('Y-m-d');
        }
        $dateObj = Carbon::createFromFormat('Y-m-d', $selectedDate);

        $currentYear = $monthDate->year;
        $currentMonth = $monthDate->month;
        $currentDay = $now->day;

        $daysInMonth = $monthDate->daysInMonth;

        // Labels für den aktuellen Monat
        $labelsForCurrentMonth = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labelsForCurrentMonth[] = Carbon::create($currentYear, $currentMonth, $d)->format('Y-m-d');
        }

        // Labels für 24-Stunden Tag, beginnend bei 04:00
        $hourLabels = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = ($i + 4) % 24;
            $hourLabels[] = sprintf('%02d:00', $hour);
        }

        // =====================================================================
        // REQUEST STATISTICS FOR METRICS
        // =====================================================================

        // Gesamtzahl aller Requests
        $totalRequests = DB::table('usage_records')->count();

        // Requests im ausgewählten Monat
        $requestsThisMonth = DB::table('usage_records')
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        // Requests am ausgewählten Datum
        $requestsOnSelectedDate = DB::table('usage_records')
            ->whereDate('created_at', $dateObj->toDateString())
            ->count();

        // Durchschnittliche Requests pro Tag im ausgewählten Monat
        // Für laufenden Monat: nur bisherige Tage | Für vergangene Monate: alle Tage
        $isCurrentMonth = $currentYear === $now->year && $currentMonth === $now->month;
        $daysToCalculate = $isCurrentMonth ? $currentDay : $daysInMonth;
        $avgRequestsPerDay = $requestsThisMonth > 0 ? round($requestsThisMonth / $daysToCalculate, 0) : 0;

        // Prozentuale Abweichung vom ausgewählten Datum zum Durchschnitt
        $requestsDateDelta = ($avgRequestsPerDay > 0)
            ? round((($requestsOnSelectedDate - $avgRequestsPerDay) / $avgRequestsPerDay) * 100, 2)
            : 0;

        // =====================================================================
        // REQUESTS PER DAY (für Line Chart) - mit Provider-Aufschlüsselung
        // =====================================================================

        // Hole Requests pro Tag und Provider
        if ($usageUsersDailyExists && DB::table('usage_users_daily')->exists()) {
            $requestsPerDayProviderData = DB::table('usage_users_daily')
                ->select(
                    DB::raw('DAY(date) as day'),
                    'api_provider',
                    DB::raw('SUM(api_requests) as requests')
                )
                ->whereYear('date', $currentYear)
                ->whereMonth('date', $currentMonth)
                ->whereNotNull('api_provider')
                ->groupBy('day', 'api_provider')
                ->orderBy('day')
                ->get();
        } else {
            $requestsPerDayProviderData = DB::table('usage_records')
                ->select(
                    DB::raw('DAY(created_at) as day'),
                    'api_provider',
                    DB::raw('COUNT(*) as requests')
                )
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->whereNotNull('api_provider')
                ->groupBy('day', 'api_provider')
                ->orderBy('day')
                ->get();
        }

        // Gruppiere nach Provider
        $providersByDay = [];
        $allProviders = [];
        foreach ($requestsPerDayProviderData as $data) {
            $day = (int) $data->day;
            $provider = $data->api_provider;
            $requests = (int) $data->requests;

            if (! isset($providersByDay[$provider])) {
                $providersByDay[$provider] = array_fill(0, $daysInMonth, 0);
                $allProviders[] = $provider;
            }

            $index = $day - 1;
            if ($index >= 0 && $index < $daysInMonth) {
                $providersByDay[$provider][$index] = $requests;
            }
        }

        // Sortiere Provider alphabetisch für konsistente Farbreihenfolge
        sort($allProviders);

        // Füge "Total" als erste Option hinzu
        $availableProvidersWithTotal = array_merge(['Total'], $allProviders);

        // Hole ausgewählte Provider aus Query Parameters
        $selectedProviders = $request->input('providers', []);

        // Wenn keine Provider ausgewählt sind, zeige alle an (inkl. Total)
        if (empty($selectedProviders)) {
            $selectedProviders = $availableProvidersWithTotal;
        } else {
            // Stelle sicher, dass selectedProviders ein Array ist
            $selectedProviders = is_array($selectedProviders) ? $selectedProviders : [$selectedProviders];
        }

        // Erstelle Datenreihen nur für ausgewählte Provider
        $requestsPerDay = [];
        $totalRequestsArray = array_fill(0, $daysInMonth, 0);
        $showTotal = in_array('Total', $selectedProviders);

        // Berechne Total-Linie über ALLE Provider (nicht nur ausgewählte)
        if ($showTotal) {
            foreach ($allProviders as $provider) {
                for ($i = 0; $i < $daysInMonth; $i++) {
                    $totalRequestsArray[$i] += $providersByDay[$provider][$i];
                }
            }
        }

        // Füge einzelne Provider-Linien hinzu
        foreach ($allProviders as $provider) {
            if (in_array($provider, $selectedProviders)) {
                $requestsPerDay[] = [
                    'name' => $provider,
                    'labels' => $labelsForCurrentMonth,
                    'values' => $providersByDay[$provider],
                ];
            }
        }

        // Füge die kumulierte Gesamt-Linie hinzu, wenn "Total" ausgewählt ist
        if ($showTotal && ! empty($allProviders)) {
            $requestsPerDay[] = [
                'name' => 'Total',
                'labels' => $labelsForCurrentMonth,
                'values' => $totalRequestsArray,
            ];
        }

        // Wenn keine Provider-Daten vorhanden, zeige Gesamt-Requests
        if (empty($requestsPerDay)) {
            $totalRequestsPerDay = DB::table('usage_records')
                ->select(DB::raw('DAY(created_at) as day'), DB::raw('COUNT(*) as requests'))
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $requestsPerDayArray = array_fill(0, $daysInMonth, 0);
            foreach ($totalRequestsPerDay as $data) {
                $index = (int) $data->day - 1;
                if ($index >= 0 && $index < $daysInMonth) {
                    $requestsPerDayArray[$index] = (int) $data->requests;
                }
            }

            $requestsPerDay = [
                [
                    'name' => 'Total Requests',
                    'labels' => $labelsForCurrentMonth,
                    'values' => $requestsPerDayArray,
                ],
            ];
        }

        // =====================================================================
        // REQUESTS PER HOUR (für ausgewähltes Datum) - mit Provider-Aufschlüsselung
        // =====================================================================

        // Hole Requests pro Stunde und Provider für das ausgewählte Datum
        if ($usageUsersDailyExists && DB::table('usage_users_daily')->exists()) {
            $requestsPerHourProviderData = DB::table('usage_users_daily')
                ->join('usage_records', function ($join) use ($dateObj) {
                    $join->on('usage_users_daily.user_id', '=', 'usage_records.user_id')
                        ->on('usage_users_daily.api_provider', '=', 'usage_records.api_provider')
                        ->whereDate('usage_records.created_at', $dateObj->toDateString());
                })
                ->select(
                    DB::raw('HOUR(usage_records.created_at) as hour'),
                    'usage_records.api_provider',
                    DB::raw('COUNT(*) as requests')
                )
                ->whereDate('usage_users_daily.date', $dateObj->toDateString())
                ->whereNotNull('usage_records.api_provider')
                ->groupBy('hour', 'usage_records.api_provider')
                ->orderBy('hour')
                ->get();
        } else {
            $requestsPerHourProviderData = DB::table('usage_records')
                ->select(
                    DB::raw('HOUR(created_at) as hour'),
                    'api_provider',
                    DB::raw('COUNT(*) as requests')
                )
                ->whereDate('created_at', $dateObj->toDateString())
                ->whereNotNull('api_provider')
                ->groupBy('hour', 'api_provider')
                ->orderBy('hour')
                ->get();
        }

        // Gruppiere nach Provider für Stundenansicht
        $providersByHour = [];
        $hourProviders = [];
        foreach ($requestsPerHourProviderData as $data) {
            $hour = (int) $data->hour;
            $provider = $data->api_provider;
            $requests = (int) $data->requests;

            if (! isset($providersByHour[$provider])) {
                $providersByHour[$provider] = array_fill(0, 24, 0);
                $hourProviders[] = $provider;
            }

            $providersByHour[$provider][$hour] = $requests;
        }

        // Sortiere Provider alphabetisch für konsistente Farbreihenfolge
        sort($hourProviders);

        // Erstelle Datenreihen nur für ausgewählte Provider
        // Ordne Werte neu an, um bei 04:00 zu beginnen
        $requestsPerHour = [];
        $totalRequestsHourArray = array_fill(0, 24, 0);

        // Berechne Total-Linie über ALLE Provider (nicht nur ausgewählte)
        if ($showTotal) {
            foreach ($hourProviders as $provider) {
                for ($i = 0; $i < 24; $i++) {
                    $hour = ($i + 4) % 24;
                    $totalRequestsHourArray[$i] += $providersByHour[$provider][$hour];
                }
            }
        }

        // Füge einzelne Provider-Linien hinzu
        foreach ($hourProviders as $provider) {
            if (in_array($provider, $selectedProviders)) {
                // Ordne Array neu an: 04:00-23:00, dann 00:00-03:00
                $reorderedValues = [];
                for ($i = 0; $i < 24; $i++) {
                    $hour = ($i + 4) % 24;
                    $reorderedValues[] = $providersByHour[$provider][$hour];
                }

                $requestsPerHour[] = [
                    'name' => $provider,
                    'labels' => $hourLabels,
                    'values' => $reorderedValues,
                ];
            }
        }

        // Füge die kumulierte Gesamt-Linie hinzu, wenn "Total" ausgewählt ist
        if ($showTotal && ! empty($hourProviders)) {
            $requestsPerHour[] = [
                'name' => 'Total',
                'labels' => $hourLabels,
                'values' => $totalRequestsHourArray,
            ];
        }

        // Wenn keine Provider-Daten vorhanden, zeige Gesamt-Requests
        if (empty($requestsPerHour)) {
            $rawRequestsPerHour = DB::table('usage_records')
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
                ->whereDate('created_at', $dateObj->toDateString())
                ->groupBy('hour')
                ->get();

            $requestsPerHourArray = array_fill(0, 24, 0);
            foreach ($rawRequestsPerHour as $data) {
                $hourIndex = (int) $data->hour;
                $requestsPerHourArray[$hourIndex] = (int) $data->count;
            }

            // Ordne Array neu an, um bei 04:00 zu beginnen
            $reorderedHourValues = [];
            for ($i = 0; $i < 24; $i++) {
                $hour = ($i + 4) % 24;
                $reorderedHourValues[] = $requestsPerHourArray[$hour];
            }

            $requestsPerHour = [
                [
                    'name' => 'Total Requests',
                    'labels' => $hourLabels,
                    'values' => $reorderedHourValues,
                ],
            ];
        }

        // =====================================================================
        // REQUESTS PER PROVIDER (dynamisch aus Datenbank)
        // =====================================================================

        // Verwende usage_users_daily wenn verfügbar, sonst usage_records
        if ($usageUsersDailyExists && DB::table('usage_users_daily')->exists()) {
            $providerData = DB::table('usage_users_daily')
                ->select('api_provider', DB::raw('SUM(api_requests) as total'))
                ->whereYear('date', $currentYear)
                ->whereMonth('date', $currentMonth)
                ->groupBy('api_provider')
                ->get();

            $providerLabels = $providerData->pluck('api_provider')->toArray();
            $providerValues = $providerData->pluck('total')->map(fn ($v) => (int) $v)->toArray();
        } else {
            $providerData = DB::table('usage_records')
                ->select('api_provider', DB::raw('COUNT(*) as total'))
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->whereNotNull('api_provider')
                ->groupBy('api_provider')
                ->get();

            $providerLabels = $providerData->pluck('api_provider')->toArray();
            $providerValues = $providerData->pluck('total')->map(fn ($v) => (int) $v)->toArray();
        }

        $requestsPerProvider = [
            [
                'labels' => $providerLabels,
                'name' => 'Requests per Provider',
                'values' => $providerValues,
            ],
        ];

        // =====================================================================
        // REQUESTS PER MODEL (dynamisch aus Datenbank)
        // =====================================================================

        if ($usageUsersDailyExists && DB::table('usage_users_daily')->exists()) {
            $modelData = DB::table('usage_users_daily')
                ->select('model', DB::raw('SUM(api_requests) as total'))
                ->whereYear('date', $currentYear)
                ->whereMonth('date', $currentMonth)
                ->groupBy('model')
                ->orderByDesc('total')
                ->limit(10) // Top 10 Modelle
                ->get();

            $modelLabels = $modelData->pluck('model')->toArray();
            $modelValues = $modelData->pluck('total')->map(fn ($v) => (int) $v)->toArray();
        } else {
            $modelData = DB::table('usage_records')
                ->select('model', DB::raw('COUNT(*) as total'))
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->groupBy('model')
                ->orderByDesc('total')
                ->limit(10) // Top 10 Modelle
                ->get();

            $modelLabels = $modelData->pluck('model')->toArray();
            $modelValues = $modelData->pluck('total')->map(fn ($v) => (int) $v)->toArray();
        }

        $requestsPerModel = [
            [
                'labels' => $modelLabels,
                'name' => 'Requests per Model',
                'values' => $modelValues,
            ],
        ];

        // =====================================================================
        // TOP 10 USERS (meiste Requests im ausgewählten Monat)
        // =====================================================================

        // Hole Sortier-Parameter (requests oder tokens)
        $sortBy = $request->input('sort_by', 'requests');
        $orderColumn = $sortBy === 'tokens' ? 'total_tokens' : 'total_requests';

        $topUsers = collect();
        if ($usageUsersDailyExists && DB::table('usage_users_daily')->exists()) {
            $topUsers = DB::table('usage_users_daily')
                ->join('users', 'usage_users_daily.user_id', '=', 'users.id')
                ->leftJoin('role_users', 'users.id', '=', 'role_users.user_id')
                ->leftJoin('roles', 'role_users.role_id', '=', 'roles.id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    DB::raw('GROUP_CONCAT(DISTINCT roles.name SEPARATOR ", ") as roles'),
                    DB::raw('SUM(usage_users_daily.api_requests) as total_requests'),
                    DB::raw('SUM(usage_users_daily.total_tokens) as total_tokens')
                )
                ->whereYear('usage_users_daily.date', $currentYear)
                ->whereMonth('usage_users_daily.date', $currentMonth)
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc($orderColumn)
                ->limit(10)
                ->get();
        } else {
            $topUsers = DB::table('usage_records')
                ->join('users', 'usage_records.user_id', '=', 'users.id')
                ->leftJoin('role_users', 'users.id', '=', 'role_users.user_id')
                ->leftJoin('roles', 'role_users.role_id', '=', 'roles.id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    DB::raw('GROUP_CONCAT(DISTINCT roles.name SEPARATOR ", ") as roles'),
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('SUM(usage_records.prompt_tokens + usage_records.completion_tokens) as total_tokens')
                )
                ->whereYear('usage_records.created_at', $currentYear)
                ->whereMonth('usage_records.created_at', $currentMonth)
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc($orderColumn)
                ->limit(10)
                ->get();
        }

        // =====================================================================
        // TOOL USE PER PROVIDER METRICS
        // =====================================================================

        $toolUseMetrics = [];

        if ($usageUsersDailyExists && DB::table('usage_users_daily')->exists()) {
            // Hole Tool-Use-Daten pro Provider
            $providerToolUseData = DB::table('usage_users_daily')
                ->leftJoin('api_providers', 'usage_users_daily.api_provider', '=', 'api_providers.unique_name')
                ->select(
                    'usage_users_daily.api_provider',
                    DB::raw('COALESCE(api_providers.provider_name, usage_users_daily.api_provider) as provider_name'),
                    DB::raw('GROUP_CONCAT(CASE WHEN usage_users_daily.server_tool_use IS NOT NULL AND usage_users_daily.server_tool_use != "" THEN usage_users_daily.server_tool_use END SEPARATOR "|||") as tool_use_data')
                )
                ->whereYear('usage_users_daily.date', $currentYear)
                ->whereMonth('usage_users_daily.date', $currentMonth)
                ->whereNotNull('usage_users_daily.api_provider')
                ->groupBy('usage_users_daily.api_provider', 'api_providers.provider_name')
                ->get();

            foreach ($providerToolUseData as $data) {
                $toolUseDetails = $this->parseToolUseData($data->tool_use_data ?? '');

                if (! empty($toolUseDetails)) {
                    $totalToolUses = array_sum($toolUseDetails);
                    $topTools = array_slice($toolUseDetails, 0, 3, true);

                    // Verwende provider_name für die Anzeige, aber speichere auch api_provider für Filter-Abgleich
                    $displayName = $data->provider_name ?? $data->api_provider;
                    $toolUseMetrics[$displayName] = [
                        'totalToolUses' => $totalToolUses,
                        'topTools' => $topTools,
                        'identifier' => $data->api_provider, // unique_name für Filter-Abgleich
                    ];
                }
            }
        }

        return [
            'requestsPerDay' => $requestsPerDay,
            'requestsPerHour' => $requestsPerHour,
            'requestsPerProvider' => $requestsPerProvider,
            'requestsPerModel' => $requestsPerModel,
            'topUsers' => $topUsers,
            'sortBy' => $sortBy,
            'availableProviders' => $availableProvidersWithTotal,
            'selectedProviders' => $selectedProviders,
            'selectedMonth' => $monthName,
            'selectedDate' => $dateObj->format('l, F j, Y'),
            'toolUseMetrics' => $toolUseMetrics,

            'metrics' => [
                'totalRequests' => number_format($totalRequests),
                'requestsThisMonth' => ['value' => number_format($requestsThisMonth), 'diff' => 0],
                'avgRequestsPerDay' => number_format($avgRequestsPerDay, 0),
                'requestsOnSelectedDate' => ['value' => number_format($requestsOnSelectedDate), 'diff' => $requestsDateDelta],
            ],
        ];
    }

    /**
     * Liefert Platzhalter-Daten für das Dashboard, wenn keine echten Daten verfügbar sind
     */
    private function getPlaceholderData(): array
    {
        // Aktuelles Datum für Labels mit Carbon
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;
        $daysInMonth = $now->daysInMonth;

        // Labels für aktuellen Monat erstellen
        $labelsForCurrentMonth = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labelsForCurrentMonth[] = Carbon::create($currentYear, $currentMonth, $d)->format('Y-m-d');
        }

        // Statische Labels für 24h-Tag, beginnend bei 04:00
        $hourLabels = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = ($i + 4) % 24;
            $hourLabels[] = sprintf('%02d:00', $hour);
        }

        // Platzhalterwerte für Charts
        $placeholderDailyRequests = array_fill(0, $daysInMonth, 0);
        $placeholderDailyUsers = array_fill(0, $daysInMonth, 0);
        $placeholderHourlyRequests = array_fill(0, 24, 0);

        return [
            'requestsPerDay' => [
                [
                    'labels' => $labelsForCurrentMonth,
                    'name' => 'Requests',
                    'values' => $placeholderDailyRequests,
                ],
            ],
            'requestsPerHour' => [
                [
                    'labels' => $hourLabels,
                    'name' => 'Requests per Hour',
                    'values' => $placeholderHourlyRequests,
                ],
            ],
            'requestsPerProvider' => [
                [
                    'labels' => ['OpenAI', 'Google', 'Anthropic'],
                    'name' => 'Requests per Provider',
                    'values' => [0, 0, 0],
                ],
            ],
            'requestsPerModel' => [
                [
                    'labels' => ['GPT-4', 'Gemini', 'Claude'],
                    'name' => 'Requests per Model',
                    'values' => [0, 0, 0],
                ],
            ],
            'topUsers' => collect(),
            'sortBy' => 'requests',
            'availableProviders' => [],
            'selectedProviders' => [],
            'selectedMonth' => $now->format('F Y'),
            'selectedDate' => $now->format('l, F j, Y'),
            'toolUseMetrics' => [],
            'metrics' => [
                'totalRequests' => '0',
                'requestsThisMonth' => ['value' => '0', 'diff' => 0],
                'avgRequestsPerDay' => '0',
                'requestsOnSelectedDate' => ['value' => '0', 'diff' => 0],
            ],
        ];
    }

    /**
     * Get provider details for a specific user.
     */
    public function getUserProviderDetails(Request $request)
    {
        $userId = $request->input('user_id');
        $monthlyDate = $request->input('monthly_date');

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'User ID is required',
            ], 400);
        }

        try {
            // Parse Datum mit Fallback
            if (empty($monthlyDate)) {
                $monthDate = Carbon::now();
            } else {
                try {
                    $monthDate = Carbon::createFromFormat('Y-m', $monthlyDate);
                } catch (\Exception $e) {
                    // Fallback zu aktuellen Monat wenn Parsing fehlschlägt
                    $monthDate = Carbon::now();
                    Log::warning('Invalid date format in getUserProviderDetails', [
                        'monthly_date' => $monthlyDate,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $currentYear = $monthDate->year;
            $currentMonth = $monthDate->month;

            // Prüfe ob usage_users_daily Tabelle existiert
            if (! Schema::hasTable('usage_users_daily')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usage aggregation table not found',
                ], 404);
            }

            // Hole Provider-Details für den User
            $providerDetails = DB::table('usage_users_daily')
                ->leftJoin('api_providers', 'usage_users_daily.api_provider', '=', 'api_providers.unique_name')
                ->select(
                    'usage_users_daily.api_provider',
                    DB::raw('COALESCE(api_providers.provider_name, usage_users_daily.api_provider) as provider_name'),
                    DB::raw('SUM(usage_users_daily.api_requests) as api_requests'),
                    DB::raw('SUM(usage_users_daily.total_tokens) as total_tokens'),
                    DB::raw('SUM(usage_users_daily.prompt_tokens) as prompt_tokens'),
                    DB::raw('SUM(usage_users_daily.completion_tokens) as completion_tokens'),
                    DB::raw('SUM(CASE WHEN usage_users_daily.server_tool_use IS NOT NULL AND usage_users_daily.server_tool_use != "" THEN 1 ELSE 0 END) as tool_use_count'),
                    DB::raw('GROUP_CONCAT(CASE WHEN usage_users_daily.server_tool_use IS NOT NULL AND usage_users_daily.server_tool_use != "" THEN usage_users_daily.server_tool_use END SEPARATOR "|||") as tool_use_data')
                )
                ->where('usage_users_daily.user_id', $userId)
                ->whereYear('usage_users_daily.date', $currentYear)
                ->whereMonth('usage_users_daily.date', $currentMonth)
                ->whereNotNull('usage_users_daily.api_provider')
                ->groupBy('usage_users_daily.api_provider', 'api_providers.provider_name')
                ->orderBy('api_requests', 'desc')
                ->get();

            // Hole Model-Details pro Provider
            $modelDetails = DB::table('usage_users_daily')
                ->leftJoin('api_providers', 'usage_users_daily.api_provider', '=', 'api_providers.unique_name')
                ->select(
                    'usage_users_daily.api_provider',
                    DB::raw('COALESCE(api_providers.provider_name, usage_users_daily.api_provider) as provider_display_name'),
                    'usage_users_daily.model',
                    DB::raw('SUM(usage_users_daily.api_requests) as api_requests'),
                    DB::raw('SUM(usage_users_daily.total_tokens) as total_tokens'),
                    DB::raw('SUM(usage_users_daily.prompt_tokens) as prompt_tokens'),
                    DB::raw('SUM(usage_users_daily.completion_tokens) as completion_tokens'),
                    DB::raw('SUM(CASE WHEN usage_users_daily.server_tool_use IS NOT NULL AND usage_users_daily.server_tool_use != "" THEN 1 ELSE 0 END) as tool_use_count'),
                    DB::raw('GROUP_CONCAT(CASE WHEN usage_users_daily.server_tool_use IS NOT NULL AND usage_users_daily.server_tool_use != "" THEN usage_users_daily.server_tool_use END SEPARATOR "|||") as tool_use_data')
                )
                ->where('usage_users_daily.user_id', $userId)
                ->whereYear('usage_users_daily.date', $currentYear)
                ->whereMonth('usage_users_daily.date', $currentMonth)
                ->whereNotNull('usage_users_daily.api_provider')
                ->whereNotNull('usage_users_daily.model')
                ->groupBy('usage_users_daily.api_provider', 'api_providers.provider_name', 'usage_users_daily.model')
                ->orderBy('api_provider')
                ->orderBy('api_requests', 'desc')
                ->get();

            // Gruppiere Modelle nach Provider
            $modelsByProvider = $modelDetails->groupBy('api_provider');

            return response()->json([
                'success' => true,
                'providers' => $providerDetails->map(function ($item) use ($modelsByProvider) {
                    $provider = $item->api_provider;
                    $models = $modelsByProvider->get($provider, collect())->map(function ($model) {
                        $toolUseDetails = $this->parseToolUseData($model->tool_use_data ?? '');
                        $toolUseCount = array_sum($toolUseDetails);

                        return [
                            'model' => $model->model,
                            'api_requests' => (int) $model->api_requests,
                            'total_tokens' => (int) $model->total_tokens,
                            'prompt_tokens' => (int) $model->prompt_tokens,
                            'completion_tokens' => (int) $model->completion_tokens,
                            'tool_use_count' => $toolUseCount,
                            'tool_use_details' => $toolUseDetails,
                        ];
                    })->values();

                    $providerToolUseDetails = $this->parseToolUseData($item->tool_use_data ?? '');
                    $providerToolUseCount = array_sum($providerToolUseDetails);

                    return [
                        'api_provider' => $item->provider_name ?? $provider,
                        'api_requests' => (int) $item->api_requests,
                        'total_tokens' => (int) $item->total_tokens,
                        'prompt_tokens' => (int) $item->prompt_tokens,
                        'completion_tokens' => (int) $item->completion_tokens,
                        'tool_use_count' => $providerToolUseCount,
                        'tool_use_details' => $providerToolUseDetails,
                        'models' => $models,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user provider details', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse tool use data from concatenated JSON strings.
     */
    private function parseToolUseData(?string $toolUseData): array
    {
        if (empty($toolUseData) || $toolUseData === 'NULL') {
            return [];
        }

        Log::debug('parseToolUseData: Raw input', [
            'length' => strlen($toolUseData),
            'preview' => substr($toolUseData, 0, 500),
        ]);

        $toolCounts = [];

        // Split by custom separator
        $jsonStrings = explode('|||', $toolUseData);

        Log::debug('parseToolUseData: After split', [
            'count' => count($jsonStrings),
            'strings' => array_map(fn ($s) => substr($s, 0, 200), $jsonStrings),
        ]);

        foreach ($jsonStrings as $jsonString) {
            $jsonString = trim($jsonString);

            // Skip empty strings and NULL values
            if (empty($jsonString) || $jsonString === 'NULL' || $jsonString === 'null') {
                continue;
            }

            try {
                $tools = json_decode($jsonString, true);

                // Check if json_decode was successful
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('JSON decode error in parseToolUseData', [
                        'error' => json_last_error_msg(),
                        'data' => substr($jsonString, 0, 100),
                    ]);

                    continue;
                }

                if (is_array($tools)) {
                    Log::debug('parseToolUseData: Found tools array', [
                        'tools' => $tools,
                        'is_associative' => array_keys($tools) !== range(0, count($tools) - 1),
                    ]);

                    // Check if it's an associative array (object) or indexed array
                    if (array_keys($tools) !== range(0, count($tools) - 1)) {
                        // It's an associative array/object: {"web_search": 1, "calculator": 2}
                        foreach ($tools as $toolName => $count) {
                            // Skip metadata fields like tool_use_tokens
                            if ($toolName === 'tool_use_tokens') {
                                continue;
                            }

                            $toolCounts[$toolName] = ($toolCounts[$toolName] ?? 0) + (int) $count;
                        }
                    } else {
                        // It's an indexed array: [{"name": "web_search"}, {"name": "calculator"}]
                        foreach ($tools as $tool) {
                            if (is_array($tool) && isset($tool['name'])) {
                                $toolName = $tool['name'];
                                $toolCounts[$toolName] = ($toolCounts[$toolName] ?? 0) + 1;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Exception in parseToolUseData', [
                    'error' => $e->getMessage(),
                    'data' => substr($jsonString, 0, 100),
                ]);

                continue;
            }
        }

        // Sort by count descending
        arsort($toolCounts);

        return $toolCounts;
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Requests Dashboard';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Overview of request metrics for HAWKI';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Aggregate Usage Data')
                ->icon('bs.arrow-clockwise')
                ->confirm('This will aggregate all usage data without existing daily aggregations. Continue?')
                ->method('aggregateUsageData'),
        ];
    }

    /**
     * Calculate the number of days to backfill based on missing aggregations.
     */
    private function calculateBackfillDays(): int
    {
        // Prüfe ob beide Tabellen existieren
        if (! Schema::hasTable('usage_records') || ! Schema::hasTable('usage_users_daily')) {
            return 0;
        }

        // Finde das früheste Datum in usage_records
        $earliestUsageRecord = DB::table('usage_records')
            ->selectRaw('DATE(created_at) as date')
            ->orderBy('created_at', 'asc')
            ->first();

        if (! $earliestUsageRecord) {
            return 0; // Keine usage_records vorhanden
        }

        // Finde das früheste Datum in usage_users_daily
        $earliestAggregation = DB::table('usage_users_daily')
            ->selectRaw('DATE(date) as date')
            ->orderBy('date', 'asc')
            ->first();

        $earliestRecordDate = Carbon::parse($earliestUsageRecord->date);
        $today = Carbon::today();

        if (! $earliestAggregation) {
            // Keine Aggregationen vorhanden - backfill von frühestem Record bis heute
            return $earliestRecordDate->diffInDays($today) + 1;
        }

        $earliestAggDate = Carbon::parse($earliestAggregation->date);

        // Wenn Aggregationen vor den Records existieren, keine Aktion nötig
        if ($earliestAggDate <= $earliestRecordDate) {
            // Prüfe ob es Lücken gibt
            $missingDates = DB::table('usage_records')
                ->selectRaw('DISTINCT DATE(created_at) as date')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('usage_users_daily')
                        ->whereRaw('DATE(usage_users_daily.date) = DATE(usage_records.created_at)');
                })
                ->count();

            return $missingDates > 0 ? $earliestRecordDate->diffInDays($today) + 1 : 0;
        }

        // Berechne Tage von frühestem Record bis heute
        return $earliestRecordDate->diffInDays($today) + 1;
    }

    /**
     * Handler method to aggregate usage data.
     */
    public function aggregateUsageData(): void
    {
        $backfillDays = $this->calculateBackfillDays();

        if ($backfillDays === 0) {
            Toast::info('All usage data is already aggregated.');

            return;
        }

        try {
            // Führe Artisan Command aus
            Artisan::call('usage:aggregate-daily', [
                '--backfill' => $backfillDays,
            ]);

            $output = Artisan::output();
            Log::info('Usage aggregation completed', ['output' => $output]);

            Toast::success("Successfully aggregated {$backfillDays} days of usage data.");
        } catch (\Exception $e) {
            Log::error('Usage aggregation failed', ['error' => $e->getMessage()]);
            Toast::error('Failed to aggregate usage data: '.$e->getMessage());
        }
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        $selectedMonth = request('monthly_date', Carbon::now()->format('Y-m'));
        $monthName = Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y');

        $selectedDate = request('date', Carbon::now()->format('Y-m-d'));
        $dateDisplay = Carbon::createFromFormat('Y-m-d', $selectedDate)->format('l, F j, Y');

        $requestsPerDayChart = LineChart::make('requestsPerDay', 'Requests per Day')
            ->title('Requests per Day')
            ->description('Overview of API requests per day for '.$monthName.'.');

        $requestsPerHourChart = LineChart::make('requestsPerHour', 'Requests per Hour')
            ->title('Requests per Hour')
            ->description('Overview of API requests per hour on '.$dateDisplay.' (starting at 04:00).');

        $requestsProviderPieChart = PieChart::make('requestsPerProvider')
            ->title('Requests per Provider')
            ->description('Distribution of requests across API providers for '.$monthName.'.');

        $requestsModelPieChart = PieChart::make('requestsPerModel')
            ->title('Requests per Model')
            ->description('Distribution of requests across AI models for '.$monthName.'.');

        return [
            Layout::columns([
                Layout::view('orchid.partials.month-selector-empty'),
                Layout::view('orchid.partials.month-selector-empty'),
                Layout::view('orchid.partials.month-selector-empty'),
                Layout::view('orchid.partials.month-selector', [
                    'currentMonth' => request('monthly_date', Carbon::now()->format('Y-m')),
                    'route' => 'platform.dashboard.requests',
                ]),
            ]),

            Layout::metrics([
                'Total Requests' => 'metrics.totalRequests',
                'Requests this Month' => 'metrics.requestsThisMonth',
                'Average Daily Requests' => 'metrics.avgRequestsPerDay',
                'Requests on Selected Date' => 'metrics.requestsOnSelectedDate',
            ]),

            Layout::view('orchid.partials.provider-filter', [
                'route' => 'platform.dashboard.requests',
            ]),

            Layout::columns([
                $requestsPerDayChart,
            ]),

            Layout::columns([
                Layout::view('orchid.partials.date-selector-empty'),
                Layout::view('orchid.partials.date-selector-empty'),
                Layout::view('orchid.partials.date-selector-empty'),
                Layout::view('orchid.partials.date-selector', [
                    'currentDate' => request('date', Carbon::now()->format('Y-m-d')),
                    'route' => 'platform.dashboard.requests',
                ]),
            ]),

            Layout::columns([
                $requestsPerHourChart,
            ]),

            Layout::split([
                $requestsProviderPieChart,
                $requestsModelPieChart,
            ])->ratio('50/50'),

            Layout::view('orchid.partials.tool-use-metrics', [
                'monthName' => $monthName,
            ]),

            Layout::view('orchid.partials.top-users-table', [
                'monthName' => $monthName,
                'route' => 'platform.dashboard.requests',
            ]),
        ];
    }
}
