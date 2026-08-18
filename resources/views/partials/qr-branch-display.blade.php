@extends('layouts.app')

@section('title', 'QR Code Succursale — ' . $branch->name . ' | GP Flow')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <!-- En-tête -->
    <div class="row mb-4">
        
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">
                        <i class="fas fa-qrcode me-2 text-primary"></i>
                        QR Code — {{ $branch->name }}
                    </h4>
                    <p class="text-muted mb-0">QR Code unique pour la succursale — détection automatique du département</p>
                    <small class="text-primary">
                        <i class="fas fa-calendar me-1"></i>{{ now()->format('d/m/Y H:i') }}
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('company.settings.branches.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-building me-2"></i>
                        <div>
                            <h5 class="mb-0 text-white">{{ $branch->name }}</h5>
                            <small class="opacity-75">QR Code de Pointage — Toutes Départements</small>
                        </div>
                    </div>
                </div>
                <div class="card-body text-center py-4">

                    <!-- QR Code image -->
                    <div class="qr-wrapper d-inline-block p-3 bg-white rounded-3 shadow mb-4" id="qrContainer">
                        <img src="data:image/svg+xml;base64,{{ $qrCode }}"
                             alt="QR Code — {{ $branch->name }}"
                             class="img-fluid qr-img"
                             id="qrImage"
                             style="max-width: {{ $size }}px; display:block;">
                        <div class="mt-2 text-center" style="color:#1a3a6b; font-weight:700; font-size:.8rem; letter-spacing:1px;">
                            {{ strtoupper($branch->name) }}
                        </div>
                    </div>

                    <!-- Info boxes -->
                    <div class="row g-3 mb-4 text-start">
                        <div class="col-md-6">
                            <div class="alert alert-primary mb-0 py-2 px-3">
                                <small>
                                    <strong><i class="fas fa-building me-1"></i>Entreprise :</strong> {{ $company->name }}<br>
                                    <strong><i class="fas fa-map-marker-alt me-1"></i>Succursale :</strong> {{ $branch->name }}<br>
                                    @if($branch->code)
                                    <strong><i class="fas fa-hashtag me-1"></i>Code :</strong> {{ $branch->code }}<br>
                                    @endif
                                    <strong><i class="fas fa-clock me-1"></i>Généré le :</strong> {{ date('d/m/Y H:i', $timestamp) }}
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success mb-0 py-2 px-3">
                                <small>
                                    <strong><i class="fas fa-magic me-1"></i>Logique automatique :</strong><br>
                                    ✅ Un seul QR pour TOUS les départements<br>
                                    🔍 Détection auto du département de l'employé<br>
                                    ⏰ Horaires appliqués selon le département<br>
                                    🚫 Accès refusé si mauvaise succursale
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Statuts expliqués -->
                    <div class="alert alert-info text-start py-2 px-3 mb-4">
                        <small>
                            <strong><i class="fas fa-info-circle me-1"></i>Statuts de scan :</strong><br>
                            <span class="badge bg-success me-1">✅ À l'heure</span> Arrivée avant l'heure limite<br>
                            <span class="badge bg-danger me-1">⏰ Retard</span> Arrivée après <code>check_in_end</code> du département<br>
                            <span class="badge bg-warning text-dark me-1">⚡ En avance</span> Départ avant <code>check_out_start</code> du département<br>
                            <span class="badge bg-secondary me-1">♾ Libre</span> Aucun WorkLocation configuré pour ce département
                        </small>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <form action="{{ route('company.settings.attendance-system.qr-code-download') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="qr_string" value="{{ $qrString }}">
                            <input type="hidden" name="location_name" value="{{ $branch->name }}_succursale">
                            <input type="hidden" name="size" value="{{ $size }}">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-download me-2"></i>Télécharger le QR Code
                            </button>
                        </form>

                        <button type="button" class="btn btn-outline-secondary" onclick="printQR()">
                            <i class="fas fa-print me-2"></i>Imprimer
                        </button>

                        {{-- Depuis le back-office on reste dans le dashboard : le portail
                             plein écran est réservé au scan du QR sur mobile / tablette. --}}
                        <a href="{{ route('company.times.qrcode-pointage.collective', ['branch_id' => $branch->id]) }}"
                           class="btn btn-outline-info">
                            <i class="fas fa-users me-2"></i>Pointage collectif
                        </a>
                    </div>
                </div>
            </div>

            <!-- URL encodée dans le QR -->
            <div class="card mt-3">
                <div class="card-body py-2">
                    <small class="text-muted">
                        <i class="fas fa-link me-1"></i>
                        <strong>URL encodée dans le QR :</strong>
                        <code class="ms-1" style="font-size:.78rem; word-break:break-all;">{{ $qrString }}</code>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .qr-wrapper { transition: box-shadow .3s; }
    .qr-wrapper:hover { box-shadow: 0 8px 30px rgba(26,58,107,.2) !important; }
    .qr-img { border-radius: 6px; }

    @media print {
        .breadcrumb, .header-navbar, .main-menu, .btn, form,
        .alert-info, .alert-success, .card:last-child { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .card-header { background: none !important; border-bottom: 1px solid #ddd !important; color: #000 !important; }
        .card-header * { color: #000 !important; }
        .alert-primary { border: 1px solid #ccc !important; background: #f8f9fa !important; color: #000 !important; }
        .qr-wrapper { box-shadow: none !important; }
    }
</style>
@endpush

@push('scripts')
<script>
function printQR() { window.print(); }
</script>
@endpush
