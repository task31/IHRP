<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $recipientEmail,
        public string $subjectLine,
        public string $note
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invoice-note',
            with: ['note' => $this->note],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $pdf = (new PdfService)->generateInvoice($this->invoice);

        return [
            Attachment::fromData(fn () => $pdf, 'invoice_'.$this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
