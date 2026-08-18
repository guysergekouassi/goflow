@php
    use Carbon\Carbon;
@endphp
@extends('layouts.app')

@section('page-title')
    {{ __('Pointeuse') }}
@stop

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">📅 Gestion des présences et absences
                        @if($periode) | Exercice :
                         {{ $periode->exercice->nom }} - Statut : <span class="badge bg-label-{{ $periode->statut === 'en_cours' ? 'success' : ($periode->statut === 'cloture' ? 'secondary' : 'warning') }}">
                        {{ ucfirst($periode->exercice->statut) }}
                        @endif
                        </span>
                    </h4>
                    <p class="text-muted mb-0">Gérez les absences des employés pour la période en cours.</p>  
                    <nav aria-label="breadcrumb"> 
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('company.paiesalaries.dashboard') }}">Paie</a>
                            </li>
                            <li class="breadcrumb-item active">Absence @if($periode) - Période : {{ $periode->nom }} | Statut : <span class="badge bg-label-{{ $periode->statut === 'en_cours' ? 'success' : ($periode->statut === 'cloture' ? 'secondary' : 'warning') }}">{{ ucfirst($periode->statut) }}</span> @endif</li>
                        </ol>
                    </nav>
                    <small class="text-primary">
                        <i class="fas fa-calendar me-1"></i>
                        {{ ucfirst(\Carbon\Carbon::now()->locale('fr_FR')->isoFormat('dddd D MMMM YYYY')) }} •
                        <i class="fas fa-clock me-1"></i>
                        {{ \Carbon\Carbon::now()->locale('fr_FR')->isoFormat('HH:mm') }}
                    </small>
                </div>
                <div>
                    @if($periode)
                    <a href="{{ route('company.paiesalaries.periodes.show', $periode->id) }}" type="button" class="align-items-center btn btn-info">
                        <i class="fas fa-arrow-left me-2"></i>Retour à la période
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAbsenceModal">
                        <i class="fas fa-plus me-2"></i>Ajouter une absence
                    </button> 
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="row"> 
        <div class="col-sm-12 employee-tracker">
            <div class="card shadow">
                <div class="card-header bg-primary text-white mb-3">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h5 class="mb-0 text-white"><i class="fas fa-clock me-2"></i>{{ __('Pointeuse') }}</h5>
                        </div>
                        <div class="col-4 text-end">
                            <!-- Info serveur client DC-KNOWING -->
                            @if (\Auth::user()->active_status == 1 && \Auth::user()->username == 'DCKN24')
                                <a href="http://localhost:81/pointeuse/pointeuse.php" id="syncLink" class="btn btn-danger">
                                    <i class="fas fa-sync-alt me-2"></i> Synchroniser
                                </a>
                            @endif
                            <a href="{{ route('company.times.qrcode-pointage.reports') }}" class="btn btn-warning me-2 text-dark">
                                <i class="fas fa-chart-bar me-2"></i> Rapports & Stats
                            </a>
                            <a href="#" id="ExportFiltered" class="btn btn-success">
                                <i class="fas fa-files me-2"></i> Exporter (Filtré)
                            </a>
                        </div>
                    </div>
                    <div class="progress-container" id="progressBarContainer">
                        <div class="progress-bar" id="progressBar">0%</div>
                    </div>
                </div>

                <!-- SECTION FILTRES -->
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-filter me-2"></i>Filtres de recherche
                                        <button class="btn btn-sm btn-outline-secondary float-end" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    </h6>
                                </div>
                                <div class="collapse show" id="filtersCollapse">
                                    <div class="card-body">
                                        <form id="filterForm" method="GET" action="{{ route('company.times.qrcode-pointage.index') }}">
                                            <div class="row">
                                                <!-- Filtre par employé -->
                                                <div class="col-md-3 mb-3">
                                                    <label for="employee_id" class="form-label">Employé</label>
                                                    <select class="form-select" id="employee_id" name="employee_id">
                                                        <option value="">Tous les employés</option>
                                                        @foreach($allEmployees as $emp)
                                                            <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                                                                {{ $emp->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <!-- Filtre par type de période -->
                                                <div class="col-md-3 mb-3">
                                                    <label for="period_type" class="form-label">Type de période</label>
                                                    <select class="form-select" id="period_type" name="period_type">
                                                        <option value="week" {{ request('period_type', 'week') == 'week' ? 'selected' : '' }}>Semaine</option>
                                                        <option value="month" {{ request('period_type') == 'month' ? 'selected' : '' }}>Mois</option>
                                                        <option value="day" {{ request('period_type') == 'day' ? 'selected' : '' }}>Jour</option>
                                                        <option value="custom" {{ request('period_type') == 'custom' ? 'selected' : '' }}>Période personnalisée</option>
                                                    </select>
                                                </div>

                                                <!-- Filtre par année/mois (pour semaine et mois) -->
                                                <div class="col-md-2 mb-3" id="yearFilter">
                                                    <label for="year" class="form-label">Année</label>
                                                    <select class="form-select" id="year" name="year">
                                                        @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                                                            <option value="{{ $y }}" {{ request('year', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                                                        @endfor
                                                    </select>
                                                </div>

                                                <!-- Filtre par semaine -->
                                                <div class="col-md-2 mb-3" id="weekFilter">
                                                    <label for="week" class="form-label">Semaine</label>
                                                    <select class="form-select" id="week" name="week">
                                                        @for($w = 1; $w <= 53; $w++)
                                                            <option value="{{ $w }}" {{ request('week', date('W')) == $w ? 'selected' : '' }}>Semaine {{ $w }}</option>
                                                        @endfor
                                                    </select>
                                                </div>

                                                <!-- Filtre par mois -->
                                                <div class="col-md-2 mb-3" id="monthFilter" style="display: block;">
                                                    <label for="month" class="form-label">Mois</label>
                                                    <select class="form-select" id="month" name="month">
                                                        @for($m = 1; $m <= 12; $m++)
                                                            <option value="{{ $m }}" {{ request('month', date('n')) == $m ? 'selected' : '' }}>
                                                                {{ \Carbon\Carbon::create()->month($m)->locale('fr')->isoFormat('MMMM') }}
                                                            </option>
                                                        @endfor
                                                    </select>
                                                </div>

                                                <!-- Filtre par jour spécifique -->
                                                <div class="col-md-3 mb-3" id="dayFilter" style="display: none;">
                                                    <label for="specific_date" class="form-label">Date spécifique</label>
                                                    <input type="date" class="form-control" id="specific_date" name="specific_date"
                                                        value="{{ request('specific_date', date('Y-m-d')) }}">
                                                </div>

                                                <!-- Période personnalisée -->
                                                <div class="col-md-3 mb-3" id="customStartDate" style="display: none;">
                                                    <label for="start_date" class="form-label">Date de début</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date"
                                                        value="{{ request('start_date') }}">
                                                </div>

                                                <div class="col-md-3 mb-3" id="customEndDate" style="display: none;">
                                                    <label for="end_date" class="form-label">Date de fin</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date"
                                                        value="{{ request('end_date') }}">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary me-2">
                                                        <i class="fas fa-search me-1"></i>Filtrer
                                                    </button>
                                                    <a href="{{ route('company.times.qrcode-pointage.index') }}" class="btn btn-secondary">
                                                        <i class="fas fa-times me-1"></i>Réinitialiser
                                                    </a>
                                                    <div class="float-end">
                                                        <span class="badge bg-info">
                                                            {{ $employees->count() }} employé(s) affiché(s)
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation entre périodes (adaptée selon le type de filtre) -->
                    @if(request('period_type', 'week') == 'week')
                    <div class="row mb-4">
                        <div class="col-md-3 text-start">
                            <a href="{{ route('company.times.qrcode-pointage.index', array_merge(request()->all(), ['year' => $previousYear, 'week' => $previousWeek])) }}"
                            class="btn btn-outline-primary">
                                <i class="fa fa-arrow-circle-left me-2"></i> Semaine précédente
                            </a>
                        </div>
                        <div class="col-md-6 text-center">
                            <h4 class="text-uppercase font-weight-bold mb-0">
                                Semaine du {{ $startDate->locale('fr')->isoFormat('D MMMM YYYY') }}
                                au {{ $endDate->locale('fr')->isoFormat('D MMMM YYYY') }}
                                <div class="small text-muted">(Semaine {{ $currentWeek }})</div>
                            </h4>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="{{ route('company.times.qrcode-pointage.index', array_merge(request()->all(), ['year' => $nextYear, 'week' => $nextWeek])) }}"
                            class="btn btn-outline-primary">
                                Semaine suivante <i class="fa fa-arrow-circle-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                    @elseif(request('period_type') == 'month')
                    <div class="row mb-4">
                        <div class="col-md-3 text-start">
                            <a href="{{ route('company.times.qrcode-pointage.index', array_merge(request()->all(), ['year' => $previousMonthYear, 'month' => $previousMonth])) }}"
                            class="btn btn-outline-primary">
                                <i class="fa fa-arrow-circle-left me-2"></i> Mois précédent
                            </a>
                        </div>
                        <div class="col-md-6 text-center">
                            <h4 class="text-uppercase font-weight-bold mb-0">
                                {{ \Carbon\Carbon::create(request('year'), request('month'), 1)->locale('fr')->isoFormat('MMMM YYYY') }}
                            </h4>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="{{ route('company.times.qrcode-pointage.index', array_merge(request()->all(), ['year' => $nextMonthYear, 'month' => $nextMonth])) }}"
                            class="btn btn-outline-primary">
                                Mois suivant <i class="fa fa-arrow-circle-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                    @endif

                    <!-- Affichage conditionnel du titre de période -->
                    @if(request('period_type') == 'day')
                    <div class="row mb-4">
                        <div class="col-12 text-center">
                            <h4 class="text-uppercase font-weight-bold mb-0">
                                {{ \Carbon\Carbon::parse(request('specific_date', date('Y-m-d')))->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                            </h4>
                        </div>
                    </div>
                    @elseif(request('period_type') == 'custom')
                    <div class="row mb-4">
                        <div class="col-12 text-center">
                            <h4 class="text-uppercase font-weight-bold mb-0">
                                Du {{ \Carbon\Carbon::parse(request('start_date'))->locale('fr')->isoFormat('D MMMM YYYY') }}
                                au {{ \Carbon\Carbon::parse(request('end_date'))->locale('fr')->isoFormat('D MMMM YYYY') }}
                            </h4>
                        </div>
                    </div>
                    @endif

                    <!-- Tableau de présence -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>
                                        Liste des employés
                                        <div class="small text-muted">Total : {{ $employees->count() }}</div>
                                    </th>
                                    @foreach($days as $day)
                                        @php
                                            // Colonne grisée seulement quand la journée est fermée pour
                                            // tous les sites : les rythmes pouvant différer, la couleur
                                            // ne peut pas trancher pour un site en particulier.
                                            $headerClosed = in_array($day->format('Y-m-d'), $closedForAllSites ?? [], true);
                                        @endphp
                                        <th class="text-center" style="background-color:{{ $headerClosed ? '#e74c3c' : '' }}{{ $day->isToday() ? '#2ecc71' : '' }}; color:#000">
                                            {{ $day->locale('fr')->isoFormat('ddd D') }}
                                            <div class="small {{ $headerClosed || $day->isToday() ? 'text-white' : 'text-muted' }}">
                                                {{ $day->locale('fr')->isoFormat('MMMM') }}
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($employees as $employee)
                                    @php
                                        // Jours de fermeture du site de l'employé : chaque succursale a
                                        // son propre rythme. Sans site rattaché, on retombe sur celui
                                        // de l'entreprise (clé 0).
                                        $employeeClosedDates = $closedDatesByBranch[$employee->branch_id ?? 0]
                                            ?? ($closedDates ?? []);
                                    @endphp
                                    <tr>
                                        <td class="font-weight-bold" width="30%">
                                            {{ $employee->name }}<br>
                                            <div class="small text-start badge bg-label-secondary">
                                                Total Hebdomadaire: <span class="badge bg-warning">{{ $timeTrackingService->calculateWeeklyTotal($employee, $daysArray) }} heures</span>
                                                <hr>
                                                Total Mensuel: <span class="badge bg-primary">{{ $timeTrackingService->calculateMonthlyTotal($employee, request('month', date('n')), request('year', date('Y'))) }} heures</span>
                                            </div>
                                        </td>
                                        @foreach($days as $day)
                                            @php
                                                $dayKey = $day->format('Y-m-d');
                                                // Férié chômé : entreprise fermée, aucune absence comptée.
                                                $isHoliday = in_array($dayKey, $holidayDates ?? [], true);
                                                // Férié travaillé : journée ouvrée normale, absence comptée.
                                                $isWorkedHoliday = in_array($dayKey, $workedHolidayDates ?? [], true);
                                                // Fermeture hebdomadaire du site de l'employé, hors férié
                                                // travaillé qui rouvre la journée pour tout le monde.
                                                $isClosed = in_array($dayKey, $employeeClosedDates, true);

                                                $pointages = $employee->pointeuses
                                                    ->where('auth_date', $dayKey)
                                                    ->sortBy('auth_time');
                                            @endphp
                                            <td class="{{ $isClosed ? 'bg-weekend' : '' }}" align="center">
                                                @if($isWorkedHoliday)
                                                    <div class="small mb-1">
                                                        <span class="badge bg-label-warning" title="Jour férié sur lequel l'entreprise reste ouverte : les employés sont attendus">
                                                            <i class="fas fa-briefcase me-1"></i>Férié travaillé
                                                        </span>
                                                    </div>
                                                @endif

                                                @if($pointages->isNotEmpty())
                                                    @php
                                                        $entree = $pointages->firstWhere('type', 'entree');
                                                        $sortie = $pointages->firstWhere('type', 'sortie');
                                                    @endphp
                                                    <div class="d-flex flex-column gap-1 align-items-center">
                                                        @if($entree)
                                                            @php
                                                                $isLate = ($entree->scan_status === 'retard') || (($entree->duree_retard ?? 0) > 0);
                                                                $dureeRetardFormatted = (int)round(abs($entree->duree_retard ?? 0));
                                                            @endphp
                                                            <span class="badge {{ $isLate ? 'bg-warning text-dark' : 'bg-success' }}" title="{{ $isLate ? 'Retard de ' . $dureeRetardFormatted . ' min' : 'À l\'heure' }}">
                                                                Arrivée: {{ $entree->auth_time }}
                                                                @if($isLate)
                                                                    <span class="ms-1 fw-bold">(Retard {{ $dureeRetardFormatted }}m)</span>
                                                                @else
                                                                    <span class="ms-1">(À l'heure)</span>
                                                                @endif
                                                            </span>
                                                        @else
                                                            <span class="badge bg-secondary">Arrivée: --:--</span>
                                                        @endif

                                                        @if($sortie)
                                                            @php
                                                                $scanTimeStr = \Carbon\Carbon::parse($sortie->auth_time)->format('H:i:s');
                                                                $checkOutStart = '17:00:00';
                                                                $isEarly = ($sortie->scan_status === 'en_avance') || ($scanTimeStr < $checkOutStart);
                                                                $rawDuree = ($sortie->scan_status === 'en_avance' && ($sortie->duree_retard ?? 0) != 0)
                                                                    ? $sortie->duree_retard
                                                                    : \Carbon\Carbon::parse($checkOutStart)->diffInMinutes(\Carbon\Carbon::parse($scanTimeStr));
                                                                $dureeAvanceFormatted = (int)round(abs($rawDuree));
                                                            @endphp
                                                            <span class="badge {{ $isEarly ? 'bg-warning text-dark' : 'bg-success' }}" title="{{ $isEarly ? 'Départ en avance de ' . $dureeAvanceFormatted . ' min' : 'À l\'heure' }}">
                                                                Départ: {{ $sortie->auth_time }}
                                                                @if($isEarly)
                                                                    <span class="ms-1 fw-bold">(En avance {{ $dureeAvanceFormatted }}m)</span>
                                                                @else
                                                                    <span class="ms-1">(À l'heure)</span>
                                                                @endif
                                                            </span>
                                                        @else
                                                            <span class="badge bg-secondary">Départ: --:--</span>
                                                        @endif
                                                    </div>
                                                    <hr class="my-1">
                                                    <div class="small mt-1 text-center" style="font-size:0.9rem;">
                                                        Total: <span class="badge bg-info">{{ $timeTrackingService->calculateWorkingTime($pointages) }} heures</span>
                                                    </div>

                                                    @php
                                                        // Pointage saisi par un tiers : les portails enregistrent le nom de
                                                        // l'opérateur dans « direction ». Un auto-pointage via l'app mobile
                                                        // enregistre « Pointeuse QR CODE » et n'affiche donc rien ici.
                                                        $saisiPar = $pointages
                                                            ->filter(fn ($p) => \Illuminate\Support\Str::startsWith($p->name_device ?? '', 'Portail'))
                                                            ->pluck('direction')
                                                            ->filter()
                                                            ->unique()
                                                            ->implode(', ');
                                                    @endphp

                                                    @if($saisiPar !== '')
                                                        <div class="small text-center text-muted mt-1" style="font-size:0.75rem;"
                                                            title="Pointage saisi depuis un portail par un tiers, et non par l'employé lui-même">
                                                            <i class="fas fa-user-shield me-1"></i>Pointé par {{ $saisiPar }}
                                                        </div>
                                                    @endif
                                                @else
                                                    @php
                                                        $justif = $justifiedByEmployee[$employee->id][$dayKey] ?? null;
                                                    @endphp

                                                    @if($isHoliday)
                                                        {{-- Férié chômé : l'entreprise ferme, aucune pénalité --}}
                                                        <span class="badge bg-label-info"><i class="fas fa-calendar-day me-1"></i>Férié</span>
                                                    @elseif($isClosed)
                                                        {{-- Hors des jours ouvrés de l'entreprise : rien n'est attendu --}}
                                                        <span class="badge bg-label-secondary" title="L'entreprise ne travaille pas ce jour-là">
                                                            <i class="fas fa-door-closed me-1"></i>Fermé
                                                        </span>
                                                    @elseif($justif)
                                                        {{-- Absence approuvée : on affiche le motif justifié --}}
                                                        <span class="badge bg-label-success" title="{{ $justif['remark'] ?? '' }}">
                                                            <i class="fas fa-check-circle me-1"></i>{{ $justif['motif'] }}
                                                        </span>
                                                        @if($justif['remark'])
                                                            <div class="small text-muted mt-1" style="font-size:.72rem;">
                                                                {{ \Illuminate\Support\Str::limit($justif['remark'], 30) }}
                                                            </div>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-label-warning">Absent</span>
                                                    @endif
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .bg-weekend {
        background-color: #e74c3c;
    }
    .badge {
        font-size: 0.8rem;
        padding: 0.35em 0.5em;
    }

    .today-header {
        background-color: #2ecc71;
        color: white;
        font-weight: bold;
    }

    .btn-sync {
        background-color: #27ae60;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .btn-sync:hover {
        background-color: #219a52;
        color: white;
    }

    /* Style pour la barre de progression */
    .progress-container {
        width: 100%;
        background-color: #f3f3f3;
        border-radius: 4px;
        margin: 15px 0;
        overflow: hidden;
        display: none;
    }

    .progress-bar {
        height: 20px;
        background-color: #3498db;
        text-align: center;
        line-height: 20px;
        color: white;
        transition: width 0.3s ease;
    }

    /* Styles pour les filtres */
    .card {
        margin-bottom: 1rem;
    }

    .form-label {
        font-weight: 600;
        color: #495057;
    }

    #filtersCollapse {
        border-top: 1px solid #dee2e6;
    }

    .badge.bg-info {
        font-size: 0.9rem;
    }
</style>
@endpush

@push('scripts')
<script>
    // Gestion de l'affichage conditionnel des filtres
    document.addEventListener('DOMContentLoaded', function() {
        const periodType = document.getElementById('period_type');
        const yearFilter = document.getElementById('yearFilter');
        const weekFilter = document.getElementById('weekFilter');
        const monthFilter = document.getElementById('monthFilter');
        const dayFilter = document.getElementById('dayFilter');
        const customStartDate = document.getElementById('customStartDate');
        const customEndDate = document.getElementById('customEndDate');

        function toggleFilters() {
            const selectedType = periodType.value;

            // Masquer tous les filtres par défaut
            yearFilter.style.display = 'none';
            weekFilter.style.display = 'none';
            monthFilter.style.display = 'block';
            dayFilter.style.display = 'none';
            customStartDate.style.display = 'none';
            customEndDate.style.display = 'none';

            // Afficher les filtres appropriés
            switch(selectedType) {
                case 'week':
                    yearFilter.style.display = 'block';
                    weekFilter.style.display = 'block';
                    break;
                case 'month':
                    yearFilter.style.display = 'block';
                    monthFilter.style.display = 'block';
                    break;
                case 'day':
                    dayFilter.style.display = 'block';
                    break;
                case 'custom':
                    customStartDate.style.display = 'block';
                    customEndDate.style.display = 'block';
                    break;
            }
        }

        // Initialiser l'affichage au chargement
        toggleFilters();

        // Écouter les changements
        periodType.addEventListener('change', toggleFilters);

        // Gestion de l'export filtré
        document.getElementById('ExportFiltered').addEventListener('click', function(e) {
            e.preventDefault();

            // Récupérer tous les paramètres du formulaire
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);

            // Construire l'URL d'export avec les paramètres de filtre
            const exportUrl = `{{ route('company.times.qrcode-pointage.export') }}?${params.toString()}`;

            // Déclencher le téléchargement
            window.location.href = exportUrl;
        });

        // Fonction pour mettre à jour la barre de progression
        function updateProgressBar() {
            var progressBar = document.getElementById("progressBar");
            var progressContainer = document.getElementById("progressBarContainer");
            var width = 0;

            // Afficher la barre de progression
            progressContainer.style.display = 'block';

            var interval = setInterval(function() {
                if (width >= 100) {
                    clearInterval(interval);
                    // Exécuter le lien une fois la barre de progression terminée
                    window.location.href = document.getElementById("syncLink").href;
                } else {
                    width += 10; // Augmenter progressivement
                    progressBar.style.width = width + '%';
                    progressBar.innerHTML = width + '%';
                }
            }, 500); // Mise à jour plus rapide pour une animation plus fluide
        }

        // Fonction pour déclencher la synchronisation automatique toutes les 30 minutes
        function syncEvery30Minutes() {
            setInterval(function() {
                console.log("Début de la synchronisation automatique");
                updateProgressBar();
            }, 30 * 60 * 1000); // 30 minutes en millisecondes
        }

        // Ajouter un écouteur d'événement au bouton de synchronisation
        var syncButton = document.getElementById('syncLink');
        if (syncButton) {
            syncButton.addEventListener('click', function(e) {
                e.preventDefault();
                updateProgressBar();
            });
        }

        // Démarrer la synchronisation automatique
        syncEvery30Minutes();

        // Afficher/masquer les messages de résultat
        var resultAction = document.getElementById('result_action');
        if (resultAction && resultAction.innerHTML.trim() !== '') {
            resultAction.style.display = 'block';

            // Masquer après 5 secondes
            setTimeout(function() {
                resultAction.style.display = 'none';
            }, 5000);
        }

        // Désactiver le bouton de synchronisation si nécessaire
        if (syncButton && {{ \Auth::check() ? 'true' : 'false' }}) {
            syncButton.classList.add('disabled');
            syncButton.setAttribute('aria-disabled', 'true');
            syncButton.style.pointerEvents = 'none';
            syncButton.style.opacity = '0.6';
        }
    });

    // Fonction helper pour obtenir le numéro de semaine
    function getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }
</script>
@endpush
