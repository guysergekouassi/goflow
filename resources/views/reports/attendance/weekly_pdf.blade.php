<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Rapport Hebdomadaire de Présence — {{ $company['name'] ?? 'GP Flow' }}</title>
  <style>
    @page {
      size: a4 landscape;
      margin: 8mm 10mm;
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
      line-height: 1.25;
      margin: 0;
      padding: 0;
    }

    .page {
      width: 100%;
      background: #ffffff;
    }

    /* ── Header ── */
    .doc-header-table {
      width: 100%;
      border-collapse: collapse;
      background: #1d3d8f;
    }
    .doc-header-table td {
      vertical-align: middle;
      padding: 12px 18px;
    }
    .dh-company {
      font-size: 11pt;
      font-weight: 700;
      color: #ffffff;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .dh-company-sub {
      font-size: 7.5pt;
      color: #9cb4e8;
      margin-top: 3px;
    }
    .dh-title-block {
      text-align: center;
    }
    .dh-title {
      font-size: 10.5pt;
      font-weight: 700;
      color: #ffffff;
      text-transform: uppercase;
      letter-spacing: 1.5px;
    }
    .dh-period {
      font-size: 8pt;
      color: #b5c7f2;
      margin-top: 3px;
    }
    .dh-right {
      text-align: right;
    }
    .dh-week {
      font-size: 8pt;
      color: #b5c7f2;
    }
    .dh-headcount {
      font-size: 7.5pt;
      color: #9cb4e8;
      margin-top: 3px;
    }

    /* ── Barre méta ── */
    .meta-bar-table {
      width: 100%;
      border-collapse: collapse;
      border-bottom: 1px solid #e5e7eb;
      margin-bottom: 12px;
    }
    .meta-bar-table td {
      padding: 5px 18px;
      font-size: 7pt;
      color: #9ca3af;
    }

    /* ── Contenu ── */
    .content {
      padding: 0 10px;
    }

    /* ── Titre de section ── */
    .sec {
      font-size: 8pt;
      font-weight: 700;
      color: #1d3d8f;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-bottom: 8px;
      padding-bottom: 4px;
      border-bottom: 1px solid #e5e7eb;
    }

    /* ── Grille stats (Table 4 colonnes) ── */
    .stats-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #e5e7eb;
      margin-bottom: 14px;
      background: #e5e7eb;
    }
    .stat-cell {
      width: 25%;
      background: #ffffff;
      padding: 10px 14px;
      border: 1px solid #e5e7eb;
      vertical-align: top;
    }
    .stat-val {
      font-size: 20pt;
      font-weight: 800;
      color: #111827;
      line-height: 1;
    }
    .stat-val.green { color: #16a34a; }
    .stat-lbl {
      font-size: 7pt;
      color: #6b7280;
      margin-top: 5px;
      line-height: 1.2;
    }

    /* ── Matrice des pointages ── */
    .matrix {
      width: 100%;
      border-collapse: collapse;
      font-size: 7.5pt;
    }
    .matrix thead th {
      background: #1d3d8f;
      color: #ffffff;
      padding: 6px 4px;
      text-align: center;
      font-weight: 600;
      font-size: 7.5pt;
      border: 1px solid #2a4fa8;
    }
    .matrix thead th.th-emp {
      text-align: left;
      padding-left: 10px;
    }
    .matrix tbody td {
      border: 1px solid #e5e7eb;
      padding: 5px 4px;
      vertical-align: middle;
    }
    .matrix tbody tr:nth-child(even) td { background: #f9fafb; }

    .emp-name { font-weight: 700; color: #111827; font-size: 7.5pt; }
    .emp-sub  { font-size: 6pt; color: #9ca3af; margin-top: 1px; }

    .pc { text-align: center; line-height: 1.35; }
    .pt { font-weight: 600; color: #111827; font-size: 7pt; }
    .pd { color: #6b7280; font-size: 6.5pt; }
    .pm { color: #9ca3af; font-size: 6pt; margin-top: 1px; }

    .sa { color: #dc2626; font-weight: 700; font-size: 7pt; text-align: center; display: block; }
    .sj { color: #1d4ed8; font-size: 7pt; text-align: center; display: block; font-weight: 600; }
    .sm { color: #9ca3af; font-size: 6pt; text-align: center; display: block; }

    .cs { text-align: center; font-weight: 700; font-size: 8pt; color: #374151; }

    /* ── Footer ── */
    .doc-footer-table {
      width: 100%;
      border-collapse: collapse;
      border-top: 1px solid #e5e7eb;
      background: #f9fafb;
      margin-top: 14px;
    }
    .doc-footer-table td {
      padding: 6px 18px;
      font-size: 7pt;
      color: #9ca3af;
      vertical-align: middle;
    }
    .doc-footer-table .brand {
      color: #1d3d8f;
      font-weight: 700;
      font-size: 7.5pt;
    }
  </style>
</head>
<body>

<div class="page">
  <!-- En-tête -->
  <table class="doc-header-table">
    <tr>
      <td style="width: 32%;">
        <div class="dh-company">{{ $company['name'] ?? 'ENTREPRISE' }}</div>
        <div class="dh-company-sub">Rapport de présence — Ressources Humaines</div>
      </td>
      <td style="width: 44%; text-align: center;">
        <div class="dh-title">Rapport Hebdomadaire de Présence</div>
        <div class="dh-period">{{ $period['start_formatted'] }} — {{ $period['end_formatted'] }}</div>
      </td>
      <td style="width: 24%; text-align: right;">
        <div class="dh-week">Semaine n° {{ $period['week_number'] }} — {{ $period['year'] }}</div>
        <div class="dh-headcount">Effectif actif : {{ $indicators['total_employees'] }} employés</div>
      </td>
    </tr>
  </table>

  <!-- Barre méta -->
  <table class="meta-bar-table">
    <tr>
      <td style="text-align: left; width: 33%;">© GPFLOW — Système de Gestion RH</td>
      <td style="text-align: center; width: 34%;">Date de tirage : {{ $period['generated_at'] }}</td>
      <td style="text-align: right; width: 33%;">Rapport officiel</td>
    </tr>
  </table>

  <div class="content">
    <!-- Statistiques de la semaine (KPIs retenus : 4 colonnes) -->
    <div class="sec">Statistiques de la semaine</div>
    <table class="stats-table">
      <tr>
        <td class="stat-cell">
          <div class="stat-val">{{ $indicators['total_employees'] }}</div>
          <div class="stat-lbl">Employés actifs</div>
        </td>
        <td class="stat-cell">
          <div class="stat-val">{{ $indicators['employees_punched_count'] }}</div>
          <div class="stat-lbl">Ont pointé</div>
        </td>
        <td class="stat-cell">
          <div class="stat-val green">{{ round($indicators['presence_rate']) }}%</div>
          <div class="stat-lbl">Taux de présence</div>
        </td>
        <td class="stat-cell">
          <div class="stat-val">{{ $indicators['total_presences'] }}</div>
          <div class="stat-lbl">Jours de présence pointés</div>
        </td>
      </tr>
    </table>

    <!-- Matrice détaillée des pointages par employé -->
    <div class="sec">Détail des pointages par employé</div>
    <p style="font-size: 6.8pt; color: #9ca3af; margin-bottom: 8px;">
      Chaque case : arrivée – départ · durée de travail · mode de pointage.
    </p>

    <table class="matrix">
      <thead>
        <tr>
          <th class="th-emp" style="width: 21%;">Employé</th>
          @foreach($workdays as $wd)
            <th style="width: 12.6%;">
              {{ mb_convert_case($wd->locale('fr')->isoFormat('ddd. DD/MM'), MB_CASE_TITLE, "UTF-8") }}
            </th>
          @endforeach
          <th style="width: 8%;">Présence</th>
          <th style="width: 8%;">Heures</th>
        </tr>
      </thead>
      <tbody>
        @foreach($employees as $emp)
          <tr>
            <td>
              <div class="emp-name">{{ $emp['name'] }}</div>
              <div class="emp-sub">{{ $emp['matricule'] }} · {{ $emp['department'] }}</div>
            </td>
            @foreach($workdays as $wd)
              @php
                $dKey = $wd->format('Y-m-d');
                $dayInfo = $emp['days'][$dKey] ?? null;
              @endphp
              <td>
                @if(!$dayInfo)
                  <span class="sm">—</span>
                @elseif($dayInfo['status_type'] === 'present')
                  <div class="pc">
                    <div class="pt">{{ $dayInfo['arrival_time'] }} – {{ $dayInfo['departure_time'] }}</div>
                    <div class="pd">{{ $dayInfo['worked_time'] }}</div>
                    <div class="pm">{{ $dayInfo['mode'] }}</div>
                  </div>
                @elseif($dayInfo['status_type'] === 'unjustified_absence')
                  <span class="sa">Absent</span>
                @elseif($dayInfo['status_type'] === 'justified_absence')
                  <span class="sj">Justifié</span>
                  <span class="sm">{{ $dayInfo['motif'] }}</span>
                @elseif($dayInfo['status_type'] === 'holiday')
                  <span class="sm" style="color: #d97706; font-weight: 700;">Férié</span>
                @elseif($dayInfo['status_type'] === 'closed')
                  <span class="sm">Fermé</span>
                @else
                  <span class="sm">{{ $dayInfo['status'] }}</span>
                @endif
              </td>
            @endforeach
            <td class="cs">{{ $emp['present_days'] }} j</td>
            <td class="cs">{{ $emp['total_hours'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <!-- Pied de page -->
  <table class="doc-footer-table">
    <tr>
      <td style="width: 25%;" class="brand">GP Flow</td>
      <td style="width: 50%; text-align: center;">Rapport généré automatiquement — Confidentiel RH</td>
      <td style="width: 25%; text-align: right;">Document officiel</td>
    </tr>
  </table>
</div>

</body>
</html>
