@extends('layouts.app')

@section('title', 'Sites & Succursales - GP Flow')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- En-tête --> 
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">🏢 Sites & Succursales</h4>
                    <p class="text-muted mb-0">Gérez les différents sites de votre entreprise</p>
                    <small class="text-primary">
                        <i class="fas fa-calendar me-1"></i>
                        {{ now()->format('l d F Y') }} •
                        <i class="fas fa-clock me-1"></i>
                        {{ now()->format('H:i') }}
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('company.settings.config') }}" class="btn btn-outline-info">
                        <i class="fas fa-arrow-left me-1"></i>Retour
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBranchModal">
                        <i class="fas fa-plus me-1"></i>Nouvelle Succursale
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                        <div class="avatar-initial bg-label-primary rounded">
                            <i class="fas fa-building fa-28px"></i>
                        </div>
                    </div>
                    <h3 class="mb-1 text-primary">{{ $stats['total_branches'] }}</h3>
                    <p class="text-muted mb-2">Total Sites</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                        <div class="avatar-initial bg-label-success rounded">
                            <i class="fas fa-check-circle fa-28px"></i>
                        </div>
                    </div>
                    <h3 class="mb-1 text-success">{{ $stats['active_branches'] }}</h3>
                    <p class="text-muted mb-2">Sites Actifs</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                        <div class="avatar-initial bg-label-warning rounded">
                            <i class="fas fa-pause-circle fa-28px"></i>
                        </div>
                    </div>
                    <h3 class="mb-1 text-warning">{{ $stats['inactive_branches'] }}</h3>
                    <p class="text-muted mb-2">Sites Inactifs</p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                        <div class="avatar-initial bg-label-info rounded">
                            <i class="fas fa-users fa-28px"></i>
                        </div>
                    </div>
                    <h3 class="mb-1 text-info">{{ $company->departments()->count() }}</h3>
                    <p class="text-muted mb-2">Services Totaux</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des Succursales -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">📋 Sites Configurés</h5>
                    <span class="badge bg-label-primary">{{ $stats['total_branches'] }} sites</span>
                </div>
                <div class="card-body">
                    @if($branches->count() > 0)
                        <div class="row">
                            @foreach($branches as $branch)
                            <div class="col-xl-6 col-lg-6 mb-4">
                                <div class="card border h-100 {{ $branch->is_active ? 'border-primary' : 'border-secondary' }}">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm me-3" style="width: 40px; height: 40px;">
                                                    <div class="avatar-initial {{ $branch->is_active ? 'bg-label-primary' : 'bg-label-secondary' }} rounded">
                                                        <i class="fas fa-building fa-20px"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">{{ $branch->name }}</h6>
                                                    <small class="text-muted">Code: {{ $branch->code }}</small>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn p-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="editBranch({{ $branch->id }})">
                                                        <i class="fas fa-edit me-1"></i>Modifier
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteBranch({{ $branch->id }}, '{{ $branch->name }}')">
                                                        <i class="fas fa-trash me-1"></i>Supprimer
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            @if($branch->address)
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                                                    <small class="text-muted">{{ $branch->address }}</small>
                                                </div>
                                            @endif

                                            @if($branch->phone)
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-phone me-2 text-muted"></i>
                                                    <small class="text-muted">{{ $branch->phone }}</small>
                                                </div>
                                            @endif

                                            @if($branch->email)
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-envelope me-2 text-muted"></i>
                                                    <small class="text-muted">{{ $branch->email }}</small>
                                                </div>
                                            @endif

                                            @php
                                                $nomsJours = ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
                                            @endphp
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-calendar-week me-2 text-muted"></i>
                                                <small class="text-muted">
                                                    {{ implode(', ', collect($branch->workingDays())->map(fn ($d) => $nomsJours[$d])->all()) }}
                                                    @unless($branch->hasOwnWorkingDays())
                                                        <span class="badge bg-label-secondary ms-1" title="Ce site suit le rythme défini pour l'entreprise">hérité</span>
                                                    @endunless
                                                </small>
                                            </div>

                                            @if($branch->manager)
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-user-tie me-2 text-muted"></i>
                                                    <small class="text-muted">Manager: {{ $branch->manager->name }}</small>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <span class="badge {{ $branch->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
                                                    {{ $branch->is_active ? '✅ Actif' : '❌ Inactif' }}
                                                </span>
                                                <span class="badge bg-label-info ms-1">{{ $branch->departments->count() }} services</span>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">{{ $branch->departments->count() }} départements</small>
                                            </div>
                                        </div>

                                        <!-- Actions buttons -->
                                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editBranch({{ $branch->id }})" title="Modifier">
                                                <i class="fas fa-edit me-1"></i>
                                            </button>
                                            @if(!$branch->is_active)
                                            <button class="btn btn-sm btn-outline-success" onclick="activateBranch({{ $branch->id }}, '{{ addslashes($branch->name) }}', false)" title="Activer">
                                                <i class="fas fa-check me-1"></i>
                                            </button>
                                            @else
                                            <button class="btn btn-sm btn-outline-warning" onclick="activateBranch({{ $branch->id }}, '{{ addslashes($branch->name) }}', true)" title="Désactiver">
                                                <i class="fas fa-times me-1"></i>
                                            </button>
                                            @endif
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteBranch({{ $branch->id }}, '{{ addslashes($branch->name) }}')" title="Supprimer">
                                                <i class="fas fa-trash me-1"></i>
                                            </button>
                                            {{-- ✅ QR Code unique par succursale --}}
                                            <a href="{{ route('company.settings.branches.qr-code', $branch->id) }}"
                                                class="btn btn-sm btn-info text-white"
                                                title="Générer le QR Code unique de cette succursale">
                                                <i class="fas fa-qrcode me-1"></i>QR Code
                                            </a>
                                            @if($branch->qr_token)
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="deleteBranchQrCode({{ $branch->id }}, '{{ addslashes($branch->name) }}')"
                                                title="Supprimer (révoquer) le QR Code de cette succursale">
                                                <i class="fas fa-qrcode me-1"></i><i class="fas fa-times"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="mb-3 d-flex justify-content-center align-items-center">
                                <div class="avatar avatar-xl" style="width: 80px; height: 80px;">
                                    <div class="avatar-initial bg-label-secondary text-center justify-content-center align-items-center rounded">
                                        <i class="fas fa-building fa-32px"></i>
                                    </div>
                                </div>
                            </div>
                            <h5 class="text-muted">Aucune succursale configurée</h5>
                            <p class="text-muted mb-4">Commencez par créer votre premier site</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBranchModal">
                                <i class="fas fa-plus me-1"></i>Créer une Succursale
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Création -->
<div class="modal fade" id="createBranchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">➕ Nouvelle Succursale</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('company.settings.branches.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom de la Succursale <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="Ex: Siège Social, Succursale Nord..." id="branchName">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" required placeholder="SUCC-XXXX" id="branchCode" readonly>
                            <small class="text-muted">Généré automatiquement</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Adresse</label>
                        <textarea class="form-control" name="address" rows="2" placeholder="123 Rue de la Paix, 75001 Paris"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-control" name="phone" placeholder="+225 01 01 222 333">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="contact@site.com">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Manager du Site <span class="text-danger">*</span></label>
                        <div class="alert alert-info py-2 px-3 small mb-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Désigner un manager ici ne fait pas que l'identifier : cela <strong>restreint son
                            compte à ce seul site</strong>. Il ne pourra faire le pointage collectif que pour
                            les employés rattachés ici, et ne verra plus les autres sites.
                            <br>
                            Un même utilisateur ne peut donc être manager que d'<strong>un seul site</strong>.
                            Le compte propriétaire fait exception : il garde l'accès à l'ensemble.
                        </div>
                        @if($companyUsers->count() > 0)
                            @if($companyUsers->count() > 10)
                                <!-- Select avec recherche pour plus de 100 utilisateurs -->
                                <select class="form-select select2" name="manager_id" data-placeholder="Rechercher un utilisateur...">
                                    <option value="">Aucun manager</option>
                                    @foreach($companyUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                            @else
                                <!-- Select simple pour moins de 100 utilisateurs -->
                                <select class="form-select" name="manager_id">
                                    <option value="">Aucun manager</option>
                                    @foreach($companyUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                            @endif
                        @else
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Aucun utilisateur trouvé. Créez d'abord des utilisateurs dans votre entreprise.
                            </div>
                            <input type="hidden" name="manager_id" value="">
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jours d'ouverture du site</label>

                        <input type="hidden" name="inherit_working_days" value="0">
                        <div class="form-check mb-2">
                            <input class="form-check-input js-inherit-days" type="checkbox"
                                   name="inherit_working_days" value="1" id="createInheritDays" checked
                                   data-target="create">
                            <label class="form-check-label" for="createInheritDays">
                                Suivre le rythme de l'entreprise
                                (<strong>{{ implode(', ', collect($company->workingDays())->map(fn ($d) => ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'][$d])->all()) }}</strong>)
                            </label>
                        </div>

                        <div class="d-flex flex-wrap gap-3 ps-3 border-start">
                            @foreach(['1' => 'Lundi', '2' => 'Mardi', '3' => 'Mercredi', '4' => 'Jeudi', '5' => 'Vendredi', '6' => 'Samedi', '7' => 'Dimanche'] as $num => $nom)
                                <div class="form-check">
                                    <input class="form-check-input js-day-create" type="checkbox" name="working_days[]"
                                           value="{{ $num }}" id="createDay{{ $num }}" disabled
                                           {{ in_array((int) $num, $company->workingDays(), true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="createDay{{ $num }}">{{ $nom }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">
                            Décochez « Suivre le rythme de l'entreprise » pour donner ses propres jours à ce site.
                            <strong>Les jours cochés sont la liste complète des jours d'ouverture</strong>, pas un ajout :
                            tout jour non coché apparaîtra « Fermé » dans la gestion des présences, sans y compter d'absence.
                        </div>
                    </div>

                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" checked>
                        <label class="form-check-label" for="isActive">
                            Site actif
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'Édition -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">✏️ Modifier la Succursale</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editBranchForm">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom de la Succursale <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required placeholder="Ex: Siège Social, Succursale Nord..." id="editBranchName">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" required placeholder="SUCC-XXXX" id="editBranchCode" readonly>
                            <small class="text-muted">Généré automatiquement</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Adresse</label>
                        <textarea class="form-control" name="address" rows="2" placeholder="123 Rue de la Paix, 75001 Paris" id="editBranchAddress"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" class="form-control" name="phone" placeholder="+225 01 01 222 333" id="editBranchPhone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="contact@site.com" id="editBranchEmail">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Manager du Site <span class="text-danger">*</span></label>
                        <div class="alert alert-info py-2 px-3 small mb-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Désigner un manager ici ne fait pas que l'identifier : cela <strong>restreint son
                            compte à ce seul site</strong>. Il ne pourra faire le pointage collectif que pour
                            les employés rattachés ici, et ne verra plus les autres sites.
                            <br>
                            Un même utilisateur ne peut donc être manager que d'<strong>un seul site</strong>.
                            Le compte propriétaire fait exception : il garde l'accès à l'ensemble.
                        </div>
                        @if($companyUsers->count() > 0)
                            @if($companyUsers->count() > 10)
                                <!-- Select avec recherche pour plus de 10 utilisateurs -->
                                <select class="form-select select2" name="manager_id" id="editBranchManager" data-placeholder="Rechercher un utilisateur...">
                                    <option value="">Aucun manager</option>
                                    @foreach($companyUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                            @else
                                <!-- Select simple pour moins de 10 utilisateurs -->
                                <select class="form-select" name="manager_id" id="editBranchManager">
                                    <option value="">Aucun manager</option>
                                    @foreach($companyUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                            @endif
                        @else
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Aucun utilisateur trouvé. Créez d'abord des utilisateurs dans votre entreprise.
                            </div>
                            <input type="hidden" name="manager_id" value="">
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jours d'ouverture du site</label>

                        <input type="hidden" name="inherit_working_days" value="0">
                        <div class="form-check mb-2">
                            <input class="form-check-input js-inherit-days" type="checkbox"
                                   name="inherit_working_days" value="1" id="editInheritDays"
                                   data-target="edit">
                            <label class="form-check-label" for="editInheritDays">
                                Suivre le rythme de l'entreprise
                                (<strong>{{ implode(', ', collect($company->workingDays())->map(fn ($d) => ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'][$d])->all()) }}</strong>)
                            </label>
                        </div>

                        <div class="d-flex flex-wrap gap-3 ps-3 border-start">
                            @foreach(['1' => 'Lundi', '2' => 'Mardi', '3' => 'Mercredi', '4' => 'Jeudi', '5' => 'Vendredi', '6' => 'Samedi', '7' => 'Dimanche'] as $num => $nom)
                                <div class="form-check">
                                    <input class="form-check-input edit-working-day js-day-edit" type="checkbox" name="working_days[]"
                                           value="{{ $num }}" id="editDay{{ $num }}">
                                    <label class="form-check-label" for="editDay{{ $num }}">{{ $nom }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">
                            <strong>Les jours cochés sont la liste complète des jours d'ouverture</strong>, pas un ajout.
                            Tout jour non coché apparaîtra « Fermé » dans la gestion des présences.
                        </div>
                    </div>

                    <input type="hidden" name="is_active" value="0" id="editBranchIsActiveHidden">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="editBranchIsActive" value="1">
                        <label class="form-check-label" for="editBranchIsActive">
                            Site actif
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* Card hover effects */
    .card {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: none;
        transition: all 0.3s ease;
        border-radius: 12px;
    }

    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        background: linear-gradient(135deg, rgba(105, 110, 255, 0.05) 0%, rgba(3, 195, 236, 0.05) 100%);
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px 12px 0 0 !important;
    }

    .card-header h5 {
        color: #566a7f;
        font-weight: 600;
    }

    /* Badge improvements */
    .badge {
        font-size: 0.75rem;
        font-weight: 500;
        border-radius: 6px;
        padding: 0.375rem 0.75rem;
    }

    .bg-label-primary {
        background-color: rgba(105, 110, 255, 0.1) !important;
        color: #253e87 !important;
    }

    .bg-label-success {
        background-color: rgba(40, 200, 72, 0.1) !important;
        color: #28c848 !important;
    }

    .bg-label-warning {
        background-color: rgba(255, 205, 7, 0.1) !important;
        color: #ffcd07 !important;
    }

    .bg-label-info {
        background-color: rgba(3, 195, 236, 0.1) !important;
        color: #03c3ec !important;
    }

    .bg-label-secondary {
        background-color: rgba(133, 146, 163, 0.1) !important;
        color: #8592a3 !important;
    }

    /* Select2 Custom Styles */
    .select2-container {
        width: 100% !important;
    }

    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px;
        border: 1px solid #d4d4d8;
        min-height: 38px;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding: 6px 12px;
        line-height: 1.5;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        top: 50%;
        transform: translateY(-50%);
        right: 12px;
    }

    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow b {
        border-color: #6c757d transparent transparent transparent;
        border-width: 5px 4px 0 4px;
        margin-top: -2px;
    }

    .select2-container--bootstrap-5 .select2-dropdown {
        border-radius: 8px;
        border: 1px solid #d4d4d8;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .select2-results__option {
        padding: 8px 12px;
    }

    .select2-results__option--selected {
        background-color: #696cff;
    }

    .select2-results__option--highlighted {
        background-color: rgba(105, 110, 255, 0.1);
        color: #253e87;
    }

    /* Code input styling */
    #branchCode {
        background-color: #f8f9fa;
        font-family: 'Courier New', monospace;
        font-weight: bold;
        letter-spacing: 1px;
    }
</style>
@endpush

@push('scripts')
<script>
    // Générer automatiquement le code de la branche
    function generateBranchCode() {
        const nameInput = document.getElementById('branchName');
        const codeInput = document.getElementById('branchCode');

        if (nameInput && codeInput) {
            nameInput.addEventListener('input', function() {
                const name = this.value.trim();
                let code = 'SUCC-';

                if (name.length > 0) {
                    // Prendre les 4 premières lettres du nom et les mettre en majuscules
                    const namePart = name.substring(0, 4).toUpperCase().replace(/[^A-Z0-9]/g, '');

                    // Ajouter un nombre aléatoire pour éviter les doublons
                    const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');

                    code += namePart + randomNum;
                }

                codeInput.value = code;
            });
        }
    }

    // Générer automatiquement le code de la branche pour l'édition
    function generateEditBranchCode() {
        const nameInput = document.getElementById('editBranchName');
        const codeInput = document.getElementById('editBranchCode');

        if (nameInput && codeInput) {
            nameInput.addEventListener('input', function() {
                const name = this.value.trim();
                let code = 'SUCC-';

                if (name.length > 0) {
                    // Prendre les 4 premières lettres du nom et les mettre en majuscules
                    const namePart = name.substring(0, 4).toUpperCase().replace(/[^A-Z0-9]/g, '');

                    // Ajouter un nombre aléatoire pour éviter les doublons
                    const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');

                    code += namePart + randomNum;
                }

                codeInput.value = code;
            });
        }
    }

    // « Suivre le rythme de l'entreprise » : quand c'est coché, la liste de
    // jours est désactivée et rien n'est envoyé, ce que le serveur interprète
    // comme un retour à l'héritage.
    document.addEventListener('change', function (event) {
        if (!event.target.classList.contains('js-inherit-days')) {
            return;
        }

        const cible = event.target.dataset.target === 'edit' ? '.js-day-edit' : '.js-day-create';

        document.querySelectorAll(cible).forEach(function (input) {
            input.disabled = event.target.checked;
        });
    });

    // Initialiser Select2 sur les listes déroulantes riches (au-delà de 10 utilisateurs)
    function initSelect2() {
        if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') {
            return;
        }

        $('.select2').each(function() {
            var $select = $(this);

            // Le layout initialise déjà les .select2 avec ses options par
            // défaut. On détruit son instance pour réappliquer celles de cet
            // écran : empiler deux instances vide la liste déroulante.
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }

            // Les deux selects vivent dans une modal Bootstrap. Sans
            // dropdownParent, le piège à focus de la modal empêche de saisir
            // quoi que ce soit dans le champ de recherche de Select2.
            var $modal = $select.closest('.modal');

            $select.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Rechercher un utilisateur...',
                allowClear: true,
                dropdownParent: $modal.length ? $modal : $(document.body),
                // Pas de minimumInputLength : toutes les options sont déjà
                // rendues côté serveur. En exiger 2 affichait une liste vide
                // à l'ouverture, sans indiquer qu'il fallait taper.
                language: {
                    noResults: function() {
                        return "Aucun utilisateur trouvé";
                    }
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Générer le code automatiquement
        generateBranchCode();
        generateEditBranchCode();

        // Initialiser Select2 si nécessaire
        initSelect2();

        // Réinitialiser le formulaire quand le modal se ferme
        const createModal = document.getElementById('createBranchModal');
        if (createModal) {
            createModal.addEventListener('hidden.bs.modal', function() {
                // Réinitialiser les champs
                document.getElementById('branchName').value = '';
                document.getElementById('branchCode').value = '';
                document.querySelector('textarea[name="description"]').value = '';
                document.querySelector('textarea[name="address"]').value = '';
                document.querySelector('input[name="phone"]').value = '';
                document.querySelector('input[name="email"]').value = '';
                document.querySelector('select[name="manager_id"]').value = '';

                // Réinitialiser la checkbox
                document.getElementById('isActive').checked = true;
            });
        }

        // Réinitialiser le formulaire d'édition quand le modal se ferme
        const editModal = document.getElementById('editBranchModal');
        if (editModal) {
            editModal.addEventListener('hidden.bs.modal', function() {
                // Réinitialiser les champs
                document.getElementById('editBranchName').value = '';
                document.getElementById('editBranchCode').value = '';
                document.getElementById('editBranchDescription').value = '';
                document.getElementById('editBranchAddress').value = '';
                document.getElementById('editBranchPhone').value = '';
                document.getElementById('editBranchEmail').value = '';
                document.getElementById('editBranchManager').value = '';
                document.querySelectorAll('.js-day-edit').forEach(function (input) {
                    input.checked = false;
                    input.disabled = true;
                });
                document.getElementById('editInheritDays').checked = true;
                document.getElementById('editBranchIsActive').checked = true;

                // Remettre l'action par défaut
                document.getElementById('editBranchForm').action = '';
            });
        }
    });

    function editBranch(id) {
        // Récupérer les données de la branche via AJAX
        fetch(`{{ url('/company/settings/branches') }}/${id}/edit`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const branch = data.branch;

                // Remplir le formulaire d'édition
                document.getElementById('editBranchName').value = branch.name;
                document.getElementById('editBranchCode').value = branch.code;
                document.getElementById('editBranchAddress').value = branch.address || '';
                document.getElementById('editBranchPhone').value = branch.phone || '';
                document.getElementById('editBranchEmail').value = branch.email || '';

                // Manager
                const managerSelect = document.getElementById('editBranchManager');
                if (managerSelect) {
                    managerSelect.value = branch.manager_id || '';
                }

                // Jours d'ouverture. On coche toujours le rythme EFFECTIF du site
                // (le sien, ou celui hérité de l'entreprise) : afficher des cases
                // vides pour un site qui hérite laissait croire qu'en cocher une
                // l'ajoutait, alors que la liste cochée remplace tout.
                const joursEffectifs = (branch.effective_working_days || []).map(Number);
                const heriteRythme = !branch.has_own_working_days;

                document.querySelectorAll('.js-day-edit').forEach(function (input) {
                    input.checked = joursEffectifs.includes(Number(input.value));
                    input.disabled = heriteRythme;
                });

                const inheritEdit = document.getElementById('editInheritDays');
                inheritEdit.checked = heriteRythme;

                // Status actif
                document.getElementById('editBranchIsActive').checked = branch.is_active;

                // Mettre à jour l'action du formulaire
                document.getElementById('editBranchForm').action = `{{ url('/company/settings/branches') }}/${id}`;

                // Réinitialiser Select2 si nécessaire
                if (typeof $ !== 'undefined' && $('.select2').length > 0) {
                    $('.select2').trigger('change');
                }

                // Ouvrir le modal
                const modal = new bootstrap.Modal(document.getElementById('editBranchModal'));
                modal.show();
            } else {
                alert('Erreur lors du chargement des données: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors du chargement des données');
        });
    }

    function activateBranch(id, name, isActive) {
        const action = isActive ? 'désactiver' : 'activer';
        const confirmMessage = `Êtes-vous sûr de vouloir ${action} la succursale "${name}" ?`;

        if (confirm(confirmMessage)) {
            // Créer un formulaire pour la route toggle avec méthode PUT
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ url('/company/settings/branches') }}/${id}/toggle`;

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';

            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'PUT';

            form.appendChild(csrf);
            form.appendChild(method);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteBranch(id, name) {
        if (confirm(`Êtes-vous sûr de vouloir supprimer la succursale "${name}" ? Cette action est irréversible.`)) {
            // Créer un formulaire de suppression avec la route nommée
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ url('/company/settings/branches') }}/${id}`;

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';

            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'DELETE';

            form.appendChild(csrf);
            form.appendChild(method);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteBranchQrCode(id, name) {
        if (confirm(`Supprimer le QR Code de la succursale "${name}" ?\n\nTous les QR Codes déjà imprimés ou distribués pour ce site seront immédiatement refusés au pointage.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ url('/company/settings/branches') }}/${id}/qr-code`;

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';

            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'DELETE';

            form.appendChild(csrf);
            form.appendChild(method);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
@endpush