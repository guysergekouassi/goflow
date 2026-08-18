<?php

namespace Modules\Time\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaiePeriode;
use App\Models\PaieExercice;
use App\Services\TimeTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Time\Models\TimeSheet;
use Modules\Employees\Models\Employee;
use Modules\Time\Models\Pointeuse;
use Modules\Settings\Models\WorkLocation;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceExport;

class TimeController extends Controller
{
    protected $timeTrackingService;

    public function __construct(TimeTrackingService $timeTrackingService)
    {
        $this->timeTrackingService = $timeTrackingService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $company_id = $user->company_id;

        $periode = null;
        if ($request->has("periode_id")) {
            $periode = PaiePeriode::with("exercice")->findOrFail(
                $request->periode_id,
            );
            // Récupérer le mois sélectionné ou le mois en cours
            $selectedMonth = $request->input('month', $periode->date_debut->format('Y-m'));
        } else {
            // Récupérer le mois sélectionné ou le mois en cours
            $selectedMonth = $request->input('month', date('Y-m'));
        }

        $exercices = PaieExercice::with("periodes")
            ->where("company_id", Auth::user()->company_id)
            ->orderBy("date_debut", "desc")
            ->get();

        // Récupération de l'année et du numéro de semaine (par défaut semaine courante)
        $year = $request->get('year', Carbon::now()->year);
        $week = $request->get('week', Carbon::now()->weekOfYear);

        // Création de la date à partir de l'année et du numéro de semaine
        $currentDate = Carbon::now();
        $currentDate->setISODate($year, $week);

        // Dates de début et fin de semaine
        $startDate2 = $currentDate->copy()->startOfWeek();
        $endDate2 = $currentDate->copy()->endOfWeek();

        // Récupérer tous les employés pour le filtre
        $allEmployees = Employee::where('company_id', Auth::user()->company_id)
            ->where('is_active', '!=', 2)
            ->with([
                'pointeuses' => function ($query) use ($startDate2, $endDate2) {
                    $query->whereBetween('auth_date', [
                        $startDate2->format('Y-m-d'),
                        $endDate2->format('Y-m-d')
                    ])
                        ->orderBy('auth_time');
                }
            ])
            ->get();

        // Déterminer le type de période
        $periodType = $request->get('period_type', 'week');
        $employeeId = $request->get('employee_id');

        // Variables pour les dates et navigation
        $days = collect();
        $employees = collect();
        $currentWeek = null;
        $previousWeek = null;
        $nextWeek = null;
        $previousYear = null;
        $nextYear = null;
        $startDate = null;
        $endDate = null;


        // Variables pour navigation mensuelle
        $previousMonth = null;
        $nextMonth = null;
        $previousMonthYear = null;
        $nextMonthYear = null;

        switch ($periodType) {
            case 'week':
                $this->handleWeekFilter($request, $days, $currentWeek, $previousWeek, $nextWeek, $previousYear, $nextYear, $startDate, $endDate);
                break;

            case 'month':
                $this->handleMonthFilter($request, $days, $startDate, $endDate, $previousMonth, $nextMonth, $previousMonthYear, $nextMonthYear);
                break;

            case 'day':
                $this->handleDayFilter($request, $days, $startDate, $endDate);
                break;

            case 'custom':
                $this->handleCustomFilter($request, $days, $startDate, $endDate);
                break;
        }

        $daysArray = $days->toArray();
        // Filtrer les employés
        $employeesQuery = Employee::query();

        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }

        $employees = $employeesQuery->with([
            'pointeuses' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('auth_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orderBy('auth_time');
            }
        ])->where('company_id', Auth::user()->company_id)->orderBy('name')->get();

        // Jours fériés chômés : affichés « Férié », aucune absence comptée.
        $holidayDates = \Modules\Time\Models\Holiday::datesBetween($company_id, $startDate, $endDate, false);

        // Jours fériés travaillés : l'entreprise reste ouverte, la journée est
        // un jour ouvré normal. Signalés comme tels, mais l'absence est comptée.
        $workedHolidayDates = \Modules\Time\Models\Holiday::datesBetween($company_id, $startDate, $endDate, true);

        // Jours de fermeture, calculés site par site : chaque succursale peut
        // avoir son propre rythme (cf. Branch::workingDays), et à défaut suit
        // celui de l'entreprise. Affichés « Fermé » plutôt qu'« Absent ».
        // Un férié déclaré travaillé rouvre la journée et prime sur la règle.
        $company = \App\Models\Company::find($company_id);

        $closedDatesByBranch = $this->closedDatesByBranch(
            $company_id,
            $company,
            $days,
            $workedHolidayDates
        );

        // Repli pour les employés sans site rattaché : le rythme de l'entreprise
        $closedDates = $closedDatesByBranch[0] ?? [];

        // En-tête de colonne : grisée uniquement si la journée est fermée pour
        // tous les sites. Avec des rythmes distincts, un samedi fermé au siège
        // peut être ouvert en succursale — le barrer serait trompeur.
        $closedForAllSites = $closedDatesByBranch === []
            ? []
            : array_values(array_intersect(...array_values($closedDatesByBranch)));

        // Absences déjà justifiées (congé approuvé, permission…) : indexées par
        // employé puis par date, pour afficher le motif à la place d'« Absent ».
        $justifiedByEmployee = [];

