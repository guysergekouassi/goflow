@extends('layouts.app')

@section('title', 'QR Code de Pointage - ' . ($location->name ?? 'Emplacement') . ' - GP Flow')

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
                                    <i class="ti ti-qrcode me-1"></i>Pointage par QR Code
                                </span>
                                @if(isset($qrType) && $qrType === 'web_portal')
                                    <span class="badge bg-success text-white px-2 py-1">
                                        <i class="ti ti-world me-1"></i>Portail Web Collectif
                                    </span>
                                @else
                                    <span class="badge bg-info text-white px-2 py-1">
                                        <i class="ti ti-device-mobile me-1"></i>Application Mobile
                                    </span>
                                @endif
                            </div>
                            <h3 class="fw-bold text-white mb-1">QR Code Officiel de Pointage</h3>
                            <p class="text-white-50 mb-0">
                                {{ $location->name }} &bull; {{ $company->name }}
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

    {{-- Carte d'affichage principale du QR Code --}}
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm qr-presentation-card">
                
                {{-- En-tête de la carte style fiche imprimable --}}
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar avatar-sm rounded bg-label-primary text-primary d-flex align-items-center justify-content-center">
                            <i class="ti ti-building fs-4"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 fw-bold text-dark">{{ $location->name }}</h5>
                            <small class="text-muted">{{ $company->name }}</small>
                        </div>
                    </div>
                    <span class="badge bg-label-secondary">
                        <i class="ti ti-clock me-1"></i>{{ date('d/m/Y H:i', $timestamp) }}
                    </span>
                </div>

                <div class="card-body p-4 text-center">

                    {{-- Badge informatif sur la nature du QR Code --}}
                    @if(isset($qrType) && $qrType === 'web_portal')
                        <div class="alert alert-success d-flex align-items-center mb-4 text-start" role="alert">
                            <i class="ti ti-world fs-3 me-3 text-success flex-shrink-0"></i>
                            <div class="small">
                                <strong>Portail Web Collectif :</strong> Ce QR Code permet de pointer tout employé directement sur navigateur sans application mobile installée.
                            </div>
                        </div>
                    @else
                        <div class="alert alert-primary d-flex align-items-center mb-4 text-start" role="alert">
                            <i class="ti ti-scan fs-3 me-3 text-primary flex-shrink-0"></i>
                            <div class="small">
                                <strong>Pointage Mobile :</strong> Les employés scannent ce QR Code avec leur application mobile GP-FLOW pour enregistrer leur montée (arrivée) ou leur descente (départ).
                            </div>
                        </div>
                    @endif

                    {{-- Cadre stylisé du QR Code --}}
                    <div class="qr-code-frame d-inline-flex flex-column align-items-center p-4 bg-white rounded-4 shadow-sm border mb-4">
                        <div class="qr-image-container position-relative">
                            <img src="data:image/svg+xml;base64,{{ $qrCode }}" 
                                 alt="QR Code de pointage - {{ $location->name }}" 
                                 class="img-fluid rounded-2 qr-svg-image" 
                                 style="width: 280px; max-width: 100%; height: auto; display: block;">
                        </div>
                        <div class="mt-3 text-center">
                            <span class="badge bg-label-dark px-3 py-2 fw-semibold text-uppercase" style="letter-spacing: 0.5px;">
                                <i class="ti ti-map-pin me-1"></i>{{ $location->name }}
                            </span>
                        </div>
                    </div>

                    {{-- Grille d'informations détaillées --}}
                    <div class="row g-2 mb-4 text-start">
                        <div class="col-sm-6">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted d-block small mb-1">
                                    <i class="ti ti-building me-1 text-primary"></i>Site / Succursale
                                </span>
                                <span class="fw-bold text-dark">
                                    {{ $location->branch ? $location->branch->name : 'Tous les sites' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted d-block small mb-1">
                                    <i class="ti ti-category me-1 text-primary"></i>Département / Service
                                </span>
                                @if($location->department)
                                    <span class="badge bg-label-primary fw-semibold">{{ $location->department->name }}</span>
                                @else
                                    <span class="badge bg-label-secondary">Global (Tous départements)</span>
                                @endif
                            </div>
                        </div>
                        @if($location->check_in_start || $location->check_out_start)
                        <div class="col-sm-6">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted d-block small mb-1">
                                    <i class="ti ti-clock-check me-1 text-success"></i>Plage de Montée (Arrivée)
                                </span>
                                <span class="fw-bold text-dark">
                                    {{ $location->check_in_start ? \Carbon\Carbon::parse($location->check_in_start)->format('H:i') : '--:--' }} 
                                    &rarr; 
                                    {{ $location->check_in_end ? \Carbon\Carbon::parse($location->check_in_end)->format('H:i') : '--:--' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 rounded border bg-light h-100">
                                <span class="text-muted d-block small mb-1">
                                    <i class="ti ti-clock-pause me-1 text-danger"></i>Plage de Descente (Départ)
                                </span>
                                <span class="fw-bold text-dark">
                                    {{ $location->check_out_start ? \Carbon\Carbon::parse($location->check_out_start)->format('H:i') : '--:--' }} 
                                    &rarr; 
                                    {{ $location->check_out_end ? \Carbon\Carbon::parse($location->check_out_end)->format('H:i') : '--:--' }}
                                </span>
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Boutons d'action clairs et stylisés --}}
                    <div class="d-flex flex-wrap justify-content-center gap-2 pt-2 border-top">
                        <form action="{{ route('company.settings.attendance-system.qr-code-download') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="qr_string" value="{{ $qrString }}">
                            <input type="hidden" name="location_name" value="{{ $location->name }}">
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

                        <a href="{{ route('company.settings.attendance-system.index') }}" class="btn btn-outline-dark d-inline-flex align-items-center gap-1">
                            <i class="ti ti-arrow-left fs-5"></i>
                            <span>Retour</span>
                        </a>
                    </div>
                </div>

                {{-- Pied de carte discret --}}
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

    /* Style optimisé pour l'impression A4 */
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
