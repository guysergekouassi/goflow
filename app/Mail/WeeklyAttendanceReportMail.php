<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rapport hebdomadaire de présence envoyé à l'admin de l'entreprise :
 * résumé dans le corps du mail, détail complet dans le PDF joint.
 */
class WeeklyAttendanceReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $rapport, private string $pdf)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rapport de présence – semaine du ' . $this->rapport['debut']->format('d/m/Y')
                . ' au ' . $this->rapport['fin']->format('d/m/Y') . ' – ' . $this->rapport['company']->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.weekly-attendance-report');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->nomFichier())->withMime('application/pdf'),
        ];
    }

    public function nomFichier(): string
    {
        return 'rapport-presence-' . $this->rapport['debut']->format('Y-m-d') . '.pdf';
    }
}
