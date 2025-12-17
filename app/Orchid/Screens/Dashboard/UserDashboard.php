<?php

namespace App\Orchid\Screens\Dashboard;

use App\Models\UsageUsersDaily;
use App\Orchid\Layouts\Charts\LineChart;
use App\Orchid\Layouts\Charts\PercentageChart;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Orchid\Platform\Models\Role;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class UserDashboard extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Request $request): iterable
    {
        // Überprüfen, ob die benötigten Tabellen existieren
        $usersExists = Schema::hasTable('users');
        $usageRecordsExists = Schema::hasTable('usage_records');

        // Überprüfen, ob Daten in den Tabellen vorhanden sind
        $hasUsers = false;
        $hasUsageRecords = false;

        if ($usersExists) {
            $hasUsers = DB::table('users')->exists();
        }

        if ($usageRecordsExists) {
            $hasUsageRecords = DB::table('usage_records')->exists();
        }

        // Wenn die benötigten Tabellen nicht existieren oder leer sind, zeige Platzhalter
        if (! $usersExists || ! $hasUsers || ! $usageRecordsExists || ! $hasUsageRecords) {
            return $this->getPlaceholderData();
        }

        // Labels
        // Dynamisch erstellte Labels für den aktuell ausgewählten Monat mit Carbon
        $now = Carbon::now();

        // Hole den ausgewählten Monat aus dem Request (Format: Y-m, z.B. "2025-12")
        $selectedMonth = $request->input('monthly_date', $now->format('Y-m'));
        // Fallback auf aktuellen Monat, wenn leer oder ungültig
        if (empty($selectedMonth) || ! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = $now->format('Y-m');
        }
        $monthDate = Carbon::createFromFormat('Y-m', $selectedMonth);
        $monthName = $monthDate->format('F Y');

        $currentYear = $monthDate->year;
        $currentMonth = $monthDate->month;
        $currentDay = $now->day;

        $daysInMonth = $monthDate->daysInMonth;
        $labelsForCurrentMonth = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labelsForCurrentMonth[] = Carbon::create($currentYear, $currentMonth, $d)->format('Y-m-d');
        }

        // Statische Labels für einen 24h-Stunden Tag, beginnend bei 4 Uhr
        $hourLabels = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = ($i + 4) % 24;
            $hourLabels[] = sprintf('%02d:00', $hour);
        }

        // User Statistics - basierend auf dem ausgewählten Monat
        // Total Users bis zum Ende des ausgewählten Monats
        $endOfSelectedMonth = Carbon::createFromFormat('Y-m', $selectedMonth)->endOfMonth();
        $totalUsers = DB::table('users')
            ->where('created_at', '<=', $endOfSelectedMonth)
            ->count();

        // Total Users bis zum Ende des Vormonats (für Wachstumsberechnung)
        $endOfPreviousMonth = $monthDate->copy()->subMonth()->endOfMonth();
        $totalUsersPreviousMonth = DB::table('users')
            ->where('created_at', '<=', $endOfPreviousMonth)
            ->count();

        // Berechne prozentuales Wachstum zum Vormonat
        $totalUsersGrowth = ($totalUsersPreviousMonth > 0)
            ? round((($totalUsers - $totalUsersPreviousMonth) / $totalUsersPreviousMonth) * 100, 2)
            : 0;

        // Aktive User pro Tag für den ganzen Monat - aus usage_users_daily Tabelle
        // Prüfen, ob die Tabelle existiert
        $usageUsersDailyExists = Schema::hasTable('usage_users_daily');

        if ($usageUsersDailyExists) {
            // Verwende die Model-Methode für bessere Kapselung
            $activeUsersPerDay = UsageUsersDaily::getDistinctUsersByDay($currentYear, $currentMonth, $daysInMonth);

            // Erstelle $dailyData für Kompatibilität mit bestehenden Berechnungen
            $dailyData = collect();
            foreach ($activeUsersPerDay as $day => $count) {
                $dailyData->push((object) ['day' => $day + 1, 'activeUsers' => $count]);
            }
        } else {
            // Fallback auf usage_records, falls usage_users_daily nicht existiert
            $dailyData = DB::table('usage_records')
                ->select(DB::raw('DAY(created_at) as day'), DB::raw('COUNT(DISTINCT user_id) as activeUsers'))
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
        }

        // Berechne den Durchschnitt der aktiven Nutzer
        // Für laufenden Monat: nur bisherige Tage | Für vergangene Monate: alle Tage
        $isCurrentMonth = $currentYear === $now->year && $currentMonth === $now->month;

        if ($isCurrentMonth) {
            // Laufender Monat: Durchschnitt nur über bisherige Tage (bis heute)
            $daysToCalculate = $currentDay;
            $sumUpToToday = array_sum(array_slice($activeUsersPerDay, 0, $currentDay));
            $activeUsersDelta = round($sumUpToToday / $daysToCalculate, 2);
        } else {
            // Vergangener Monat: Durchschnitt über alle Tage des Monats
            $activeUsersDelta = round(array_sum($activeUsersPerDay) / $daysInMonth, 2);
        }

        // Berechne Average Daily Active Users für den Vormonat
        $previousMonth = $monthDate->copy()->subMonth();
        $previousMonthAvgUsers = 0;

        if ($usageUsersDailyExists) {
            $previousMonthData = UsageUsersDaily::getDistinctUsersByDay(
                $previousMonth->year,
                $previousMonth->month,
                $previousMonth->daysInMonth
            );
            $previousMonthAvgUsers = round(array_sum($previousMonthData) / $previousMonth->daysInMonth, 2);
        } else {
            $previousMonthQuery = DB::table('usage_records')
                ->select(DB::raw('DAY(created_at) as day'), DB::raw('COUNT(DISTINCT user_id) as activeUsers'))
                ->whereYear('created_at', $previousMonth->year)
                ->whereMonth('created_at', $previousMonth->month)
                ->groupBy('day')
                ->get();

            if ($previousMonthQuery->isNotEmpty()) {
                $previousMonthAvgUsers = round($previousMonthQuery->avg('activeUsers'), 2);
            }
        }

        // Berechne prozentuale Abweichung zum Vormonat
        $activeUsersDeltaDiff = ($previousMonthAvgUsers > 0)
            ? round((($activeUsersDelta - $previousMonthAvgUsers) / $previousMonthAvgUsers) * 100, 2)
            : 0;

        // Berechne Max Users für den ausgewählten Monat
        $maxUsers = max($activeUsersPerDay);
        $previousMonthMaxUsers = 0;

        if ($usageUsersDailyExists) {
            $previousMonthData = UsageUsersDaily::getDistinctUsersByDay(
                $previousMonth->year,
                $previousMonth->month,
                $previousMonth->daysInMonth
            );
            $previousMonthMaxUsers = max($previousMonthData);
        } else {
            $previousMonthQuery = DB::table('usage_records')
                ->select(DB::raw('DAY(created_at) as day'), DB::raw('COUNT(DISTINCT user_id) as activeUsers'))
                ->whereYear('created_at', $previousMonth->year)
                ->whereMonth('created_at', $previousMonth->month)
                ->groupBy('day')
                ->get();

            if ($previousMonthQuery->isNotEmpty()) {
                $previousMonthMaxUsers = $previousMonthQuery->max('activeUsers');
            }
        }

        // Berechne prozentuale Abweichung zum Vormonat
        $maxUsersDiff = ($previousMonthMaxUsers > 0)
            ? round((($maxUsers - $previousMonthMaxUsers) / $previousMonthMaxUsers) * 100, 2)
            : 0;

        // =====================================================================
        // NEW vs RECURRING USERS LOGIC
        // =====================================================================
        // Hole alle aktiven User im aktuellen Monat
        $activeUserIds = [];
        if ($usageUsersDailyExists) {
            $activeUserIds = DB::table('usage_users_daily')
                ->whereYear('date', $currentYear)
                ->whereMonth('date', $currentMonth)
                ->distinct()
                ->pluck('user_id')
                ->toArray();
        } else {
            $activeUserIds = DB::table('usage_records')
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->distinct()
                ->pluck('user_id')
                ->toArray();
        }

        // Von den aktiven Usern: wie viele wurden im gleichen Monat erstellt?
        $newUsersThisMonth = 0;
        if (! empty($activeUserIds)) {
            $newUsersThisMonth = DB::table('users')
                ->whereIn('id', $activeUserIds)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->count();
        }

        // Die restlichen aktiven User sind recurring
        $totalActiveUsersThisMonth = count($activeUserIds);
        $recurringUsers = $totalActiveUsersThisMonth - $newUsersThisMonth;

        // Berechne Prozentsatz für Metrics
        $percentage = ($totalActiveUsersThisMonth > 0)
            ? round(($newUsersThisMonth / $totalActiveUsersThisMonth) * 100, 2)
            : 0;

        // =====================================================================
        // PREVIOUS MONTH CALCULATIONS FOR NEW METRICS
        // =====================================================================
        // Hole alle aktiven User im Vormonat
        $previousMonthUserIds = [];
        if ($usageUsersDailyExists) {
            $previousMonthUserIds = DB::table('usage_users_daily')
                ->whereYear('date', $previousMonth->year)
                ->whereMonth('date', $previousMonth->month)
                ->distinct()
                ->pluck('user_id')
                ->toArray();
        } else {
            $previousMonthUserIds = DB::table('usage_records')
                ->whereYear('created_at', $previousMonth->year)
                ->whereMonth('created_at', $previousMonth->month)
                ->distinct()
                ->pluck('user_id')
                ->toArray();
        }

        $totalActiveUsersPreviousMonth = count($previousMonthUserIds);

        // Von den aktiven Usern im Vormonat: wie viele wurden im gleichen Monat erstellt?
        $newUsersPreviousMonth = 0;
        if (! empty($previousMonthUserIds)) {
            $newUsersPreviousMonth = DB::table('users')
                ->whereIn('id', $previousMonthUserIds)
                ->whereYear('created_at', $previousMonth->year)
                ->whereMonth('created_at', $previousMonth->month)
                ->count();
        }

        $recurringUsersPreviousMonth = $totalActiveUsersPreviousMonth - $newUsersPreviousMonth;

        // Berechne prozentuale Abweichungen zum Vormonat
        $totalActiveUsersDiff = ($totalActiveUsersPreviousMonth > 0)
            ? round((($totalActiveUsersThisMonth - $totalActiveUsersPreviousMonth) / $totalActiveUsersPreviousMonth) * 100, 2)
            : 0;

        $recurringUsersDiff = ($recurringUsersPreviousMonth > 0)
            ? round((($recurringUsers - $recurringUsersPreviousMonth) / $recurringUsersPreviousMonth) * 100, 2)
            : 0;

        $newUsersDiff = ($newUsersPreviousMonth > 0)
            ? round((($newUsersThisMonth - $newUsersPreviousMonth) / $newUsersPreviousMonth) * 100, 2)
            : 0;

        // Users per Role Metrics (für Custom Metrics Element)
        $roles = Role::with('users')->get();
        $roleMetrics = [];

        // Berechne User ohne Rolle
        $usersWithoutRoleCurrent = DB::table('users')
            ->whereNotIn('id', function ($query) {
                $query->select('user_id')
                    ->from('role_users');
            })
            ->where('created_at', '<=', $endOfSelectedMonth)
            ->count();

        $usersWithoutRolePreviousMonth = DB::table('users')
            ->whereNotIn('id', function ($query) {
                $query->select('user_id')
                    ->from('role_users');
            })
            ->where('created_at', '<=', $endOfPreviousMonth)
            ->count();

        // Neue User ohne Rolle diesen Monat
        $newUsersWithoutRoleThisMonth = DB::table('users')
            ->whereNotIn('id', function ($query) {
                $query->select('user_id')
                    ->from('role_users');
            })
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        if ($usersWithoutRoleCurrent > 0 || $usersWithoutRolePreviousMonth > 0) {
            $percentageOfTotal = $totalUsers > 0 ? round(($usersWithoutRoleCurrent / $totalUsers) * 100, 1) : 0;
            $growthRate = $usersWithoutRolePreviousMonth > 0
                ? round((($usersWithoutRoleCurrent - $usersWithoutRolePreviousMonth) / $usersWithoutRolePreviousMonth) * 100, 2)
                : 0;

            $roleMetrics['No Role'] = [
                'totalCount' => $usersWithoutRoleCurrent,
                'totalPercentage' => $percentageOfTotal,
                'newThisMonth' => $newUsersWithoutRoleThisMonth,
                'growthRate' => $growthRate,
            ];
        }

        // Berechne Metriken pro Rolle
        foreach ($roles as $role) {
            $userCountCurrent = $role->users()
                ->where('created_at', '<=', $endOfSelectedMonth)
                ->count();

            $userCountPreviousMonth = $role->users()
                ->where('created_at', '<=', $endOfPreviousMonth)
                ->count();

            // Neue User in dieser Rolle diesen Monat
            $newRoleUsersThisMonth = $role->users()
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $currentMonth)
                ->count();

            // Nur Rollen mit aktuellen oder vorherigen Usern anzeigen
            if ($userCountCurrent > 0 || $userCountPreviousMonth > 0) {
                $percentageOfTotal = $totalUsers > 0 ? round(($userCountCurrent / $totalUsers) * 100, 1) : 0;
                $growthRate = $userCountPreviousMonth > 0
                    ? round((($userCountCurrent - $userCountPreviousMonth) / $userCountPreviousMonth) * 100, 2)
                    : 0;

                $roleMetrics[$role->name] = [
                    'totalCount' => $userCountCurrent,
                    'totalPercentage' => $percentageOfTotal,
                    'newThisMonth' => $newRoleUsersThisMonth,
                    'growthRate' => $growthRate,
                ];
            }
        }

        // =====================================================================
        // ROLE FILTER SETUP
        // =====================================================================

        // Sammle alle verfügbaren Rollen
        $roles = Role::all();
        $allRoles = [];
        foreach ($roles as $role) {
            // Filtere Administrator und Gast aus
            if (! in_array(strtolower($role->name), ['administrator', 'gast'])) {
                $allRoles[] = $role->name;
            }
        }
        sort($allRoles);

        // Füge "Total" als erste Option hinzu
        $availableRolesWithTotal = array_merge(['Total'], $allRoles);

        // Hole ausgewählte Rollen aus Query Parameters
        $selectedRoles = $request->input('roles', []);

        // Wenn keine Rollen ausgewählt sind, zeige alle an (inkl. Total)
        if (empty($selectedRoles)) {
            $selectedRoles = $availableRolesWithTotal;
        } else {
            // Stelle sicher, dass selectedRoles ein Array ist
            $selectedRoles = is_array($selectedRoles) ? $selectedRoles : [$selectedRoles];
        }

        // =====================================================================
        // Users per Role über den ausgewählten Monat (für Line Chart)
        // =====================================================================
        $roleLabels = [];
        $roleData = [];

        // Bestimme bis zu welchem Tag Daten gezeigt werden sollen
        // Für laufenden Monat: nur bis heute | Für vergangene Monate: alle Tage
        $isCurrentMonth = $currentYear === $now->year && $currentMonth === $now->month;
        $daysToShow = $isCurrentMonth ? $currentDay : $daysInMonth;

        // Erstelle Tages-Labels für die verfügbaren Daten
        for ($d = 1; $d <= $daysToShow; $d++) {
            $roleLabels[] = Carbon::create($currentYear, $currentMonth, $d)->format('M d');
        }

        // Berechne Daten pro Rolle (für Filterung)
        $rolesByDay = [];
        foreach ($roles as $role) {
            // Filtere Administrator und Gast aus
            if (in_array(strtolower($role->name), ['administrator', 'gast'])) {
                continue;
            }

            $roleValues = [];
            for ($d = 1; $d <= $daysToShow; $d++) {
                $targetDate = Carbon::create($currentYear, $currentMonth, $d)->endOfDay();
                $count = $role->users()
                    ->where('created_at', '<=', $targetDate)
                    ->count();
                $roleValues[] = $count;
            }

            $rolesByDay[$role->name] = $roleValues;
        }

        // Erstelle Datenreihen nur für ausgewählte Rollen
        $totalUsersArray = array_fill(0, $daysToShow, 0);
        $showTotal = in_array('Total', $selectedRoles);

        // Berechne Total-Linie über ALLE Rollen (nicht nur ausgewählte)
        if ($showTotal) {
            foreach ($allRoles as $roleName) {
                if (isset($rolesByDay[$roleName])) {
                    for ($i = 0; $i < $daysToShow; $i++) {
                        $totalUsersArray[$i] += $rolesByDay[$roleName][$i];
                    }
                }
            }
        }

        // Füge einzelne Rollen-Linien hinzu
        foreach ($allRoles as $roleName) {
            if (in_array($roleName, $selectedRoles) && isset($rolesByDay[$roleName])) {
                if (array_sum($rolesByDay[$roleName]) > 0) {
                    $roleData[] = [
                        'name' => $roleName,
                        'values' => $rolesByDay[$roleName],
                        'labels' => $roleLabels,
                    ];
                }
            }
        }

        // Füge die kumulierte Gesamt-Linie hinzu, wenn "Total" ausgewählt ist
        if ($showTotal && ! empty($allRoles)) {
            $roleData[] = [
                'name' => 'Total',
                'values' => $totalUsersArray,
                'labels' => $roleLabels,
            ];
        }

        $usersPerRole = $roleData;

        // =====================================================================
        // Active Users pro Tag nach Rolle (Merged Chart)
        // =====================================================================
        $activeUsersByRole = [];
        $activeRolesByDay = [];

        // Für jede Rolle: Zähle aktive User pro Tag
        foreach ($roles as $role) {
            // Filtere Administrator und Gast aus
            if (in_array(strtolower($role->name), ['administrator', 'gast'])) {
                continue;
            }
            $dailyActiveByRole = array_fill(0, $daysInMonth, 0);

            if ($usageUsersDailyExists) {
                // Nutze usage_users_daily mit Join auf role_users
                $dailyRoleData = DB::table('usage_users_daily')
                    ->join('role_users', 'usage_users_daily.user_id', '=', 'role_users.user_id')
                    ->select(DB::raw('DAY(usage_users_daily.date) as day'), DB::raw('COUNT(DISTINCT usage_users_daily.user_id) as count'))
                    ->whereYear('usage_users_daily.date', $currentYear)
                    ->whereMonth('usage_users_daily.date', $currentMonth)
                    ->where('role_users.role_id', $role->id)
                    ->groupBy('day')
                    ->get();

                foreach ($dailyRoleData as $data) {
                    $index = (int) $data->day - 1;
                    if ($index >= 0 && $index < $daysInMonth) {
                        $dailyActiveByRole[$index] = (int) $data->count;
                    }
                }
            } else {
                // Fallback auf usage_records
                $dailyRoleData = DB::table('usage_records')
                    ->join('role_users', 'usage_records.user_id', '=', 'role_users.user_id')
                    ->select(DB::raw('DAY(usage_records.created_at) as day'), DB::raw('COUNT(DISTINCT usage_records.user_id) as count'))
                    ->whereYear('usage_records.created_at', $currentYear)
                    ->whereMonth('usage_records.created_at', $currentMonth)
                    ->where('role_users.role_id', $role->id)
                    ->groupBy('day')
                    ->get();

                foreach ($dailyRoleData as $data) {
                    $index = (int) $data->day - 1;
                    if ($index >= 0 && $index < $daysInMonth) {
                        $dailyActiveByRole[$index] = (int) $data->count;
                    }
                }
            }

            $activeRolesByDay[$role->name] = $dailyActiveByRole;
        }

        // Erstelle Datenreihen nur für ausgewählte Rollen (basierend auf Filter)
        $totalActiveUsersArray = array_fill(0, $daysInMonth, 0);
        $showTotalActive = in_array('Total', $selectedRoles);

        // Berechne Total-Linie über ALLE Rollen (nicht nur ausgewählte)
        if ($showTotalActive) {
            foreach ($allRoles as $roleName) {
                if (isset($activeRolesByDay[$roleName])) {
                    for ($i = 0; $i < $daysInMonth; $i++) {
                        $totalActiveUsersArray[$i] += $activeRolesByDay[$roleName][$i];
                    }
                }
            }
        }

        // Füge einzelne Rollen-Linien hinzu
        foreach ($allRoles as $roleName) {
            if (in_array($roleName, $selectedRoles) && isset($activeRolesByDay[$roleName])) {
                if (array_sum($activeRolesByDay[$roleName]) > 0) {
                    $activeUsersByRole[] = [
                        'labels' => $labelsForCurrentMonth,
                        'name' => $roleName,
                        'values' => $activeRolesByDay[$roleName],
                    ];
                }
            }
        }

        // Füge die kumulierte Gesamt-Linie hinzu, wenn "Total" ausgewählt ist
        if ($showTotalActive && ! empty($allRoles)) {
            $activeUsersByRole[] = [
                'labels' => $labelsForCurrentMonth,
                'name' => 'Total',
                'values' => $totalActiveUsersArray,
            ];
        }

        // Abfrage der durchschnittlichen User pro Stunde für den ausgewählten Monat
        // Mit Min/Max Werten für Stacked Bar Chart
        $hourlyStats = DB::table('usage_records')
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(DISTINCT user_id) as user_count')
            )
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->groupBy('date', 'hour')
            ->get()
            ->groupBy('hour');

        // Berechne Durchschnitt, Min und Max für jede Stunde
        $avgPerHour = array_fill(0, 24, 0);
        $minPerHour = array_fill(0, 24, 0);
        $maxPerHour = array_fill(0, 24, 0);

        foreach ($hourlyStats as $hour => $records) {
            $counts = $records->pluck('user_count')->toArray();
            $hourIndex = (int) $hour;

            if ($hourIndex >= 0 && $hourIndex < 24 && count($counts) > 0) {
                $avgPerHour[$hourIndex] = round(array_sum($counts) / count($counts), 1);
                $minPerHour[$hourIndex] = min($counts);
                $maxPerHour[$hourIndex] = max($counts);
            }
        }

        // Ordne die Arrays neu, um bei Stunde 4 (04:00) zu beginnen
        $reorderedMin = array_merge(array_slice($minPerHour, 4), array_slice($minPerHour, 0, 4));
        $reorderedAvg = array_merge(array_slice($avgPerHour, 4), array_slice($avgPerHour, 0, 4));
        $reorderedMax = array_merge(array_slice($maxPerHour, 4), array_slice($maxPerHour, 0, 4));

        // Berechne Average und Max concurrent users für Metrics
        $avgConcurrentUsers = count($reorderedAvg) > 0 ? round(array_sum($reorderedAvg) / count($reorderedAvg), 1) : 0;
        $maxConcurrentUsers = count($reorderedMax) > 0 ? max($reorderedMax) : 0;

        // Berechne die gleichen Werte für den Vormonat
        $previousMonthHourlyStats = DB::table('usage_records')
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(DISTINCT user_id) as user_count')
            )
            ->whereYear('created_at', $previousMonth->year)
            ->whereMonth('created_at', $previousMonth->month)
            ->groupBy('hour', 'date')
            ->get()
            ->groupBy('hour');

        $prevAvgPerHour = array_fill(0, 24, 0);
        $prevMaxPerHour = array_fill(0, 24, 0);

        foreach ($previousMonthHourlyStats as $hour => $records) {
            $counts = $records->pluck('user_count')->toArray();
            $hourIndex = (int) $hour;

            if ($hourIndex >= 0 && $hourIndex < 24 && count($counts) > 0) {
                $prevAvgPerHour[$hourIndex] = round(array_sum($counts) / count($counts), 1);
                $prevMaxPerHour[$hourIndex] = max($counts);
            }
        }

        $prevAvgConcurrentUsers = count($prevAvgPerHour) > 0 ? round(array_sum($prevAvgPerHour) / count($prevAvgPerHour), 1) : 0;
        $prevMaxConcurrentUsers = count($prevMaxPerHour) > 0 ? max($prevMaxPerHour) : 0;

        // Berechne prozentuale Abweichungen
        $avgConcurrentUsersDiff = ($prevAvgConcurrentUsers > 0)
            ? round((($avgConcurrentUsers - $prevAvgConcurrentUsers) / $prevAvgConcurrentUsers) * 100, 2)
            : 0;

        $maxConcurrentUsersDiff = ($prevMaxConcurrentUsers > 0)
            ? round((($maxConcurrentUsers - $prevMaxConcurrentUsers) / $prevMaxConcurrentUsers) * 100, 2)
            : 0;

        // Line Chart mit absoluten Werten für Min, Average und Max
        $usersPerHour = [
            [
                'labels' => $hourLabels,
                'name' => 'Minimum',
                'values' => $reorderedMin,
            ],
            [
                'labels' => $hourLabels,
                'name' => 'Average',
                'values' => $reorderedAvg,
            ],
            [
                'labels' => $hourLabels,
                'name' => 'Maximum',
                'values' => $reorderedMax,
            ],
        ];

        return [
            'activeUsersByRole' => $activeUsersByRole,
            'usersPerHour' => $usersPerHour,
            'usersPerRole' => $usersPerRole,
            'roleMetrics' => $roleMetrics,
            'selectedMonth' => $selectedMonth,
            'monthName' => $monthName,
            'availableRoles' => $availableRolesWithTotal,
            'selectedRoles' => $selectedRoles,
            'metrics' => [
                'totalUsers' => ['value' => number_format($totalUsers), 'diff' => $totalUsersGrowth],
                'newUsers' => ['value' => number_format($newUsersThisMonth), 'diff' => $percentage],
                'activeUsersDelta' => ['value' => number_format($activeUsersDelta), 'diff' => $activeUsersDeltaDiff],
                'maxUsers' => ['value' => number_format($maxUsers), 'diff' => $maxUsersDiff],
                'distinctActiveUsers' => ['value' => number_format($totalActiveUsersThisMonth), 'diff' => $totalActiveUsersDiff],
                'recurringActiveUsers' => ['value' => number_format($recurringUsers), 'diff' => $recurringUsersDiff],
                'avgConcurrentUsers' => ['value' => number_format($avgConcurrentUsers, 1), 'diff' => $avgConcurrentUsersDiff],
                'maxConcurrentUsers' => ['value' => number_format($maxConcurrentUsers), 'diff' => $maxConcurrentUsersDiff],
            ],
            'percentageChart' => [
                [
                    'labels' => ['Recurring Users', 'New Users'],
                    'name' => 'Recurring vs New Users',
                    'values' => [$recurringUsers, $newUsersThisMonth],
                ],
            ],
            // Füge leere Chat-Counts hinzu
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
        // Aktuelle Zeiträume für Labels mit Carbon
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;
        $daysInMonth = $now->daysInMonth;

        $labelsForCurrentMonth = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labelsForCurrentMonth[] = Carbon::create($currentYear, $currentMonth, $d)->format('Y-m-d');
        }

        // Statische Labels für 24h-Tag
        $hourLabels = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourLabels[] = sprintf('%02d:00', $hour);
        }

        // Platzhalterwerte für Charts
        $placeholderDailyUsers = array_fill(0, $daysInMonth, 0);
        $placeholderHourlyRequests = array_fill(0, 24, 0);

        return [
            'dailyActiveUsers' => [
                [
                    'labels' => $labelsForCurrentMonth,
                    'name' => 'Daily Users',
                    'values' => $placeholderDailyUsers,
                ],
            ],
            'activeUsersByRole' => [
                [
                    'labels' => $labelsForCurrentMonth,
                    'name' => 'No Role',
                    'values' => $placeholderDailyUsers,
                ],
            ],
            'usersPerHour' => [
                [
                    'labels' => $hourLabels,
                    'name' => 'Users per Hour',
                    'values' => $placeholderHourlyRequests,
                ],
            ],
            'usersPerRole' => [
                [
                    'labels' => [],
                    'name' => 'No Role',
                    'values' => [],
                ],
            ],
            'roleMetrics' => [],
            'percentageChart' => [
                [
                    'labels' => ['Recurring Users', 'New Users'],
                    'name' => 'Recurring vs New Users',
                    'values' => [0, 0],
                ],
            ],
            'selectedMonth' => Carbon::now()->format('Y-m'),
            'monthName' => $now->format('F Y'),
            'metrics' => [
                'totalUsers' => ['value' => '0', 'diff' => 0],
                'newUsers' => ['value' => '0', 'diff' => 0],
                'activeUsersDelta' => ['value' => '0', 'diff' => 0],
                'maxUsers' => ['value' => '0', 'diff' => 0],
            ],
            // Chat-Count Platzhalter
            'chatCountToday' => 0,
            'chatCountWeek' => 0,
            'chatCountMonth' => 0,
            'chatCountTotal' => 0,
        ];
    }

    /**
     * Gibt die Anzahl der Chats für einen bestimmten Zeitraum zurück
     *
     * @param  string  $period  Der Zeitraum ('today', 'week', 'month', 'total')
     * @return int Die Anzahl der Chats
     */
    private function getChatCount(string $period): int
    {
        // Prüfen, ob die Tabelle 'conversations' existiert
        if (! Schema::hasTable('conversations')) {
            return 0;
        }

        $query = DB::table('conversations');

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'week':
                $query->whereBetween('created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]);
                break;
            case 'month':
                $query->whereYear('created_at', Carbon::now()->year)
                    ->whereMonth('created_at', Carbon::now()->month);
                break;
                // 'total' benötigt keine zusätzlichen Filter
        }

        try {
            return $query->count();
        } catch (\Exception $e) {
            Log::error('Error counting conversations: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * The name of the screen displayed in the header.
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
        $selectedMonth = request('monthly_date', Carbon::now()->format('Y-m'));
        $monthName = Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y');

        $activeUsersByRoleChart = LineChart::make('activeUsersByRole', 'Active Users by Role')
            ->title('Daily Active Users (by Role)')
            ->description('Daily active users for '.$monthName.'. Shows total and per-role activity patterns. Use filter to select roles.');

        $usersPerHourChart = LineChart::make('usersPerHour', 'Users per hour')
            ->title('Hourly User Activity')
            ->description('Hourly user activity for '.$monthName.' showing minimum, average, and maximum values as separate lines.');

        $percentageChart = PercentageChart::make('percentageChart', 'Recurring vs New Users')
            ->title('Recurring vs New Users')
            ->description('The ratio of returning to new users in the selected month.');

        $usersPerRoleChart = LineChart::make('usersPerRole', 'Users per Role')
            ->title('Users per Role Over Time')
            ->description('Cumulative user growth per role for '.$monthName.'. Use filter to select roles.');

        return [
            Layout::split([
                Layout::view('orchid.partials.month-selector-empty'),
                Layout::view('orchid.partials.month-selector', [
                    'currentMonth' => request('monthly_date', Carbon::now()->format('Y-m')),
                    'route' => 'platform.dashboard.users',
                ]),
            ])->ratio('70/30'),

            Layout::metrics([
                'Total Users' => 'metrics.totalUsers',
                'New Users' => 'metrics.newUsers',
                'Average Active Users per Day' => 'metrics.activeUsersDelta',
                'Max Active Users per Day' => 'metrics.maxUsers',
            ]),
            Layout::metrics([
                'Distinct Active Users ' => 'metrics.distinctActiveUsers',
                'Recurring Active Users' => 'metrics.recurringActiveUsers',
                'Average Concurrent Users per hour' => 'metrics.avgConcurrentUsers',
                'Max Concurrent Users per hour' => 'metrics.maxConcurrentUsers',
            ]),

            Layout::columns([
                $percentageChart,
            ]),

            Layout::view('orchid.partials.role-filter'),

            Layout::columns([
                $activeUsersByRoleChart,
            ]),

            Layout::columns([
                $usersPerHourChart,
            ]),

            Layout::view('orchid.partials.role-metrics', [
                'monthName' => $monthName,
            ]),

            Layout::columns([
                $usersPerRoleChart,
            ]),
        ];
    }
}
