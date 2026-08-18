<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pointage — {{ $branch->name }} | GP Flow</title>
    <meta name="description" content="Portail de pointage pour la succursale {{ $branch->name }}">

    <link rel="icon" type="image/x-icon" href="{{ asset('img/favicon/favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --primary:   #1a3a6b;
            --accent:    #3ec6ff;
            --bg-start:  #070f20;
            --bg-end:    #0e1f45;
            --glass-bg:  rgba(255,255,255,0.05);
            --glass-br:  rgba(255,255,255,0.10);
            --text-main: #ffffff;
            --text-sub:  #94a9c4;
            --green:     #00d98b;
            --red:       #ff4d6a;
            --orange:    #ffb347;
        }

        *, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, var(--bg-start) 0%, var(--bg-end) 100%);
            min-height: 100vh;
            margin: 0;
            color: var(--text-main);
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* Animated background orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.35;
            pointer-events: none;
        }
        .orb-1 { width:320px; height:320px; background:var(--primary); top:-80px; right:-80px; animation: drift1 22s infinite alternate ease-in-out; }
        .orb-2 { width:260px; height:260px; background:var(--accent); bottom:-60px; left:-60px; animation: drift2 16s infinite alternate ease-in-out; }
        .orb-3 { width:180px; height:180px; background:#6c63ff; top:40%; left:50%; transform:translateX(-50%); animation: drift3 28s infinite alternate ease-in-out; }

        @keyframes drift1 { to { transform: translate(60px, 50px) scale(1.15); } }
        @keyframes drift2 { to { transform: translate(-40px, 60px) scale(1.2); } }
        @keyframes drift3 { to { transform: translate(-50%, 50px) scale(0.85); } }

        /* Layout */
        .app-wrap {
            flex: 1; z-index: 1;
            display: flex; flex-direction: column;
            max-width: 520px; margin: 0 auto; width: 100%; padding: 20px 16px;
        }

        /* Header */
        .app-header { text-align: center; margin: 16px 0 28px; }
        .brand-img { height: 42px; margin-bottom: 14px; filter: drop-shadow(0 2px 6px rgba(0,0,0,0.3)); }
        .app-title {
            font-size: 1.45rem; font-weight: 800; letter-spacing: -0.5px; margin: 0;
            background: linear-gradient(90deg, #fff 30%, var(--accent) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .app-sub { font-size: 0.82rem; color: var(--text-sub); margin-top: 4px; }

        /* Clock live */
        .live-clock {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-bottom: 22px;
        }
        .clock-pill {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 100px; padding: 7px 18px;
            font-size: 1.1rem; font-weight: 700; letter-spacing: 2px;
            color: var(--accent);
        }
        .date-pill {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 100px; padding: 6px 14px;
            font-size: 0.78rem; color: var(--text-sub); font-weight: 500;
        }

        /* Branch badge */
        .branch-badge {
            display: flex; align-items: center;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(62,198,255,0.18);
            border-radius: 18px; padding: 14px 18px;
            margin-bottom: 22px;
            box-shadow: 0 0 0 1px rgba(62,198,255,0.06);
        }
        .branch-icon {
            width: 44px; height: 44px; border-radius: 14px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary), #1e5799);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-right: 14px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .branch-info .label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-sub); margin-bottom: 2px; }
        .branch-name { font-size: 1.05rem; font-weight: 700; }
        .pulse { width: 8px; height: 8px; background: var(--green); border-radius: 50%; margin-left: auto; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(0,217,139,.6);} 70%{box-shadow:0 0 0 7px rgba(0,217,139,0);} }

        /* Glass card */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
            border: 1px solid var(--glass-br);
            border-radius: 24px; padding: 24px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
            margin-bottom: 18px;
            transition: border-color .3s;
        }
        .glass:hover { border-color: rgba(255,255,255,0.18); }

        /* Info banner — mode libre / horaires */
        .info-banner {
            border-radius: 14px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.82rem;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .info-banner.mode-libre { background: rgba(255,179,71,0.12); border: 1px solid rgba(255,179,71,0.25); color: var(--orange); }
        .info-banner.mode-horaire { background: rgba(0,217,139,0.10); border: 1px solid rgba(0,217,139,0.22); color: var(--green); }
        .info-banner i { margin-top: 1px; flex-shrink: 0; }

        /* Labels */
        .field-label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--text-sub); margin-bottom: 10px; display: block; }

        /* ── Sélecteur multi-employés ─────────────────────────────────────── */

        /* Search box */
        .emp-search-wrap { position: relative; margin-bottom: 12px; }
        .emp-search-wrap i {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: var(--text-sub); font-size: .9rem; pointer-events: none;
        }
        .emp-search {
            width: 100%; height: 52px; border-radius: 16px; padding: 0 16px 0 42px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);
            color: #fff; font-size: .95rem; font-family: 'Inter', sans-serif; outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .emp-search::placeholder { color: var(--text-sub); }
        .emp-search:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(62,198,255,.15); }

        /* Toolbar: select all + counter */
        .emp-toolbar {
            display: flex; align-items: center; gap: 12px;
            background: rgba(62,198,255,.07); border: 1px solid rgba(62,198,255,.18);
            border-radius: 14px; padding: 12px 16px; margin-bottom: 10px;
        }
        .emp-toolbar label { display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; flex: 1; }
        .emp-toolbar .txt { font-size: .85rem; font-weight: 600; color: var(--accent); }
        .count-pill {
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
            border-radius: 100px; padding: 4px 12px; font-size: .78rem; font-weight: 700;
            color: #fff; white-space: nowrap;
        }

        /* Checkbox (custom, gros pour usage tactile) */
        .cbx { appearance: none; -webkit-appearance: none; flex-shrink: 0;
            width: 24px; height: 24px; border-radius: 8px; cursor: pointer;
            background: rgba(255,255,255,.06); border: 2px solid rgba(255,255,255,.25);
            display: grid; place-content: center; transition: background .15s, border-color .15s;
        }
        .cbx::before {
            content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            font-size: .7rem; color: #fff; transform: scale(0); transition: transform .15s;
        }
        .cbx:checked { background: var(--accent); border-color: var(--accent); }
        .cbx:checked::before { transform: scale(1); }
        .cbx:indeterminate { background: rgba(62,198,255,.4); border-color: var(--accent); }
        .cbx:indeterminate::before { content: '\f068'; transform: scale(1); }

        /* Employee list */
        .emp-list {
            max-height: 320px; overflow-y: auto; overscroll-behavior: contain;
            border-radius: 16px; border: 1px solid rgba(255,255,255,.10);
            background: rgba(0,0,0,.15);
        }
        .emp-list::-webkit-scrollbar { width: 6px; }
        .emp-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 3px; }

        .emp-item {
            display: flex; align-items: center; gap: 12px; cursor: pointer; margin: 0;
            padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,.05);
            transition: background .15s;
        }
        .emp-item:last-child { border-bottom: none; }
        .emp-item:active { background: rgba(255,255,255,.06); }
        .emp-item.is-checked { background: rgba(62,198,255,.10); }
        .emp-item.is-hidden { display: none; }

        .emp-avatar {
            width: 36px; height: 36px; border-radius: 11px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary), #1e5799);
            display: flex; align-items: center; justify-content: center;
            font-size: .78rem; font-weight: 700; letter-spacing: .5px; color: #fff;
        }
        .emp-meta { min-width: 0; flex: 1; }
        .emp-name { font-size: .92rem; font-weight: 600; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .emp-dept { font-size: .74rem; color: var(--text-sub); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .emp-empty { padding: 24px 16px; text-align: center; font-size: .85rem; color: var(--text-sub); }

        /* Recap dans la SweetAlert de résultat */
        .recap { text-align: left; max-height: 300px; overflow-y: auto; margin-top: 6px; }
        .recap-row {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 9px 12px; border-radius: 10px; margin-bottom: 6px; font-size: .85rem;
            background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.06);
        }
        .recap-row .who { font-weight: 700; color: #fff; }
        .recap-row .what { display: block; font-size: .78rem; color: #94a9c4; }
        .recap-row.ko { background: rgba(255,77,106,.10); border-color: rgba(255,77,106,.25); }

        /* Action buttons */
        .btns-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 28px; }
        .btn-action {
            height: 64px; border-radius: 18px; font-size: 1rem; font-weight: 700;
            letter-spacing: .3px; display: flex; align-items: center; justify-content: center; gap: 10px;
            border: none; color: #fff; cursor: pointer; position: relative; overflow: hidden;
            transition: transform .2s cubic-bezier(.175,.885,.32,1.275), box-shadow .2s;
        }
        .btn-action:active { transform: scale(.94); }
        .btn-action i { font-size: 1.15rem; }
        .btn-entree { background: linear-gradient(135deg, #00d98b 0%, #00af6e 100%); box-shadow: 0 10px 28px rgba(0,217,139,.3); }
        .btn-entree:hover { box-shadow: 0 14px 36px rgba(0,217,139,.5); transform: translateY(-2px); }
        .btn-sortie { background: linear-gradient(135deg, #ff4d6a 0%, #d63050 100%); box-shadow: 0 10px 28px rgba(255,77,106,.3); }
        .btn-sortie:hover { box-shadow: 0 14px 36px rgba(255,77,106,.5); transform: translateY(-2px); }
        .btn-action:disabled { opacity:.6; cursor:not-allowed; transform:none; }

        /* Supervisor footer */
        .footer-info { text-align: center; margin-top: auto; padding: 18px 0; border-top: 1px solid rgba(255,255,255,.05); }
        .sv-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.06); border-radius:100px; padding:7px 18px; font-size:.8rem; color:var(--text-sub); }
        .sv-badge i { color:var(--accent); }
        .logout-lk { display:inline-block; margin-top:8px; font-size:.78rem; color:var(--red); text-decoration:none; font-weight:600; opacity:.8; }
        .logout-lk:hover { opacity:1; color:var(--red); }

        /* SweetAlert dark */
        .swal2-popup { background:#0d1b3e !important; border-radius:24px !important; color:#fff !important; border:1px solid rgba(255,255,255,.1) !important; }
        .swal2-title { color:#fff !important; font-family:'Inter',sans-serif !important; }
        .swal2-confirm { background:var(--primary) !important; border-radius:12px !important; padding:10px 26px !important; }
        .swal2-html-container { color: #b0c4de !important; font-size: .93rem !important; }

        /* Responsive */
        @media (max-width: 380px) {
            .btns-wrap { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="app-wrap">

        <!-- Header -->
        <div class="app-header">
            <img src="{{ asset('img/logos/logo.png') }}" alt="GP Flow" class="brand-img"
                 onerror="this.src='https://dc-knowing.com/rhflow/img/logos/logo.png'">
            <h1 class="app-title">Pointage Succursale</h1>
            <div class="app-sub">Pointage collectif — horaires détectés par département</div>
        </div>

        <!-- Live clock -->
        <div class="live-clock">
            <div class="clock-pill" id="liveClock">--:--</div>
            <div class="date-pill" id="liveDate">---</div>
        </div>

        <!-- Branch badge -->
        <div class="branch-badge">
            <div class="branch-icon"><i class="fa-solid fa-building"></i></div>
            <div class="branch-info">
                <div class="label">Succursale</div>
                <div class="branch-name">{{ $branch->name }}</div>
                @if($branch->address)
                    <small style="color:var(--text-sub); font-size:.75rem">
                        <i class="fa-solid fa-location-dot me-1"></i>{{ $branch->address }}
                    </small>
                @endif
            </div>
            <div class="pulse"></div>
        </div>

        <!-- Main form card -->
        <div class="glass">

            <!-- Info banner: mode libre si aucun WorkLocation configuré -->
            @php
                $hasWorkLocations = \Modules\Settings\Models\WorkLocation::where('company_id', Auth::user()->company_id)
                    ->where('branch_id', $branch->id)->where('is_active', 1)->exists();
            @endphp

            @if(!$hasWorkLocations)
            <div class="info-banner mode-libre">
                <i class="fa-solid fa-infinity"></i>
                <span><strong>Mode Libre :</strong> Aucun horaire n'est configuré pour cette succursale. Les pointages seront enregistrés sans calcul de retard ou d'avance.</span>
            </div>
            @else
            <div class="info-banner mode-horaire">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span><strong>Horaires actifs :</strong> Le statut (à l'heure / retard / avance) sera calculé automatiquement selon le département de chaque employé.</span>
            </div>
            @endif

            <form id="portalForm">
                <input type="hidden" id="branch_id" name="branch_id" value="{{ $branch->id }}">

                <!-- Sélection multiple des employés -->
                @if($employees->isEmpty())
                    <div class="alert alert-warning" style="background:rgba(255,179,71,.12);border:1px solid rgba(255,179,71,.3);color:#ffb347;border-radius:14px;">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        Aucun employé actif rattaché à cette succursale.
                    </div>
                @else
                    <label class="field-label">
                        <i class="fa-solid fa-users me-1"></i>Sélectionner le ou les employés
                    </label>

                    <div class="emp-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" class="emp-search" id="empSearch"
                               placeholder="Rechercher un employé..." autocomplete="off">
                    </div>

                    <div class="emp-toolbar">
                        <label>
                            <input type="checkbox" class="cbx" id="selectAll">
                            <span class="txt">Tout sélectionner</span>
                        </label>
                        <span class="count-pill" id="countPill">0 / {{ $employees->count() }}</span>
                    </div>

                    <div class="emp-list" id="empList">
                        @foreach($employees as $emp)
                            @php
                                $deptName = optional($emp->department)->name;
                                $parts    = preg_split('/\s+/', trim($emp->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                                $initials = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
                            @endphp
                            <label class="emp-item"
                                   data-search="{{ mb_strtolower($emp->name . ' ' . $deptName) }}">
                                <input type="checkbox" class="cbx emp-cbx"
                                       name="employee_ids[]" value="{{ $emp->id }}"
                                       data-name="{{ $emp->name }}">
                                <span class="emp-avatar">{{ $initials }}</span>
                                <span class="emp-meta">
                                    <span class="emp-name">{{ $emp->name }}</span>
                                    <span class="emp-dept">
                                        <i class="fa-solid fa-layer-group me-1"></i>{{ $deptName ?: 'Sans département' }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                        <div class="emp-empty" id="empNoResult" style="display:none">
                            <i class="fa-solid fa-user-slash me-2"></i>Aucun employé ne correspond à cette recherche.
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="btns-wrap">
                        <button type="button" class="btn-action btn-entree" id="btnEntree" data-type="entree">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            <span class="btn-txt">ENTRÉE</span>
                        </button>
                        <button type="button" class="btn-action btn-sortie" id="btnSortie" data-type="sortie">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            <span class="btn-txt">SORTIE</span>
                        </button>
                    </div>
                @endif
            </form>
        </div>

        <!-- Supervisor footer -->
        <div class="footer-info">
            <div class="sv-badge">
                <i class="fa-solid fa-shield-halved"></i>
                Superviseur : <strong>{{ Auth::user()->name }}</strong>
            </div>
            <br>
            <a href="#" class="logout-lk" onclick="event.preventDefault(); document.getElementById('lf').submit();">
                <i class="fa-solid fa-power-off me-1"></i> Déconnexion
            </a>
            <form id="lf" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
        </div>

    </div><!-- /.app-wrap -->

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    <script>
    $(function () {

        // ── Live clock ──────────────────────────────────────────────────────
        function updateClock() {
            const now  = new Date();
            const hh   = String(now.getHours()).padStart(2,'0');
            const mm   = String(now.getMinutes()).padStart(2,'0');
            const ss   = String(now.getSeconds()).padStart(2,'0');
            $('#liveClock').text(hh + ':' + mm + ':' + ss);
            const days = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
            const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
            $('#liveDate').text(days[now.getDay()] + ' ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear());
        }
        updateClock();
        setInterval(updateClock, 1000);

        // ── CSRF setup ──────────────────────────────────────────────────────
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        // ── Échappement HTML (les noms d'employés sont injectés dans les Swal) ──
        function esc(str) {
            return $('<div>').text(str == null ? '' : str).html();
        }

        // ── Sélection : compteur, « tout sélectionner », état visuel ────────
        const $all      = $('#selectAll');
        const $cbx      = $('.emp-cbx');
        const $btns     = $('#btnEntree, #btnSortie');
        const totalEmp  = $cbx.length;

        function visibleCbx() {
            return $cbx.filter(function () {
                return !$(this).closest('.emp-item').hasClass('is-hidden');
            });
        }

        function refreshState() {
            const selected = $cbx.filter(':checked').length;

            $('#countPill').text(selected + ' / ' + totalEmp);

            // Libellé des boutons : rappel du nombre sur le point d'être pointé
            $('#btnEntree .btn-txt').text(selected > 0 ? 'ENTRÉE (' + selected + ')' : 'ENTRÉE');
            $('#btnSortie .btn-txt').text(selected > 0 ? 'SORTIE (' + selected + ')' : 'SORTIE');

            // « Tout sélectionner » ne juge que les lignes visibles (filtre actif)
            const $vis     = visibleCbx();
            const visSel   = $vis.filter(':checked').length;
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

        // ── Recherche : filtre la liste sans toucher aux cases déjà cochées ──
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

        // ── Pointage collectif ──────────────────────────────────────────────
        $('.btn-action').on('click', function () {
            const type     = $(this).data('type');
            const branchId = $('#branch_id').val();
            const $btn     = $(this);

            const $sel  = $cbx.filter(':checked');
            const ids   = $sel.map(function () { return this.value; }).get();
            const names = $sel.map(function () { return $(this).data('name'); }).get();

            if (ids.length === 0) {
                Swal.fire({
                    icon: 'warning', title: 'Attention',
                    text: 'Veuillez sélectionner au moins un employé avant de pointer.',
                    confirmButtonColor: '#1a3a6b'
                });
                return;
            }

            const label = type === 'entree' ? 'Entrée' : 'Sortie';
            const icon  = type === 'entree'
                ? '<i class="fa-solid fa-arrow-right-to-bracket" style="color:#00d98b"></i>'
                : '<i class="fa-solid fa-arrow-right-from-bracket" style="color:#ff4d6a"></i>';

            // Aperçu des noms : au-delà de 8, on résume pour garder la boîte lisible
            const preview = names.slice(0, 8).map(n => '• ' + esc(n)).join('<br>')
                + (names.length > 8 ? '<br><em>… et ' + (names.length - 8) + ' autre(s)</em>' : '');

            Swal.fire({
                title: 'Confirmer le pointage',
                html: `${icon} <strong>${label}</strong> pour <strong>${ids.length} employé(s)</strong>`
                    + `<div style="margin-top:12px;text-align:left;font-size:.85rem;color:#b0c4de;max-height:200px;overflow-y:auto">${preview}</div>`,
                showCancelButton: true,
                confirmButtonText: 'Oui, valider',
                cancelButtonText: 'Annuler',
                confirmButtonColor: type === 'entree' ? '#00d98b' : '#ff4d6a',
                cancelButtonColor: '#374151',
            }).then(result => {
                if (!result.isConfirmed) return;

                // Loading state
                const orig = $btn.html();
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status"></span>');
                $btns.prop('disabled', true);

                $.ajax({
                    url: "{{ route('company.times.qrcode-pointage.branch-portal.store') }}",
                    method: 'POST',
                    data: { employee_ids: ids, branch_id: branchId, type: type },
                    success: function (res) {
                        if ('vibrate' in navigator) navigator.vibrate([60, 40, 60]);

                        // Récapitulatif : une ligne par employé, chacun ayant son
                        // propre statut (à l'heure / retard / avance / refusé).
                        const rows = (res.results || []).map(function (r) {
                            let mark = '✅', color = '#00d98b';
                            if (!r.success)                     { mark = '⛔'; color = '#ff4d6a'; }
                            else if (r.scan_status === 'retard')    { mark = '⏰'; color = '#ff4d6a'; }
                            else if (r.scan_status === 'en_avance') { mark = '⚡'; color = '#ffb347'; }

                            return `<div class="recap-row ${r.success ? '' : 'ko'}">`
                                 + `<span>${mark}</span>`
                                 + `<span><span class="who">${esc(r.name)}</span>`
                                 + `<span class="what" style="color:${color}">${esc(r.message)}</span></span></div>`;
                        }).join('');

                        Swal.fire({
                            icon: res.failed > 0 ? (res.succeeded > 0 ? 'warning' : 'error') : 'success',
                            title: res.succeeded > 0 ? 'Pointage enregistré' : 'Aucun pointage',
                            html: `<div style="font-weight:700;color:#fff;margin-bottom:10px">${esc(res.message)}</div>`
                                + `<div class="recap">${rows}</div>`,
                            confirmButtonText: 'Fermer',
                            confirmButtonColor: '#1a3a6b',
                        });

                        // Ne décocher que les employés réellement pointés : ceux
                        // qui ont été refusés restent sélectionnés pour correction.
                        (res.results || []).forEach(function (r) {
                            if (!r.success) return;
                            const $box = $cbx.filter('[value="' + r.employee_id + '"]');
                            $box.prop('checked', false).closest('.emp-item').removeClass('is-checked');
                        });
                    },
                    error: function (xhr) {
                        const msg   = xhr.responseJSON?.message ?? 'Une erreur est survenue.';
                        const is403 = xhr.status === 403;
                        Swal.fire({
                            icon: is403 ? 'warning' : 'error',
                            title: is403 ? '⛔ Accès Refusé' : 'Erreur',
                            html: `<span style="color:#b0c4de">${esc(msg)}</span>`,
                            confirmButtonColor: '#1a3a6b',
                        });
                    },
                    complete: function () {
                        // Restaurer le bouton AVANT de rafraîchir : « orig » porte
                        // encore l'ancien compteur, c'est refreshState qui le remet à jour.
                        $btn.prop('disabled', false).html(orig);
                        $btns.prop('disabled', false);
                        refreshState();
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
