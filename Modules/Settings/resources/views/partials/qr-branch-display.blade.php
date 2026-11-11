@extends('layouts.app')

@section('title', 'QR Code Succursale — ' . $branch->name . ' - GP Flow')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- En-tête avec navigation --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);">
                <div class="card-body py-4 px-4 text-white">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-white text-primary fw-semibold px-2 py-1">
                                    <i class="ti ti-qrcode me-1"></i>QR Code Unique de Site
                                </span>
                                <span class="badge bg-success text-white px-2 py-1">
                                    <i class="ti ti-check me-1"></i>Détection automatique
                                </span>
                            </div>
                            <h3 class="fw-bold text-white mb-1">QR Code de Pointage — {{ $branch->name }}</h3>
                            <p class="text-white-50 mb-0">
                                Valide pour tous les départements et employés de ce site &bull; {{ $company->name }}
                            </p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('company.settings.attendance-system.index') }}" class="btn btn-outline-light d-inline-flex align-items-center gap-1">
                                <i class="ti ti-arrow-left"></i>
                                <span>Retour aux paramètres</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm qr-presentation-card">
                
                {{-- En-tête de la carte --}}
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar avatar-sm rounded bg-label-primary text-primary d-flex align-items-center justify-content-center">
                            <i class="ti ti-building fs-4"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 fw-bold text-dark">{{ $branch->name }}</h5>
                            <small class="text-muted">Succursale / Site &bull; Code: {{ $branch->code ?? 'N/A' }}</small>
                        </div>
                    </div>
                    <span class="badge bg-label-secondary">
                        <i class="ti ti-clock me-1"></i>{{ date('d/m/Y H:i', $timestamp) }}
                    </span>
                </div>

                <div class="card-body p-4 text-center">

                    {{-- Explication de la détection automatique --}}
                    <div class="alert alert-primary d-flex align-items-center mb-4 text-start" role="alert">
                        <i class="ti ti-bulb fs-3 me-3 text-primary flex-shrink-0"></i>
                        <div class="small">
                            <strong>QR Code Intelligent :</strong> Ce QR Code unique est commun à tous les services du site. Lorsqu'un employé le scanne, le système identifie automatiquement son département et applique la plage horaire correspondante.
                        </div>
                    </div>

                    {{-- Cadre stylisé du QR Code --}}
                    <div class="qr-code-frame d-inline-flex flex-column align-items-center p-4 bg-white rounded-4 shadow-sm border mb-4">
                        <div class="qr-image-container position-relative">
                            <img src="data:image/svg+xml;base64,{{ $qrCode }}" 
                                 alt="QR Code Site - {{ $branch->name }}" 
                                 class="img-fluid rounded-2 qr-svg-image" 
                                 style="width: 280px; max-width: 100%; height: auto; display: block;">
                        </div>
                        <div class="mt-3 text-center">
                            <span class="badge bg-label-dark px-3 py-2 fw-semibold text-uppercase" style="letter-spacing: 0.5px;">
                                <i class="ti ti-building me-1"></i>{{ $branch->name }}
                            </span>
                        </div>
                    </div>

                    {{-- Tranches horaires couvertes par ce QR --}}
                    @isset($schedule)
                    <div class="card border bg-light text-start mb-4 shadow-none">
                        <div class="card-body py-3 px-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="ti ti-clock text-primary fs-5"></i>
                                <h6 class="mb-0 fw-bold text-dark">Horaires appliqués lors du scan</h6>
                            </div>

                            @if(count($schedule['rows']) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 bg-white rounded border">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Département</th>
                                            <th>Montée</th>
                                            <th>Descente</th>
                                            <th class="text-center">Effectif</th>
                                            <th>Règle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($schedule['rows'] as $row)
                                        <tr>
                                            <td>
                                                @if($row['department'])
                                                    <strong>{{ $row['department']->name }}</strong>
                                                @else
                                                    <span class="text-muted">Par défaut</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($row['check_in'])
                                                    <code class="text-success">{{ $row['check_in'] }}</code>
                                                @else
                                                    <span class="text-muted">--:--</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($row['check_out'])
                                                    <code class="text-danger">{{ $row['check_out'] }}</code>
                                                @else
                                                    <span class="text-muted">--:--</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-label-secondary">{{ $row['headcount'] }}</span>
                                            </td>
                                            <td>
                                                @if($row['source'] === 'department')
                                                    <span class="badge bg-label-primary">Département</span>
                                                @elseif($row['source'] === 'site')
                                                    <span class="badge bg-label-info">Site</span>
                                                @else
                                                    <span class="badge bg-label-secondary">Libre</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <p class="text-muted small mb-0">Aucun horaire spécifique défini pour ce site.</p>
                            @endif
                        </div>
                    </div>
                    @endisset

                    {{-- Boutons d'action --}}
                    <div class="d-flex flex-wrap justify-content-center gap-2 pt-2 border-top">
                        <form action="{{ route('company.settings.attendance-system.qr-code-download') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="qr_string" value="{{ $qrString }}">
                            <input type="hidden" name="location_name" value="{{ $branch->name }}_succursale">
                            <input type="hidden" name="size" value="{{ $size }}">
                            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
                                <i class="ti ti-download fs-5"></i>
                                <span>Télécharger le QR Code</span>
                            </button>
                        </form>

                        <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1" onclick="window.print()">
                            <i class="ti ti-printer fs-5"></i>
                            <span>Imprimer</span>
                        </button>

                        <a href="{{ route('company.times.qrcode-pointage.collective', ['branch_id' => $branch->id]) }}" class="btn btn-outline-info d-inline-flex align-items-center gap-1">
                            <i class="ti ti-users fs-5"></i>
                            <span>Pointage collectif</span>
                        </a>

                        <a href="{{ route('company.settings.branches.qr-code', ['branch' => $branch->id, 'regenerate' => 1]) }}"
                           class="btn btn-outline-warning d-inline-flex align-items-center gap-1"
                           onclick="return confirm('Régénérer le QR Code de « {{ $branch->name }} » ?\n\nLe QR actuel deviendra immédiatement invalide.');">
                            <i class="ti ti-refresh fs-5"></i>
                            <span>Régénérer</span>
                        </a>
                    </div>
                </div>

                <div class="card-footer bg-light border-top py-2 px-3 text-center">
                    <small class="text-muted" style="word-break: break-all; font-size: 0.78rem;">
                        <i class="ti ti-link me-1"></i><strong>URL encodée :</strong> {{ $qrString }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .qr-presentation-card {
        border-radius: 12px;
        overflow: hidden;
    }
    .qr-code-frame {
        border: 2px dashed #cbd5e1;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background-color: #ffffff;
    }
    .qr-code-frame:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04) !important;
        border-color: #3b82f6;
    }
    .qr-svg-image {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    @media print {
        body {
            background: #ffffff !important;
            color: #000000 !important;
        }
        .layout-navbar,
        .layout-menu,
        .content-footer,
        .btn,
        form,
        .alert,
        .card-footer,
        .badge.bg-label-secondary {
            display: none !important;
        }
        .container-xxl {
            padding: 0 !important;
            max-width: 100% !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .qr-code-frame {
            border: 2px solid #000000 !important;
            padding: 20px !important;
        }
        .qr-svg-image {
            width: 380px !important;
            max-width: 100% !important;
        }
    }
</style>
@endpush
