<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport hebdomadaire de présence</title>
    <style>
        @page { margin: 92px 24px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #2f3349; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: middle; }

        /* En-tête et pied répétés sur chaque page */
        .entete { position: fixed; top: -80px; left: 0; right: 0; }
        .entete td { padding: 0; vertical-align: middle; }
        .entete .logo { width: 76px; padding-right: 6px; }
        .entete .logo img { max-width: 70px; max-height: 42px; }
        .entete .societe { font-size: 10.5px; font-weight: bold; color: #253e87; line-height: 1.2; padding-right: 6px; }
        .entete .societe small { display: block; font-size: 7px; font-weight: normal; color: #8592a3; }
        .entete .titre { border-left: 1px solid #d9dee8; padding-left: 10px; }
        .entete .titre .grand { font-size: 13px; font-weight: bold; color: #253e87; line-height: 1.15; }
        .entete .titre .semaine { font-size: 8.5px; font-weight: bold; margin-top: 3px; }
        .entete .titre .periode { font-size: 7.5px; color: #4b5266; }
        .entete .effectif { border-left: 1px solid #d9dee8; padding-left: 10px; text-align: left; }
        .entete .effectif .valeur { font-size: 9px; font-weight: bold; color: #253e87; }
        .entete .effectif .duree { font-size: 7px; color: #4b5266; margin-top: 3px; line-height: 1.35; }
        .entete .effectif .duree strong { color: #2f3349; font-size: 7.5px; }
        .entete .filet { height: 3px; background: #253e87; margin-top: 7px; }

        .pied { position: fixed; bottom: -22px; left: 0; right: 0; background: #253e87; color: #ffffff; }
        .pied td { padding: 3px 8px; font-size: 6.5px; }
        .page-courante:after { content: counter(page); }

        /* Bandeau de section */
        .section { background: #253e87; color: #ffffff; font-size: 9px; font-weight: bold; letter-spacing: 0.5px;
                   padding: 5px 10px; border-radius: 5px; margin: 10px 0 6px; }
        .section .aide { float: right; font-size: 6.5px; font-weight: normal; letter-spacing: 0; margin-top: 1px; }

        /* Cartes d'indicateurs : 4 par ligne, sur toute la largeur */
        .cartes { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 5px; table-layout: fixed; }
        .carte { width: 25%; border: 1px solid #d9dee8; border-radius: 6px; padding: 6px 6px; background: #ffffff; vertical-align: top; }
        .carte table td { padding: 0; vertical-align: top; }
        .icone { width: 22px; height: 22px; }
        .carte .libelle { font-size: 7px; font-weight: bold; color: #2f3349; line-height: 1.25; padding-left: 6px; }
        .carte .precision { font-size: 6px; color: #8592a3; font-weight: normal; }
        .carte .valeur { font-size: 15px; font-weight: bold; color: #253e87; padding-left: 6px; margin-top: 3px; }
        .carte .pourcent { font-size: 7px; color: #4b5266; padding-left: 6px; }

        /* Graphiques */
        .graphiques { width: 100%; border-collapse: separate; border-spacing: 5px 0; table-layout: fixed; }
        .panneau { border: 1px solid #d9dee8; border-radius: 6px; padding: 6px; vertical-align: top; }
        .panneau .titre-panneau { font-size: 7.5px; font-weight: bold; color: #253e87; margin-bottom: 4px; }
        .legende td { padding: 1.5px 0; font-size: 7px; }
        .legende td.nombre { padding-left: 5px; text-align: right; white-space: nowrap; }
        .pastille { display: inline-block; width: 7px; height: 7px; border-radius: 4px; margin-right: 3px; }

        /* Détail des pointages */
        .grille th { background: #253e87; color: #ffffff; font-size: 7px; text-align: center; padding: 4px 3px; border: 1px solid #253e87; }
        .grille th.employe { text-align: left; }
        .grille th .ferie { color: #ffd666; font-size: 6px; display: block; }
        .grille td { border: 1px solid #e4e7ee; padding: 3px; text-align: center; }
        .grille td.employe { text-align: left; }
        .grille tr { page-break-inside: avoid; }
        .nom { font-weight: bold; font-size: 7.5px; }
        .petit { font-size: 6.3px; color: #8592a3; line-height: 1.3; }
        .case { border-radius: 4px; padding: 3px 2px; font-size: 6.5px; line-height: 1.3; }
        .case-pointage { background: #e9f7ef; color: #1f6f43; }
        .case-ferie { background: #fff4d6; color: #b7791f; font-weight: bold; }
        .case-conge { background: #efe9fb; color: #6b3fc1; font-weight: bold; }
        .case-absent { background: #fde8e8; color: #c53030; font-weight: bold; }
        .case-ok { background: #e9f7ef; color: #1f6f43; font-weight: bold; font-size: 7.5px; }
        .case-partiel { background: #fff1e0; color: #b45309; font-weight: bold; font-size: 7.5px; }
        .case-zero { background: #fde8e8; color: #c53030; font-weight: bold; font-size: 7.5px; }
        .case-neutre { background: #f1f3f7; color: #4b5266; font-weight: bold; font-size: 7.5px; }
        .alerte { color: #c53030; font-weight: bold; }
        .vide { color: #b0b7c3; }

        /* Annexe */
        h3 { font-size: 8.5px; color: #253e87; margin: 10px 0 4px; border-bottom: 1px solid #d9dee8; padding-bottom: 2px; }
        .liste th { background: #eef1f7; color: #253e87; text-align: left; padding: 3px 5px; font-size: 7px; }
        .liste td { border-bottom: 1px solid #e4e7ee; padding: 3px 5px; }
        .note { background: #fff6e5; border: 1px solid #ffd591; border-radius: 4px; padding: 4px 8px; margin: 2px 0 6px; font-size: 7px; }
        .aucun { color: #8592a3; font-style: italic; }
    </style>
</head>
<body>
    @php
        $s = $stats;
        $effectif = max(0, $s['effectif']);
        $date = fn ($d) => \Carbon\Carbon::parse($d)->locale('fr');
        $fmt = fn ($d) => $date($d)->isoFormat('DD/MM/YYYY');
        $jourCourt = fn ($d) => ucfirst($date($d)->isoFormat('ddd DD/MM'));
        $pct = fn ($n) => $effectif > 0 ? number_format($n / $effectif * 100, 1, ',', ' ') . ' %' : '—';
        // 32h45, 40h : minutes affichées seulement quand il y en a
        $heures = fn (int $m) => intdiv($m, 60) . 'h' . ($m % 60 ? str_pad($m % 60, 2, '0', STR_PAD_LEFT) : '');
        // 08h, 07h30 : durée de référence
        $duree = fn (int $m) => str_pad(intdiv($m, 60), 2, '0', STR_PAD_LEFT) . 'h' . ($m % 60 ? str_pad($m % 60, 2, '0', STR_PAD_LEFT) : '');
        $cal = $calendrier;
        $estFerie = fn ($j) => in_array($j, $feries, true);

        $repartition = [
            ['libelle' => 'Présents (tous les jours)', 'valeur' => $s['repartition']['complet'], 'couleur' => '#2e9e5b'],
            ['libelle' => 'Présents (partiellement)', 'valeur' => $s['repartition']['partiel'], 'couleur' => '#2f6fd6'],
            ['libelle' => 'Absents', 'valeur' => $s['repartition']['absent'], 'couleur' => '#f08a24'],
            ['libelle' => 'En congé', 'valeur' => $s['repartition']['conge'], 'couleur' => '#7b4bd1'],
        ];
        $ponctualite = [
            ['libelle' => 'À l\'heure', 'valeur' => $s['ponctualite']['a_l_heure'], 'couleur' => '#2e9e5b'],
            ['libelle' => 'En retard', 'valeur' => $s['ponctualite']['en_retard'], 'couleur' => '#f08a24'],
            ['libelle' => 'Sans pointage', 'valeur' => $s['ponctualite']['sans_pointage'], 'couleur' => '#d64545'],
        ];
        $barres = array_map(fn ($j) => [
            'libelle' => ucfirst($date($j)->isoFormat('ddd')) . "\n" . $date($j)->format('d/m'),
            'valeur' => $presents_par_jour[$j],
            'note' => $estFerie($j) && $presents_par_jour[$j] === 0 ? 'Férié' : null,
        ], $jours);

        $carte = function (string $icone, string $couleur, string $libelle, string $precision, string $valeur, string $detail = '') {
            return '<td class="carte"><table><tr>'
                . '<td style="width:22px"><img class="icone" src="' . \App\Support\SvgCharts::icone($icone, $couleur) . '" alt=""></td>'
                . '<td><div class="libelle">' . e($libelle) . ($precision !== '' ? '<br><span class="precision">' . e($precision) . '</span>' : '') . '</div>'
                . '<div class="valeur">' . e($valeur) . '</div>'
                . ($detail !== '' ? '<div class="pourcent">' . $detail . '</div>' : '')
                . '</td></tr></table></td>';
        };
        $legende = function (array $parts) use ($pct) {
            $html = '<table class="legende">';
            foreach ($parts as $p) {
                $html .= '<tr><td><span class="pastille" style="background:' . $p['couleur'] . '"></span>' . e($p['libelle']) . '</td>'
                    . '<td class="nombre"><strong>' . $p['valeur'] . '</strong> <span class="petit">(' . $pct($p['valeur']) . ')</span></td></tr>';
            }
            return $html . '</table>';
        };
    @endphp

    <div class="entete">
        <table>
            <tr>
                @if($logo)
                    <td class="logo"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td class="societe" style="width:{{ $logo ? '20%' : '29%' }}">
                    {{ mb_strtoupper($company->name) }}
                    <small>Rapport de présence</small>
                </td>
                <td class="titre" style="width:{{ $logo ? '39%' : '45%' }}">
                    <div class="grand">RAPPORT HEBDOMADAIRE DE PRÉSENCE</div>
                    <div class="semaine">Semaine n° {{ $debut->isoWeek }} — {{ $debut->isoWeekYear }}</div>
                    <div class="periode">
                        Du {{ $debut->locale('fr')->isoFormat('dddd D MMMM YYYY') }} au {{ $fin->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </div>
                </td>
                <td class="effectif" style="width:{{ $logo ? '29%' : '26%' }}">
                    <div class="valeur">Effectif actif : {{ $effectif }} employé{{ $effectif > 1 ? 's' : '' }}</div>
                    <div class="duree">
                        Durée de travail :<br>
                        <strong>{{ $duree($duree_travail['jour']) }}/jour</strong> · <strong>{{ $duree($duree_travail['semaine']) }}/semaine</strong>
                    </div>
                </td>
            </tr>
        </table>
        <div class="filet"></div>
    </div>

    <div class="pied">
        <table>
            <tr>
                <td style="width:36%">© GPFLOW — Système de Gestion RH</td>
                <td style="width:40%; text-align:center;">Date de tirage : {{ $genere_le->format('d/m/Y') }} à {{ $genere_le->format('H:i') }}</td>
                <td style="width:24%; text-align:right;">Page <span class="page-courante"></span></td>
            </tr>
        </table>
    </div>

    {{-- Synthèse générale --}}
    <div class="section" style="margin-top:0;">SYNTHÈSE GÉNÉRALE</div>
    <table class="cartes">
        <tr>
            {!! $carte('✔', '#2e9e5b', 'Employés ayant pointé', '(au moins une fois)', (string) $s['presents'], $pct($s['presents'])) !!}
            {!! $carte('✈', '#f08a24', 'Employés en congé sur la période', '(congés et absences accordées)', (string) $s['employes_conge'], $pct($s['employes_conge'])) !!}
            {!! $carte('%', '#1f9aa8', 'Taux de présence global', '(hors congés et absences accordées)',
                $s['taux_presence'] !== null ? number_format($s['taux_presence'], 1, ',', ' ') . ' %' : '—',
                $s['taux_presence'] !== null ? '' : 'jours travaillés non paramétrés') !!}
            {!! $carte('▦', '#2f6fd6', 'Jours de travail (semaine)', '(' . $cal['rythme'] . ')', (string) $cal['jours_ouvres'],
                $cal['jours_feries'] > 0 ? '<strong>' . $cal['jours_feries'] . ' jour' . ($cal['jours_feries'] > 1 ? 's' : '') . ' férié' . ($cal['jours_feries'] > 1 ? 's' : '') . '</strong>' : 'aucun jour férié') !!}
        </tr>
    </table>
    <table class="cartes">
        <tr>
            {!! $carte('◷', '#157a6e', 'Employés respectant les ' . $heures($duree_travail['semaine']) . ' de travail', '(hors congés et jours fériés)',
                $controle_absences ? (string) $s['respect_duree'] : '—', $controle_absences ? $pct($s['respect_duree']) : '') !!}
            {!! $carte('★', '#2f6fd6', 'Employés à l\'heure', '(aucun retard dans la semaine)', (string) $s['ponctualite']['a_l_heure'], $pct($s['ponctualite']['a_l_heure'])) !!}
            {!! $carte('!', '#d64545', 'Employés en retard', '(au moins une fois)', (string) $s['ponctualite']['en_retard'],
                $pct($s['ponctualite']['en_retard']) . ($s['minutes_retard'] > 0 ? ' · ' . $s['minutes_retard'] . ' min' : '')) !!}
            {!! $carte('➜', '#7b4bd1', 'Employés partis avant l\'heure', '(avant l\'heure de sortie prévue)', (string) $s['employes_departs_anticipes'], $pct($s['employes_departs_anticipes'])) !!}
        </tr>
    </table>

    @unless($controle_absences)
        <div class="note">
            Absences non justifiées et heures dues non calculées : les jours travaillés ne sont pas paramétrés.
            Renseignez le nombre de jours travaillés par semaine dans les paramètres de l'entreprise
            (ou les jours travaillés de chaque succursale).
        </div>
    @endunless

    {{-- Graphiques --}}
    <table class="graphiques">
        <tr>
            <td class="panneau" style="width:35%">
                <div class="titre-panneau">RÉPARTITION DES EMPLOYÉS</div>
                <table>
                    <tr>
                        <td style="width:84px"><img src="{{ \App\Support\SvgCharts::anneau($repartition, (string) $effectif, $effectif > 1 ? 'employés' : 'employé', 120) }}" style="width:80px; height:80px;"></td>
                        <td>{!! $legende($repartition) !!}</td>
                    </tr>
                </table>
            </td>
            <td class="panneau" style="width:31%">
                <div class="titre-panneau">PONCTUALITÉ DES EMPLOYÉS</div>
                <table>
                    <tr>
                        <td style="width:78px"><img src="{{ \App\Support\SvgCharts::camembert($ponctualite, 120) }}" style="width:74px; height:74px;"></td>
                        <td>{!! $legende($ponctualite) !!}</td>
                    </tr>
                </table>
            </td>
            <td class="panneau" style="width:34%">
                <div class="titre-panneau">EMPLOYÉS PRÉSENTS PAR JOUR</div>
                <img src="{{ \App\Support\SvgCharts::barres($barres, max($effectif, 1), 230, 110) }}" style="width:100%;">
            </td>
        </tr>
    </table>

    {{-- Détail des pointages --}}
    <div class="section">
        DÉTAIL DES POINTAGES PAR EMPLOYÉ
        <span class="aide">Chaque case : arrivée – départ (durée), lieu et mode de pointage</span>
    </div>
    <table class="grille">
        <thead>
            <tr>
                <th class="employe" style="width:{{ count($jours) > 5 ? 17 : 19 }}%">Employé<br><span style="font-weight:normal; font-size:6px;">Matricule · Service</span></th>
                @foreach($jours as $jour)
                    <th>
                        {{ $jourCourt($jour) }}
                        @if($estFerie($jour))<span class="ferie">JOUR FÉRIÉ</span>@endif
                    </th>
                @endforeach
                <th style="width:12%">Heures travaillées<br><span style="font-weight:normal; font-size:6px;">(semaine / dues)</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach($lignes as $l)
                <tr>
                    <td class="employe">
                        <div class="nom">{{ $l['employe']->name }}</div>
                        <div class="petit">{{ $l['matricule'] ?: 'Sans matricule' }}</div>
                        <div class="petit">{{ $l['departement'] }}</div>
                    </td>
                    @foreach($jours as $jour)
                        @php $j = $l['jours'][$jour]; @endphp
                        <td>
                            @if(!empty($j['arrivee']) || !empty($j['depart']))
                                <div class="case case-pointage">
                                    {{ $j['arrivee'] ?? '--:--' }} – {{ $j['depart'] ?? '--:--' }}<br>
                                    ({{ $j['duree'] }})<br>
                                    <span class="petit">{{ $j['lieu'] }} · {{ $j['mode'] }}</span>
                                    @if($j['statut'] !== 'À l\'heure')
                                        <br><span class="alerte">{{ $j['statut'] }}</span>
                                    @endif
                                </div>
                            @elseif($j['ferie'])
                                <div class="case case-ferie">JOUR FÉRIÉ</div>
                            @elseif($j['absence'])
                                <div class="case case-conge">{{ $j['absence'] }}</div>
                            @elseif($j['non_justifiee'])
                                <div class="case case-absent">Absent</div>
                            @else
                                <span class="vide">—</span>
                            @endif
                        </td>
                    @endforeach
                    @php
                        $travail = $l['totaux']['minutes_travail'];
                        $dues = $l['minutes_dues'];
                        $classe = $dues === null ? 'case-neutre'
                            : ($dues > 0 && $travail >= $dues ? 'case-ok' : ($travail > 0 ? 'case-partiel' : ($dues > 0 ? 'case-zero' : 'case-neutre')));
                    @endphp
                    <td>
                        <div class="case {{ $classe }}">
                            {{ $heures($travail) }}@if($dues !== null) / {{ $heures($dues) }}@endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Annexe : listes détaillées --}}
    <div class="section" style="page-break-before: always; margin-top:0;">ABSENCES ET RETARDS DE LA SEMAINE</div>

    <h3>Absences justifiées</h3>
    @if(count($liste_justifiees))
        <table class="liste">
            <tr><th>Employé</th><th>Motif</th><th>Du</th><th>Au</th></tr>
            @foreach($liste_justifiees as $a)
                <tr><td>{{ $a['employe'] }}</td><td>{{ $a['motif'] }}</td><td>{{ $fmt($a['du']) }}</td><td>{{ $fmt($a['au']) }}</td></tr>
            @endforeach
        </table>
    @else
        <div class="aucun">Aucune absence justifiée cette semaine.</div>
    @endif

    @if($controle_absences)
        <h3>Absences non justifiées</h3>
        @if(count($liste_non_justifiees))
            <table class="liste">
                <tr><th style="width:30%">Employé</th><th>Jours sans pointage ni absence accordée</th></tr>
                @foreach($liste_non_justifiees as $a)
                    <tr><td>{{ $a['employe'] }}</td><td class="alerte">{{ collect($a['jours'])->map($jourCourt)->implode(', ') }}</td></tr>
                @endforeach
            </table>
        @else
            <div class="aucun">Aucune absence non justifiée cette semaine.</div>
        @endif
    @endif

    <h3>Retards</h3>
    @if(count($retards))
        <table class="liste">
            <tr><th>Employé</th><th>Service</th><th>Nombre de retards</th><th>Total</th></tr>
            @foreach($retards as $l)
                <tr><td>{{ $l['employe']->name }}</td><td>{{ $l['departement'] }}</td><td>{{ $l['totaux']['retards'] }}</td><td>{{ $l['totaux']['minutes_retard'] }} min</td></tr>
            @endforeach
        </table>
    @else
        <div class="aucun">Aucun retard cette semaine.</div>
    @endif

    @if(count($derogations))
        <h3>Absences accordées malgré un effectif insuffisant</h3>
        <table class="liste">
            <tr><th>Employé</th><th>Du</th><th>Au</th><th>Motif de la dérogation</th></tr>
            @foreach($derogations as $d)
                <tr><td>{{ $d['employe'] }}</td><td>{{ $fmt($d['du']) }}</td><td>{{ $fmt($d['au']) }}</td><td>{{ $d['motif'] }}</td></tr>
            @endforeach
        </table>
    @endif

    @if(count($a_venir))
        <h3>Absences prévues la semaine prochaine</h3>
        <table class="liste">
            <tr><th>Employé</th><th>Motif</th><th>Du</th><th>Au</th></tr>
            @foreach($a_venir as $a)
                <tr><td>{{ $a['employe'] }}</td><td>{{ $a['motif'] }}</td><td>{{ $fmt($a['du']) }}</td><td>{{ $fmt($a['au']) }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
