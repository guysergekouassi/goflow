@php
    $company     = $reportData['company'] ?? ($company ?? []);
    $period      = $reportData['period'] ?? ($period ?? []);
    $indicators  = $reportData['indicators'] ?? ($indicators ?? []);
    $departments = $reportData['departments'] ?? ($departments ?? []);
    $employees   = $reportData['employees'] ?? ($employees ?? []);
    $anomalies   = $reportData['anomalies'] ?? ($anomalies ?? []);

    $monthYearTitle = trim(($period['month_label'] ?? '') . ' ' . ($period['year'] ?? ''));
    if (empty($monthYearTitle)) {
        $monthYearTitle = $period['report_month'] ?? date('m/Y');
    }
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rapport Mensuel des Pointages — {{ $company['name'] ?? 'GP Flow' }} — {{ $monthYearTitle }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 10mm 12mm 14mm 12mm;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #ffffff;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #1f2937;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        .page-container {
            width: 100%;
            background: #ffffff;
        }

        /* ── En-tête du document ── */
        .doc-header-table {
            width: 100%;
            border-collapse: collapse;
            background: #1e3a8a;
            color: #ffffff;
            margin-bottom: 0;
        }
        .doc-header-table td {
            vertical-align: middle;
            padding: 14px 18px;
        }
        .dh-company {
            font-size: 12pt;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }
        .dh-company-sub {
            font-size: 7.5pt;
            color: #bfdbfe;
            margin-top: 3px;
        }
        .dh-title-block {
            text-align: center;
        }
        .dh-title {
            font-size: 11pt;
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }
        .dh-month {
            font-size: 9.5pt;
            font-weight: 700;
            color: #93c5fd;
            margin-top: 2px;
            text-transform: capitalize;
        }
        .dh-period {
            font-size: 7pt;
            color: #e0e7ff;
            margin-top: 3px;
        }
        .dh-right {
            text-align: right;
        }
        .dh-badge {
            display: inline-block;
            background: #1e40af;
            border: 1px solid #3b82f6;
            color: #ffffff;
            padding: 3px 8px;
            font-size: 7pt;
            font-weight: 600;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .dh-headcount {
            font-size: 7.5pt;
            color: #bfdbfe;
            margin-top: 5px;
        }

        /* ── Barre méta ── */
        .meta-bar-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            margin-bottom: 12px;
        }
        .meta-bar-table td {
            padding: 5px 18px;
            font-size: 6.8pt;
            color: #64748b;
        }

        /* ── Contenu ── */
        .content {
            padding: 0 4px;
        }

        /* ── Titre de section ── */
        .sec-title {
            font-size: 8.5pt;
            font-weight: 700;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            margin-bottom: 7px;
            padding-bottom: 4px;
            border-bottom: 1.5px solid #1e3a8a;
        }

        /* ── Grille KPI (Tableau 4 colonnes) ── */
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .kpi-card {
            width: 25%;
            background: #f8fafc;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .kpi-card.highlight {
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        .kpi-value {
            font-size: 16pt;
            font-weight: 800;
            color: #1e293b;
            line-height: 1.1;
        }
        .kpi-value.green {
            color: #15803d;
        }
        .kpi-value.blue {
            color: #1e40af;
        }
        .kpi-value.orange {
            color: #c2410c;
        }
        .kpi-value.red {
            color: #b91c1c;
        }
        .kpi-label {
            font-size: 6.8pt;
            color: #64748b;
            margin-top: 4px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ── Tableaux génériques de données ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-bottom: 12px;
        }
        .data-table thead {
            display: table-header-group;
        }
        .data-table thead th {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: 700;
            font-size: 7pt;
            padding: 6px 5px;
            text-align: left;
            border: 1px solid #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .data-table thead th.th-center {
            text-align: center;
        }
        .data-table thead th.th-right {
            text-align: right;
            padding-right: 8px;
        }
        .data-table tbody tr {
            page-break-inside: avoid;
        }
        .data-table tbody td {
            border: 1px solid #e2e8f0;
            padding: 4px 5px;
            vertical-align: middle;
            color: #334155;
        }
        .data-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }
        .data-table tfoot td {
            background: #e2e8f0;
            font-weight: 700;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            padding: 5px;
            font-size: 7pt;
        }

        .tc-center { text-align: center; }
        .tc-right { text-align: right; padding-right: 8px; }
        .fw-bold { font-weight: 700; }

        .emp-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 7.2pt;
        }
        .emp-sub {
            font-size: 6pt;
            color: #64748b;
            margin-top: 1px;
        }

        /* ── Badges de taux ── */
        .rate-high {
            color: #15803d;
            font-weight: 700;
        }
        .rate-mid {
            color: #b45309;
            font-weight: 700;
        }
        .rate-low {
            color: #b91c1c;
            font-weight: 700;
        }

        /* ── Section Anomalies ── */
        .anomaly-badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 2px;
            font-size: 6.2pt;
            font-weight: 700;
            text-transform: uppercase;
        }
        .ab-retard { background: #fef3c7; color: #92400e; }
        .ab-incomplet { background: #fee2e2; color: #991b1b; }
        .ab-absence { background: #f1f5f9; color: #475569; }

        .empty-alert {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 8px 12px;
            border-radius: 3px;
            font-size: 7.2pt;
            margin-bottom: 12px;
        }

        /* ── Pied de page ── */
        .doc-footer-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
            margin-top: 16px;
        }
        .doc-footer-table td {
            padding: 6px 10px;
            font-size: 6.5pt;
            color: #94a3b8;
            vertical-align: middle;
        }
        .doc-footer-table .brand {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 7.5pt;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

<div class="page-container">

    {{-- En-tête du document --}}
    <table class="doc-header-table">
        <tr>
            <td style="width: 32%;">
                <div class="dh-company">{{ $company['name'] ?? 'GP FLOW' }}</div>
                <div class="dh-company-sub">
                    @if(!empty($company['address']))
                        {{ $company['address'] }}<br>
                    @endif
                    @if(!empty($company['email']))
                        {{ $company['email'] }}
                    @endif
                    @if(!empty($company['phone']))
                        · {{ $company['phone'] }}
                    @endif
                </div>
            </td>
            <td style="width: 44%;" class="dh-title-block">
                <div class="dh-title">Rapport Mensuel des Pointages</div>
                <div class="dh-month">{{ $monthYearTitle }}</div>
                <div class="dh-period">
                    Du {{ $period['start_date_fr'] ?? $period['start_date'] ?? '01' }} au {{ $period['end_date_fr'] ?? $period['end_date'] ?? '31' }}
                </div>
            </td>
            <td style="width: 24%;" class="dh-right">
                <div class="dh-badge">Bilan Mensuel RH</div>
                <div class="dh-headcount">Effectif actif : <strong>{{ $indicators['total_employees'] ?? count($employees) }}</strong></div>
            </td>
        </tr>
    </table>

    {{-- Barre méta --}}
    <table class="meta-bar-table">
        <tr>
            <td style="width: 35%; text-align: left;">
                GP FLOW — Plateforme de Gestion du Temps & Assiduité
            </td>
            <td style="width: 35%; text-align: center;">
                Généré le : {{ $period['generated_at'] ?? now()->format('d/m/Y à H:i') }}
            </td>
            <td style="width: 30%; text-align: right;">
                Fuseau : {{ $company['timezone'] ?? 'Africa/Abidjan' }}
            </td>
        </tr>
    </table>

    <div class="content">

        {{-- SECTION 1 : Tableau de bord mensuel (KPI en grille) --}}
        <div class="sec-title">1. Indicateurs Globaux du Mois</div>

        <table class="kpi-table">
            <tr>
                <td class="kpi-card highlight">
                    <div class="kpi-value green">{{ round($indicators['presence_rate'] ?? 0, 1) }}%</div>
                    <div class="kpi-label">Taux de présence global</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value blue">{{ $indicators['total_presence_days'] ?? 0 }}</div>
                    <div class="kpi-label">Jours de présence cumulés</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value red">{{ $indicators['total_absence_days'] ?? 0 }}</div>
                    <div class="kpi-label">Jours d'absence totale</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value orange">{{ $indicators['total_retards'] ?? 0 }}</div>
                    <div class="kpi-label">Total retards constatés</div>
                </td>
            </tr>
            <tr>
                <td class="kpi-card">
                    <div class="kpi-value">{{ $indicators['total_employees'] ?? count($employees) }}</div>
                    <div class="kpi-label">Employés actifs suivis</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value orange">{{ $indicators['total_incomplete'] ?? 0 }}</div>
                    <div class="kpi-label">Pointages incomplets (sans sortie)</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value blue">{{ $indicators['total_leaves'] ?? 0 }}</div>
                    <div class="kpi-label">Jours de congés & permissions</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value">
                        @php
                            $totalWorkingDays = $indicators['total_expected_workdays'] ?? (($indicators['total_presence_days'] ?? 0) + ($indicators['total_absence_days'] ?? 0));
                        @endphp
                        {{ $totalWorkingDays }}
                    </div>
                    <div class="kpi-label">Jours théoriques cumulés</div>
                </td>
            </tr>
        </table>

        {{-- SECTION 2 : Statistiques par département --}}
        <div class="sec-title">2. Synthèse par Département</div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Département</th>
                    <th style="width: 11%;" class="th-center">Effectif</th>
                    <th style="width: 14%;" class="th-center">Présence (j)</th>
                    <th style="width: 14%;" class="th-center">Absence (j)</th>
                    <th style="width: 10%;" class="th-center">Retards</th>
                    <th style="width: 10%;" class="th-center">Incomplets</th>
                    <th style="width: 11%;" class="th-right">Taux (%)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totDeptEmp = 0;
                    $totDeptPres = 0;
                    $totDeptAbs = 0;
                    $totDeptRet = 0;
                    $totDeptInc = 0;
                @endphp
                @forelse($departments as $dept)
                    @php
                        $totDeptEmp  += ($dept['employee_count'] ?? 0);
                        $totDeptPres += ($dept['presence_days'] ?? 0);
                        $totDeptAbs  += ($dept['absence_days'] ?? 0);
                        $totDeptRet  += ($dept['retards'] ?? 0);
                        $totDeptInc  += ($dept['incomplete'] ?? 0);
                        $dRate = (float) ($dept['presence_rate'] ?? 0);
                    @endphp
                    <tr>
                        <td class="fw-bold">{{ $dept['name'] ?? 'Général' }}</td>
                        <td class="tc-center">{{ $dept['employee_count'] ?? 0 }}</td>
                        <td class="tc-center">{{ $dept['presence_days'] ?? 0 }} j</td>
                        <td class="tc-center">{{ $dept['absence_days'] ?? 0 }} j</td>
                        <td class="tc-center">{{ $dept['retards'] ?? 0 }}</td>
                        <td class="tc-center">{{ $dept['incomplete'] ?? 0 }}</td>
                        <td class="tc-right">
                            <span class="{{ $dRate >= 90 ? 'rate-high' : ($dRate >= 75 ? 'rate-mid' : 'rate-low') }}">
                                {{ round($dRate, 1) }}%
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="tc-center" style="padding: 10px; color: #94a3b8;">
                            Aucun regroupement par département disponible.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if(count($departments) > 0)
                <tfoot>
                    <tr>
                        <td>TOTAL GÉNÉRAL</td>
                        <td class="tc-center">{{ $totDeptEmp }}</td>
                        <td class="tc-center">{{ $totDeptPres }} j</td>
                        <td class="tc-center">{{ $totDeptAbs }} j</td>
                        <td class="tc-center">{{ $totDeptRet }}</td>
                        <td class="tc-center">{{ $totDeptInc }}</td>
                        <td class="tc-right">
                            <span class="rate-high">{{ round($indicators['presence_rate'] ?? 0, 1) }}%</span>
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>

        {{-- SECTION 3 : Détails individuels par employé --}}
        <div class="sec-title" style="margin-top: 14px;">3. Assiduité Individuelle des Collaborateurs</div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Matricule</th>
                    <th style="width: 25%;">Employé</th>
                    <th style="width: 17%;">Département</th>
                    <th style="width: 8%;" class="th-center">Présence</th>
                    <th style="width: 8%;" class="th-center">Absence</th>
                    <th style="width: 8%;" class="th-center">Retards</th>
                    <th style="width: 8%;" class="th-center">Incomplets</th>
                    <th style="width: 8%;" class="th-center">Congés</th>
                    <th style="width: 8%;" class="th-right">Taux</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $emp)
                    @php
                        $rate = (float) ($emp['presence_rate'] ?? 0);
                    @endphp
                    <tr>
                        <td style="color: #64748b; font-size: 6.5pt;">{{ $emp['matricule'] ?? '—' }}</td>
                        <td>
                            <div class="emp-name">{{ $emp['name'] ?? 'Employé' }}</div>
                            @if(!empty($emp['branch']))
                                <div class="emp-sub">{{ $emp['branch'] }}</div>
                            @endif
                        </td>
                        <td>{{ $emp['department'] ?? '—' }}</td>
                        <td class="tc-center fw-bold">{{ $emp['presence_days'] ?? 0 }} j</td>
                        <td class="tc-center">{{ $emp['absence_days'] ?? 0 }} j</td>
                        <td class="tc-center">{{ $emp['retards'] ?? 0 }}</td>
                        <td class="tc-center">{{ $emp['incomplete_days'] ?? 0 }}</td>
                        <td class="tc-center">{{ $emp['leave_days'] ?? 0 }} j</td>
                        <td class="tc-right">
                            <span class="{{ $rate >= 90 ? 'rate-high' : ($rate >= 75 ? 'rate-mid' : 'rate-low') }}">
                                {{ round($rate, 1) }}%
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="tc-center" style="padding: 12px; color: #94a3b8;">
                            Aucun enregistrement d'employé trouvé pour cette période.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- SECTION 4 : Anomalies --}}
        <div class="sec-title" style="margin-top: 14px;">4. Signalements & Anomalies Constatées</div>

        @if(!empty($anomalies) && count($anomalies) > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Date</th>
                        <th style="width: 25%;">Employé</th>
                        <th style="width: 18%;">Type d'anomalie</th>
                        <th style="width: 45%;">Description / Détail</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($anomalies as $anomaly)
                        @php
                            $typeStr = strtolower($anomaly['type'] ?? '');
                            $badgeClass = 'ab-absence';
                            if (str_contains($typeStr, 'retard')) {
                                $badgeClass = 'ab-retard';
                            } elseif (str_contains($typeStr, 'incomplet') || str_contains($typeStr, 'sortie')) {
                                $badgeClass = 'ab-incomplet';
                            }
                        @endphp
                        <tr>
                            <td>{{ $anomaly['date'] ?? '—' }}</td>
                            <td class="fw-bold">{{ $anomaly['employee_name'] ?? '—' }}</td>
                            <td>
                                <span class="anomaly-badge {{ $badgeClass }}">{{ $anomaly['type'] ?? 'Anomalie' }}</span>
                            </td>
                            <td>{{ $anomaly['description'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-alert">
                ✓ <strong>Conformité :</strong> Aucune anomalie majeure (retards excessifs récurrents ou pointages non clôturés) n'a été répertoriée pour ce mois.
            </div>
        @endif

    </div>

    {{-- Pied de page officiel --}}
    <table class="doc-footer-table">
        <tr>
            <td style="width: 25%;" class="brand">
                GP FLOW · RH
            </td>
            <td style="width: 50%; text-align: center;">
                Rapport mensuel officiel — Usage strictement confidentiel & interne
            </td>
            <td style="width: 25%; text-align: right;">
                {{ $monthYearTitle }}
            </td>
        </tr>
    </table>

</div>

</body>
</html>
