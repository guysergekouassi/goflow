@php
    $company     = $reportData['company'] ?? ($company ?? []);
    $period      = $reportData['period'] ?? ($period ?? []);
    $indicators  = $reportData['indicators'] ?? ($indicators ?? []);
    $departments = $reportData['departments'] ?? ($departments ?? []);
    $employees   = $reportData['employees'] ?? ($employees ?? []);
    $anomalies   = $reportData['anomalies'] ?? ($anomalies ?? []);

    $monthYear = trim(($period['month_label'] ?? '') . ' ' . ($period['year'] ?? ''));
    if (empty($monthYear)) {
        $monthYear = $period['report_month'] ?? date('m/Y');
    }
    $presenceRate = round($indicators['presence_rate'] ?? 0, 1);
@endphp
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Rapport Mensuel des Pointages — {{ $monthYear }}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        body, p, h1, h2, h3, h4, table, td {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 14px;
            line-height: 1.5;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            padding: 30px 10px;
        }
        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            border: 0;
            outline: none;
            text-decoration: none;
            display: block;
        }
        .container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        }
        .header-banner {
            background: #1e3a8a;
            background-color: #1e3a8a;
            padding: 32px 30px 24px;
            text-align: left;
        }
        .header-meta {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #93c5fd;
            margin-bottom: 8px;
        }
        .header-title {
            color: #ffffff;
            font-size: 22px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 8px;
        }
        .header-sub {
            color: #e0e7ff;
            font-size: 13px;
            line-height: 1.4;
        }
        .content-body {
            padding: 28px 30px;
            background-color: #ffffff;
        }
        .summary-box {
            background-color: #f8fafc;
            border-left: 4px solid #1e3a8a;
            border-radius: 4px;
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 13.5px;
            color: #334155;
            line-height: 1.55;
        }
        .summary-box strong {
            color: #0f172a;
        }
        .section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .kpi-table {
            width: 100%;
            margin-bottom: 24px;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .kpi-table td {
            padding: 11px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: middle;
        }
        .kpi-table tr:last-child td {
            border-bottom: none;
        }
        .kpi-name {
            color: #475569;
            text-align: left;
        }
        .kpi-value {
            font-weight: 700;
            text-align: right;
            color: #0f172a;
        }
        .kpi-value.green {
            color: #16a34a;
            font-size: 14.5px;
        }
        .kpi-value.orange {
            color: #c2410c;
        }
        .dept-table {
            width: 100%;
            margin-bottom: 24px;
            font-size: 12.5px;
            border-collapse: collapse;
        }
        .dept-table th {
            background-color: #f1f5f9;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
        }
        .dept-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .dept-table tr:last-child td {
            border-bottom: none;
        }
        .attachment-notice {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 24px;
            text-align: center;
        }
        .attachment-notice p {
            font-size: 13px;
            color: #1e40af;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .attachment-notice small {
            font-size: 12px;
            color: #3b82f6;
        }
        .btn-cta {
            display: inline-block;
            background-color: #1e3a8a;
            color: #ffffff !important;
            text-decoration: none;
            padding: 11px 24px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.3px;
            text-align: center;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 30px;
            text-align: center;
            font-size: 11.5px;
            color: #94a3b8;
            line-height: 1.6;
        }
        .footer strong {
            color: #64748b;
        }
    </style>
</head>
<body>

    <center>
        <div class="container">

            {{-- Bannière supérieure bleue --}}
            <div class="header-banner">
                <div class="header-meta">
                    GP FLOW · Rapport Mensuel des Pointages
                </div>
                <h1 class="header-title">
                    Bilan Mensuel d'Assiduité
                </h1>
                <div class="header-sub">
                    <strong>{{ $company['name'] ?? 'Votre Entreprise' }}</strong><br>
                    Mois de <strong>{{ $monthYear }}</strong>
                    @if(!empty($period['start_date_fr']) && !empty($period['end_date_fr']))
                        · Du {{ $period['start_date_fr'] }} au {{ $period['end_date_fr'] }}
                    @endif
                </div>
            </div>

            {{-- Corps du mail --}}
            <div class="content-body">

                {{-- Résumé synthétique --}}
                <div class="summary-box">
                    Bonjour,<br><br>
                    Le bilan mensuel des présences de <strong>{{ $company['name'] ?? 'votre entreprise' }}</strong> pour <strong>{{ $monthYear }}</strong> a été consolidé avec succès.<br><br>
                    Sur un effectif actif de <strong>{{ $indicators['total_employees'] ?? count($employees) }} collaborateur(s)</strong>, le taux de présence global s'établit à <strong style="color: {{ $presenceRate >= 90 ? '#16a34a' : ($presenceRate >= 75 ? '#d97706' : '#dc2626') }};">{{ $presenceRate }}%</strong>.
                    @if(!empty($anomalies) && count($anomalies) > 0)
                        Un total de <strong>{{ count($anomalies) }} anomalie(s)</strong> (retards importants ou pointages incomplets) a été répertorié.
                    @else
                        Aucune anomalie critique de pointage n'a été constatée sur la période.
                    @endif
                </div>

                {{-- Indicateurs Clés du Mois --}}
                <div class="section-label">Indicateurs clés du mois</div>

                <table class="kpi-table" width="100%">
                    <tr>
                        <td class="kpi-name">Effectif total suivi</td>
                        <td class="kpi-value">{{ $indicators['total_employees'] ?? count($employees) }} employés</td>
                    </tr>
                    <tr>
                        <td class="kpi-name">Taux global de présence</td>
                        <td class="kpi-value green">{{ $presenceRate }} %</td>
                    </tr>
                    <tr>
                        <td class="kpi-name">Jours de présence cumulés</td>
                        <td class="kpi-value">{{ $indicators['total_presence_days'] ?? 0 }} j</td>
                    </tr>
                    <tr>
                        <td class="kpi-name">Jours d'absence cumulés</td>
                        <td class="kpi-value">{{ $indicators['total_absence_days'] ?? 0 }} j</td>
                    </tr>
                    <tr>
                        <td class="kpi-name">Retards enregistrés</td>
                        <td class="kpi-value orange">{{ $indicators['total_retards'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td class="kpi-name">Pointages incomplets (départ manquant)</td>
                        <td class="kpi-value orange">{{ $indicators['total_incomplete'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td class="kpi-name">Congés et autorisations d'absence</td>
                        <td class="kpi-value">{{ $indicators['total_leaves'] ?? 0 }} j</td>
                    </tr>
                </table>

                {{-- Aperçu par département (si disponible) --}}
                @if(!empty($departments) && count($departments) > 0)
                    <div class="section-label">Aperçu par département</div>
                    <table class="dept-table" width="100%">
                        <thead>
                            <tr>
                                <th>Département</th>
                                <th style="text-align: center;">Effectif</th>
                                <th style="text-align: center;">Présence</th>
                                <th style="text-align: right;">Taux</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($departments, 0, 5) as $dept)
                                <tr>
                                    <td><strong>{{ $dept['name'] ?? 'Service' }}</strong></td>
                                    <td style="text-align: center;">{{ $dept['employee_count'] ?? 0 }}</td>
                                    <td style="text-align: center;">{{ $dept['presence_days'] ?? 0 }} j</td>
                                    <td style="text-align: right; font-weight: 700; color: #1e3a8a;">
                                        {{ round($dept['presence_rate'] ?? 0, 1) }} %
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Notice sur les pièces jointes --}}
                <div class="attachment-notice">
                    <p>📎 Retrouvez le rapport complet en pièce jointe.</p>
                    <small>
                        Le document joint (PDF et/ou Excel) contient la matrice exhaustive des pointages jour par jour, les détails par employé et la liste des anomalies.
                    </small>
                </div>

                {{-- Bouton vers l'espace entreprise --}}
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-top: 10px; margin-bottom: 10px;">
                    <tr>
                        <td align="center">
                            <a href="{{ config('app.url') }}/company/reports/monthly" class="btn-cta" target="_blank">
                                Consulter l'historique sur GP Flow
                            </a>
                        </td>
                    </tr>
                </table>

            </div>

            {{-- Pied de page --}}
            <div class="footer">
                <p>
                    Ce rapport mensuel est généré et transmis automatiquement par <strong>GP Flow</strong> selon vos préférences de planification.<br>
                    Pour ajuster la date d'envoi ou la liste des destinataires, accédez aux paramètres de votre espace entreprise.<br><br>
                    © {{ date('Y') }} <strong>GP Flow</strong> — Système de Gestion RH & Présence. Tous droits réservés.
                </p>
            </div>

        </div>
    </center>

</body>
</html>
