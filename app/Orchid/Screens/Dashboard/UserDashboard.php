<?php

namespace App\Orchid\Screens\Dashboard;

use Orchid\Screen\Screen;

use App\Orchid\Layouts\Charts\PieChart;
use App\Orchid\Layouts\Charts\BarChart;
use App\Orchid\Layouts\Charts\PercentageChart;

use Orchid\Screen\Fields\DateRange;

use Orchid\Support\Facades\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserDashboard extends Screen
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
        Log::info("Active Users per Day Array: " . json_encode($activeUsersPerDay));

        
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

        // Berechnung des Verhältnis von recurringUsers zu newUsers
        $recurringUsers = $totalUsers - $newUsersThisMonth;
        $recurringPercentage = ($newUsersThisMonth > 0) ? round(($recurringUsers / $newUsersThisMonth) * 100, 2) : 0;

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
        //$modelSummary = [];
        //foreach ($allModels as $model) {
        //    if (isset($model['id'])) {
        //        $count = DB::table('usage_records')
        //                   ->where('model', $model['id'])
        //                   ->count();
        //        $modelSummary[$model['id']] = $count;
        //    }
        //}

        
        //$specificModel = 'gpt-4o-mini';
        //$countForSpecificDay = DB::table('usage_records')
        //                        ->where('model', $specificModel)
        //                        ->whereDate('created_at', $specificDay)
        //                        ->count();
        //Log::info("Aufrufe von Model {$specificModel} am {$specificDay}: " . $countForSpecificDay);

    // Neue Abfrage: Anzahl der Aufrufe eines spezifischen Models im gesamten Monat
    //    $specificYear = '2025';
    //    $specificMonth = '03';
    //    $countForSpecificMonth = DB::table('usage_records')
    //                               ->where('model', $specificModel)
    //                               ->whereYear('created_at', $specificYear)
    //                               ->whereMonth('created_at', $specificMonth)
    //                               ->count();
    //    Log::info("Aufrufe von Model {$specificModel} im {$specificYear}-{$specificMonth}: " . $countForSpecificMonth);
    //
    //    Log::info('Total requests: ' . $totalRequests);
    //    Log::info('Requests OpenAI: ' . $openAiRequests->count());
    //    // Abfrage der Anzahl der Requests für den spezifischen Tag und Erstellen eines Arrays als Werte
    //    $requestsCountForSpecificDay = DB::table('usage_records')
    //                                    ->whereDate('created_at', $specificDay)
    //                                    ->count();
    //    $requestsPerDayArray = [$requestsCountForSpecificDay];

        // Neuer Code: Abfrage der Requests pro Stunde anhand der created_at-Spalte 
        // zur Ermittlung der distinct active Users pro Stunde
        $rawRequestsPerHour = DB::table('usage_records')
                                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(DISTINCT user_id) as count'))
                                ->whereDate('created_at', $specificDay)
                                ->groupBy('hour')
                                ->get();
        $requestsPerHourArray = array_fill(0, 24, 0);
        foreach ($rawRequestsPerHour as $data) {
            $hourIndex = (int)$data->hour;
            $requestsPerHourArray[$hourIndex] = $data->count;
        }
        
        $usersPerHour = [
                [
                    'labels' => $hourLabels,
                    'name'   => 'Users per Hour',
                    'values' => $requestsPerHourArray,
                ]
            ];    
        Log::info('Users per Hour today: ' . json_encode($usersPerHour));
    
        return [
            'dailyActiveUsers' => $dailyActiveUsers,
            'usersPerHour' => $usersPerHour,
            'metrics' => [
                'totalUsers'=> number_format($totalUsers),
                'newUsers'     => ['value' => number_format($newUsersThisMonth), 'diff' => $percentage],
                'activeUsersDelta'  => number_format($activeUsersDelta),
                'activeUsersToday'  => ['value' => number_format($activeUsersToday ? $activeUsersToday->activeUsers : 0), 'diff' => $activeUsersDeltaDiff],
            ],
            'percentageChart' => [
                [
                    'labels' => ['Recurring Users', 'New Users'],
                    'name'   => 'Recurring vs New Users',
                    'values' => [$recurringUsers, $newUsersThisMonth]
                ]
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
        return 'User Dashboard';
    }
    /**
     * Display header description.
     */
     public function description(): ?string
    {
        return 'Overview of user metrics for HAWKI';
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
        $dailyUsersChart = BarChart::make('dailyActiveUsers', 'Daily Users')
                ->title('Daily Active Users')
                ->description('Overview of users per day, that interacted with an AI model.');

        $usersPerHourChart = BarChart::make('usersPerHour', 'Users per hour')
                ->title('Users per Hour')
                ->description('Overview of active users per hour.');        

        $percentageChart = PercentageChart::make('percentageChart', 'Recurring vs New Users')
            ->title('Recurring vs New Users')
            ->description('Das Verhältnis von wiederkehrenden Nutzern zu neuen Nutzern (im ausgewählten Monat).');

        // Entferne den Layout::view() Aufruf für $dailyusersChart
        return [
            Layout::metrics([
                'Total Users'    => 'metrics.totalUsers',
                'New Users this Month' => 'metrics.newUsers',
                'Average Daily Active Users' => 'metrics.activeUsersDelta',
                'Active Today' => 'metrics.activeUsersToday',
                
            ]),    
            
            //Layout::rows([
            //    DateRange::make('rangeDate')
            //        ->title('Range date'),
            //]),

            Layout::columns([
                $dailyUsersChart,
            ]),

            Layout::columns([
                    $usersPerHourChart,
                ]),

            Layout::columns([
                $percentageChart,
            ]),
        ];
    }
}
