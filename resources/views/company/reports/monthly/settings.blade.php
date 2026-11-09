@extends('layouts.app')

@section('title', 'Configuration du Rapport Mensuel - GP Flow')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Messages flash --}}
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
                <h6 class="mb-0 text-danger">Des erreurs ont été détectées dans le formulaire :</h6>
            </div>
            <ul class="mb-0 ps-4">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- En-tête avec bouton retour --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1 text-primary">
                        <i class="ti ti-settings me-2"></i>Paramètres du Rapport Mensuel
                    </h4>
                    <p class="text-muted mb-0">Définissez la fréquence d'envoi, les formats et la liste des destinataires pour votre entreprise.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ Route::has('company.reports.monthly.index') ? route('company.reports.monthly.index') : url('/company/reports/monthly') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>Retour à l'historique
                    </a>
                </div>
            </div>
        </div>
    </div>

    @php
        $isEnabled = old('is_enabled', $config->is_enabled ?? true);
        $autoSendEnabled = old('auto_send_enabled', $config->auto_send_enabled ?? true);
        $sendDay = (int) old('send_day', $config->send_day ?? 2);
        $sendHour = (int) old('send_hour', $config->send_hour ?? 8);
        $sendMinute = (int) old('send_minute', $config->send_minute ?? 0);
        $reportFormat = old('report_format', $config->report_format ?? 'both');

        // Récupération des destinataires
        $recipientsList = old('recipients');
        if (!is_array($recipientsList)) {
            $recipientsList = $config && is_array($config->recipients) && count($config->recipients) > 0
                ? $config->recipients
                : (!empty($company->email) ? [$company->email] : ['']);
        }
    @endphp

    <div class="row">
        {{-- Formulaire de paramétrage --}}
        <div class="col-lg-8">
            <form action="{{ Route::has('company.reports.monthly.settings.update') ? route('company.reports.monthly.settings.update') : (Route::has('company.reports.monthly.settings.save') ? route('company.reports.monthly.settings.save') : url('/company/reports/monthly/settings')) }}" method="POST" id="formMonthlySettings">
                @csrf

                {{-- Carte 1 : Activation et Planification --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="ti ti-calendar-time me-2 text-primary"></i>Planification & Fréquence
                        </h5>
                    </div>
                    <div class="card-body p-4">

                        {{-- Toggle Général : Activation du service --}}
                        <div class="d-flex justify-content-between align-items-center p-3 rounded mb-4 bg-light">
                            <div>
                                <h6 class="fw-bold mb-1">Activer le service de rapport mensuel</h6>
                                <p class="text-muted small mb-0">Active le calcul périodique, la compilation et la mise à disposition des bilans mensuels.</p>
                            </div>
                            <div class="form-check form-switch form-switch-lg ms-3">
                                <input type="hidden" name="is_enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" value="1" {{ $isEnabled ? 'checked' : '' }}>
                            </div>
                        </div>

                        {{-- Toggle Envoi Automatique --}}
                        <div class="d-flex justify-content-between align-items-center p-3 rounded mb-4 border">
                            <div>
                                <h6 class="fw-bold mb-1">Envoi automatique par e-mail</h6>
                                <p class="text-muted small mb-0">Achemine automatiquement les rapports aux destinataires le jour planifié.</p>
                            </div>
                            <div class="form-check form-switch form-switch-lg ms-3">
                                <input type="hidden" name="auto_send_enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="auto_send_enabled" id="auto_send_enabled" value="1" {{ $autoSendEnabled ? 'checked' : '' }}>
                            </div>
                        </div>

                        <div class="row g-3">
                            {{-- Jour d'envoi du mois --}}
                            <div class="col-md-6">
                                <label for="send_day" class="form-label fw-bold">
                                    Jour du mois pour l'envoi <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('send_day') is-invalid @enderror" id="send_day" name="send_day" required>
                                    @for($day = 1; $day <= 31; $day++)
                                        <option value="{{ $day }}" {{ $sendDay == $day ? 'selected' : '' }}>
                                            Le {{ $day }}{{ $day == 1 ? 'er' : '' }} du mois
                                        </option>
                                    @endfor
                                </select>
                                <small class="text-muted d-block mt-1">
                                    <i class="ti ti-info-circle me-1"></i>Si le mois a moins de jours, le dernier jour sera automatiquement utilisé.
                                </small>
                                @error('send_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Heure d'envoi --}}
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    Heure d'envoi programmée <span class="text-danger">*</span>
                                </label>
                                <div class="row g-2">
                                    <div class="col-7">
                                        <select class="form-select @error('send_hour') is-invalid @enderror" id="send_hour" name="send_hour" required>
                                            @for($hour = 0; $hour < 24; $hour++)
                                                <option value="{{ $hour }}" {{ $sendHour == $hour ? 'selected' : '' }}>
                                                    {{ sprintf('%02d', $hour) }} h
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-5">
                                        <select class="form-select @error('send_minute') is-invalid @enderror" id="send_minute" name="send_minute" required>
                                            @foreach([0, 15, 30, 45] as $min)
                                                <option value="{{ $min }}" {{ $sendMinute == $min ? 'selected' : '' }}>
                                                    : {{ sprintf('%02d', $min) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Fuseau : <strong>{{ $company->timezone ?? config('app.timezone', 'Africa/Abidjan') }}</strong>
                                </small>
                                @error('send_hour')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Carte 2 : Format des documents --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="ti ti-file-text me-2 text-primary"></i>Format des Rapports
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <label class="form-label fw-bold mb-3">Sélectionnez le format généré et envoyé :</label>

                        <div class="row g-3">
                            {{-- Format Both --}}
                            <div class="col-md-4">
                                <div class="form-check custom-option custom-option-basic p-3 border rounded h-100 position-relative">
                                    <input class="form-check-input" type="radio" name="report_format" id="format_both" value="both" {{ $reportFormat === 'both' ? 'checked' : '' }}>
                                    <label class="form-check-label w-100 ms-2" for="format_both">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="ti ti-files fs-3 text-primary me-2"></i>
                                            <span class="fw-bold">PDF + Excel</span>
                                        </div>
                                        <small class="text-muted d-block">Recommandé : synthèse graphique imprimable et données brutes pour tableur.</small>
                                    </label>
                                </div>
                            </div>

                            {{-- Format PDF --}}
                            <div class="col-md-4">
                                <div class="form-check custom-option custom-option-basic p-3 border rounded h-100 position-relative">
                                    <input class="form-check-input" type="radio" name="report_format" id="format_pdf" value="pdf" {{ $reportFormat === 'pdf' ? 'checked' : '' }}>
                                    <label class="form-check-label w-100 ms-2" for="format_pdf">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="ti ti-file-type-pdf fs-3 text-danger me-2"></i>
                                            <span class="fw-bold">PDF seul</span>
                                        </div>
                                        <small class="text-muted d-block">Idéal pour impression directe, affichage formel et archivage légal.</small>
                                    </label>
                                </div>
                            </div>

                            {{-- Format Excel --}}
                            <div class="col-md-4">
                                <div class="form-check custom-option custom-option-basic p-3 border rounded h-100 position-relative">
                                    <input class="form-check-input" type="radio" name="report_format" id="format_excel" value="excel" {{ $reportFormat === 'excel' ? 'checked' : '' }}>
                                    <label class="form-check-label w-100 ms-2" for="format_excel">
                                        <div class="d-flex align-items-center mb-1">
                                            <i class="ti ti-file-spreadsheet fs-3 text-success me-2"></i>
                                            <span class="fw-bold">Excel seul</span>
                                        </div>
                                        <small class="text-muted d-block">Tableau de bord tabulaire modifiable pour analyses approfondies et paie.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        @error('report_format')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Carte 3 : Liste des destinataires --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0 fw-bold text-dark">
                                <i class="ti ti-mail-share me-2 text-primary"></i>Destinataires du Rapport
                            </h5>
                            <small class="text-muted">Adresses e-mail qui recevront automatiquement le rapport mensuel</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRecipient">
                            <i class="ti ti-plus me-1"></i>Ajouter une adresse
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <div id="recipientsContainer">
                            @foreach($recipientsList as $index => $email)
                                <div class="input-group mb-2 recipient-row">
                                    <span class="input-group-text bg-light text-muted"><i class="ti ti-mail"></i></span>
                                    <input type="email"
                                           name="recipients[]"
                                           class="form-control recipient-input @error('recipients.'.$index) is-invalid @enderror"
                                           placeholder="direction@entreprise.com"
                                           value="{{ $email }}"
                                           {{ $autoSendEnabled ? 'required' : '' }}>
                                    <button type="button" class="btn btn-outline-danger btn-remove-recipient" title="Supprimer ce destinataire">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="ti ti-shield-check me-1"></i>Les e-mails recevront un récapitulatif des indicateurs clés avec les rapports complets en pièce jointe.
                        </small>
                        @error('recipients')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Boutons d'action principaux --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4 fw-semibold" id="btnSaveConfig">
                            <i class="ti ti-device-floppy me-2"></i>Sauvegarder les paramètres
                        </button>
                        <a href="{{ Route::has('company.reports.monthly.index') ? route('company.reports.monthly.index') : url('/company/reports/monthly') }}" class="btn btn-outline-secondary">
                            Annuler
                        </a>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#testReportModal">
                            <i class="ti ti-send me-1"></i>Tester maintenant
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Panneau latéral : Prochaine exécution & Guide d'aide --}}
        <div class="col-lg-4">

            {{-- Bloc dynamique récapitulant la prochaine date --}}
            <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #1e40af !important;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar avatar-sm bg-primary text-white rounded p-1 me-2 d-flex align-items-center justify-content-center">
                            <i class="ti ti-calendar-event fs-4"></i>
                        </div>
                        <h6 class="fw-bold text-primary mb-0">Prochain Envoi Automatique</h6>
                    </div>

                    <div class="bg-white p-3 rounded shadow-sm mb-3">
                        <span class="text-muted small d-block">Estimation temps réel :</span>
                        <h5 class="fw-bold text-dark mb-1" id="nextScheduledDisplay">—</h5>
                        <small class="text-primary d-block" id="nextScheduledPeriod">Période concernée : Mois précédent</small>
                    </div>

                    <div class="small text-muted" id="scheduleStatusText">
                        <i class="ti ti-info-circle me-1"></i>Calculé automatiquement selon le jour et l'heure choisis.
                    </div>
                </div>
            </div>

            {{-- Guide & Explications --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="ti ti-help-circle me-2 text-primary"></i>Aide & Explications
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <h6 class="fw-bold small text-dark mb-1">
                            <i class="ti ti-clock-hour-3 text-primary me-1"></i>Période couverte
                        </h6>
                        <p class="small text-muted mb-0">
                            Le rapport mensuel traite l'intégralité du mois civil écoulé (du 1er jour à 00h00 au dernier jour du mois à 23h59).
                        </p>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold small text-dark mb-1">
                            <i class="ti ti-calculator text-primary me-1"></i>Indicateurs clés
                        </h6>
                        <p class="small text-muted mb-0">
                            Sont comptabilisés : jours ouvrés travaillés, retards constatés, pointages incomplets, absences justifiées et injustifiées.
                        </p>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold small text-dark mb-1">
                            <i class="ti ti-file-analytics text-primary me-1"></i>Formats fournis
                        </h6>
                        <ul class="list-unstyled small text-muted mb-0">
                            <li class="mb-1">• <strong>PDF :</strong> Format A4 haute qualité avec mise en page optimisée pour la direction.</li>
                            <li>• <strong>Excel :</strong> Classeur tabulaire complet intégrant les feuilles Récapitulatif, Départements et Employés.</li>
                        </ul>
                    </div>

                    <div class="p-3 bg-light rounded small">
                        <i class="ti ti-sparkles text-warning me-1"></i>
                        <strong>Conseil RH :</strong> Programmez l'envoi entre le 1er et le 5 du mois pour disposer d'un bilan d'assiduité avant la préparation des fiches de paie.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Modal "Tester maintenant" --}}
<div class="modal fade" id="testReportModal" tabindex="-1" aria-labelledby="testReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title text-white fw-bold" id="testReportModalLabel">
                    <i class="ti ti-send me-2"></i>Tester la Génération Immédiate
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ Route::has('company.reports.monthly.test') ? route('company.reports.monthly.test') : (Route::has('company.reports.monthly.generate') ? route('company.reports.monthly.generate') : url('/company/reports/monthly/generate')) }}" method="POST" id="formTestReport">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Cette action déclenche une génération immédiate des rapports du mois dernier et vous envoie un e-mail de prévisualisation.
                    </p>

                    <div class="mb-3">
                        <label for="test_recipient_email" class="form-label fw-bold small">Envoyer le test à l'adresse suivante :</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-mail"></i></span>
                            <input type="email"
                                   name="test_email"
                                   id="test_recipient_email"
                                   class="form-control"
                                   value="{{ auth()->user()->email ?? $company->email ?? '' }}"
                                   required>
                        </div>
                        <small class="text-muted">Par défaut, votre adresse e-mail connectée est sélectionnée.</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small">Format pour le test :</label>
                        <select name="format" class="form-select form-select-sm">
                            <option value="both" selected>PDF et Excel (Complet)</option>
                            <option value="pdf">PDF seul</option>
                            <option value="excel">Excel seul</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitTest">
                        <i class="ti ti-player-play me-1"></i>Démarrer le test
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── Gestion dynamique des destinataires (+ / -) ──
        const container = document.getElementById('recipientsContainer');
        const btnAdd = document.getElementById('btnAddRecipient');

        function createRecipientRow(value = '') {
            const div = document.createElement('div');
            div.className = 'input-group mb-2 recipient-row';
            div.innerHTML = `
                <span class="input-group-text bg-light text-muted"><i class="ti ti-mail"></i></span>
                <input type="email" name="recipients[]" class="form-control recipient-input" placeholder="direction@entreprise.com" value="${value}">
                <button type="button" class="btn btn-outline-danger btn-remove-recipient" title="Supprimer ce destinataire">
                    <i class="ti ti-trash"></i>
                </button>
            `;
            attachRemoveEvent(div.querySelector('.btn-remove-recipient'));
            return div;
        }

        function attachRemoveEvent(button) {
            button.addEventListener('click', function () {
                const rows = container.querySelectorAll('.recipient-row');
                if (rows.length > 1) {
                    this.closest('.recipient-row').remove();
                } else {
                    // Si une seule ligne reste, on vide simplement l'input
                    this.closest('.recipient-row').querySelector('.recipient-input').value = '';
                }
            });
        }

        // Attacher le listener sur les boutons existants
        container.querySelectorAll('.btn-remove-recipient').forEach(attachRemoveEvent);

        // Ajouter une nouvelle ligne
        if (btnAdd) {
            btnAdd.addEventListener('click', function () {
                const newRow = createRecipientRow('');
                container.appendChild(newRow);
                newRow.querySelector('input').focus();
            });
        }

        // ── Calcul dynamique de la prochaine date d'envoi en JS ──
        const daySelect = document.getElementById('send_day');
        const hourSelect = document.getElementById('send_hour');
        const minuteSelect = document.getElementById('send_minute');
        const toggleEnabled = document.getElementById('is_enabled');
        const toggleAutoSend = document.getElementById('auto_send_enabled');
        const recipientsContainer = document.getElementById('recipientsContainer');
        const displayEl = document.getElementById('nextScheduledDisplay');
        const statusTextEl = document.getElementById('scheduleStatusText');
        const periodEl = document.getElementById('nextScheduledPeriod');

        const monthNamesFr = [
            'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
        ];
        const dayNamesFr = [
            'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'
        ];

        function calculateNextRun() {
            recipientsContainer.querySelectorAll('.recipient-input').forEach(input => {
                input.required = toggleAutoSend.checked;
            });

            if (!toggleEnabled.checked || !toggleAutoSend.checked) {
                displayEl.innerHTML = '<span class="text-secondary fs-6">Envoi désactivé</span>';
                statusTextEl.innerHTML = '<i class="ti ti-alert-triangle text-warning me-1"></i>Activez le service et l\'envoi automatique pour planifier les diffusions.';
                if (periodEl) periodEl.textContent = 'Service inactif';
                return;
            }

            const chosenDay = parseInt(daySelect.value, 10) || 2;
            const chosenHour = parseInt(hourSelect.value, 10) || 8;
            const chosenMinute = parseInt(minuteSelect.value, 10) || 0;

            const now = new Date();
            let targetYear = now.getFullYear();
            let targetMonth = now.getMonth(); // 0-indexed

            // Trouver le dernier jour du mois courant
            const daysInCurrentMonth = new Date(targetYear, targetMonth + 1, 0).getDate();
            const clampedDayThisMonth = Math.min(chosenDay, daysInCurrentMonth);

            const scheduledThisMonth = new Date(targetYear, targetMonth, clampedDayThisMonth, chosenHour, chosenMinute, 0);

            let nextRun;
            let reportTargetMonthIndex;

            if (now.getTime() < scheduledThisMonth.getTime()) {
                // L'envoi de ce mois n'a pas encore eu lieu
                nextRun = scheduledThisMonth;
                reportTargetMonthIndex = targetMonth - 1; // Le rapport couvrira le mois d'avant
            } else {
                // Passer au mois suivant
                targetMonth += 1;
                if (targetMonth > 11) {
                    targetMonth = 0;
                    targetYear += 1;
                }
                const daysInNextMonth = new Date(targetYear, targetMonth + 1, 0).getDate();
                const clampedDayNextMonth = Math.min(chosenDay, daysInNextMonth);
                nextRun = new Date(targetYear, targetMonth, clampedDayNextMonth, chosenHour, chosenMinute, 0);
                reportTargetMonthIndex = targetMonth - 1;
            }

            const dayName = dayNamesFr[nextRun.getDay()];
            const dayNum = nextRun.getDate();
            const monthName = monthNamesFr[nextRun.getMonth()];
            const yearNum = nextRun.getFullYear();
            const hourStr = String(nextRun.getHours()).padStart(2, '0');
            const minuteStr = String(nextRun.getMinutes()).padStart(2, '0');

            displayEl.textContent = `${dayName} ${dayNum} ${monthName} ${yearNum} à ${hourStr}:${minuteStr}`;
            statusTextEl.innerHTML = `<i class="ti ti-clock-check text-success me-1"></i>Diffusion programmée le ${dayNum} du mois à ${hourStr}h${minuteStr}.`;

            if (reportTargetMonthIndex < 0) {
                reportTargetMonthIndex = 11;
            }
            if (periodEl) {
                periodEl.textContent = `Période couverte : ${monthNamesFr[reportTargetMonthIndex]} (mois complet)`;
            }
        }

        // Écouter les changements pour recalculer en direct
        [daySelect, hourSelect, minuteSelect, toggleEnabled, toggleAutoSend].forEach(el => {
            if (el) {
                el.addEventListener('change', calculateNextRun);
            }
        });

        // Calcul initial
        calculateNextRun();

        // Gestion du submit pour le bouton test
        const formTest = document.getElementById('formTestReport');
        const btnSubmitTest = document.getElementById('btnSubmitTest');
        if (formTest && btnSubmitTest) {
            formTest.addEventListener('submit', function () {
                btnSubmitTest.disabled = true;
                btnSubmitTest.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Génération...';
            });
        }
    });
</script>
@endpush
@endsection
