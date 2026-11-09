<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rapport hebdomadaire de présence — {{ $company['name'] ?? 'GP Flow' }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: #f0f0f0;
      padding: 48px 16px;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      -webkit-font-smoothing: antialiased;
      color: #111827;
    }

    .wrap {
      max-width: 480px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 4px;
      overflow: hidden;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    }

    /* ── Bandeau supérieur ── */
    .top-bar {
      background: #1d3d8f;
      height: 4px;
    }

    /* ── En-tête ── */
    .header {
      padding: 36px 40px 28px;
      border-bottom: 1px solid #e5e7eb;
    }
    .header-meta {
      font-size: 10px;
      font-weight: 600;
      color: #9ca3af;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-bottom: 12px;
    }
    .header h1 {
      font-size: 20px;
      font-weight: 700;
      color: #111827;
      line-height: 1.25;
      margin-bottom: 8px;
    }
    .header-sub {
      font-size: 13px;
      color: #6b7280;
      line-height: 1.5;
    }
    .header-sub strong {
      color: #374151;
      font-weight: 600;
    }

    /* ── Corps ── */
    .body {
      padding: 32px 40px;
    }

    .section-label {
      font-size: 10px;
      font-weight: 600;
      color: #9ca3af;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-bottom: 20px;
    }

    /* ── Métriques ── */
    .metrics-table {
      width: 100%;
      border-collapse: collapse;
    }
    .metrics-table td {
      padding: 12px 0;
      border-bottom: 1px solid #f3f4f6;
      vertical-align: middle;
    }
    .metrics-table tr:last-child td {
      border-bottom: none;
    }
    .metric-name {
      font-size: 13.5px;
      color: #4b5563;
      font-weight: 400;
      text-align: left;
    }
    .metric-value {
      font-size: 14px;
      font-weight: 700;
      color: #111827;
      text-align: right;
    }
    .metric-value.good  { color: #16a34a; }

    /* ── Notice ── */
    .notice {
      margin-top: 28px;
      padding: 14px 16px;
      border-left: 2px solid #1d3d8f;
      background: #f9fafb;
      font-size: 12.5px;
      color: #4b5563;
      line-height: 1.6;
    }

    /* ── Pied ── */
    .footer {
      padding: 20px 40px 28px;
      border-top: 1px solid #e5e7eb;
    }
    .footer p {
      font-size: 11px;
      color: #9ca3af;
      line-height: 1.6;
      margin: 0;
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="top-bar"></div>

  <div class="header">
    <div class="header-meta">GP Flow · Semaine {{ $period['week_number'] ?? date('W') }} / {{ $period['year'] ?? date('Y') }}</div>
    <h1>Rapport hebdomadaire<br>de présence</h1>
    <p class="header-sub">
      <strong>{{ $company['name'] ?? 'Entreprise' }}</strong><br>
      {{ $period['range_short'] ?? ($period['start_date_fr'] . ' — ' . $period['end_date_fr']) }}
    </p>
  </div>

  <div class="body">
    <div class="section-label">Indicateurs de la semaine</div>

    <table class="metrics-table">
      <tr>
        <td class="metric-name">Employés actifs</td>
        <td class="metric-value">{{ $indicators['total_employees'] ?? 0 }}</td>
      </tr>
      <tr>
        <td class="metric-name">Employés ayant pointé</td>
        <td class="metric-value">{{ $indicators['employees_punched_count'] ?? 0 }}</td>
      </tr>
      <tr>
        <td class="metric-name">Taux de présence</td>
        <td class="metric-value good">{{ round($indicators['presence_rate'] ?? 0) }} %</td>
      </tr>
      <tr>
        <td class="metric-name">Jours de présence pointés</td>
        <td class="metric-value">{{ $indicators['total_presences'] ?? 0 }} j</td>
      </tr>
    </table>

    <div class="notice">
      Le rapport complet — pointages jour par jour et présences — est joint en <strong>PDF</strong> et <strong>Excel</strong> à cet e-mail.
    </div>
  </div>

  <div class="footer">
    <p>
      Rapport envoyé automatiquement chaque vendredi à 18 h par GP Flow.<br>
      © {{ $period['year'] ?? date('Y') }} GP Flow — Système de Gestion RH
    </p>
  </div>
</div>
</body>
</html>
