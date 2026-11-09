<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WeeklyAttendanceExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Pointages Hebdo S' . ($this->reportData['period']['week_number'] ?? 'W');
    }

    public function headings(): array
    {
        // Les en-têtes sont inclus dans la collection ci-dessous pour permettre les lignes d'intro/statistiques
        return [];
    }

    public function collection()
    {
        $rows = collect();

        $company = $this->reportData['company'];
        $period = $this->reportData['period'];
        $ind = $this->reportData['indicators'];
        $methods = $this->reportData['methods_breakdown'];

        // 1. Titre & Informations générales
        $rows->push(['RAPPORT HEBDOMADAIRE DES POINTAGES — ' . mb_strtoupper($company['name'])]);
        $rows->push(['Période couverte : ' . $period['start_formatted'] . ' au ' . $period['end_formatted']]);
        $rows->push(['Généré le : ' . $period['generated_at'] . ' | Semaine N° ' . $period['week_number'] . ' (' . $period['year'] . ')']);
        $rows->push(['']);

        // 2. Indicateurs récapitulatifs
        $rows->push(['INDICATEURS CLÉS DE LA SEMAINE']);
        $rows->push(['Employés actifs', $ind['total_employees'], 'Présences cumulées', $ind['total_presences'] . ' j']);
        $rows->push(['Employés en retard', $ind['late_employees_count'], 'Total retards constatés', $ind['total_retards_count'] . ' (' . $ind['total_retards_minutes'] . ' min)']);
        $rows->push(['Absences justifiées', $ind['total_justified_absences'] . ' j', 'Absences non justifiées', $ind['total_unjustified_absences'] . ' j']);
        $rows->push(['Pointages incomplets', $ind['total_incomplete_pointages'], 'Anomalies à vérifier', $ind['total_anomalies']]);
        $rows->push(['Déclarations en attente', $ind['total_pending_declarations'], 'Déclarations rejetées', $ind['total_rejected_declarations']]);
        $rows->push(['']);

        // 3. Méthodes de pointage
        $rows->push(['RÉPARTITION DES MÉTHODES DE POINTAGE']);
        $rows->push([
            'QR Code : ' . ($methods['qr_code']['count'] ?? 0) . ' (' . ($methods['qr_code']['percentage'] ?? 0) . '%)',
            'GPS : ' . ($methods['gps']['count'] ?? 0) . ' (' . ($methods['gps']['percentage'] ?? 0) . '%)',
            'Biométrie : ' . ($methods['biometrique']['count'] ?? 0) . ' (' . ($methods['biometrique']['percentage'] ?? 0) . '%)',
            'Autre / Portail : ' . ($methods['autre']['count'] ?? 0) . ' (' . ($methods['autre']['percentage'] ?? 0) . '%)',
        ]);
        $rows->push(['']);

        // 4. En-têtes du tableau détaillé
        $rows->push([
            'Matricule',
            'Nom & Prénoms',
            'Département',
            'Site',
            'Lundi',
            'Mardi',
            'Mercredi',
            'Jeudi',
            'Vendredi (max 18h)',
            'Présences',
            'Retards (Nb / Min)',
            'Abs. Justifiées',
            'Abs. Non Justif.',
            'Heures Totales',
        ]);

        // 5. Lignes par employé
        foreach ($this->reportData['employees'] as $emp) {
            $daysText = [];
            foreach ($this->reportData['workdays'] as $workday) {
                $dayKey = $workday->toDateString();
                $dayData = $emp['days'][$dayKey] ?? null;

                if (!$dayData) {
                    $daysText[] = '--';
                    continue;
                }

                if ($dayData['status_type'] === 'present') {
                    $info = "{$dayData['arrival_time']} - {$dayData['departure_time']}";
                    if ($dayData['is_late']) {
                        $info .= " [Retard {$dayData['retard_minutes']}m]";
                    }
                    if ($dayData['is_incomplete']) {
                        $info .= " [Incomplet]";
                    }
                    $daysText[] = $info;
                } else {
                    $daysText[] = $dayData['status'] . ($dayData['motif'] ? ' (' . $dayData['motif'] . ')' : '');
                }
            }

            $rows->push([
                $emp['matricule'],
                $emp['name'],
                $emp['department'],
                $emp['branch'],
                $daysText[0] ?? '--',
                $daysText[1] ?? '--',
                $daysText[2] ?? '--',
                $daysText[3] ?? '--',
                $daysText[4] ?? '--',
                $emp['present_days'] . ' j',
                $emp['late_days'] . ' (' . $emp['late_minutes'] . ' min)',
                $emp['justified_days'] . ' j',
                $emp['unjustified_days'] . ' j',
                $emp['total_hours'],
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Titre
        $sheet->mergeCells('A1:N1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Sous-titres
        $sheet->getStyle('A2:A3')->getFont()->setItalic(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('4B5563'));

        // Titre section Indicateurs
        $sheet->getStyle('A5')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E40AF'));
        $sheet->getStyle('A6:D11')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A6:D11')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');

        // Titre section Méthodes
        $sheet->getStyle('A13')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E40AF'));
        $sheet->getStyle('A14:D14')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFF6FF');

        // En-têtes du tableau des employés (ligne 16)
        $sheet->getStyle('A16:N16')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
        $sheet->getStyle('A16:N16')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $sheet->getStyle('A16:N16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Lignes de données
        $totalRows = 16 + count($this->reportData['employees']);
        if ($totalRows >= 17) {
            $sheet->getStyle("A17:N{$totalRows}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A17:N{$totalRows}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, // Matricule
            'B' => 24, // Nom
            'C' => 18, // Département
            'D' => 18, // Site
            'E' => 22, // Lundi
            'F' => 22, // Mardi
            'G' => 22, // Mercredi
            'H' => 22, // Jeudi
            'I' => 24, // Vendredi
            'J' => 12, // Présences
            'K' => 18, // Retards
            'L' => 14, // Abs Justif
            'M' => 14, // Abs Non Justif
            'N' => 14, // Heures Totales
        ];
    }
}
