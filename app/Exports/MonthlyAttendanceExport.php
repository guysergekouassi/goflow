<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonthlyAttendanceExport implements WithMultipleSheets
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            new MonthlyReportSummarySheet($this->reportData),
            new MonthlyReportDeptSheet($this->reportData),
            new MonthlyReportEmployeesSheet($this->reportData),
            new MonthlyReportAnomaliesSheet($this->reportData),
        ];
    }
}

/**
 * Feuille 1 : Résumé général et indicateurs clés
 */
class MonthlyReportSummarySheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Résumé';
    }

    public function headings(): array
    {
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
        $rows->push(['RAPPORT MENSUEL DES POINTAGES — ' . mb_strtoupper($company['name'])]);
        $rows->push(['Période couverte : ' . $period['start_formatted'] . ' au ' . $period['end_formatted']]);
        $rows->push(['Généré le : ' . $period['generated_at'] . ' | Mois : ' . $period['month_name_fr'] . ' ' . $period['year']]);
        $rows->push(['']);

        // 2. Indicateurs clés du mois
        $rows->push(['INDICATEURS CLÉS DU MOIS']);
        $rows->push(['Employés actifs', $ind['total_employees'], 'Présences cumulées', $ind['total_presences'] . ' j']);
        $rows->push(['Taux de présence global', $ind['presence_rate'] . ' %', 'Jours ouvrés attendus', $ind['total_expected_workdays'] . ' j']);
        $rows->push(['Employés en retard', $ind['late_employees_count'], 'Total retards constatés', $ind['total_retards_count'] . ' (' . $ind['total_retards_minutes'] . ' min)']);
        $rows->push(['Absences justifiées', $ind['total_justified_absences'] . ' j', 'Absences non justifiées', $ind['total_unjustified_absences'] . ' j']);
        $rows->push(['Jours de congés', $ind['total_leave_days'] . ' j', 'Pointages incomplets', $ind['total_incomplete_pointages']]);
        $rows->push(['Départs anticipés', $ind['total_early_departures'], 'Anomalies à vérifier', $ind['total_anomalies']]);
        $rows->push(['Déclarations GPS en attente', $ind['total_pending_declarations'], 'Déclarations GPS rejetées', $ind['total_rejected_declarations']]);
        $rows->push(['']);

        // 3. Répartition des méthodes de pointage
        $rows->push(['RÉPARTITION DES MÉTHODES DE POINTAGE']);
        $rows->push([
            'QR Code : ' . ($methods['qr_code']['count'] ?? 0) . ' (' . ($methods['qr_code']['percentage'] ?? 0) . '%)',
            'GPS : ' . ($methods['gps']['count'] ?? 0) . ' (' . ($methods['gps']['percentage'] ?? 0) . '%)',
            'Biométrie : ' . ($methods['biometrique']['count'] ?? 0) . ' (' . ($methods['biometrique']['percentage'] ?? 0) . '%)',
            'Autre / Portail : ' . ($methods['autre']['count'] ?? 0) . ' (' . ($methods['autre']['percentage'] ?? 0) . '%)',
        ]);
        $rows->push(['']);

        // 4. Synthèse par département
        $rows->push(['SYNTHÈSE PAR DÉPARTEMENT']);
        $rows->push([
            'Département',
            'Effectif',
            'Jours Présence',
            'Taux Présence',
            'Retards (Nb / Min)',
            'Abs. Justifiées',
            'Abs. Non Justif.',
            'Jours Congés',
            'Heures Totales',
        ]);

        foreach ($this->reportData['departments'] as $dept) {
            $rows->push([
                $dept['name'],
                $dept['employees_count'],
                $dept['present_days'] . ' j',
                $dept['presence_rate'] . ' %',
                $dept['late_days'] . ' (' . $dept['late_minutes'] . 'm)',
                $dept['justified_absences'] . ' j',
                $dept['unjustified_absences'] . ' j',
                $dept['leave_days'] . ' j',
                $dept['total_hours'],
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Titre
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new Color('1E3A8A'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Sous-titres
        $sheet->getStyle('A2:A3')->getFont()->setItalic(true)->setSize(10)->setColor(new Color('4B5563'));

        // Titre section Indicateurs
        $sheet->getStyle('A5')->getFont()->setBold(true)->setColor(new Color('1E40AF'));
        $sheet->getStyle('A6:D12')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A6:D12')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');

        // Titre section Méthodes
        $sheet->getStyle('A14')->getFont()->setBold(true)->setColor(new Color('1E40AF'));
        $sheet->getStyle('A15:D15')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFF6FF');

        // Titre section Synthèse départements
        $sheet->getStyle('A17')->getFont()->setBold(true)->setColor(new Color('1E40AF'));
        $sheet->getStyle('A18:I18')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $sheet->getStyle('A18:I18')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $sheet->getStyle('A18:I18')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $deptCount = count($this->reportData['departments']);
        if ($deptCount > 0) {
            $endRow = 18 + $deptCount;
            $sheet->getStyle("A19:I{$endRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A19:I{$endRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 26,
            'B' => 16,
            'C' => 22,
            'D' => 22,
            'E' => 20,
            'F' => 16,
            'G' => 16,
            'H' => 16,
            'I' => 16,
        ];
    }
}

/**
 * Feuille 2 : Statistiques détaillées par département
 */
class MonthlyReportDeptSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Par département';
    }

    public function headings(): array
    {
        return [
            'Département',
            'Effectif',
            'Jours Ouvrés Attendus',
            'Jours Présence',
            'Taux Présence (%)',
            'Retards (Nb)',
            'Retards (Min)',
            'Abs. Justifiées (j)',
            'Abs. Non Justif. (j)',
            'Congés (j)',
            'Pointages Incomplets',
            'Heures Travaillées',
        ];
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->reportData['departments'] as $dept) {
            $rows->push([
                $dept['name'],
                $dept['employees_count'],
                $dept['expected_workdays'],
                $dept['present_days'],
                $dept['presence_rate'] . ' %',
                $dept['late_days'],
                $dept['late_minutes'],
                $dept['justified_absences'],
                $dept['unjustified_absences'],
                $dept['leave_days'],
                $dept['incomplete_days'],
                $dept['total_hours'],
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowCount = count($this->reportData['departments']);
        if ($rowCount > 0) {
            $lastRow = 1 + $rowCount;
            $sheet->getStyle("A2:L{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A2:L{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 26, // Département
            'B' => 12, // Effectif
            'C' => 22, // Jours attendus
            'D' => 16, // Jours présence
            'E' => 18, // Taux présence
            'F' => 14, // Retards (nb)
            'G' => 14, // Retards (min)
            'H' => 18, // Abs justif
            'I' => 18, // Abs non justif
            'J' => 14, // Congés
            'K' => 20, // Incomplets
            'L' => 18, // Heures
        ];
    }
}

/**
 * Feuille 3 : Détails par employé
 */
class MonthlyReportEmployeesSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Détails employés';
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'Nom & Prénoms',
            'Département',
            'Site',
            'Jours Ouvrés',
            'Jours Présence',
            'Taux Présence (%)',
            'Retards (Nb)',
            'Retards (Min)',
            'Abs. Justifiées (j)',
            'Abs. Non Justif. (j)',
            'Congés (j)',
            'Pointages Incomplets',
            'Heures Totales',
        ];
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->reportData['employees'] as $emp) {
            $rows->push([
                $emp['matricule'],
                $emp['name'],
                $emp['department'],
                $emp['branch'],
                $emp['expected_workdays'],
                $emp['present_days'],
                $emp['presence_rate'] . ' %',
                $emp['late_days'],
                $emp['late_minutes'],
                $emp['justified_days'],
                $emp['unjustified_days'],
                $emp['leave_days'],
                $emp['incomplete_days'],
                $emp['total_hours'],
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:N1')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $sheet->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $sheet->getStyle('A1:N1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowCount = count($this->reportData['employees']);
        if ($rowCount > 0) {
            $lastRow = 1 + $rowCount;
            $sheet->getStyle("A2:N{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A2:N{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, // Matricule
            'B' => 26, // Nom
            'C' => 20, // Département
            'D' => 18, // Site
            'E' => 14, // Jours ouvrés
            'F' => 14, // Jours présence
            'G' => 18, // Taux présence
            'H' => 14, // Retards nb
            'I' => 14, // Retards min
            'J' => 18, // Abs justif
            'K' => 18, // Abs non justif
            'L' => 14, // Congés
            'M' => 20, // Incomplets
            'N' => 16, // Heures totales
        ];
    }
}

/**
 * Feuille 4 : Anomalies et signalements RH
 */
class MonthlyReportAnomaliesSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function title(): string
    {
        return 'Anomalies';
    }

    public function headings(): array
    {
        return [
            'Date',
            'Matricule',
            'Collaborateur',
            'Département',
            'Type d\'anomalie',
            'Description / Détail',
        ];
    }

    public function collection()
    {
        $rows = collect();
        $anomalies = $this->reportData['anomalies'] ?? [];

        if (empty($anomalies)) {
            $rows->push([
                '—',
                '—',
                'Aucune anomalie constatée',
                '—',
                'Conforme',
                'Tous les pointages du mois sont complets et conformes.',
            ]);
            return $rows;
        }

        foreach ($anomalies as $a) {
            $rows->push([
                $a['date'] ?? '—',
                $a['matricule'] ?? '—',
                $a['employee_name'] ?? '—',
                $a['department'] ?? '—',
                $a['type'] ?? 'Anomalie',
                $a['description'] ?? '—',
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setColor(new Color('FFFFFF'));
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B91C1C');
        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $anomalies = $this->reportData['anomalies'] ?? [];
        $rowCount = !empty($anomalies) ? count($anomalies) : 1;
        $lastRow = 1 + $rowCount;

        $sheet->getStyle("A2:F{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A2:F{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, // Date
            'B' => 14, // Matricule
            'C' => 26, // Collaborateur
            'D' => 22, // Département
            'E' => 24, // Type d'anomalie
            'F' => 50, // Description
        ];
    }
}
