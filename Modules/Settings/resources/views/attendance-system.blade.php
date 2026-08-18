@extends('layouts.app')

@section('title', 'Système de Présence - GP Flow')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">⏰ Système de Présence</h4>
                    <p class="text-muted mb-0">Configurez la méthode de gestion des présences de votre entreprise</p>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                        <div class="avatar-initial bg-label-info rounded">
                            <i class="fas fa-users fa-28px"></i>
                        </div>
                    </div>
                    <h3 class="mb-1 text-info">{{ $attendanceStats['total_employees'] }}</h3>
                    <p class="text-muted mb-2">Total Employés</p>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                        <div class="avatar-initial bg-label-success rounded">
                            <i class="fas fa-edit fa-28px"></i>
                        </div>
                    </div>
                    <h3 class="mb-1 text-success">{{ $attendanceStats['manual_attendance'] }}</h3>
                    <p class="text-muted mb-2">Saisie Manuelle</p>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 60px; height: 60px;">
                        <div class="avatar-initial bg-label-warning rounded">
                            <i class="fas fa-qrcode fa-28px"></i>
                        </div>
                    </div>
                    <h3 class="mb-1 text-warning">{{ $attendanceStats['qr_code_attendance'] }}</h3>
                    <p class="text-muted mb-2">QR Code</p>
                </div>
            </div>
        </div>
    </div>

    @if(($company->default_attendance_type ?? '') == 'qr_code' || ($user->attendance_type ?? '') == 'qr_code' || ($company->allow_multiple_attendance_types ?? false))

    @if(isset($branches) && $branches->count() > 0)
    <!-- ⭐ QR Codes Uniques par Succursale / Site -->
    <div class="card border-primary mb-4 shadow-sm">
        <div class="card-header bg-label-primary d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary">
                <i class="fas fa-star me-2"></i>
                {{ __('QR Code Unique par Succursale / Site (Recommandé)') }}
            </h5>
            <span class="badge bg-primary">1 QR Code = Tous les départements du site</span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                <i class="fas fa-magic text-primary me-1"></i>
                {{ __('Un seul QR Code par site : il détecte automatiquement le département de l\'employé qui scanne, applique ses horaires spécifiques (retard/avance) et refuse l\'accès aux employés d\'autres sites.') }}
            </p>
            <div class="row">
                @foreach($branches as $b)
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="d-flex align-items-center justify-content-between p-3 border rounded bg-light">
                        <div>
                            <strong class="d-block text-dark"><i class="fas fa-building text-primary me-1"></i>{{ $b->name }}</strong>
                            <small class="text-muted">{{ $b->departments->count() }} département(s) rattaché(s)</small>
                            <div class="mt-1">
                                @if($b->qr_token)
                                    <span class="badge bg-label-success">
                                        <i class="fas fa-check-circle me-1"></i>QR actif
                                        @if($b->qr_generated_at)
                                            — {{ $b->qr_generated_at->format('d/m/Y') }}
                                        @endif
                                    </span>
                                @else
                                    <span class="badge bg-label-secondary">
                                        <i class="fas fa-ban me-1"></i>Aucun QR actif
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex gap-1 ms-2">
                            <a href="{{ route('company.settings.branches.qr-code', $b->id) }}" class="btn btn-sm btn-primary" title="Afficher et télécharger le QR Code unique de ce site">
                                <i class="fas fa-qrcode me-1"></i> QR Site
                            </a>
                            @if($b->qr_token)
                            <form action="{{ route('company.settings.branches.qr-code.destroy', $b->id) }}" method="POST"
                                  onsubmit="return confirm('Supprimer le QR Code de la succursale « {{ $b->name }} » ?\n\nTous les QR Codes déjà imprimés ou distribués pour ce site seront immédiatement refusés au pointage.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer (révoquer) le QR Code de ce site">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Tranches horaires appliquées par le QR unique du site -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-clock me-2 text-primary"></i>{{ __('Tranches horaires par département') }}
            </h5>
            <a href="{{ route('company.settings.work-locations.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-sliders-h me-1"></i>{{ __('Configurer les horaires') }}
            </a>
        </div>
        <div class="card-body">
            <div class="alert alert-info py-2 px-3 mb-3">
                <small>
                    <i class="fas fa-info-circle me-1"></i>
                    {{ __('Aucun QR Code n\'est généré ici : le site n\'en a qu\'un seul (section ci-dessus). Ces horaires sont uniquement la logique appliquée au scan — chaque département peut avoir sa propre tranche (ex. Commercial 08:00-08:30 / 17:00-17:30, Informatique 10:30-11:00 / 20:00-20:30). Un département sans horaire suit celui du site.') }}
                </small>
            </div>

            @if(count($locations) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('Site') }}</th>
                                <th>{{ __('Département') }}</th>
                                <th>{{ __('Montée (début → fin)') }}</th>
                                <th>{{ __('Descente (début → fin)') }}</th>
                                <th>{{ __('Statut') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($locations as $location)
                            <tr>
                                <td>
                                    <strong>{{ $location->branch->name ?? __('Tous les sites') }}</strong>
                                    <br><small class="text-muted">{{ $location->name }}</small>
                                </td>
                                <td>
                                    @if($location->department)
                                        <span class="badge bg-label-primary">{{ $location->department->name }}</span>
                                    @else
                                        <span class="badge bg-label-secondary" title="{{ __('S\'applique à tout département sans horaire propre') }}">
                                            <i class="fas fa-asterisk me-1"></i>{{ __('Horaire par défaut du site') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($location->check_in_start || $location->check_in_end)
                                        <code>{{ $location->check_in_start ? \Carbon\Carbon::parse($location->check_in_start)->format('H:i') : '--:--' }}</code>
                                        →
                                        <code>{{ $location->check_in_end ? \Carbon\Carbon::parse($location->check_in_end)->format('H:i') : '--:--' }}</code>
                                    @else
                                        <span class="text-muted">{{ __('Non défini') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($location->check_out_start || $location->check_out_end)
                                        <code>{{ $location->check_out_start ? \Carbon\Carbon::parse($location->check_out_start)->format('H:i') : '--:--' }}</code>
                                        →
                                        <code>{{ $location->check_out_end ? \Carbon\Carbon::parse($location->check_out_end)->format('H:i') : '--:--' }}</code>
                                    @else
                                        <span class="text-muted">{{ __('Non défini') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($location->is_active)
                                        <span class="badge bg-label-success">{{ __('Actif') }}</span>
                                    @else
                                        <span class="badge bg-label-danger">{{ __('Inactif') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-warning text-center mb-0">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    {{ __('Aucune tranche horaire configurée : le QR du site enregistrera les pointages sans contrôle de retard.') }}
                </div>
                <div class="text-center mt-3">
                    <a class="btn btn-primary mb-2" href="{{ route('company.settings.work-locations.index') }}">
                        <i class="fas fa-plus-circle me-2"></i> {{ __('Définir une tranche horaire') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Configuration du Système de Présence -->
    <form id="attendanceSystemForm" action="{{ route('company.settings.attendance-system.update') }}" method="POST">
        @csrf
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">⚙️ Configuration Générale</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type de Présence par Défaut</label>
                                <select class="form-select" name="default_attendance_type" id="defaultAttendanceType" required>
                                    <option value="manual" {{ (old('default_attendance_type', $company->default_attendance_type ?? 'manual')) == 'manual' ? 'selected' : '' }}>
                                        📝 Saisie Manuelle - L'employé saisi ses heures manuellement
                                    </option>
                                    <option value="qr_code" {{ (old('default_attendance_type', $company->default_attendance_type ?? 'manual')) == 'qr_code' ? 'selected' : '' }}>
                                        📱 QR Code - Pointage via QR code sur mobile
                                    </option>
                                </select>
                                <small class="text-muted">Type de présence attribué par défaut aux nouveaux employés</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Politique de Types Multiples</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="allow_multiple_types" id="allowMultipleTypes"
                                            {{ old('allow_multiple_types', $company->allow_multiple_attendance_types ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="allowMultipleTypes">
                                        Autoriser plusieurs types de présence
                                    </label>
                                </div>
                                <small class="text-muted">Permet aux employés de choisir entre différents modes de pointage</small>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Types de Présence</h6>
                            <ul class="mb-0">
                                <li><strong>Saisie Manuelle:</strong> L'employé encode ses heures de présence manuellement via l'interface web</li>
                                <li><strong>QR Code:</strong> Pointage via scan de QR code sur site avec géolocalisation</li>
                                <li><strong>Biométrique:</strong> Pointage par reconnaissance biométrique (empreinte, visage)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration QR Code -->
        <div class="row mb-4" id="qrCodeSettings" style="display: {{ (old('default_attendance_type', $company->default_attendance_type ?? 'manual')) == 'qr_code' ? 'block' : 'none' }};">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📱 Configuration QR Code</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Distance Maximale (mètres)</label>
                                <input type="number" class="form-control" name="qr_code_settings[max_distance]" value="{{ old('qr_code_settings.max_distance', $company->attendance_settings['qr_code']['max_distance'] ?? 100) }}" min="10" max="1000">
                                <small class="text-muted">Distance maximale autorisée entre l'employé et le point de pointage</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Géolocalisation Requise</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="qr_code_settings[require_location]" id="requireLocation"
                                        {{ old('qr_code_settings.require_location', $company->attendance_settings['qr_code']['require_location'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="requireLocation">
                                        Exiger la géolocalisation
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Heure d'Arrivée Début</label>
                                <input type="time" class="form-control" name="qr_code_settings[check_in_start]" value="{{ old('qr_code_settings.check_in_start', $company->attendance_settings['qr_code']['check_in_start'] ?? '08:00') }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Heure d'Arrivée Fin</label>
                                <input type="time" class="form-control" name="qr_code_settings[check_in_end]" value="{{ old('qr_code_settings.check_in_end', $company->attendance_settings['qr_code']['check_in_end'] ?? '09:30') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Heure de Départ Début</label>
                                <input type="time" class="form-control" name="qr_code_settings[check_out_start]" value="{{ old('qr_code_settings.check_out_start', $company->attendance_settings['qr_code']['check_out_start'] ?? '17:00') }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Heure de Départ Fin</label>
                                <input type="time" class="form-control" name="qr_code_settings[check_out_end]" value="{{ old('qr_code_settings.check_out_end', $company->attendance_settings['qr_code']['check_out_end'] ?? '18:30') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration Biométrique -->
        <div class="row mb-4" id="biometricSettings" style="display: {{ (old('default_attendance_type', $company->default_attendance_type ?? 'manual')) == 'biometric' ? 'block' : 'none' }};">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🔐 Configuration Biométrique</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type de Reconnaissance</label>
                                <select class="form-select" name="biometric_settings[type]">
                                    <option value="fingerprint" {{ old('biometric_settings.type', $company->attendance_settings['biometric']['type'] ?? 'fingerprint') == 'fingerprint' ? 'selected' : '' }}>
                                        Empreinte Digitale
                                    </option>
                                    <option value="facial" {{ old('biometric_settings.type', $company->attendance_settings['biometric']['type'] ?? 'fingerprint') == 'facial' ? 'selected' : '' }}>
                                        Reconnaissance Faciale
                                    </option>
                                    <option value="both" {{ old('biometric_settings.type', $company->attendance_settings['biometric']['type'] ?? 'fingerprint') == 'both' ? 'selected' : '' }}>
                                        Les Deux
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Précision Requise (%)</label>
                                <input type="number" class="form-control" name="biometric_settings[precision]" value="{{ old('biometric_settings.precision', $company->attendance_settings['biometric']['precision'] ?? 95) }}" min="80" max="100">
                                <small class="text-muted">Pourcentage minimum de précision pour valider l'identification</small>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="biometric_settings[require_verification]" id="requireVerification"
                                {{ old('biometric_settings.require_verification', $company->attendance_settings['biometric']['require_verification'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="requireVerification">
                                Vérification en double obligatoire
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="d-flex justify-content-end">
        <button class="btn btn-primary" onclick="updateAttendanceSystem()">
            <i class="fas fa-save me-1"></i>Sauvegarder
        </button>
    </div>

    <!-- Actions de Configuration
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">🚀 Actions Rapides</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <button type="button" class="btn btn-outline-primary w-100" onclick="applyDefaultToAll()">
                                <i class="fas fa-users me-2"></i>
                                Appliquer à Tous les Employés
                            </button>
                            <small class="text-muted d-block mt-1">Appliquer le type par défaut à tous les employés</small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <button type="button" class="btn btn-outline-info w-100" onclick="resetAttendanceSettings()">
                                <i class="fas fa-undo me-2"></i>
                                Réinitialiser
                            </button>
                            <small class="text-muted d-block mt-1">Remettre les paramètres par défaut</small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <button type="button" class="btn btn-outline-success w-100" onclick="exportAttendanceSettings()">
                                <i class="fas fa-download me-2"></i>
                                Exporter Config
                            </button>
                            <small class="text-muted d-block mt-1">Exporter la configuration actuelle</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> -->
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const defaultType = document.getElementById('defaultAttendanceType');
        const qrCodeSettings = document.getElementById('qrCodeSettings');
        const biometricSettings = document.getElementById('biometricSettings');

        defaultType.addEventListener('change', function() {
            if (this.value === 'qr_code') {
                qrCodeSettings.style.display = 'block';
                biometricSettings.style.display = 'none';
            } else if (this.value === 'biometric') {
                qrCodeSettings.style.display = 'none';
                biometricSettings.style.display = 'block';
            } else {
                qrCodeSettings.style.display = 'none';
                biometricSettings.style.display = 'none';
            }
        });
    });

    function updateAttendanceSystem() {
        const form = document.getElementById('attendanceSystemForm');
        const formData = new FormData(form);
        const submitBtn = document.querySelector('button[onclick="updateAttendanceSystem()"]');
        
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sauvegarde...';
        submitBtn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success('Configuration mise à jour avec succès');
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } else {
                toastr.error(data.message || 'Erreur lors de la sauvegarde');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Erreur de connexion: ' + error.message);
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }

    function applyDefaultToAll() {
        if (confirm('Êtes-vous sûr de vouloir appliquer le type de présence par défaut à tous les employés ?')) {
            const defaultType = document.getElementById('defaultAttendanceType').value;

            fetch('{{ route("company.settings.attendance-system.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    apply_to_all: true,
                    default_attendance_type: defaultType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Type de présence appliqué à tous les employés');
                    location.reload();
                } else {
                    toastr.error('Erreur lors de l\'application');
                }
            });
        }
    }

    function resetAttendanceSettings() {
        if (confirm('Êtes-vous sûr de vouloir réinitialiser tous les paramètres de présence ?')) {
            document.getElementById('defaultAttendanceType').value = 'manual';
            document.getElementById('allowMultipleTypes').checked = false;
            document.getElementById('qrCodeSettings').style.display = 'none';
            toastr.info('Paramètres réinitialisés');
        }
    }

    function exportAttendanceSettings() {
        // Simulation d'export
        toastr.info('Export de la configuration en cours...');
    }
</script>

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

    .alert-info {
        background-color: rgba(3, 195, 236, 0.1);
        border-color: rgba(3, 195, 236, 0.2);
        color: #03c3ec;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .card-body {
            padding: 1rem;
        }

        .btn {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
        }

        h3 {
            font-size: 1.5rem;
        }

        h5 {
            font-size: 1.125rem;
        }
    }
</style>
@endsection
