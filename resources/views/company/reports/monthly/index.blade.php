@extends('layouts.app')

@section('title', 'Rapports Mensuels des Pointages - GP Flow')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Messages de notification flash --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="ti ti-circle-check fs-4 me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="ti ti-alert-triangle fs-4 me-2"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center mb-1">
                <i class="ti ti-alert-circle fs-4 me-2"></i>
                <h6 class="mb-0 text-danger">Veuillez corriger les erreurs suivantes :</h6>
            </div>
            <ul class="mb-0 ps-4">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- En-tête de la page --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);">
                <div class="card-body py-4 px-4 text-white">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-white text-primary fw-semibold px-2 py-1">
                                    <i class="ti ti-calendar-stats me-1"></i>Assiduité & Présence
                                </span>
                                @if(isset($config) && $config->is_enabled && $config->auto_send_enabled)
                                    <span class="badge bg-success text-white px-2 py-1">
                                        <i class="ti ti-check me-1"></i>Envoi automatique actif
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark px-2 py-1">
                                        <i class="ti ti-alert-circle me-1"></i>Envoi automatique inactif
                                    </span>
                                @endif
                            </div>
                            <h3 class="fw-bold text-white mb-1">Rapports Mensuels des Pointages</h3>
                            <p class="text-white-50 mb-0">Consultez l'historique complet, téléchargez les bilans mensuels et générez vos synthèses RH à la demande.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-light text-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#generateMonthlyReportModal">
                                <i class="ti ti-player-play me-1"></i>Générer un rapport
                            </button>
                            <a href="{{ Route::has('company.reports.monthly.settings') ? route('company.reports.monthly.settings') : url('/company/reports/monthly/settings') }}" class="btn btn-outline-light">
                                <i class="ti ti-settings me-1"></i>Configuration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cartes statistiques rapides --}}
    <div class="row g-3 mb-4">
        {{-- Total générés --}}
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3 rounded p-2 bg-label-primary text-primary d-flex align-items-center justify-content-center">
                            <i class="ti ti-files fs-3"></i>
                        </div>
                        <div>
                            <span class="text-muted d-block small">Total générés</span>
                            <h4 class="card-title mb-0 fw-bold">{{ $histories->total() }}</h4>
                        </div>
                    </div>
                    <div class="mt-3 text-muted small">
                        <i class="ti ti-database me-1"></i>Rapports archivés en base
                    </div>
                </div>
            </div>
        </div>

        {{-- Dernière génération --}}
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3 rounded p-2 bg-label-info text-info d-flex align-items-center justify-content-center">
                            <i class="ti ti-clock-check fs-3"></i>
                        </div>
                        <div>
                            <span class="text-muted d-block small">Dernière génération</span>
                            <h5 class="card-title mb-0 fw-bold">
                                @if($histories->first())
                                    @php
                                        $lastGen = $histories->first()->generated_at ?? $histories->first()->created_at;
                                    @endphp
                                    {{ $lastGen ? $lastGen->format('d/m/Y') : '—' }}
                                    <small class="text-muted fs-6 fw-normal">{{ $lastGen ? $lastGen->format('H:i') : '' }}</small>
                                @else
                                    <span class="text-muted">Aucune</span>
                                @endif
                            </h5>
                        </div>
                    </div>
                    <div class="mt-3 text-muted small">
                        @if($histories->first())
                            <span class="badge bg-label-secondary">{{ $histories->first()->report_month }}</span>
                            {{ $histories->first()->is_manual ? 'Génération manuelle' : 'Génération automatique' }}
                        @else
                            Aucun rapport produit à ce jour
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Prochain envoi prévu --}}
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div class="avatar avatar-md me-3 rounded p-2 bg-label-warning text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="min-width:40px;">
                            <i class="ti ti-calendar-event fs-3"></i>
                        </div>
                        <div class="overflow-hidden">
                            <span class="text-muted d-block small">Prochain envoi prévu</span>
                            <p class="card-title mb-0 fw-bold lh-sm" style="font-size:0.9rem; word-break:break-word; white-space:normal;">
                                {{ $nextScheduledDate ?? 'Non configuré' }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 small">
                        @if(isset($config) && $config->is_enabled && $config->auto_send_enabled)
                            <span class="text-success"><i class="ti ti-circle-check me-1"></i>Planifié (le {{ $config->send_day ?? 2 }} du mois)</span>
                        @else
                            <span class="text-muted"><i class="ti ti-circle-x me-1"></i>Envoi automatique désactivé</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Canal & Destinataires --}}
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3 rounded p-2 bg-label-success text-success d-flex align-items-center justify-content-center">
                            <i class="ti ti-mail-forward fs-3"></i>
                        </div>
                        <div>
                            <span class="text-muted d-block small">Destinataires configurés</span>
                            <h4 class="card-title mb-0 fw-bold">
                                {{ isset($config) && is_array($config->recipients) ? count($config->recipients) : 0 }}
                            </h4>
                        </div>
                    </div>
                    <div class="mt-3 text-muted small d-flex justify-content-between align-items-center">
                        <span>Format : <strong>{{ isset($config) ? strtoupper($config->report_format) : 'BOTH' }}</strong></span>
                        <a href="{{ Route::has('company.reports.monthly.settings') ? route('company.reports.monthly.settings') : url('/company/reports/monthly/settings') }}" class="text-primary text-decoration-none">Modifier</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tableau de l'historique des rapports --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h5 class="card-title mb-0 fw-bold text-primary">
                    <i class="ti ti-history me-2"></i>Historique des Rapports Mensuels
                </h5>
                <small class="text-muted">Retrouvez et téléchargez tous les bilans générés pour votre entreprise</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                    <i class="ti ti-refresh me-1"></i>Actualiser
                </button>
            </div>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3">Période</th>
                        <th class="py-3">Date de génération</th>
                        <th class="py-3">Type</th>
                        <th class="py-3">Format</th>
                        <th class="py-3 text-center">Statut Génération</th>
                        <th class="py-3 text-center">Statut Envoi</th>
                        <th class="py-3">Destinataires</th>
                        <th class="py-3 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($histories as $history)
                        @php
                            try {
                                $carbonPeriod = \Carbon\Carbon::createFromFormat('Y-m', $history->report_month)->locale('fr');
                                $monthLabel = ucfirst($carbonPeriod->translatedFormat('F Y'));
                            } catch (\Exception $e) {
                                $monthLabel = $history->report_month;
                            }
                        @endphp
                        <tr>
                            {{-- Période --}}
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm rounded bg-label-primary text-primary me-2 d-flex align-items-center justify-content-center">
                                        <i class="ti ti-calendar"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark d-block">{{ $monthLabel }}</span>
                                        <small class="text-muted">
                                            @if($history->period_start && $history->period_end)
                                                {{ \Carbon\Carbon::parse($history->period_start)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($history->period_end)->format('d/m/Y') }}
                                            @else
                                                Mois : {{ $history->report_month }}
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            </td>

                            {{-- Date de génération --}}
                            <td>
                                @php
                                    $genDate = $history->generated_at ?? $history->created_at;
                                @endphp
                                @if($genDate)
                                    <span class="fw-medium text-dark d-block">{{ $genDate->format('d/m/Y') }}</span>
                                    <small class="text-muted"><i class="ti ti-clock me-1"></i>{{ $genDate->format('H:i') }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- Type d'exécution (Manuel vs Auto) --}}
                            <td>
                                @if($history->is_manual)
                                    <span class="badge bg-label-info text-info">
                                        <i class="ti ti-hand-finger me-1"></i>Manuel
                                    </span>
                                @else
                                    <span class="badge bg-label-dark">
                                        <i class="ti ti-robot me-1"></i>Automatique
                                    </span>
                                @endif
                            </td>

                            {{-- Format --}}
                            <td>
                                @if($history->format === 'pdf')
                                    <span class="badge bg-label-danger">
                                        <i class="ti ti-file-type-pdf me-1"></i>PDF
                                    </span>
                                @elseif($history->format === 'excel')
                                    <span class="badge bg-label-success">
                                        <i class="ti ti-file-spreadsheet me-1"></i>Excel
                                    </span>
                                @else
                                    <span class="badge bg-label-primary">
                                        <i class="ti ti-files me-1"></i>PDF + Excel
                                    </span>
                                @endif
                            </td>

                            {{-- Statut Génération --}}
                            <td class="text-center">
                                @if($history->generation_status === 'success')
                                    <span class="badge bg-success">
                                        <i class="ti ti-circle-check me-1"></i>Généré
                                    </span>
                                @elseif($history->generation_status === 'pending')
                                    <span class="badge bg-warning text-dark">
                                        <i class="ti ti-clock me-1"></i>En attente
                                    </span>
                                @elseif($history->generation_status === 'failed')
                                    <span class="badge bg-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $history->error_message ?? 'Erreur lors de la génération' }}">
                                        <i class="ti ti-alert-circle me-1"></i>Échec
                                    </span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($history->generation_status) }}</span>
                                @endif
                            </td>

                            {{-- Statut Envoi --}}
                            <td class="text-center">
                                @if($history->send_status === 'sent')
                                    <span class="badge bg-success">
                                        <i class="ti ti-send me-1"></i>Envoyé
                                    </span>
                                @elseif($history->send_status === 'pending')
                                    <span class="badge bg-warning text-dark">
                                        <i class="ti ti-hourglass-empty me-1"></i>En cours
                                    </span>
                                @elseif($history->send_status === 'skipped')
                                    <span class="badge bg-secondary" title="Non envoyé par e-mail">
                                        <i class="ti ti-circle-minus me-1"></i>Non envoyé
                                    </span>
                                @elseif($history->send_status === 'failed')
                                    <span class="badge bg-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $history->error_message ?? 'Échec lors de la distribution par e-mail' }}">
                                        <i class="ti ti-mail-off me-1"></i>Échec
                                    </span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($history->send_status) }}</span>
                                @endif
                            </td>

                            {{-- Destinataires --}}
                            <td>
                                @if(!empty($history->recipients_sent) && is_array($history->recipients_sent))
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-label-secondary" data-bs-toggle="tooltip" data-bs-html="true" title="{{ implode('<br>', $history->recipients_sent) }}">
                                            <i class="ti ti-mail me-1"></i>{{ count($history->recipients_sent) }} destinataire(s)
                                        </span>
                                    </div>
                                @else
                                    <span class="text-muted small">Aucun envoi</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                    {{-- Téléchargement PDF --}}
                                    @if($history->file_path_pdf || in_array($history->format, ['pdf', 'both']))
                                        <a href="{{ Route::has('company.reports.monthly.download.pdf') ? route('company.reports.monthly.download.pdf', $history->id) : url('/company/reports/monthly/download/' . $history->id . '/pdf') }}"
                                           class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1"
                                           target="_blank">
                                            <i class="ti ti-file-type-pdf"></i>
                                            <span>PDF</span>
                                        </a>
                                    @endif

                                    {{-- Téléchargement Excel --}}
                                    @if($history->file_path_excel || in_array($history->format, ['excel', 'both']))
                                        <a href="{{ Route::has('company.reports.monthly.download.excel') ? route('company.reports.monthly.download.excel', $history->id) : url('/company/reports/monthly/download/' . $history->id . '/excel') }}"
                                           class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1">
                                            <i class="ti ti-file-spreadsheet"></i>
                                            <span>Excel</span>
                                        </a>
                                    @endif

                                    {{-- Bouton Informations détaillées --}}
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#historyDetailsModal{{ $history->id }}">
                                        <i class="ti ti-info-circle"></i>
                                        <span>Détails</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="my-4">
                                    <div class="avatar avatar-xl bg-label-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                        <i class="ti ti-file-analytics fs-1"></i>
                                    </div>
                                    <h5 class="fw-bold mb-1">Aucun rapport mensuel généré</h5>
                                    <p class="text-muted mb-3">Vous n'avez pas encore généré de rapport mensuel pour votre entreprise.</p>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateMonthlyReportModal">
                                            <i class="ti ti-player-play me-1"></i>Générer le premier rapport
                                        </button>
                                        <a href="{{ Route::has('company.reports.monthly.settings') ? route('company.reports.monthly.settings') : url('/company/reports/monthly/settings') }}" class="btn btn-outline-primary">
                                            <i class="ti ti-settings me-1"></i>Configurer l'envoi automatique
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($histories->hasPages())
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Affichage de {{ $histories->firstItem() }} à {{ $histories->lastItem() }} sur {{ $histories->total() }} rapports
                </small>
                <div>
                    {{ $histories->withQueryString()->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Modaux d'informations détaillées pour chaque rapport --}}
@foreach($histories as $history)
    @php
        try {
            $modalPeriod = \Carbon\Carbon::createFromFormat('Y-m', $history->report_month)->locale('fr');
            $modalMonthLabel = ucfirst($modalPeriod->translatedFormat('F Y'));
        } catch (\Exception $e) {
            $modalMonthLabel = $history->report_month;
        }
    @endphp
    <div class="modal fade" id="historyDetailsModal{{ $history->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-primary">
                        <i class="ti ti-file-description me-2"></i>Détails du rapport : {{ $modalMonthLabel }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Mois cible</span>
                            <span class="fw-semibold">{{ $modalMonthLabel }} ({{ $history->report_month }})</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Période exacte</span>
                            <span class="fw-semibold">
                                {{ $history->period_start ? \Carbon\Carbon::parse($history->period_start)->format('d/m/Y') : '—' }} au {{ $history->period_end ? \Carbon\Carbon::parse($history->period_end)->format('d/m/Y') : '—' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Date & Heure d'exécution</span>
                            <span class="fw-semibold">
                                {{ ($history->generated_at ?? $history->created_at)->format('d/m/Y à H:i:s') }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Déclenchement</span>
                            <span class="badge {{ $history->is_manual ? 'bg-label-info' : 'bg-label-dark' }}">
                                {{ $history->is_manual ? 'Manuel (À la demande)' : 'Automatique (Cron)' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Statut de la génération</span>
                            @if($history->generation_status === 'success')
                                <span class="badge bg-success">Réussie</span>
                            @elseif($history->generation_status === 'pending')
                                <span class="badge bg-warning text-dark">En attente</span>
                            @else
                                <span class="badge bg-danger">Échec</span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Statut de diffusion</span>
                            @if($history->send_status === 'sent')
                                <span class="badge bg-success">Transmis par e-mail</span>
                            @elseif($history->send_status === 'skipped')
                                <span class="badge bg-secondary">Non envoyé (option désactivée)</span>
                            @elseif($history->send_status === 'pending')
                                <span class="badge bg-warning text-dark">En cours d'envoi</span>
                            @else
                                <span class="badge bg-danger">Échec d'envoi</span>
                            @endif
                        </li>
                    </ul>

                    {{-- En cas d'erreur --}}
                    @if($history->error_message)
                        <div class="alert alert-danger p-2 small mb-3">
                            <strong><i class="ti ti-bug me-1"></i>Erreur rencontrée :</strong><br>
                            {{ $history->error_message }}
                        </div>
                    @endif

                    {{-- Destinataires effectifs --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Destinataires e-mail :</label>
                        @if(!empty($history->recipients_sent) && is_array($history->recipients_sent))
                            <div class="p-2 bg-light rounded small">
                                @foreach($history->recipients_sent as $email)
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="ti ti-check text-success me-1"></i>{{ $email }}
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted small mb-0">Aucun destinataire enregistré pour cet archivage.</p>
                        @endif
                    </div>

                    {{-- Fichiers disponibles --}}
                    <div>
                        <label class="form-label fw-bold text-dark small">Fichiers générés :</label>
                        <div class="d-flex gap-2">
                            @if($history->file_path_pdf || in_array($history->format, ['pdf', 'both']))
                                <a href="{{ Route::has('company.reports.monthly.download.pdf') ? route('company.reports.monthly.download.pdf', $history->id) : url('/company/reports/monthly/download/' . $history->id . '/pdf') }}"
                                   class="btn btn-sm btn-outline-danger flex-grow-1" target="_blank">
                                    <i class="ti ti-file-type-pdf me-1"></i>Télécharger PDF
                                </a>
                            @endif
                            @if($history->file_path_excel || in_array($history->format, ['excel', 'both']))
                                <a href="{{ Route::has('company.reports.monthly.download.excel') ? route('company.reports.monthly.download.excel', $history->id) : url('/company/reports/monthly/download/' . $history->id . '/excel') }}"
                                   class="btn btn-sm btn-outline-success flex-grow-1">
                                    <i class="ti ti-file-spreadsheet me-1"></i>Télécharger Excel
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

{{-- Modal de génération manuelle --}}
<div class="modal fade" id="generateMonthlyReportModal" tabindex="-1" aria-labelledby="generateMonthlyReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); color: white;">
                <h5 class="modal-title text-white fw-bold" id="generateMonthlyReportModalLabel">
                    <i class="ti ti-player-play me-2"></i>Générer un Rapport Mensuel
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ Route::has('company.reports.monthly.generate') ? route('company.reports.monthly.generate') : url('/company/reports/monthly/generate') }}" method="POST" id="formGenerateMonthlyReport">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">
                        Sélectionnez le mois à consolider et le format souhaité. La génération traitera l'ensemble des pointages et anomalies de la période.
                    </p>

                    {{-- Sélection du mois --}}
                    <div class="mb-3">
                        <label for="report_month" class="form-label fw-bold">Mois concerné <span class="text-danger">*</span></label>
                        <select class="form-select @error('month') is-invalid @enderror" id="report_month" name="month" required>
                            @php
                                $currentDate = \Carbon\Carbon::now();
                                $defaultMonth = $currentDate->copy()->subMonth()->format('Y-m');
                            @endphp
                            @for($i = 0; $i < 12; $i++)
                                @php
                                    $monthCarbon = $currentDate->copy()->subMonths($i);
                                    $valueYm = $monthCarbon->format('Y-m');
                                    $label = ucfirst($monthCarbon->locale('fr')->translatedFormat('F Y'));
                                @endphp
                                <option value="{{ $valueYm }}" {{ $valueYm === $defaultMonth ? 'selected' : '' }}>
                                    {{ $label }} {{ $i === 1 ? '(Dernier mois clos)' : '' }}
                                </option>
                            @endfor
                        </select>
                        <small class="text-muted">Par défaut, le dernier mois complet écoulé est présélectionné.</small>
                        @error('month')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Sélection du format --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold">Format du document <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="format" id="format_both" value="both" checked>
                                <label class="btn btn-outline-primary w-100 py-2 d-flex flex-column align-items-center justify-content-center" for="format_both">
                                    <i class="ti ti-files fs-4 mb-1"></i>
                                    <span class="small fw-semibold">Les deux</span>
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="format" id="format_pdf" value="pdf">
                                <label class="btn btn-outline-danger w-100 py-2 d-flex flex-column align-items-center justify-content-center" for="format_pdf">
                                    <i class="ti ti-file-type-pdf fs-4 mb-1"></i>
                                    <span class="small fw-semibold">PDF seul</span>
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="format" id="format_excel" value="excel">
                                <label class="btn btn-outline-success w-100 py-2 d-flex flex-column align-items-center justify-content-center" for="format_excel">
                                    <i class="ti ti-file-spreadsheet fs-4 mb-1"></i>
                                    <span class="small fw-semibold">Excel seul</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Email de test optionnel --}}
                    <div class="mb-3">
                        <label for="test_email" class="form-label fw-bold">
                            Envoyer un exemplaire par e-mail
                            <span class="badge bg-label-secondary fw-normal ms-1">Optionnel</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-mail"></i></span>
                            <input type="email" class="form-control @error('test_email') is-invalid @enderror" id="test_email" name="test_email" placeholder="ex: direction@entreprise.com" value="{{ old('test_email') }}">
                        </div>
                        <small class="text-muted d-block mt-1">
                            Si renseigné, une copie des rapports sera immédiatement acheminée à cette adresse.
                        </small>
                        @error('test_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitGenerate">
                        <i class="ti ti-player-play me-1"></i>Lancer la génération
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialisation des tooltips Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Gestion du submit avec indicateur de chargement
        const formGenerate = document.getElementById('formGenerateMonthlyReport');
        const btnSubmit = document.getElementById('btnSubmitGenerate');
        if (formGenerate && btnSubmit) {
            formGenerate.addEventListener('submit', function () {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Traitement en cours...';
            });
        }
    });
</script>
@endpush
@endsection
