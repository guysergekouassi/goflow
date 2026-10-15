<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport hebdomadaire de présence</title>
    <style>
        @page { margin: 118px 26px 22px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #2f3349; }

        /* En-tête répété sur chaque page : entreprise | titre | période, puis bandeau */
        .entete { position: fixed; top: -100px; left: 0; right: 0; }
        .entete td { border: none; padding: 0; vertical-align: top; }
        .entete .societe { width: 26%; font-size: 12px; font-weight: bold; }
        .entete .societe small { display: block; font-size: 8px; font-weight: normal; }
        .entete .titre { width: 48%; text-align: center; font-size: 15px; font-weight: bold; line-height: 1.2; }
        .entete .titre small { display: block; font-size: 8px; font-weight: normal; margin-top: 2px; }
        .entete .infos { width: 26%; text-align: right; font-family: DejaVu Sans Mono, monospace; font-size: 8px; line-height: 1.5; }
        .bandeau { width: 100%; margin-top: 8px; border: 1px solid #9aa3b5; }
        .bandeau td { border: none; padding: 4px 8px; font-size: 7.5px; color: #4b5266; }
        .page-courante:after { content: counter(page); }

        /* Filigrane sur chaque page */
        .filigrane {
            position: fixed; top: 210px; left: 0; right: 0;
            text-align: center; font-size: 130px; font-weight: bold; letter-spacing: 12px;
            color: #253e87; opacity: 0.06; transform: rotate(-28deg);
        }
        h2 { font-size: 11px; color: #253e87; margin: 14px 0 5px; border-bottom: 1px solid #d9dee8; padding-bottom: 3px; }
        .muted { color: #8592a3; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #253e87; color: #fff; font-weight: bold; text-align: left; padding: 4px 5px; }
        td { border-bottom: 1px solid #e4e7ee; padding: 3px 5px; vertical-align: top; }
        tr.pair td { background: #f7f8fb; }
        .stats td { border: 1px solid #d9dee8; padding: 6px 8px; width: 25%; }
        .stats .valeur { font-size: 15px; font-weight: bold; color: #253e87; }
        .stats .libelle { color: #8592a3; }
        .note { background: #fff6e5; border: 1px solid #ffd591; padding: 5px 8px; margin-top: 6px; }
        .absent { color: #d4380d; font-weight: bold; }
        .justifie { color: #8592a3; font-style: italic; }
        .retard { color: #d4380d; }
        .ok { color: #389e0d; }
        .petit { font-size: 7.5px; color: #8592a3; }
        .grille td { border: 1px solid #e4e7ee; text-align: center; }
        .grille td.nom { text-align: left; }
        .vide { color: #8592a3; }
    </style>
</head>
<body>
    @php
        $fmt = fn ($d) => \Carbon\Carbon::parse($d)->locale('fr')->isoFormat('DD/MM/YYYY');
        $jourCourt = fn ($d) => \Carbon\Carbon::parse($d)->locale('fr')->isoFormat('ddd DD/MM');
        $s = $stats;
    @endphp

    <div class="filigrane">GPFLOW</div>

    <div class="entete">
        <table>
            <tr>
                <td class="societe">
                    {{ mb_strtoupper($company->name) }}
                    <small>Rapport de présence</small>
                </td>
                <td class="titre">
                    RAPPORT HEBDOMADAIRE DE PRÉSENCE<br>
                    — SEMAINE DU {{ mb_strtoupper($debut->locale('fr')->isoFormat('D')) }}
                    AU {{ mb_strtoupper($fin->locale('fr')->isoFormat('D MMMM YYYY')) }}
                    <small>Pointages · Absences · Retards</small>
                </td>
                <td class="infos">
                    Semaine n° {{ $debut->isoWeek }} — {{ $debut->isoWeekYear }}<br>
                    Effectif actif : {{ $stats['effectif'] }}
                </td>
            </tr>
        </table>
        <table class="bandeau">
            <tr>
                <td style="width:34%">© GPFLOW — Système de Gestion RH</td>
                <td style="width:38%; text-align:center;">Date de tirage : {{ $genere_le->format('d/m/y') }} à {{ $genere_le->format('H:i:s') }}</td>
                <td style="width:28%; text-align:right;">Page : <span class="page-courante"></span></td>
            </tr>
        </table>
    </div>

    <h2 style="margin-top:0;">Statistiques de la semaine</h2>
    <table class="stats">
        <tr>
            <td><div class="valeur">{{ $s['effectif'] }}</div><div class="libelle">Employés actifs</div></td>
            <td><div class="valeur">{{ $s['presents'] }}</div><div class="libelle">Employés ayant pointé</div></td>
            <td><div class="valeur">{{ $s['jours_presence'] }}</div><div class="libelle">Jours de présence pointés</div></td>
            <td><div class="valeur">{{ $s['taux_presence'] !== null ? $s['taux_presence'] . ' %' : '—' }}</div><div class="libelle">Taux de présence</div></td>
        </tr>
        <tr>
            <td><div class="valeur">{{ $s['retards'] }}</div><div class="libelle">Retards ({{ $s['minutes_retard'] }} min au total)</div></td>
            <td><div class="valeur">{{ $s['departs_anticipes'] }}</div><div class="libelle">Départs anticipés</div></td>
            <td><div class="valeur">{{ $s['absences_justifiees'] }}</div><div class="libelle">Jours d'absence justifiée</div></td>
            <td><div class="valeur">{{ $controle_absences ? $s['absences_non_justifiees'] : '—' }}</div><div class="libelle">Jours d'absence non justifiée</div></td>
        </tr>
    </table>
    @unless($controle_absences)
        <div class="note">
            Absences non justifiées non calculées : les jours travaillés ne sont pas paramétrés.
            Renseignez le nombre de jours travaillés par semaine dans les paramètres de l'entreprise
            (ou les jours travaillés de chaque succursale) pour les inclure dans ce rapport.
        </div>
    @endunless

    <h2>Absences justifiées</h2>
    @if(count($liste_justifiees))
        <table>
            <tr><th>Employé</th><th>Motif</th><th>Du</th><th>Au</th></tr>
            @foreach($liste_justifiees as $i => $a)
                <tr class="{{ $i % 2 ? 'pair' : '' }}">
                    <td>{{ $a['employe'] }}</td><td>{{ $a['motif'] }}</td><td>{{ $fmt($a['du']) }}</td><td>{{ $fmt($a['au']) }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <div class="vide">Aucune absence justifiée cette semaine.</div>
    @endif

    @if($controle_absences)
        <h2>Absences non justifiées</h2>
        @if(count($liste_non_justifiees))
            <table>
                <tr><th style="width:30%">Employé</th><th>Jours sans pointage ni absence accordée</th></tr>
                @foreach($liste_non_justifiees as $i => $a)
                    <tr class="{{ $i % 2 ? 'pair' : '' }}">
                        <td>{{ $a['employe'] }}</td>
                        <td class="absent">{{ collect($a['jours'])->map($jourCourt)->implode(', ') }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <div class="vide">Aucune absence non justifiée cette semaine.</div>
        @endif
    @endif

    <h2>Retards</h2>
    @if(count($retards))
        <table>
            <tr><th>Employé</th><th>Département</th><th>Nombre de retards</th><th>Total</th></tr>
            @foreach($retards as $i => $l)
                <tr class="{{ $i % 2 ? 'pair' : '' }}">
                    <td>{{ $l['employe']->name }}</td><td>{{ $l['departement'] }}</td>
                    <td>{{ $l['totaux']['retards'] }}</td><td>{{ $l['totaux']['minutes_retard'] }} min</td>
                </tr>
            @endforeach
        </table>
    @else
        <div class="vide">Aucun retard cette semaine.</div>
    @endif

    @if(count($derogations))
        <h2>Absences accordées malgré un effectif insuffisant</h2>
        <table>
            <tr><th>Employé</th><th>Du</th><th>Au</th><th>Motif de la dérogation</th></tr>
            @foreach($derogations as $i => $d)
                <tr class="{{ $i % 2 ? 'pair' : '' }}">
                    <td>{{ $d['employe'] }}</td><td>{{ $fmt($d['du']) }}</td><td>{{ $fmt($d['au']) }}</td><td>{{ $d['motif'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if(count($a_venir))
        <h2>Absences prévues la semaine prochaine</h2>
        <table>
            <tr><th>Employé</th><th>Motif</th><th>Du</th><th>Au</th></tr>
            @foreach($a_venir as $i => $a)
                <tr class="{{ $i % 2 ? 'pair' : '' }}">
                    <td>{{ $a['employe'] }}</td><td>{{ $a['motif'] }}</td><td>{{ $fmt($a['du']) }}</td><td>{{ $fmt($a['au']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2 style="page-break-before: always;">Détail des pointages par employé</h2>
    <div class="petit" style="margin-bottom:4px;">
        Chaque case : arrivée – départ (durée), statut, lieu et mode de pointage (QR ou GPS site client).
    </div>
    <table class="grille">
        <thead>
            <tr>
                <th style="width:16%">Employé</th>
                @foreach($jours as $jour)
                    <th style="text-align:center">{{ ucfirst($jourCourt($jour)) }}</th>
                @endforeach
                <th style="text-align:center; width:6%">Présence</th>
                <th style="text-align:center; width:6%">Heures</th>
                <th style="text-align:center; width:6%">Retards</th>
            </tr>
        </thead>
        @foreach($lignes as $i => $l)
            <tr class="{{ $i % 2 ? 'pair' : '' }}">
                <td class="nom">
                    <strong>{{ $l['employe']->name }}</strong>
                    <div class="petit">{{ $l['matricule'] ?: '' }}{{ $l['matricule'] ? ' · ' : '' }}{{ $l['departement'] }}</div>
                </td>
                @foreach($jours as $jour)
                    @php $j = $l['jours'][$jour]; @endphp
                    <td>
                        @if(!empty($j['arrivee']) || !empty($j['depart']))
                            {{ $j['arrivee'] ?? '--:--' }} – {{ $j['depart'] ?? '--:--' }}
                            <div class="petit">{{ $j['duree'] }}</div>
                            <div class="{{ $j['statut'] === 'À l\'heure' ? 'ok' : 'retard' }}" style="font-size:7.5px;">{{ $j['statut'] }}</div>
                            <div class="petit">{{ $j['lieu'] }} · {{ $j['mode'] }}</div>
                        @elseif($j['absence'])
                            <span class="justifie">{{ $j['absence'] }}</span>
                        @elseif($j['non_justifiee'])
                            <span class="absent">Absent</span>
                        @else
                            <span class="vide">—</span>
                        @endif
                    </td>
                @endforeach
                <td>{{ $l['totaux']['presence'] }} j</td>
                <td>{{ intdiv($l['totaux']['minutes_travail'], 60) }}h{{ str_pad($l['totaux']['minutes_travail'] % 60, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $l['totaux']['retards'] }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
