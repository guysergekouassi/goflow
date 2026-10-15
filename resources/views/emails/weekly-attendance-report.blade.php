<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:24px; background:#f5f5f9; font-family: Arial, sans-serif; color:#2f3349;">
    @php $s = $rapport['stats']; @endphp
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; padding:24px;">
        <h2 style="margin:0 0 4px; color:#253e87;">Rapport hebdomadaire de présence</h2>
        <p style="margin:0 0 16px; color:#8592a3;">
            {{ $rapport['company']->name }} — semaine du {{ $rapport['debut']->format('d/m/Y') }} au {{ $rapport['fin']->format('d/m/Y') }}
        </p>

        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="padding:6px 0;">Employés actifs</td>
                <td style="padding:6px 0; text-align:right;"><strong>{{ $s['effectif'] }}</strong></td>
            </tr>
            <tr>
                <td style="padding:6px 0;">Employés ayant pointé</td>
                <td style="padding:6px 0; text-align:right;"><strong>{{ $s['presents'] }}</strong></td>
            </tr>
            @if($s['taux_presence'] !== null)
                <tr>
                    <td style="padding:6px 0;">Taux de présence</td>
                    <td style="padding:6px 0; text-align:right;"><strong>{{ $s['taux_presence'] }} %</strong></td>
                </tr>
            @endif
            <tr>
                <td style="padding:6px 0;">Retards</td>
                <td style="padding:6px 0; text-align:right;"><strong>{{ $s['retards'] }}</strong> ({{ $s['minutes_retard'] }} min)</td>
            </tr>
            <tr>
                <td style="padding:6px 0;">Jours d'absence justifiée</td>
                <td style="padding:6px 0; text-align:right;"><strong>{{ $s['absences_justifiees'] }}</strong></td>
            </tr>
            @if($rapport['controle_absences'])
                <tr>
                    <td style="padding:6px 0;">Jours d'absence non justifiée</td>
                    <td style="padding:6px 0; text-align:right; color:#d4380d;"><strong>{{ $s['absences_non_justifiees'] }}</strong></td>
                </tr>
            @endif
        </table>

        <p style="margin:16px 0 0;">
            Le détail complet (absences, retards et pointages de chaque employé, jour par jour) est dans le PDF joint.
        </p>
        <p style="margin:16px 0 0; font-size:12px; color:#8592a3;">
            Rapport envoyé automatiquement chaque vendredi par RH Flow.
        </p>
    </div>
</body>
</html>
