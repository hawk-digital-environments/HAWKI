<?php

namespace App\Orchid\Screens\Dashboard;

use Orchid\Screen\Screen;

use App\Orchid\Layouts\Charts\PieChart;
use App\Orchid\Layouts\Charts\BarChart;
use App\Orchid\Layouts\Charts\PercentageChart;

use Orchid\Support\Facades\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestsDashboard extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
    //Labels
    // Dynamisch erstellte Labels für den aktuell ausgewählten Monat
        $currentYear = date('Y');
        $currentMonth = date('m');
        $currentDay = date('d');
        $specificDay = '2025-03-21';


        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$currentMonth, (int)$currentYear);
        $labelsForCurrentMonth = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labelsForCurrentMonth[] = sprintf('%s-%02d-%02d', $currentYear, $currentMonth, $d);
        }

        // Statische Labels für einen 24h-Stunden Tag
        $hourLabels = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourLabels[] = sprintf('%02d:00', $hour);
        }
        Log::info("BarChart Hour Labels: " . json_encode($hourLabels));

    // User Statistics
        $totalUsers = DB::table('users')->count();
        //Log::info('Total users in System: ' . $totalUsers);

        //$users = DB::table('users');

    // Anzahl der User, die sich diesen Monat neu angemeldet haben
        $newUsersThisMonth = DB::table('users')
                           ->whereYear('created_at', date('Y'))
                           ->whereMonth('created_at', date('m'))
                           ->count();

        $percentage = round((($totalUsers > 0) ? ($newUsersThisMonth / $totalUsers) * 100 : 0), 2);
        //Log::info("Prozentsatz neuer User: " . $percentage);

//    // Anzahl der User mit einem Request an einem bestimmten Tag 
//        $activeUsersCount = DB::table('usage_records')
//                              ->whereDate('created_at', $specificDay)
//                              ->distinct('user_id')
//                              ->count('user_id');
//        Log::info("Individuelle aktive User am {$specificDay}: " . $activeUsersCount);


    // Aktive User pro Tag für den ganzen Monat

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
            $index = (int)$data->day - 1;
            if ($index >= 0 && $index < $daysInMonth) {
                $activeUsersPerDay[$index] = $data->activeUsers;
            }
        }
        //Log::info("Active Users per Day Array: " . json_encode($activeUsersPerDay));

        
        // Neue Log-Ausgabe: Daten für den aktuellen Tag
        $activeUsersToday = $dailyData->firstWhere('day', (int)$currentDay);
        //Log::info("Aktive Nutzer am heutigen Tag ({$currentDay}): " . ($activeUsersToday ? $activeUsersToday->activeUsers : 0));

        // Berechne den Durchschnitt der aktiven Nutzer (activeUsersDelta)
        $activeUsersDelta = $dailyData->avg('activeUsers');
        //Log::info("Durchschnitt der aktiven Nutzer (activeUsersDelta): " . $activeUsersDelta);

        // Berechne den Prozentsatz, um den $activeUsersToday von $activeUsersDelta abweicht
        $todayActive = $activeUsersToday ? $activeUsersToday->activeUsers : 0;
        $activeUsersDeltaDiff = ($activeUsersDelta > 0) ? round((($todayActive - $activeUsersDelta) / $activeUsersDelta) * 100, 2) : 0;
        //Log::info("Prozentsatzabweichung: " . $activeUsersDeltaDiff . "%");

            // Platzhalter-Array mit fiktiven Nutzerzahlen
            $fakeUsers = [];
            for ($i = 0; $i < $daysInMonth; $i++) {
                $fakeUsers[] = rand(600, 800); // fiktive Nutzerzahlen
            }
        
        foreach ($dailyData as $data) {
            $index = (int)$data->day - 1;
            if ($index >= 0 && $index < $daysInMonth) {
                $values[$index] = $data->activeUsers;
            }
        }
        // Zusammenbauen der Daten für das Barchart
        $dailyActiveUsers = [
            [
                'labels' => $labelsForCurrentMonth,
                'name'   => 'Daily Users',
                'values' => $activeUsersPerDay,
            ]
        ];

        //Log::info($dailyActiveUsers);


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

        //$providerData = [
        //        [
        //            'labels' => $providers,
        //            'name'   => 'Requests per Provider',
        //            'values' => $totalRequestsForProvider,
        //        ]
        //];        

        
        // Neues ProviderData Array basierend auf der auskommentierten Struktur
        $providerData = [
            [
                'labels' => array_keys($providerSummary),
                'name'   => 'Requests per Provider',
                'values' => array_values($providerSummary),
            ]
        ];
        Log::info('Provider request summary: ' . json_encode($providerData));

        
        $specificModel = 'gpt-4o-mini';
        $countForSpecificDay = DB::table('usage_records')
                                ->where('model', $specificModel)
                                ->whereDate('created_at', $specificDay)
                                ->count();
        Log::info("Aufrufe von Model {$specificModel} am {$specificDay}: " . $countForSpecificDay);

    // Neue Abfrage: Anzahl der Aufrufe eines spezifischen Models im gesamten Monat
        $specificYear = '2025';
        $specificMonth = '03';
        $countForSpecificMonth = DB::table('usage_records')
                                   ->where('model', $specificModel)
                                   ->whereYear('created_at', $specificYear)
                                   ->whereMonth('created_at', $specificMonth)
                                   ->count();
        Log::info("Aufrufe von Model {$specificModel} im {$specificYear}-{$specificMonth}: " . $countForSpecificMonth);

        Log::info('Total requests: ' . $totalRequests);
        Log::info('Requests OpenAI: ' . $openAiRequests->count());
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
            $hourIndex = (int)$data->hour;
            $requestsPerHourArray[$hourIndex] = $data->count;
        }

        $requestsPerModel = [
            [
                'labels' => array_keys($modelSummary),
                'name'   => 'Requests per Provider',
                'values' => array_values($modelSummary),
            ]
        ];
        // Aktualisierung der Requests per Hour Chart mit dem neuen Array
        $requestsPerHour = [
                [
                    'labels' => $hourLabels,
                    'name'   => 'Requests per Hour',
                    'values' => $requestsPerHourArray,
                ]
            ];    
        //Log::info('Total models: ' . count($allModels));
    
        return [
            'dailyActiveUsers' => $dailyActiveUsers,
            'requestsPerProvider' => $providerData,
            'requestsPerHour' => $requestsPerHour,
            'requestsPerModel' => $requestsPerModel,

            'metrics' => [
                'totalUsers'=> number_format($totalUsers),
                'newUsers'     => ['value' => number_format($newUsersThisMonth), 'diff' => $percentage],
                'activeUsersDelta'  => number_format($activeUsersDelta),
                'activeUsersToday'  => ['value' => number_format($activeUsersToday ? $activeUsersToday->activeUsers : 0), 'diff' => $activeUsersDeltaDiff],
            ],
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
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

        // Entferne den Layout::view() Aufruf für $dailyusersChart
        return [
            Layout::metrics([
                'Total Requests'    => 'metrics.totalUsers',
                'Requests this Month' => 'metrics.newUsers',
                'Average Daily Requests' => 'metrics.activeUsersDelta',
                'Requests Today' => 'metrics.activeUsersToday',
                
            ]),    

            Layout::columns([
                    //Layout::component(UserData::class),
                    $requestsPerHourChart,
                ]),
    

            Layout::split([
                $requestsProviderPieChart,
                $requestsModelPieChart,
            ])->ratio('50/50'),
        ];
    }
}
