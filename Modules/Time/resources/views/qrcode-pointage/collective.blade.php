@extends('layouts.app')

@section('title', 'Pointage collectif')

@push('styles')
<style>
    /* Liste des employés à cocher */
    .emp-list {
        max-height: 460px;
        overflow-y: auto;
        border: 1px solid #d9dee3;
        border-radius: .5rem;
    }
    .emp-item {
        display: flex;
        align-items: center;
        gap: .85rem;
        margin: 0;
        padding: .7rem .9rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f1f3;
        transition: background-color .15s;
    }
    .emp-item:last-of-type { border-bottom: none; }
    .emp-item:hover      { background-color: #f8f9fa; }
    .emp-item.is-checked { background-color: #e7f3ff; }
    .emp-item.is-hidden  { display: none; }

    .emp-avatar {
        width: 38px; height: 38px; flex-shrink: 0;
        border-radius: .4rem;
        background: linear-gradient(135deg, #5a8dee, #3f6ad8);
        color: #fff; font-size: .78rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }
    .emp-meta  { min-width: 0; flex: 1; }
    .emp-name  { font-weight: 600; color: #384551; display: block; }
    .emp-dept  { font-size: .78rem; color: #8592a3; display: block; }
    .emp-state { flex-shrink: 0; text-align: right; }

    /* Récapitulatif dans la SweetAlert */
    .recap { text-align: left; max-height: 320px; overflow-y: auto; }
    .recap-row {
        display: flex; align-items: flex-start; gap: .6rem;
        padding: .5rem .7rem; margin-bottom: .35rem;
        border-radius: .4rem; font-size: .85rem;
        background: #f5f6f7; border: 1px solid #e7e7e8;
    }
    .recap-row.ko { background: #fdf0f2; border-color: #f5c2c7; }
    .recap-row .who  { font-weight: 700; color: #384551; }
    .recap-row .what { display: block; font-size: .78rem; }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- En-tête --}}
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">👥 Pointage collectif</h4>
            <p class="text-muted mb-0">
                Sélectionnez un ou plusieurs employés et enregistrez leur entrée ou leur sortie en une seule fois.
            </p>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('company.times.qrcode-pointage.index') }}">Gestion des présences</a>
                    </li>
                    <li class="breadcrumb-item active">Pointage collectif</li>
                </ol>
            </nav>
            <small class="text-primary">
                <i class="fas fa-calendar me-1"></i>
                {{ ucfirst(\Carbon\Carbon::now()->locale('fr_FR')->isoFormat('dddd D MMMM YYYY')) }} •
                <i class="fas fa-clock me-1"></i><span id="liveClock">--:--:--</span>
            </small>
        </div>
    </div>

    {{-- Choix de la succursale --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('company.times.qrcode-pointage.collective') }}" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="branchSelect" class="form-label">
                        <i class="fas fa-building me-1"></i>Succursale
                    </label>
                    <select name="branch_id" id="branchSelect" class="form-select" onchange="this.form.submit()">
                        <option value="">— Choisir une succursale —</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected($branch && $branch->id === $b->id)>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <noscript><button type="submit" class="btn btn-primary">Afficher</button></noscript>
                    @if($branch)
                        <span class="badge bg-label-primary">
                            {{ $employees->count() }} employé(s) actif(s)
                        </span>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if(!$branch)
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Choisissez une succursale pour afficher ses employés.
        </div>
    @elseif($employees->isEmpty())
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-2"></i>
            Aucun employé actif n'est rattaché à la succursale « {{ $branch->name }} ».
        </div>
    @else
        @php
            $hasWorkLocations = \Modules\Settings\Models\WorkLocation::where('company_id', Auth::user()->company_id)
                ->where('branch_id', $branch->id)->where('is_active', 1)->exists();
        @endphp

        @if($hasWorkLocations)
            <div class="alert alert-success d-flex align-items-start">
                <i class="fas fa-clock-rotate-left me-2 mt-1"></i>
                <div><strong>Horaires actifs :</strong> le statut (à l'heure / retard / avance) est calculé
                    automatiquement selon le département de chaque employé.</div>
            </div>
        @else
            <div class="alert alert-warning d-flex align-items-start">
                <i class="fas fa-infinity me-2 mt-1"></i>
                <div><strong>Mode libre :</strong> aucun horaire n'est configuré pour cette succursale.
                    Les pointages sont enregistrés sans calcul de retard ni d'avance.</div>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <input type="hidden" id="branch_id" value="{{ $branch->id }}">

                {{-- Recherche --}}
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control" id="empSearch"
                           placeholder="Rechercher un employé ou un département..." autocomplete="off">
                </div>

                {{-- Tout sélectionner --}}
                <div class="d-flex align-items-center justify-content-between border rounded p-3 mb-3 bg-light">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label fw-semibold" for="selectAll">Tout sélectionner</label>
                    </div>
                    <span class="badge bg-label-primary" id="countPill">0 / {{ $employees->count() }}</span>
                </div>

                {{-- Liste --}}
                <div class="emp-list mb-4" id="empList">
                    @foreach($employees as $emp)
                        @php
                            $deptName = optional($emp->department)->name;
                            $parts    = preg_split('/\s+/', trim($emp->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            $initials = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
                            $dernier  = optional($pointagesDuJour->get($emp->id))->last();
                        @endphp
                        <label class="emp-item" data-search="{{ mb_strtolower($emp->name . ' ' . $deptName) }}">
                            <input type="checkbox" class="form-check-input mt-0 emp-cbx"
                                   value="{{ $emp->id }}" data-name="{{ $emp->name }}">
                            <span class="emp-avatar">{{ $initials }}</span>
                            <span class="emp-meta">
                                <span class="emp-name">{{ $emp->name }}</span>
                                <span class="emp-dept">
                                    <i class="fas fa-layer-group me-1"></i>{{ $deptName ?: 'Sans département' }}
                                </span>
                            </span>
                            <span class="emp-state">
                                @if($dernier)
                                    <span class="badge bg-label-{{ $dernier->type === 'entree' ? 'success' : 'secondary' }}">
                                        {{ $dernier->type === 'entree' ? 'Entrée' : 'Sortie' }}
                                        {{ \Carbon\Carbon::parse($dernier->auth_time)->format('H:i') }}
                                    </span>
                                @else
                                    <span class="badge bg-label-light text-muted">Non pointé</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                    <div class="text-center text-muted py-4" id="empNoResult" style="display:none">
                        <i class="fas fa-user-slash me-2"></i>Aucun employé ne correspond à cette recherche.
                    </div>
                </div>

                {{-- Actions --}}
                <div class="row g-3">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-success w-100 btn-lg btn-action" data-type="entree">
                            <i class="fas fa-right-to-bracket me-2"></i><span class="btn-txt">ENTRÉE</span>
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="button" class="btn btn-danger w-100 btn-lg btn-action" data-type="sortie">
                            <i class="fas fa-right-from-bracket me-2"></i><span class="btn-txt">SORTIE</span>
                        </button>
                    </div>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    <i class="fas fa-shield-halved me-1"></i>
                    Pointage enregistré sous votre responsabilité : <strong>{{ Auth::user()->name }}</strong>.
                </p>
            </div>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
$(function () {

    // ── Horloge ─────────────────────────────────────────────────────────
    function tick() {
        const n = new Date();
        $('#liveClock').text(
            String(n.getHours()).padStart(2,'0') + ':' +
            String(n.getMinutes()).padStart(2,'0') + ':' +
            String(n.getSeconds()).padStart(2,'0')
        );
    }
    tick();
    setInterval(tick, 1000);

    if ($('#branch_id').length === 0) return; // aucune succursale sélectionnée

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    // Les noms d'employés sont injectés dans les SweetAlert : on échappe.
    function esc(str) { return $('<div>').text(str == null ? '' : str).html(); }

    const $all  = $('#selectAll');
    const $cbx  = $('.emp-cbx');
    const $btns = $('.btn-action');
    const total = $cbx.length;

    function visibleCbx() {
        return $cbx.filter(function () {
            return !$(this).closest('.emp-item').hasClass('is-hidden');
        });
    }

    function refreshState() {
        const selected = $cbx.filter(':checked').length;

        $('#countPill').text(selected + ' / ' + total);
        $('.btn-action[data-type="entree"] .btn-txt').text(selected > 0 ? 'ENTRÉE (' + selected + ')' : 'ENTRÉE');
        $('.btn-action[data-type="sortie"] .btn-txt').text(selected > 0 ? 'SORTIE (' + selected + ')' : 'SORTIE');

        // « Tout sélectionner » ne porte que sur les lignes visibles (filtre actif)
        const $vis   = visibleCbx();
        const visSel = $vis.filter(':checked').length;
        $all.prop('checked', $vis.length > 0 && visSel === $vis.length);
        $all.prop('indeterminate', visSel > 0 && visSel < $vis.length);
    }

    $cbx.on('change', function () {
        $(this).closest('.emp-item').toggleClass('is-checked', this.checked);
        refreshState();
    });

    $all.on('change', function () {
        const checked = this.checked;
        visibleCbx().each(function () {
            this.checked = checked;
            $(this).closest('.emp-item').toggleClass('is-checked', checked);
        });
        refreshState();
    });

    $('#empSearch').on('input', function () {
        const q = $(this).val().trim().toLowerCase();
        let shown = 0;
        $('.emp-item').each(function () {
            const match = !q || ($(this).attr('data-search') || '').indexOf(q) !== -1;
            $(this).toggleClass('is-hidden', !match);
            if (match) shown++;
        });
        $('#empNoResult').toggle(shown === 0);
        refreshState();
    });

    refreshState();

    // ── Enregistrement ──────────────────────────────────────────────────
    $btns.on('click', function () {
        const type     = $(this).data('type');
        const branchId = $('#branch_id').val();
        const $btn     = $(this);

        const $sel  = $cbx.filter(':checked');
        const ids   = $sel.map(function () { return this.value; }).get();
        const names = $sel.map(function () { return $(this).data('name'); }).get();

        if (ids.length === 0) {
            Swal.fire({
                icon: 'warning', title: 'Aucune sélection',
                text: 'Veuillez sélectionner au moins un employé avant de pointer.',
                confirmButtonColor: '#5a8dee'
            });
            return;
        }

        const label   = type === 'entree' ? 'Entrée' : 'Sortie';
        const preview = names.slice(0, 8).map(n => '• ' + esc(n)).join('<br>')
            + (names.length > 8 ? '<br><em>… et ' + (names.length - 8) + ' autre(s)</em>' : '');

        Swal.fire({
            title: 'Confirmer le pointage',
            html: `<strong>${label}</strong> pour <strong>${ids.length} employé(s)</strong>`
                + `<div class="text-start small text-muted mt-3" style="max-height:200px;overflow-y:auto">${preview}</div>`,
            showCancelButton: true,
            confirmButtonText: 'Oui, valider',
            cancelButtonText: 'Annuler',
            confirmButtonColor: type === 'entree' ? '#39da8a' : '#ff5b5c',
            cancelButtonColor: '#8592a3',
        }).then(result => {
            if (!result.isConfirmed) return;

            const orig = $btn.html();
            $btn.html('<span class="spinner-border spinner-border-sm"></span>');
            $btns.prop('disabled', true);

            $.ajax({
                url: "{{ route('company.times.qrcode-pointage.branch-portal.store') }}",
                method: 'POST',
                data: { employee_ids: ids, branch_id: branchId, type: type },
                success: function (res) {
                    // Un récapitulatif par employé : chacun a son propre statut.
                    const rows = (res.results || []).map(function (r) {
                        let mark = '✅', color = '#39da8a';
                        if (!r.success)                         { mark = '⛔'; color = '#ff5b5c'; }
                        else if (r.scan_status === 'retard')    { mark = '⏰'; color = '#ff5b5c'; }
                        else if (r.scan_status === 'en_avance') { mark = '⚡'; color = '#fdac41'; }

                        return `<div class="recap-row ${r.success ? '' : 'ko'}">`
                             + `<span>${mark}</span><span><span class="who">${esc(r.name)}</span>`
                             + `<span class="what" style="color:${color}">${esc(r.message)}</span></span></div>`;
                    }).join('');

                    Swal.fire({
                        icon: res.failed > 0 ? (res.succeeded > 0 ? 'warning' : 'error') : 'success',
                        title: res.succeeded > 0 ? 'Pointage enregistré' : 'Aucun pointage',
                        html: `<div class="fw-bold mb-3">${esc(res.message)}</div><div class="recap">${rows}</div>`,
                        confirmButtonText: 'Fermer',
                        confirmButtonColor: '#5a8dee',
                        width: 600,
                    }).then(() => {
                        // Recharger : met à jour les badges « déjà pointé aujourd'hui »
                        if (res.succeeded > 0) window.location.reload();
                    });

                    // Ne décocher que les employés réellement pointés : ceux
                    // refusés restent sélectionnés pour correction.
                    (res.results || []).forEach(function (r) {
                        if (!r.success) return;
                        $cbx.filter('[value="' + r.employee_id + '"]')
                            .prop('checked', false)
                            .closest('.emp-item').removeClass('is-checked');
                    });
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.message ?? 'Une erreur est survenue.';
                    Swal.fire({
                        icon: xhr.status === 403 ? 'warning' : 'error',
                        title: xhr.status === 403 ? 'Accès refusé' : 'Erreur',
                        html: esc(msg),
                        confirmButtonColor: '#5a8dee',
                    });
                },
                complete: function () {
                    // Restaurer avant de rafraîchir : « orig » porte l'ancien compteur.
                    $btn.html(orig);
                    $btns.prop('disabled', false);
                    refreshState();
                }
            });
        });
    });
});
</script>
@endpush
