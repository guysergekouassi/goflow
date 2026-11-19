@extends($superAdmin ? 'layouts.super-admin' : 'layouts.app')

@section('title', 'Récapitulatifs des accès')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="mb-4">
            <h4 class="mb-1 text-primary"><i class="fas fa-key me-2"></i>Récapitulatifs des accès</h4>
            <p class="text-muted mb-0">
                Identifiants et mots de passe provisoires des comptes ouverts depuis RHFlow.
            </p>
        </div>

        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="fas fa-shield-alt fs-5 mt-1"></i>
            <div class="small">
                <strong>Document confidentiel.</strong>
                Chaque fichier contient des mots de passe en clair : remettez-les individuellement, puis supprimez le fichier.
                Les récapitulatifs sont effacés automatiquement {{ \App\Services\ProvisionnementRhflowService::DUREE_LOT_JOURS }} jours après leur création ;
                passé ce délai, un mot de passe oublié se réinitialise depuis la fiche du salarié.
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Récapitulatif</th>
                            @if ($superAdmin)
                                <th>Société</th>
                            @endif
                            <th class="text-center">Comptes</th>
                            <th>Expire le</th>
                            <th>Dernier téléchargement</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lots as $lot)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $lot->libelle }}</div>
                                    <small class="text-muted">Créé le {{ $lot->created_at->format('d/m/Y à H:i') }}</small>
                                </td>
                                @if ($superAdmin)
                                    <td>{{ $lot->company->name ?? 'Société #' . $lot->company_id }}</td>
                                @endif
                                <td class="text-center"><span class="badge bg-label-primary">{{ $lot->nb_comptes }}</span></td>
                                <td>{{ $lot->expire_le->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if ($lot->telecharge_le)
                                        {{ $lot->telecharge_le->format('d/m/Y H:i') }}
                                        <small class="text-muted">({{ $lot->telechargements }} fois)</small>
                                    @else
                                        <span class="text-muted">Jamais</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('acces-gpflow.telecharger', $lot) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-file-excel me-1"></i>Télécharger
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $superAdmin ? 6 : 5 }}" class="text-center text-muted py-4">
                                    Aucun récapitulatif disponible.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
