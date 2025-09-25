<?php

namespace App\Orchid\Screens\Dashboard;

use App\Orchid\Layouts\Charts\BarChart;
use App\Orchid\Layouts\Charts\PercentageChart;
use App\Orchid\Layouts\Charts\PieChart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class Dashboard extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Überprüfen, ob die benötigten Tabellen existieren
        $usageRecordsExists = Schema::hasTable('usage_records');
        $conversationsExists = Schema::hasTable('conversations');

        // Überprüfen, ob Daten in der usage_records Tabelle vorhanden sind
        $hasUsageRecords = false;
        if ($usageRecordsExists) {
            $hasUsageRecords = DB::table('usage_records')->exists();
        }

        // Wenn die benötigten Tabellen nicht existieren oder leer sind, zeige Platzhalter
        if (! $usageRecordsExists || ! $conversationsExists || ! $hasUsageRecords) {
            Log::warning('Required tables do not exist or are empty. Showing placeholder data.');

            return $this->getPlaceholderData();
        }

        // Labels
        // Dynamisch erstellte Labels für den aktuell ausgewählten Monat
        $currentYear = date('Y');
        $currentMonth = date('m');
        $currentDay = date('d');
        $specificDay = '2025-03-21';
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) $currentMonth, (int) $currentYear);
        $labelsForCurrentMonth = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labelsForCurrentMonth[] = sprintf('%s-%02d-%02d', $currentYear, $currentMonth, $d);
        }

        // Statische Labels für einen 24h-Stunden Tag
        $hourLabels = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourLabels[] = sprintf('%02d:00', $hour);
        }

        // User Statistics
        $totalUsers = DB::table('users')->count();

        // Anzahl der User, die sich diesen Monat neu angemeldet haben
        $newUsersThisMonth = DB::table('users')
            ->whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->count();
        $percentage = round((($totalUsers > 0) ? ($newUsersThisMonth / $totalUsers) * 100 : 0), 2);

        $dailyData = DB::table('usage_records')
            ->select(DB::raw('DAY(created_at) as day'), DB::raw('count(DISTINCT user_id) as activeUsers'))
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->groupBy('day')
            ->orderBy('day')
            ->get();
        // Erstelle ein Array mit 0 als Standardwert für jeden Tag
        $activeUsersPerDay = array_fill(0, $daysInMonth, 0);
        foreach ($dailyData as $data) {
            $index = (int) $data->day - 1;
            if ($index >= 0 && $index < $daysInMonth) {
                $activeUsersPerDay[$index] = $data->activeUsers;
            }
        }

        // Neue Log-Ausgabe: Daten für den aktuellen Tag
        $activeUsersToday = $dailyData->firstWhere('day', (int) $currentDay);

        // Berechne den Durchschnitt der aktiven Nutzer (activeUsersDelta)
        $activeUsersDelta = $dailyData->avg('activeUsers');

        // Berechne den Prozentsatz, um den $activeUsersToday von $activeUsersDelta abweicht
        $todayActive = $activeUsersToday ? $activeUsersToday->activeUsers : 0;
        $activeUsersDeltaDiff = ($activeUsersDelta > 0) ? round((($todayActive - $activeUsersDelta) / $activeUsersDelta) * 100, 2) : 0;

        // Platzhalter-Array mit fiktiven Nutzerzahlen
        $fakeUsers = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            $fakeUsers[] = rand(600, 800); // fiktive Nutzerzahlen
        }

        foreach ($dailyData as $data) {
            $index = (int) $data->day - 1;
            if ($index >= 0 && $index < $daysInMonth) {
                $values[$index] = $data->activeUsers;
            }
        }
        // Zusammenbauen der Daten für das Barchart
        $dailyActiveUsers = [
            [
                'labels' => $labelsForCurrentMonth,
                'name' => 'Daily Users',
                'values' => $activeUsersPerDay,
            ],
        ];

        // Request Statistics
        $totalRequests = DB::table('usage_records')->count();
        $openAiRequests = DB::table('usage_records')
            ->where('model', 'gpt-4o')
            ->get();

        // Lese Modelle aus der Konfiguration
        $providers = config('model_providers.providers');
        $allModels = [];
        foreach ($providers as $providerKey => $provider) {
            if (isset($provider['models'])) {
                foreach ($provider['models'] as $model) {
                    $allModels[] = $model;
                }
            }
        }

        // Führe für jedes Modell eine Datenbankabfrage durch und fasse die Ergebnisse pro Provider zusammen
        $providerSummary = [];
        foreach ($providers as $providerKey => $provider) {
            $totalRequestsForProvider = 0;
            if (isset($provider['models'])) {
                foreach ($provider['models'] as $model) {
                    $count = DB::table('usage_records')
                        ->where('model', $model['id'])
                        ->count();
                    $totalRequestsForProvider += $count;
                }
            }
            $providerSummary[$providerKey] = $totalRequestsForProvider;
        }
        // Erstelle eine modelSummary, die die Anfragen auf die verschiedenen Modelle aufschlüsselt
        $modelSummary = [];
        foreach ($allModels as $model) {
            if (isset($model['id'])) {
                $count = DB::table('usage_records')
                    ->where('model', $model['id'])
                    ->count();
                $modelSummary[$model['id']] = $count;
            }
        }

        // Neues ProviderData Array basierend auf der auskommentierten Struktur
        $providerData = [
            [
                'labels' => array_keys($providerSummary),
                'name' => 'Requests per Provider',
                'values' => array_values($providerSummary),
            ],
        ];

        $specificModel = 'gpt-4o-mini';
        $countForSpecificDay = DB::table('usage_records')
            ->where('model', $specificModel)
            ->whereDate('created_at', $specificDay)
            ->count();

        // Neue Abfrage: Anzahl der Aufrufe eines spezifischen Models im gesamten Monat
        $specificYear = '2025';
        $specificMonth = '03';
        $countForSpecificMonth = DB::table('usage_records')
            ->where('model', $specificModel)
            ->whereYear('created_at', $specificYear)
            ->whereMonth('created_at', $specificMonth)
            ->count();

        // Abfrage der Anzahl der Requests für den spezifischen Tag und Erstellen eines Arrays als Werte
        $requestsCountForSpecificDay = DB::table('usage_records')
            ->whereDate('created_at', $specificDay)
            ->count();
        $requestsPerDayArray = [$requestsCountForSpecificDay];

        // Neuer Code: Abfrage der Requests pro Stunde anhand der created_at-Spalte
        $rawRequestsPerHour = DB::table('usage_records')
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
            ->whereDate('created_at', $specificDay)
            ->groupBy('hour')
            ->get();
        $requestsPerHourArray = array_fill(0, 24, 0);
        foreach ($rawRequestsPerHour as $data) {
            $hourIndex = (int) $data->hour;
            $requestsPerHourArray[$hourIndex] = $data->count;
        }

        $requestsPerModel = [
            [
                'labels' => array_keys($modelSummary),
                'name' => 'Requests per Provider',
                'values' => array_values($modelSummary),
            ],
        ];

        // Aktualisierung der Requests per Hour Chart mit dem neuen Array
        $requestsPerHour = [
            [
                'labels' => $hourLabels,
                'name' => 'Requests per Hour',
                'values' => $requestsPerHourArray,
            ],
        ];

        $models = $this->fetchModels();

        return [
            'dailyActiveUsers' => $dailyActiveUsers,
            'requestsPerProvider' => $providerData,
            'requestsPerHour' => $requestsPerHour,
            'requestsPerModel' => $requestsPerModel,
            'metrics' => [
                'totalUsers' => ['value' => number_format($totalUsers), 'icon' => 'people'],
                'newUsers' => ['value' => number_format($newUsersThisMonth), 'diff' => $percentage, 'icon' => 'bs.graph-up'],
                'activeUsersDelta' => ['value' => number_format($activeUsersDelta), 'diff' => $percentage, 'icon' => 'bs.chat'],
                'activeUsersToday' => ['value' => number_format($activeUsersToday ? $activeUsersToday->activeUsers : 0), 'diff' => $activeUsersDeltaDiff, 'icon' => 'bs.currency-euro'],
            ],
            'modelCards' => $models,
            'chatCountToday' => $this->getChatCount('today'),
            'chatCountWeek' => $this->getChatCount('week'),
            'chatCountMonth' => $this->getChatCount('month'),
            'chatCountTotal' => $this->getChatCount('total'),
        ];
    }

    /**
     * Liefert Platzhalter-Daten für das Dashboard, wenn keine echten Daten verfügbar sind
     */
    private function getPlaceholderData(): array
    {
        // Labels für aktuellen Monat erstellen
        $currentYear = date('Y');
        $currentMonth = date('m');
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) $currentMonth, (int) $currentYear);

        $labelsForCurrentMonth = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labelsForCurrentMonth[] = sprintf('%s-%02d-%02d', $currentYear, $currentMonth, $d);
        }

        // Statische Labels für 24h-Tag
        $hourLabels = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourLabels[] = sprintf('%02d:00', $hour);
        }

        // Platzhalterwerte für Charts
        $placeholderDailyUsers = array_fill(0, $daysInMonth, 0);
        $placeholderHourlyRequests = array_fill(0, 24, 0);

        $dailyActiveUsers = [
            [
                'labels' => $labelsForCurrentMonth,
                'name' => 'Daily Users',
                'values' => $placeholderDailyUsers,
            ],
        ];

        $requestsPerHour = [
            [
                'labels' => $hourLabels,
                'name' => 'Requests per Hour',
                'values' => $placeholderHourlyRequests,
            ],
        ];

        $providerData = [
            [
                'labels' => ['OpenAI', 'Google', 'Anthropic'],
                'name' => 'Requests per Provider',
                'values' => [0, 0, 0],
            ],
        ];

        $requestsPerModel = [
            [
                'labels' => ['GPT-4', 'Gemini', 'Claude'],
                'name' => 'Requests per Model',
                'values' => [0, 0, 0],
            ],
        ];

        return [
            'dailyActiveUsers' => $dailyActiveUsers,
            'requestsPerProvider' => $providerData,
            'requestsPerHour' => $requestsPerHour,
            'requestsPerModel' => $requestsPerModel,
            'metrics' => [
                'totalUsers' => ['value' => '0', 'icon' => 'bs.people'],
                'newUsers' => ['value' => '0', 'diff' => 0, 'icon' => 'bs.graph-up'],
                'activeUsersDelta' => ['value' => '0', 'diff' => 0, 'icon' => 'bs.chat'],
                'activeUsersToday' => ['value' => '0', 'diff' => 0, 'icon' => 'bs.currency-euro'],
            ],
            'modelCards' => [], // Leeres Array für Modellkarten
            'chatCountToday' => 0,
            'chatCountWeek' => 0,
            'chatCountMonth' => 0,
            'chatCountTotal' => 0,
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
        $dailyusersChart = BarChart::make('dailyActiveUsers', 'Daily Users')
            ->title('Daily Active Users')
            ->description('Overview of Users per day, that interacted with an AI model.');

        $requestsPerHourChart = BarChart::make('requestsPerHour', 'Requests per User')
            ->title('Requests per Hour')
            ->description('Overview of LLM Requests per hour.');

        $requestsProviderPieChart = PieChart::make('requestsPerProvider')
            ->title('Request per Provider')
            ->description('Overview of Request per Provider.');

        $requestsModelPieChart = PieChart::make('requestsPerModel')
            ->title('Request per Model')
            ->description('Overview of Request per Model.');

        $percentageChart = PercentageChart::make('newUsersPercentage')
            ->title('New Users Percentage')
            ->description('Percentage of new users relative to total users');

        // Entferne den Layout::view() Aufruf für $dailyusersChart
        return [

            Layout::metrics([
                'Total Users' => 'metrics.totalUsers',
                'Average Active Users per Month' => 'metrics.newUsers',
                'Average Requests per User' => 'metrics.activeUsersDelta',
                'Average Cost per User' => 'metrics.activeUsersToday',
            ])->title('Overview'),

            Layout::columns([
                $percentageChart,
            ]),
            Layout::tabs([
                'OpenAI' => [
                    Layout::metrics([
                        'Requests per Day' => 'metrics.totalUsers',
                        'Average Input' => 'metrics.newUsers',
                        'Average Output' => 'metrics.activeUsersDelta',
                        'Cost Estimate' => 'metrics.activeUsersToday',

                    ]),
                ],
                'Google' => [
                    Layout::metrics([
                        'Requests per Day' => 'metrics.totalUsers',
                        'Average Input' => 'metrics.newUsers',
                        'Average Output' => 'metrics.activeUsersDelta',
                        'Cost Estimate' => 'metrics.activeUsersToday',

                    ]),

                ],
            ]),
        ];
    }

    /**
     * Bereitet die Modelldaten für die Anzeige vor
     *
     * @param  array  $rawModels  Array mit den Rohdaten der Modelle
     * @return array Aufbereitete Modelldaten
     */
    private function prepareModelData($rawModels): array
    {
        $models = [];

        // Überprüfe, ob Modelldaten existieren, bevor wir sie verarbeiten
        if (empty($rawModels)) {
            return $models;
        }

        foreach ($rawModels as $model) {
            // Prüfe, ob alle notwendigen Schlüssel existieren
            if (! isset($model['id']) || ! isset($model['name'])) {
                continue; // Überspringe Modelle mit fehlenden erforderlichen Feldern
            }

            $models[] = [
                'id' => $model['id'] ?? 'unknown',
                'name' => $model['name'] ?? 'Unbekanntes Modell',
                'description' => $model['description'] ?? 'Keine Beschreibung verfügbar',
                'status' => $model['status'] ?? 'unknown',
                'provider' => $model['provider'] ?? 'unknown',
                'avatar' => $model['avatar'] ?? null,
                'provider_model_id' => $model['provider_model_id'] ?? null,
                'created_at' => isset($model['created_at']) ?
                    date('d.m.Y', strtotime($model['created_at'])) :
                    'Unbekannt',
            ];
        }

        return $models;
    }

    /**
     * Holt die Modelldaten aus dem Cache oder der API
     *
     * @return array Array mit den Modelldaten
     */
    private function fetchModels(): array
    {
        try {
            // Prüfen, ob die notwendige Eigenschaft/Service existiert
            if (! property_exists($this, 'aiHandler') || ! $this->aiHandler) {
                Log::warning('aiHandler is not available.');

                return [];
            }

            // Prüfen, ob die Methode existiert
            if (! method_exists($this->aiHandler, 'getAvailableModels')) {
                Log::warning('getAvailableModels method not available.');

                return [];
            }

            // Stelle sicher, dass immer ein Array zurückgegeben wird
            $result = $this->aiHandler->getAvailableModels();

            // Prüfe explizit auf null oder nicht-Array
            if ($result === null || ! is_array($result)) {
                Log::warning('getAvailableModels returned a non-array value or null: '.gettype($result));

                return [];
            }

            return $this->prepareModelData($result);
        } catch (\Exception $e) {
            Log::error('Error fetching models: '.$e->getMessage());

            return []; // Leeres Array zurückgeben, wenn ein Fehler auftritt
        }
    }
}
