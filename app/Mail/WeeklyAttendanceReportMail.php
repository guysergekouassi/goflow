<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Rapport hebdomadaire de présence :
 * Compatible à la fois avec la commande rapports:absences-hebdo (PDF unique)
 * et le service multi-entreprises WeeklyAttendanceReportService (PDF + Excel).
 */
class WeeklyAttendanceReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $reportData;
    public array $rapport;
    protected ?string $pdfContent;
    protected ?string $excelContent;

    /**
     * @param array $reportData Données du rapport
     * @param string|null $pdfContent Contenu binaire du PDF
     * @param string|null $excelContent Contenu binaire du fichier Excel (optionnel)
     */
    public function __construct(array $reportData, ?string $pdfContent = null, ?string $excelContent = null)
    {
        $this->reportData = $reportData;
        $this->rapport = $reportData;
        $this->pdfContent = $pdfContent;
        $this->excelContent = $excelContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        if (isset($this->rapport['debut'], $this->rapport['fin'], $this->rapport['company'])) {
            return new Envelope(
                subject: 'Rapport de présence – semaine du ' . $this->rapport['debut']->format('d/m/Y')
                    . ' au ' . $this->rapport['fin']->format('d/m/Y') . ' – ' . $this->rapport['company']->name,
            );
        }

        $companyName = $this->reportData['company']['name'] ?? 'Entreprise';
        $weekNumber = $this->reportData['period']['week_number'] ?? date('W');
        $year = $this->reportData['period']['year'] ?? date('Y');

        return new Envelope(
            subject: "[GP FLOW] Rapport hebdomadaire des pointages - Semaine {$weekNumber}/{$year} - {$companyName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        if (view()->exists('emails.weekly-attendance-report') && isset($this->rapport['debut'])) {
            return new Content(view: 'emails.weekly-attendance-report');
        }

        return new Content(
            view: 'emails.attendance.weekly_report',
            with: $this->reportData,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Cas simple commande rapports:absences-hebdo
        if (isset($this->rapport['debut']) && $this->pdfContent !== null && $this->excelContent === null) {
            $attachments[] = Attachment::fromData(fn () => $this->pdfContent, $this->nomFichier())
                ->withMime('application/pdf');
            return $attachments;
        }

        $slug = Str::slug($this->reportData['company']['name'] ?? 'entreprise');
        $week = $this->reportData['period']['week_number'] ?? date('W');
        $year = $this->reportData['period']['year'] ?? date('Y');

        // Pièce jointe PDF
        if ($this->pdfContent !== null) {
            $attachments[] = Attachment::fromData(
                fn () => $this->pdfContent,
                "rapport_pointages_semaine_{$week}_{$year}_{$slug}.pdf"
            )->withMime('application/pdf');
        }

        // Pièce jointe Excel
        if ($this->excelContent !== null) {
            $attachments[] = Attachment::fromData(
                fn () => $this->excelContent,
                "rapport_pointages_semaine_{$week}_{$year}_{$slug}.xlsx"
            )->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        return $attachments;
    }

    public function nomFichier(): string
    {
        if (isset($this->rapport['debut']) && is_object($this->rapport['debut']) && method_exists($this->rapport['debut'], 'format')) {
            return 'rapport-presence-' . $this->rapport['debut']->format('Y-m-d') . '.pdf';
        }

        return 'rapport-presence.pdf';
    }
}
