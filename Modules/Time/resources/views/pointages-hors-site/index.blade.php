@extends('layouts.app')

@section('title', 'Pointages hors site | GP Flow')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Pointages hors site</h4>
            <p class="text-muted mb-0">
                Pointages GPS faits en dehors de tout site connu : visite chez un client, autre site,
                localité qui a changé… Ils ne comptent dans les présences qu'une fois validés.
                @if($user->type !== 'company')
                    Seuls les pointages qui vous sont confiés apparaissent ici.
                @endif
            </p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-1"></i>{{ $errors->first() }}
        </div>
    @endif

    <ul class="nav nav-pills mb-3">
        @foreach($statuts as $cle => $libelle)
            <li class="nav-item">
                <a class="nav-link {{ $status === $cle ? 'active' : '' }}"
                   href="{{ route('company.times.pointages-hors-site.index', ['status' => $cle]) }}">
                    {{ $libelle }}
                    <span class="badge {{ $status === $cle ? 'bg-white text-primary' : 'bg-label-secondary' }} ms-1">{{ $compteurs[$cle] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-body">
            @if($pointages->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Pointage</th>
                            <th>Position</th>
                            <th>Motif</th>
                            <th>{{ $status === 'en_attente' ? 'Confié à' : 'Décision' }}</th>
                            @if($status === 'en_attente')
                                <th class="text-center">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pointages as $pointage)
                        <tr>
                            <td>
                                <strong>{{ $pointage->employee?->name ?? '—' }}</strong>
                                @if($pointage->employee?->department)
                                    <br><small class="text-muted">{{ $pointage->employee->department->name }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $pointage->type === 'sortie' ? 'bg-label-danger' : 'bg-label-success' }}">
                                    <i class="fas {{ $pointage->type === 'sortie' ? 'fa-sign-out-alt' : 'fa-sign-in-alt' }} me-1"></i>{{ $pointage->libelleType() }}
                                </span>
                                <br>{{ $pointage->pointed_at->format('d/m/Y') }}
                                <strong>{{ $pointage->pointed_at->format('H:i') }}</strong>
                                @if($pointage->scan_status === 'retard')
                                    <br><small class="text-warning">Retard de {{ $pointage->duree_retard }} min</small>
                                @elseif($pointage->scan_status === 'en_avance')
                                    <br><small class="text-warning">Départ anticipé de {{ $pointage->duree_retard }} min</small>
                                @endif
                            </td>
                            <td>
                                <a href="{{ $pointage->mapsUrl() }}" target="_blank" rel="noopener">
                                    <i class="fas fa-map me-1"></i>Voir sur la carte
                                </a>
                                @if($pointage->gps_accuracy !== null)
                                    <small class="text-muted">(± {{ $pointage->gps_accuracy }} m)</small>
                                @endif
                                <br>
                                <small class="text-muted">
                                    @if($pointage->nearestLocation)
                                        À {{ $pointage->nearest_distance >= 1000 ? number_format($pointage->nearest_distance / 1000, 1, ',', ' ') . ' km' : $pointage->nearest_distance . ' m' }}
                                        de « {{ $pointage->nearestLocation->name }} »
                                    @else
                                        Aucun site connu à proximité
                                    @endif
                                </small>
                            </td>
                            <td style="max-width: 260px;">{{ $pointage->motif }}</td>
                            <td>
                                @if($status === 'en_attente')
                                    {{ $pointage->validator?->name ?? 'Compte entreprise' }}
                                    @if($pointage->validator_level)
                                        <br><small class="text-muted">
                                            {{ ['service' => 'Gestionnaire du service', 'succursale' => 'Gestionnaire de la succursale', 'entreprise' => 'Compte entreprise'][$pointage->validator_level] ?? '' }}
                                        </small>
                                    @endif
                                @else
                                    <span class="badge {{ $pointage->status === 'valide' ? 'bg-label-success' : 'bg-label-danger' }}">{{ $pointage->libelleStatut() }}</span>
                                    <br><small class="text-muted">
                                        par {{ $pointage->decidedBy?->name ?? '—' }}
                                        @if($pointage->decided_at) le {{ $pointage->decided_at->format('d/m/Y à H:i') }} @endif
                                    </small>
                                    @if($pointage->decision_comment)
                                        <br><small>« {{ $pointage->decision_comment }} »</small>
                                    @endif
                                @endif
                            </td>
                            @if($status === 'en_attente')
                                <td class="text-center text-nowrap">
                                    @if($service->canDecide($user, $pointage))
                                        <form action="{{ route('company.times.pointages-hors-site.decide', $pointage->id) }}"
                                              method="POST" class="d-inline"
                                              onsubmit="return confirm('Valider ce pointage ? Il sera compté dans les présences de {{ addslashes($pointage->employee?->name ?? 'l\'employé') }}.');">
                                            @csrf
                                            <input type="hidden" name="decision" value="valider">
                                            <button type="submit" class="btn btn-sm btn-success me-1">
                                                <i class="fas fa-check me-1"></i>Valider
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-label-danger"
                                                data-bs-toggle="modal" data-bs-target="#refusModal{{ $pointage->id }}">
                                            <i class="fas fa-times me-1"></i>Refuser
                                        </button>
                                    @else
                                        <span class="text-muted small">Confié à un autre supérieur</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $pointages->links() }}
            </div>
            @else
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-1"></i>
                @if($status === 'en_attente')
                    Aucun pointage hors site en attente de validation.
                @else
                    Aucun pointage hors site {{ $status === 'valide' ? 'validé' : 'refusé' }} pour le moment.
                @endif
            </div>
            @endif
        </div>
    </div>
</div>

@if($status === 'en_attente')
    @foreach($pointages as $pointage)
        @if($service->canDecide($user, $pointage))
        <div class="modal fade" id="refusModal{{ $pointage->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('company.times.pointages-hors-site.decide', $pointage->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="decision" value="refuser">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Refuser le pointage de {{ $pointage->employee?->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-2">
                                {{ $pointage->libelleType() }} du {{ $pointage->pointed_at->format('d/m/Y à H:i') }} —
                                motif de l'employé : « {{ $pointage->motif }} »
                            </p>
                            <label class="form-label">Raison du refus <span class="text-danger">*</span></label>
                            <textarea name="commentaire" class="form-control" rows="3" maxlength="500" required
                                      placeholder="Elle sera transmise à l'employé."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-danger">Refuser le pointage</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif
    @endforeach
@endif
@endsection