        $justified = TimeSheet::where('company_id', $company_id)
            ->where('motif_justify', 'Oui')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                      ->orWhereBetween('arrival_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          // Absence qui englobe toute la période affichée
                          $q->where('date', '<=', $startDate->format('Y-m-d'))
                            ->where('arrival_date', '>=', $endDate->format('Y-m-d'));
                      });
            })
            ->get();

        foreach ($justified as $sheet) {
            $from = Carbon::parse($sheet->date);
            $to   = $sheet->arrival_date ? Carbon::parse($sheet->arrival_date) : $from->copy();

            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $justifiedByEmployee[$sheet->employee_id][$day->format('Y-m-d')] = [
                    'motif'  => $sheet->type_permis ?: 'Absence justifiée',
                    'remark' => $sheet->remark,
                ];
            }
        }

        return view('time::qrcode-pointage.index', compact(
            'employees',
            'periode',
            'exercices',
            'allEmployees',
            'days',
            'daysArray',
            'holidayDates',
            'workedHolidayDates',
            'closedDates',
            'closedDatesByBranch',
            'closedForAllSites',
            'justifiedByEmployee',
            'currentWeek',
            'previousWeek',
            'nextWeek',
            'previousYear',
            'nextYear',
            'previousMonth',
            'nextMonth',
            'previousMonthYear',
            'nextMonthYear',
            'startDate',
            'endDate'
        ))->with('timeTrackingService', $this->timeTrackingService);
    }

    /**
     * Jours de fermeture, indexés par identifiant de site.
     *
     * Chaque succursale peut définir ses propres jours d'ouverture ; celles qui
     * n'en ont pas suivent le rythme de l'entreprise. La clé 0 porte ce rythme
     * d'entreprise, utilisée pour les employés sans site rattaché.
     *
     * Un jour férié déclaré travaillé rouvre la journée pour tout le monde et
     * n'est donc jamais compté comme fermé.
     *
     * @param  iterable  $days                Jours de la période (Carbon)
     * @param  array     $workedHolidayDates  Fériés travaillés, au format Y-m-d
     * @return array<int, array<int, string>>
     */
    private function closedDatesByBranch($companyId, $company, $days, array $workedHolidayDates): array
    {
        $branches = \App\Models\Branch::where('company_id', $companyId)->get();
        $closed   = [0 => []];

        foreach ($branches as $branch) {
            $closed[$branch->id] = [];
        }

        foreach ($days as $day) {
            $dayString = $day->format('Y-m-d');

            // Un férié travaillé prime : la journée est ouverte partout
            if (in_array($dayString, $workedHolidayDates, true)) {
                continue;
            }

            if ($company && !$company->worksOn($day)) {
                $closed[0][] = $dayString;
            }

            foreach ($branches as $branch) {
                if (!$branch->worksOn($day)) {
                    $closed[$branch->id][] = $dayString;
                }
            }
        }

        return $closed;
    }

    private function handleWeekFilter(Request $request, &$days, &$currentWeek, &$previousWeek, &$nextWeek, &$previousYear, &$nextYear, &$startDate, &$endDate)
    {
        $currentWeek = $request->get('week', date('W'));
        $currentYear = $request->get('year', date('Y'));

        // Calculer la date de début de la semaine
        $startDate = Carbon::now()->setISODate($currentYear, $currentWeek, 1);
        $endDate = $startDate->copy()->addDays(6);

        // Navigation
        $previousWeek = $currentWeek - 1;
        $nextWeek = $currentWeek + 1;
        $previousYear = $currentYear;
        $nextYear = $currentYear;

        if ($previousWeek < 1) {
            $previousWeek = 53;
            $previousYear = $currentYear - 1;
        }

        if ($nextWeek > 53) {
            $nextWeek = 1;
            $nextYear = $currentYear + 1;
        }

        // Générer tous les jours entre les deux dates
        $currentDay = $startDate->copy();
        while ($currentDay->lte($endDate)) {
            $days->push($currentDay->copy());
            $currentDay->addDay();
        }
    }

    public function export(Request $request)
    {
        // Récupérer les mêmes filtres que pour l'affichage
        $periodType = $request->get('period_type', 'week');
        $employeeId = $request->get('employee_id');

        // Variables pour les dates
        $days = collect();
        $startDate = null;
        $endDate = null;

        // Appliquer les mêmes filtres de date que dans l'index
        switch ($periodType) {
            case 'week':
                $currentWeek = $request->get('week', date('W'));
                $currentYear = $request->get('year', date('Y'));
                $startDate = Carbon::now()->setISODate($currentYear, $currentWeek, 1);
                $endDate = $startDate->copy()->addDays(6);
                for ($i = 0; $i < 7; $i++) {
                    $days->push($startDate->copy()->addDays($i));
                }
                break;

            case 'month':
                $currentMonth = $request->get('month', date('n'));
                $currentYear = $request->get('year', date('Y'));
                $startDate = Carbon::create($currentYear, $currentMonth, 1);
                $endDate = $startDate->copy()->endOfMonth();
                $currentDay = $startDate->copy();
                while ($currentDay->lte($endDate)) {
                    $days->push($currentDay->copy());
                    $currentDay->addDay();
                }
                break;

            case 'day':
                $specificDate = $request->get('specific_date', date('Y-m-d'));
                $startDate = Carbon::parse($specificDate);
                $endDate = $startDate->copy();
                $days->push($startDate->copy());
                break;

            case 'custom':
                $startDate = Carbon::parse($request->get('start_date', date('Y-m-d')));
                $endDate = Carbon::parse($request->get('end_date', date('Y-m-d')));
                $currentDay = $startDate->copy();
                while ($currentDay->lte($endDate)) {
                    $days->push($currentDay->copy());
                    $currentDay->addDay();
                }
                break;
        }
        // Filtrer les employés
        $employeesQuery = Employee::query();

        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }

        $employees = $employeesQuery->with([
            'pointeuses' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('auth_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orderBy('auth_time');
            }
        ])->where('company_id', Auth::user()->company_id)->orderBy('name')->get();

        // Même calendrier que l'écran : fériés chômés, fériés travaillés et
        // jours de fermeture par site. Sans eux, l'export marquait « Absent »
        // tous les jours sans pointage, y compris fériés et jours fermés.
        $companyId = Auth::user()->company_id;

        $exportHolidays       = \Modules\Time\Models\Holiday::datesBetween($companyId, $startDate, $endDate, false);
        $exportWorkedHolidays = \Modules\Time\Models\Holiday::datesBetween($companyId, $startDate, $endDate, true);

        $exportClosedByBranch = $this->closedDatesByBranch(
            $companyId,
            \App\Models\Company::find($companyId),
            $days,
            $exportWorkedHolidays
        );

        // Générer le nom du fichier selon le type de période
        $filename = $this->generateFilename($periodType, $request, $startDate, $endDate);

        return Excel::download(
            new AttendanceExport(
                $employees,
                $days,
                $periodType,
                $request,
                $exportHolidays,
                $exportWorkedHolidays,
                $exportClosedByBranch
            ),
            $filename
        );
    }

    private function generateFilename($periodType, $request, $startDate, $endDate)
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $companyName = Auth::user()->company->name;

        switch ($periodType) {
            case 'week':
                $week = $request->get('week', date('W'));
                $year = $request->get('year', date('Y'));
                return "pointages_semaine_{$companyName}_{$week}_{$year}_{$timestamp}.xlsx";

            case 'month':
                $month = $request->get('month', date('n'));
                $year = $request->get('year', date('Y'));
                $monthName = Carbon::create($year, $month, 1)->locale('fr')->isoFormat('MMMM');
                return "pointages_{$companyName}_{$monthName}_{$year}_{$timestamp}.xlsx";

            case 'day':
                $date = $startDate->format('Y-m-d');
                return "pointages_{$companyName}_{$date}_{$timestamp}.xlsx";

            case 'custom':
                $start = $startDate->format('Y-m-d');
                $end = $endDate->format('Y-m-d');
                return "pointages_{$companyName}_{$start}_au_{$end}_{$timestamp}.xlsx";

            default:
                return "pointages_export_{$companyName}_{$timestamp}.xlsx";
        }
    }

    private function handleMonthFilter(Request $request, &$days, &$startDate, &$endDate, &$previousMonth, &$nextMonth, &$previousMonthYear, &$nextMonthYear)
    {
        $currentMonth = $request->get('month', date('n'));
        $currentYear = $request->get('year', date('Y'));

        $startDate = Carbon::create($currentYear, $currentMonth, 1);
        $endDate = $startDate->copy()->endOfMonth();

        // Navigation mensuelle
        $previousMonth = $currentMonth - 1;
        $nextMonth = $currentMonth + 1;
        $previousMonthYear = $currentYear;
        $nextMonthYear = $currentYear;

        if ($previousMonth < 1) {
            $previousMonth = 12;
            $previousMonthYear = $currentYear - 1;
        }

        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextMonthYear = $currentYear + 1;
        }

        // Générer tous les jours du mois
        $currentDay = $startDate->copy();
        while ($currentDay->lte($endDate)) {
            $days->push($currentDay->copy());
            $currentDay->addDay();
        }
    }

    private function handleDayFilter(Request $request, &$days, &$startDate, &$endDate)
    {
        $specificDate = $request->get('specific_date', date('Y-m-d'));
        $startDate = Carbon::parse($specificDate);
        $endDate = $startDate->copy();

        $days->push($startDate->copy());
    }

    private function handleCustomFilter(Request $request, &$days, &$startDate, &$endDate)
    {
        $startDate = Carbon::parse($request->get('start_date', date('Y-m-d')));
        $endDate = Carbon::parse($request->get('end_date', date('Y-m-d')));

        // Générer les jours de la semaine
        for ($i = 0; $i < 7; $i++) {
            $days->push($startDate->copy()->addDays($i));
        }
    }

    private function calculateWorkingTime($pointages)
    {
        $entree = $pointages->firstWhere('type', 'entree');
        $sortie = $pointages->firstWhere('type', 'sortie');

        if (!$entree || !$sortie) {
            return '--:--';
        }

        $start = Carbon::parse("{$entree->auth_date} {$entree->auth_time}");
        $end = Carbon::parse("{$sortie->auth_date} {$sortie->auth_time}");

        $diff = $start->diff($end);
        return sprintf('%02d:%02d', $diff->h, $diff->i);
    }

    // Cumul hebdomadaire
    private function calculateWeeklyTotal(Request $request, &$employee, &$days)
    {
        $totalMinutes = 0;
        foreach ($days as $day) {
            $pointages = $employee->pointages
                ->where('auth_date', $day->format('Y-m-d'))
                ->sortBy('auth_time');
            $entree = $pointages->firstWhere('type', 'entree');
            $sortie = $pointages->firstWhere('type', 'sortie');
            if ($entree && $sortie) {
                $start = Carbon::parse("{$entree->auth_date} {$entree->auth_time}");
                $end = Carbon::parse("{$sortie->auth_date} {$sortie->auth_time}");
                $totalMinutes += $start->diffInMinutes($end);
            }
        }
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    // Cumul mensuel
    private function calculateMonthlyTotal(Request $request, &$employee, &$month, &$year)
    {
        $totalMinutes = 0;
        foreach ($employee->pointages as $pointage) {
            $date = Carbon::parse($pointage->auth_date);
            if ($date->month == $month && $date->year == $year) {
                // On ne compte que les journées où il y a une entrée et une sortie
                $dayPointages = $employee->pointages->where('auth_date', $date->format('Y-m-d'));
                $entree = $dayPointages->firstWhere('type', 'entree');
                $sortie = $dayPointages->firstWhere('type', 'sortie');
                if ($entree && $sortie) {
                    $start = Carbon::parse("{$entree->auth_date} {$entree->auth_time}");
                    $end = Carbon::parse("{$sortie->auth_date} {$sortie->auth_time}");
                    $totalMinutes += $start->diffInMinutes($end);
                }
            }
        }
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function exportExcel(Request $request)
    {
        // Récupérer les mêmes données que dans votre vue
        $year = $request->get('year', date('Y'));
        $week = $request->get('week', date('W'));

        // Calculer les dates de début et fin de semaine
        $startDate = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $endDate = Carbon::now()->setISODate($year, $week)->endOfWeek();

        // Générer les jours de la semaine
        $days = collect();
        for ($i = 0; $i < 7; $i++) {
            $days->push($startDate->copy()->addDays($i));
        }

        // Jours fériés chômés de la semaine : signalés « Férié » au lieu de « Absent »
        $exportHolidays = \Modules\Time\Models\Holiday::datesBetween(
            Auth::user()->company_id,
            $startDate,
            $endDate,
            false
        );

        // Fériés travaillés : l'entreprise reste ouverte, la journée redevient
        // ouvrée et l'absence y est donc comptée normalement.
        $exportWorkedHolidays = \Modules\Time\Models\Holiday::datesBetween(
            Auth::user()->company_id,
            $startDate,
            $endDate,
            true
        );

        // Jours de fermeture, par site : « Fermé » plutôt qu'« Absent ».
        $exportCompany       = \App\Models\Company::find(Auth::user()->company_id);
        $exportClosedByBranch = $this->closedDatesByBranch(
            Auth::user()->company_id,
            $exportCompany,
            $days,
            $exportWorkedHolidays
        );

        // Récupérer les employés avec leurs pointages
        $employees = Employee::where('company_id', '=', Auth::user()->company_id)
            ->where('is_active', 1)
            ->with([
                'pointeuses' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('auth_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                        ->orderBy('auth_time');
                }
            ])
            ->get();

        // Créer un nouveau spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Titre du document
        $sheet->setCellValue('A1', 'RAPPORT DE PRÉSENCE');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Période
        $sheet->setCellValue('A2', 'Semaine du ' . $startDate->locale('fr')->isoFormat('D MMMM YYYY') . ' au ' . $endDate->locale('fr')->isoFormat('D MMMM YYYY') . ' (Semaine ' . $week . ')');
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // En-têtes
        $row = 4;
        $sheet->setCellValue('A' . $row, 'Employé');

        $col = 'B';
        foreach ($days as $day) {
            $sheet->setCellValue($col . $row, $day->locale('fr')->isoFormat('ddd D MMM'));
            $col++;
        }

        // Style des en-têtes
        $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E8F4FD');

        // Données des employés
        $row++;
        foreach ($employees as $employee) {
            $sheet->setCellValue('A' . $row, $employee->name);

            // Jours de fermeture du site de l'employé ; sans site rattaché,
            // le rythme de l'entreprise (clé 0).
            $exportClosed = $exportClosedByBranch[$employee->branch_id ?? 0]
                ?? ($exportClosedByBranch[0] ?? []);

            $col = 'B';
            foreach ($days as $day) {
                $pointages = $employee->pointages
                    ->where('auth_date', $day->format('Y-m-d'))
                    ->sortBy('auth_time');

                if ($pointages->isNotEmpty()) {
                    $entree = $pointages->firstWhere('type', 'entree');
                    $sortie = $pointages->firstWhere('type', 'sortie');
                    $totalHours = $this->calculateWorkingTime($pointages);

                    $cellValue = '';
                    if ($entree) {
                        $cellValue .= 'Arrivée: ' . $entree->auth_time . "\n";
                    }
                    if ($sortie) {
                        $cellValue .= 'Départ: ' . $sortie->auth_time . "\n";
                    }
                    $cellValue .= 'Total: ' . $totalHours . ' heures';

                    $sheet->setCellValue($col . $row, $cellValue);
                    $sheet->getStyle($col . $row)->getAlignment()->setWrapText(true);
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('D4F6D4'); // Vert clair pour présent
                } elseif (in_array($day->format('Y-m-d'), $exportHolidays, true)) {
                    // Férié chômé : ne pas pointer n'est pas une absence
                    $sheet->setCellValue($col . $row, 'Férié');
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('E3F0FF'); // Bleu clair pour férié
                } elseif (in_array($day->format('Y-m-d'), $exportClosed, true)) {
                    // Hors des jours ouvrés de l'entreprise : rien n'était attendu
                    $sheet->setCellValue($col . $row, 'Fermé');
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFF2CC'); // Jaune pour jour de fermeture
                } else {
                    $sheet->setCellValue($col . $row, 'Absent');
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFE6E6'); // Rouge clair pour absent
                }

                $col++;
            }
            $row++;
        }

        // Ajuster la largeur des colonnes
        $sheet->getColumnDimension('A')->setWidth(20);
        for ($col = 'B'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }

        // Ajuster la hauteur des lignes
        for ($i = 5; $i < $row; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(60);
        }

        // Bordures
        $sheet->getStyle('A4:H' . ($row - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Alignement
        $sheet->getStyle('B4:H' . ($row - 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Nom du fichier
        $filename = 'rapport_presence_semaine_' . $week . '_' . $year . '_' . Auth::user()->name . '.xlsx';

        // Préparer la réponse
        $writer = new Xlsx($spreadsheet);

        // Headers pour le téléchargement
        return response()->stream(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    private function determinerTypePointage($employeeId, Carbon $now)
    {
        // Récupérer le dernier pointage de l'employé pour aujourd'hui
        $dernierPointage = Pointeuse::where('employee_id', $employeeId)
            ->whereDate('auth_date', $now->toDateString())
            ->latest('auth_date_time')
            ->first();

        // Si pas de pointage aujourd'hui ou dernier pointage = sortie, alors c'est une entrée
        if (!$dernierPointage || $dernierPointage->type === 'sortie') {
            return 'entree';
        }

        // Sinon c'est une sortie
        return 'sortie';
    }

    public function syncData()
    {
        // Obtenir la date d'aujourd'hui
        date_default_timezone_set('Africa/Abidjan');
        $today = date('Y-m-d');

        // L'emplacement de l'utilisateur connecté
        $location = WorkLocation::where('company_id', '=', Auth::user()->company_id)
            ->where('is_active', '=', 1)
            ->first();

        // Chemin vers le fichier JSON sur le serveur avec la date d'aujourd'hui
        $jsonPath = 'https://dc-knowing.com/rhflow/pointeuse_json/eventsocp_' . $today . '.json';

        // Lire le contenu du fichier JSON
        $response = file_get_contents($jsonPath);

        if ($response === false) {
            return redirect()->back()->with('error', 'Erreur lors de la lecture du fichier JSON.');
        }

        // Décoder le JSON
        $data = json_decode($response, true);

        if ($data === null) {
            return redirect()->back()->with('error', 'Erreur lors du décodage des données JSON.');
        }

        $events = $data['AcsEvent']['InfoList'] ?? [];

        // Récupérer tous les employés actifs une seule fois avant la boucle
        $employees = Employee::where('company_id', '=', Auth::user()->company_id)
            ->where('is_active', 1)
            ->get();

        foreach ($events as $event) {
            // Trouver l'employé correspondant
            $emp = $employees->first(function ($employee) use ($event) {
                return isset($event['name']) &&
                    stripos(trim($event['name']), trim($employee->name)) !== false;
            });

            $employeeId = $event['employeeNoString'] ?? 'N/A';
            $eventDateTime = $event['time'];
            $eventDate = Carbon::parse($eventDateTime)->format('Y-m-d');
            $eventTime = Carbon::parse($eventDateTime)->format('H:i:s');

            // Vérifier si une entrée existe déjà pour cet employé à cette date/heure EXACTE
            $existingEntry = Pointeuse::where('company_id', Auth::user()->company_id)
                ->where('auth_date_time', $eventDateTime)
                ->where('employee_id', $employeeId)
                ->first();

            // Si l'entrée existe déjà, on passe au suivant (évite les doublons)
            if ($existingEntry) {
                continue;
            }

            // Vérifier s'il y a un pointage dans les 2 minutes précédentes ou suivantes
            // pour éviter les doubles pointages accidentels
            $timeBuffer = 2; // minutes
            $startTime = Carbon::parse($eventDateTime)->subMinutes($timeBuffer);
            $endTime = Carbon::parse($eventDateTime)->addMinutes($timeBuffer);

            $recentEntry = Pointeuse::where('company_id', Auth::user()->company_id)
                ->where('employee_id', $employeeId)
                ->whereBetween('auth_date_time', [$startTime, $endTime])
                ->first();

            // Si un pointage existe dans cette fenêtre de temps, on l'ignore
            if ($recentEntry) {
                continue;
            }

            // Compter le nombre de pointages existants pour cet employé à cette DATE (pas heure)
            $count = Pointeuse::where('company_id', Auth::user()->company_id)
                ->where('employee_id', $employeeId)
                ->whereDate('auth_date_time', $eventDate)
                ->count();

            // Déterminer le type basé sur le nombre de pointages du jour
            if ($count % 2 == 0) {
                $typeFromRequest = 'entree';  // Premier pointage du jour = entrée
            } else {
                $typeFromRequest = 'sortie';  // Deuxième pointage = sortie
            }

            // Validation supplémentaire : vérifier la cohérence temporelle
            if ($typeFromRequest == 'sortie') {
                // Vérifier qu'il y a bien une entrée avant cette sortie
                $lastEntry = Pointeuse::where('company_id', Auth::user()->company_id)
                    ->where('employee_id', $employeeId)
                    ->whereDate('auth_date_time', $eventDate)
                    ->orderBy('auth_date_time', 'desc')
                    ->first();

                // Si la dernière entrée est une sortie, alors ce pointage devrait être une entrée
                if ($lastEntry && $lastEntry->type == 'sortie') {
                    $typeFromRequest = 'entree';
                }
            }

            // Insérer le nouveau pointage
            DB::table('pointeuses')->insert([
                'auth_date_time' => $eventDateTime,
                'auth_date' => $eventDate,
                'auth_time' => $eventTime,
                'employee_id' => $employeeId,
                'direction' => $location->name ?? 'N/A',
                'name_device' => 'DS-K1T805MBFWX',
                'type' => $typeFromRequest,
                'no_device' => $event['cardReaderNo'] ?? 'N/A',
                'emp_id' => $emp->id ?? null,
                'name_emp' => $event['name'] ?? 'N/A',
                'card_no' => $event['cardNo'] ?? 'N/A',
                'location_id' => $location->id ?? null,
                'status' => 1,
                'company_id' => Auth::user()->company_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        return redirect()->back()->with('success', 'Données synchronisées avec succès.');
    }

    /**
     * Afficher le calendrier des absences
     */
    public function calendar()
    {
        $user = Auth::user();

        // Récupérer les absences en fonction des permissions
        if ($user->type == 'employee') {
            $absences = TimeSheet::where('employee_id', $user->employee->id ?? 0)
                ->with('employee')
                ->get();
        } else {
            $absences = TimeSheet::query()
                ->with('employee')
                ->when($user->type != 'super admin', function ($query) use ($user) {
                    return $query->where('company_id', $user->company_id);
                })
                ->get();
        }

        // Formater les événements pour le calendrier
        $events = [];
        foreach ($absences as $absence) {
            $endDate = $absence->arrival_date ? Carbon::parse($absence->arrival_date)->addDay() : Carbon::parse($absence->date)->addDay();

            $events[] = [
                'id' => $absence->id,
                'title' => $absence->employee->name ?? 'Employé inconnu',
                'start' => $absence->date,
                'end' => $endDate->format('Y-m-d'),
                'color' => $this->getStatusColor($absence->statut),
                'extendedProps' => [
                    'type' => 'absence',
                    'status' => $absence->statut,
                    'hours' => $absence->hours,
                    'days' => $absence->days,
                    'motif' => $absence->remark,
                ]
            ];
        }

        return view('time::calendar', compact('events'));
    }

    /**
     * Obtenir la couleur en fonction du statut
     */
    private function getStatusColor($status)
    {
        switch ($status) {
            case 'approved':
                return '#28a745'; // Vert
            case 'rejected':
                return '#dc3545'; // Rouge
            default:
                return '#ffc107'; // Jaune (par défaut pour 'pending')
        }
    }

    /**
     * Affiche le portail mobile de pointage pour un lieu spécifique
     */
    public function portal(Request $request)
    {
        $locationId = $request->query('location_id');

        if (!$locationId) {
            abort(400, 'Paramètre location_id manquant.');
        }

        $location = WorkLocation::where('id', $locationId)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$location) {
            abort(404, 'Lieu de travail invalide ou introuvable.');
        }

        // Un manager cantonné à un site ne peut ouvrir que les lieux de travail
        // rattachés à sa succursale. Un lieu sans succursale est refusé : rien
        // ne permet alors de vérifier qu'il relève bien de son périmètre.
        if (!Auth::user()->canPointForBranch($location->branch_id)) {
            abort(403, 'Vous n\'êtes pas autorisé à pointer pour ce lieu de travail.');
        }

        // Récupérer uniquement les employés actifs appartenant au site/département du lieu
        $employeesQuery = Employee::where('company_id', Auth::user()->company_id)
            ->where('is_active', 1);

        if ($location->branch_id) {
            $employeesQuery->where('branch_id', $location->branch_id);
        }
        if ($location->department_id) {
            $employeesQuery->where('department_id', $location->department_id);
        }

        $employees = $employeesQuery->orderBy('name')->get();

        return view('time::qrcode-pointage.portal', compact('location', 'employees'));
    }

    /**
     * Enregistre un pointage via le portail mobile
     */
    public function storePortalPointage(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'location_id' => 'required',
            'type' => 'required|in:entree,sortie'
        ]);

        $supervisor = Auth::user();

        // Valider l'employé et le lieu
        $employee = Employee::where('id', $request->employee_id)
            ->where('company_id', $supervisor->company_id)
            ->first();

        $location = WorkLocation::with(['branch', 'department'])
            ->where('id', $request->location_id)
            ->where('company_id', $supervisor->company_id)
            ->first();

        if (!$employee || !$location) {
            return response()->json([
                'success' => false,
                'message' => 'Informations invalides (Employé ou Lieu introuvable).'
            ], 422);
        }

        // Même cloisonnement qu'à l'affichage : c'est ici qu'il protège
        // réellement, la requête pouvant être forgée sans passer par la page.
        if (!$supervisor->canPointForBranch($location->branch_id)) {
            return response()->json([
                'success' => false,
                'message' => '⛔ Vous n\'êtes pas autorisé à pointer pour ce lieu de travail.'
            ], 403);
        }

        // ✅ SÉCURITÉ : Vérifier que l'employé appartient à la même succursale que le lieu de travail
        if ($location->branch_id && $employee->branch_id !== $location->branch_id) {
            $branchName = $location->branch ? $location->branch->name : 'autre succursale';
            return response()->json([
                'success' => false,
                'message' => '⛔ Accès refusé : vous n\'appartenez pas à la succursale "' . $branchName . '". Ce QR code est réservé aux employés de ce site.'
            ], 403);
        }

        // ✅ SÉCURITÉ : Vérifier que l'employé appartient au même département que le lieu de travail
        if ($location->department_id && $employee->department_id !== $location->department_id) {
            $deptName = $location->department ? $location->department->name : 'autre département';
            return response()->json([
                'success' => false,
                'message' => '⛔ Accès refusé : vous n\'appartenez pas au service "' . $deptName . '". Ce QR code est réservé aux employés de ce département.'
            ], 403);
        }

        $now = Carbon::now();

        // Déterminer le statut du scan et la durée du retard / avance (entrée ou sortie)
        $scanStatus = 'a_l_heure';
        $dureeRetard = 0;

        $scanTimeStr = $now->format('H:i:s');

        if ($request->type === 'entree') {
            $limitRaw = $location->check_in_end ?? '08:30:00';
            if (is_object($limitRaw) && method_exists($limitRaw, 'format')) {
                $limitStr = $limitRaw->format('H:i:s');
            } else {
                $limitStr = (string)$limitRaw;
                if (strlen($limitStr) == 5) {
                    $limitStr .= ':00';
                }
            }

            if (Carbon::parse($scanTimeStr)->gt(Carbon::parse($limitStr))) {
                $scanStatus = 'retard';
                $dureeRetard = (int)round(abs(Carbon::parse($limitStr)->diffInMinutes(Carbon::parse($scanTimeStr))));
            }
        } else {
            // Sortie (Départ) : vérification du départ en avance par rapport à l'heure minimale de départ
            $limitRaw = $location->check_out_start ?? $location->check_out_end ?? '17:00:00';
            if (is_object($limitRaw) && method_exists($limitRaw, 'format')) {
                $limitStr = $limitRaw->format('H:i:s');
            } else {
                $limitStr = (string)$limitRaw;
                if (strlen($limitStr) == 5) {
                    $limitStr .= ':00';
                }
            }

            if (Carbon::parse($scanTimeStr)->lt(Carbon::parse($limitStr))) {
                $scanStatus = 'en_avance';
                $dureeRetard = (int)round(abs(Carbon::parse($limitStr)->diffInMinutes(Carbon::parse($scanTimeStr))));
            }
        }

        // Un seul pointage de chaque type par jour et par employé : sans ce
        // contrôle, un double clic ou un rechargement de page enregistre
        // plusieurs entrées pour la même personne.
        $precedent = Pointeuse::where('employee_id', $employee->id)
            ->whereDate('auth_date', $now->toDateString())
            ->where('type', $request->type)
            ->orderBy('auth_date_time')
            ->first();

        if ($precedent) {
            return response()->json([
                'success' => false,
                'message' => $employee->name . ' a déjà pointé « '
                    . ($request->type === 'entree' ? 'Entrée' : 'Sortie') . ' » à '
                    . Carbon::parse($precedent->auth_date_time)->format('H:i') . '.',
            ], 422);
        }

        // Création du pointage (enregistré dans tous les cas)
        Pointeuse::create([
            'employee_id' => $employee->id,
            'auth_date_time' => $now,
            'auth_date' => $now->toDateString(),
            'auth_time' => $now->toTimeString(),
            'type' => $request->type,
            'direction' => $supervisor->name,
            'name_device' => 'Portail Web (Par ' . $supervisor->name . ')',
            'no_device' => $supervisor->id, // Traçabilité du superviseur
            'emp_id' => $employee->id,
            'card_no' => 'N/A',
            'name_emp' => $employee->name,
            'location_id' => $location->id,
            'status' => 1,
            'company_id' => $employee->company_id,
            'scan_status' => $scanStatus,
            'duree_retard' => $dureeRetard,
        ]);

        if ($scanStatus === 'retard') {
            $statusMsg = ' - En retard de ' . $dureeRetard . ' min';
        } elseif ($scanStatus === 'en_avance') {
            $statusMsg = ' - En avance de ' . $dureeRetard . ' min';
        } else {
            $statusMsg = ' - À l\'heure';
        }

        return response()->json([
            'success' => true,
            'message' => 'Pointage enregistré : ' . $employee->name . ' (' . ($request->type === 'entree' ? 'Entrée' : 'Sortie') . $statusMsg . ') à ' . $now->format('H:i'),
            'scan_status' => $scanStatus,
            'duree_retard' => $dureeRetard
        ]);
    }

    /**
     * Affiche les rapports et statistiques de présences / absences
     */
    public function reports(Request $request)
    {
        $company_id = Auth::user()->company_id;
        
        $period = $request->get('period', 'current_month');
        
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        
        if ($period == 'last_month') {
            $startDate = Carbon::now()->subMonth()->startOfMonth();
            $endDate = Carbon::now()->subMonth()->endOfMonth();
        } elseif ($period == 'year') {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now()->endOfYear();
        }

        // 1. Calcul des retards
        // On récupère les emplacements de travail de l'entreprise pour avoir les heures limites (check_in_end)
        $locations = WorkLocation::where('company_id', $company_id)->get()->keyBy('id');
        $defaultCheckInEnd = '08:00:00';
        if ($locations->count() > 0 && $locations->first()->check_in_end) {
            $defaultCheckInEnd = $locations->first()->check_in_end;
        }

        // Récupérer toutes les entrées de la période
        $entrees = Pointeuse::with('employee')
            ->where('company_id', $company_id)
            ->where('type', 'entree')
            ->whereBetween('auth_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $latesCount = [];
        foreach ($entrees as $entree) {
            // Ignorer si l'employé n'existe plus
            if (!$entree->employee) continue;

            $checkInEnd = $defaultCheckInEnd;
            if ($entree->location_id && isset($locations[$entree->location_id])) {
                $checkInEnd = $locations[$entree->location_id]->check_in_end ?? $defaultCheckInEnd;
            }
            
            $isLate = false;
            $dureeRetard = 0;

            if (!empty($entree->scan_status)) {
                $isLate = ($entree->scan_status === 'retard');
                $dureeRetard = (int)($entree->duree_retard ?? 0);
            } else {
                if (Carbon::parse($entree->auth_time)->format('H:i:s') > Carbon::parse($checkInEnd)->format('H:i:s')) {
                    $isLate = true;
                    $dureeRetard = Carbon::parse($checkInEnd)->diffInMinutes(Carbon::parse($entree->auth_time));
                }
            }

            if ($isLate) {
                if (!isset($latesCount[$entree->employee_id])) {
                    $latesCount[$entree->employee_id] = [
                        'employee' => $entree->employee,
                        'count' => 0,
                        'total_delay_minutes' => 0
                    ];
                }
                $latesCount[$entree->employee_id]['count']++;
                $latesCount[$entree->employee_id]['total_delay_minutes'] += $dureeRetard;
            }
        }
        
        // Trier par nombre de retards
        usort($latesCount, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        $topLates = array_slice($latesCount, 0, 10);

        // 2. Calcul des absences
        // On se base sur les jours ouvrés (lun-ven) sans pointage pour chaque employé actif
        $employees = Employee::where('company_id', $company_id)->where('is_active', 1)->get();
        $absencesCount = [];
        
        // Générer les jours ouvrés de la période
        $workingDays = [];
        $currentDate = $startDate->copy();
        $realEndDate = $endDate->isFuture() ? Carbon::now() : $endDate; // On ne compte pas les absences futures

        // Fériés chômés : l'entreprise ferme, ne pas y pointer n'est pas une
        // absence. Les fériés travaillés, eux, restent des jours ouvrés.
        $holidays = \Modules\Time\Models\Holiday::datesBetween($company_id, $startDate, $realEndDate, false);
        $workedHolidays = \Modules\Time\Models\Holiday::datesBetween($company_id, $startDate, $realEndDate, true);

        // Jours ouvrés calculés par site : chaque succursale a son propre rythme
        // et celles qui n'en ont pas suivent l'entreprise. La clé 0 porte le
        // rythme d'entreprise, pour les employés sans site rattaché.
        $reportCompany  = \App\Models\Company::find($company_id);
        $reportBranches = \App\Models\Branch::where('company_id', $company_id)->get();

        $workingDaysByBranch = [0 => []];

        foreach ($reportBranches as $reportBranch) {
            $workingDaysByBranch[$reportBranch->id] = [];
        }

        while ($currentDate <= $realEndDate) {
            $dayKey = $currentDate->format('Y-m-d');

            // Férié chômé : personne n'est attendu, la journée n'est ouvrée nulle part
            if (in_array($dayKey, $holidays, true)) {
                $currentDate->addDay();
                continue;
            }

            // Férié travaillé : la journée est ouvrée partout, y compris un week-end
            $forceOuvert = in_array($dayKey, $workedHolidays, true);

            $ouvreEntreprise = $reportCompany
                ? $reportCompany->worksOn($currentDate)
                : !$currentDate->isWeekend();

            if ($forceOuvert || $ouvreEntreprise) {
                $workingDaysByBranch[0][] = $dayKey;
            }

            foreach ($reportBranches as $reportBranch) {
                if ($forceOuvert || $reportBranch->worksOn($currentDate)) {
                    $workingDaysByBranch[$reportBranch->id][] = $dayKey;
                }
            }

            $currentDate->addDay();
        }

        // Conservé pour les employés sans site et comme repli
        $workingDays = $workingDaysByBranch[0];
        
        foreach ($employees as $employee) {
            $employeePointages = Pointeuse::where('employee_id', $employee->id)
                ->whereBetween('auth_date', [$startDate->format('Y-m-d'), $realEndDate->format('Y-m-d')])
                ->pluck('auth_date')
                ->toArray();
                
            $employeePointages = array_unique($employeePointages);
            
            // Jours où il n'y a aucun pointage
            // Jours ouvrés du site de l'employé : une absence ne se compte que
            // sur les jours où son site était effectivement ouvert.
            $employeeWorkingDays = $workingDaysByBranch[$employee->branch_id ?? 0] ?? $workingDays;

            $missingDays = array_diff($employeeWorkingDays, $employeePointages);
            
            // Déduire les congés autorisés (TimeSheet)
            $leaves = \Modules\Time\Models\TimeSheet::where('employee_id', $employee->id)
                ->where('statut', 'approved')
                ->where(function($query) use ($startDate, $realEndDate) {
                    $query->whereBetween('date', [$startDate->format('Y-m-d'), $realEndDate->format('Y-m-d')])
                          ->orWhereBetween('arrival_date', [$startDate->format('Y-m-d'), $realEndDate->format('Y-m-d')]);
                })->get();
                
            $leaveDays = [];
            foreach ($leaves as $leave) {
                $startLeave = Carbon::parse($leave->date);
                $endLeave = $leave->arrival_date ? Carbon::parse($leave->arrival_date) : $startLeave;
                $curr = $startLeave->copy();
                while ($curr <= $endLeave) {
                    $leaveDays[] = $curr->format('Y-m-d');
                    $curr->addDay();
                }
            }
            
            $unjustifiedAbsences = array_diff($missingDays, $leaveDays);
            $count = count($unjustifiedAbsences);
            
            if ($count > 0) {
                $absencesCount[] = [
                    'employee' => $employee,
                    'count' => $count
                ];
            }
        }
        
        usort($absencesCount, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        $topAbsences = array_slice($absencesCount, 0, 10);
        
        // 3. Top Heures Travaillées
        $topWorkers = [];
        foreach ($employees as $employee) {
            if ($period == 'current_month') {
                $timeStr = $this->timeTrackingService->calculateCurrentMonthTotal($employee);
            } else {
                 $timeStr = $this->timeTrackingService->calculatePeriodTotal($employee, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
            }
            
            // Convertir HH:MM en minutes pour le tri
            $parts = explode(':', $timeStr);
            if (count($parts) == 2) {
                $minutes = ($parts[0] * 60) + $parts[1];
                if ($minutes > 0) {
                    $topWorkers[] = [
                        'employee' => $employee,
                        'time' => $timeStr,
                        'minutes' => $minutes
                    ];
                }
            }
        }
        
        usort($topWorkers, function($a, $b) {
            return $b['minutes'] <=> $a['minutes'];
        });
        $topWorkers = array_slice($topWorkers, 0, 10);

        return view('time::qrcode-pointage.reports', compact('topLates', 'topAbsences', 'topWorkers', 'period', 'startDate', 'endDate'));
    }
}
