@extends('layouts.app')

@section('title', 'Jours fériés | GP Flow')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1"><i class="fas fa-calendar-day me-2 text-primary"></i>Jours fériés &amp; chômés</h4>
                <p class="text-muted mb-0">
                    Les dates déclarées ici sont retirées des jours ouvrés : un employé qui ne pointe pas
                    ces jours-là n'est plus compté absent.
                </p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                <i class="fas fa-plus me-1"></i>Ajouter un jour férié
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Année {{ $year }}</h5>
            <form method="GET" action="{{ route('company.times.holidays.index') }}" class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 me-1 text-muted small">Année</label>
                <select name="year" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="card-body">
            @if($holidays->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th>Date</th>
                            <th>Fin (pont)</th>
                            <th>Récurrence</th>
                            <th>Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($holidays as $holiday)
                        <tr>
                            <td>
                                <strong>{{ $holiday->name }}</strong>
                                @if($holiday->description)
                                    <br><small class="text-muted">{{ $holiday->description }}</small>
                                @endif
                            </td>
                            <td>{{ $holiday->date->format('d/m/Y') }}</td>
                            <td>
                                @if($holiday->end_date)
                                    {{ $holiday->end_date->format('d/m/Y') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($holiday->is_recurring)
                                    <span class="badge bg-label-info" title="Rejoué automatiquement chaque année">
                                        <i class="fas fa-sync-alt me-1"></i>Chaque année
                                    </span>
                                @else
                                    <span class="badge bg-label-secondary">Ponctuel</span>
                                @endif

                                @if($holiday->is_worked)
                                    <span class="badge bg-label-warning" title="L'entreprise reste ouverte : les employés sont attendus et une absence est signalée">
                                        <i class="fas fa-briefcase me-1"></i>Travaillé
                                    </span>
                                @else
                                    <span class="badge bg-label-secondary" title="L'entreprise ferme : aucune absence n'est comptée">
                                        <i class="fas fa-door-closed me-1"></i>Chômé
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($holiday->is_active)
                                    <span class="badge bg-label-success">Actif</span>
                                @else
                                    <span class="badge bg-label-danger">Inactif</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-icon btn-label-primary btn-sm me-1"
                                        data-bs-toggle="modal" data-bs-target="#editHolidayModal{{ $holiday->id }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('company.times.holidays.destroy', $holiday->id) }}"
                                      method="POST" class="d-inline"
                                      onsubmit="return confirm('Supprimer « {{ $holiday->name }} » ?\n\nCe jour redeviendra un jour ouvré et les employés sans pointage y seront comptés absents.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-label-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Aucun jour férié déclaré pour {{ $year }}. Tant qu'aucune date n'est saisie, chaque jour de
                semaine sans pointage est compté comme une <strong>absence injustifiée</strong>.
            </div>
            @endif
        </div>
    </div>
</div>


@foreach($holidays as $holiday)
<div class="modal fade" id="editHolidayModal{{ $holiday->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('company.times.holidays.update', $holiday->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier « {{ $holiday->name }} »</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Libellé <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ $holiday->name }}" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" value="{{ $holiday->date->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date de fin</label>
                            <input type="date" name="end_date" class="form-control"
                                   value="{{ $holiday->end_date ? $holiday->end_date->format('Y-m-d') : '' }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ $holiday->description }}</textarea>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_recurring" value="1"
                               id="recurring{{ $holiday->id }}" {{ $holiday->is_recurring ? 'checked' : '' }}>
                        <label class="form-check-label" for="recurring{{ $holiday->id }}">
                            Se répète chaque année (date fixe)
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_worked" value="1"
                               id="worked{{ $holiday->id }}" {{ $holiday->is_worked ? 'checked' : '' }}>
                        <label class="form-check-label" for="worked{{ $holiday->id }}">
                            Jour férié travaillé — l'entreprise reste ouverte
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                               id="active{{ $holiday->id }}" {{ $holiday->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="active{{ $holiday->id }}">Actif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- Modal d'ajout --}}
<div class="modal fade" id="addHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('company.times.holidays.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Nouveau jour férié</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Libellé <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Fête du Travail, Indépendance…" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date de fin</label>
                            <input type="date" name="end_date" class="form-control">
                            <small class="text-muted">À remplir uniquement pour un pont de plusieurs jours.</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="addRecurring">
                        <label class="form-check-label" for="addRecurring">
                            Se répète chaque année (date fixe, ex. 1<sup>er</sup> janvier)
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_worked" value="1" id="addWorked">
                        <label class="form-check-label" for="addWorked">
                            <strong>Jour férié travaillé</strong> — l'entreprise reste ouverte
                        </label>
                        <div class="form-text">
                            Décoché (cas courant) : l'entreprise ferme, la journée s'affiche « Férié » et
                            aucune absence n'est comptée.<br>
                            Coché : la journée compte comme un jour ouvré normal, les employés sont
                            attendus et une absence est signalée s'ils ne pointent pas.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

