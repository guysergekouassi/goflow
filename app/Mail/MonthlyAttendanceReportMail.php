<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MonthlyAttendanceReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $reportData;
    protected ?string $pdfContent;
    protected ?string $excelContent;

    /**
     * Create a new message instance.
     */
    public function __construct(array $reportData, ?string $pdfContent = null, ?string $excelContent = null)
    {
        $this->reportData = $reportData;
        $this->pdfContent = $pdfContent;
        $this->excelContent = $excelContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $companyName = $this->reportData['company']['name'] ?? 'Entreprise';
        $monthName = $this->reportData['period']['month_name_fr'] ?? date('F');
        $year = $this->reportData['period']['year'] ?? date('Y');

        return new Envelope(
            subject: "[GP FLOW] Rapport mensuel des pointages - {$monthName} {$year} - {$companyName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.attendance.monthly_report',
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
        $slug = Str::slug($this->reportData['company']['name'] ?? 'entreprise');
        $reportMonth = $this->reportData['period']['report_month'] ?? date('Y-m');

        // Pièce jointe PDF
        if ($this->pdfContent !== null) {
            $attachments[] = Attachment::fromData(
                fn () => $this->pdfContent,
                "rapport_pointages_mensuel_{$reportMonth}_{$slug}.pdf"
            )->withMime('application/pdf');
        }

        // Pièce jointe Excel
        if ($this->excelContent !== null) {
            $attachments[] = Attachment::fromData(
                fn () => $this->excelContent,
                "rapport_pointages_mensuel_{$reportMonth}_{$slug}.xlsx"
            )->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        return $attachments;
    }
}
